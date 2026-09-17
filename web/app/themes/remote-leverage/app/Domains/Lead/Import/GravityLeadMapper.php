<?php

declare(strict_types=1);

namespace App\Domains\Lead\Import;

use App\Domains\Lead\Services\AttributionCollector;
use Carbon\CarbonImmutable;

/**
 * Turns one Gravity Forms entry into the shape `rl_leads` stores.
 *
 * The leads schema was ported from this exact form — see
 * {@see AttributionCollector}, whose `NAMED`/`EXTRA` split names the
 * same ~45 hidden fields this export carries — so most of the mapping is one column to one
 * column. The three places it is not are the three worth reading:
 *
 * ## 1. `Date Updated` is the timestamp, not `Entry Date`
 *
 * Across a 7,397-entry backfill the gap between the two columns took exactly two values: 14h for
 * every single Partial, 7h for every single Final. That is not edit history, it is a fixed
 * offset — `Entry Date` is rendered in the site's local zone (UTC-7 at the time of export) and
 * partials, created through the REST endpoint rather than a page submit, are shifted twice.
 *
 * `Date Updated` is the column that is actually UTC. The proof is the booked slot: `timestamp`
 * is explicitly UTC, and reading `Date Updated` as UTC puts the earliest booking 0.5h after its
 * submission — someone taking a slot half an hour out — while reading `Entry Date` as UTC claims
 * nobody in three months ever booked less than 7.5h ahead, which no calendar funnel produces.
 * It also lands 17 minutes before the export file's own mtime, where `Entry Date` lands 7 hours
 * before it.
 *
 * Getting this wrong does not fail; it silently files every lead in the wrong hour, which is
 * exactly the kind of error that survives into a report about when leads arrive.
 *
 * ## 2. `submission_type` carries the status
 *
 * Gravity only marks an entry Final once the visitor has booked — a Partial is a form that
 * validated and never completed. So Final maps to `booked` and Partial to `abandoned`. The
 * scheduler link is *not* the signal: 1,058 Finals carry no link, and treating its absence as
 * "did not book" would discard a third of the conversions.
 *
 * ## 3. Landing and referrer come from HandL, not from `Source Url`
 *
 * `Source Url` is wherever the submission was posted from, which for every Partial is the
 * `/wp-json/rl/v1/calendly/validate-form` endpoint — a URL no visitor ever saw. HandL's
 * `handl_landing_page` is the page they actually arrived on and `handl_original_ref` the
 * external referrer that sent them.
 */
final class GravityLeadMapper
{
    /**
     * Gravity column => `rl_leads` column, for the values that map straight across.
     *
     * @var array<string, string>
     */
    private const DIRECT = [
        'Business Email' => 'email',
        'Name (First)' => 'first_name',
        'Name (Last)' => 'last_name',
        'Phone' => 'phone',
        'Phone (Region Code)' => 'phone_country',
        "What is your company's current monthly revenue?" => 'monthly_revenue',
        'timezone' => 'timezone',
        'UTM Source' => 'utm_source',
        'UTM Medium' => 'utm_medium',
        'UTM Campaign' => 'utm_campaign',
        'UTM Term' => 'utm_term',
        'UTM Content' => 'utm_content',
        'utm_id' => 'utm_id',
        'gclid' => 'gclid',
        'fbclid' => 'fbclid',
        'li_fat_id' => 'li_fat_id',
        '_fbc (HandL)' => 'fbc',
        'oppref' => 'oppref',
        'partner' => 'partner',
        'intake_form' => 'intake_form',
        'data_source' => 'data_source',
        'submission_type' => 'submission_type',
        'Scheduler link' => 'scheduler_link',
        'User IP' => 'ip_address',
    ];

    /**
     * Where a lead column should fall back when its first source is blank.
     *
     * The HandL cookie set survives navigation the current URL no longer shows, so it fills in
     * for a visitor who landed on an ad and converted three pages later.
     *
     * @var array<string, array<int, string>>
     */
    private const FALLBACKS = [
        'utm_medium' => ['utm_medium (HandL)'],
        'utm_term' => ['utm_term (HandL)'],
        'utm_content' => ['utm_content (HandL)'],
        'gclid' => ['gclid (HandL)'],
        'fbclid' => ['fbclid (HandL)'],
        'ip_address' => ['handl_ip (HandL)'],
    ];

    /**
     * HandL columns kept in the `attribution` blob, mirroring `AttributionCollector::EXTRA`.
     *
     * Audit trail rather than selection criteria: useful when reconstructing where a lead came
     * from, never used to filter or route, so a JSON blob is the right home.
     *
     * @var array<int, string>
     */
    private const HANDL = [
        'first_utm_medium (HandL)',
        'first_utm_term (HandL)',
        'first_utm_content (HandL)',
        'msclkid (HandL)',
        'wbraid (HandL)',
        'gbraid (HandL)',
        'gaclientid (HandL)',
        'traffic_source (HandL)',
        'first_traffic_source (HandL)',
        'organic_source (HandL)',
        'organic_source_str (HandL)',
        'handl_original_ref (HandL)',
        'handl_landing_page (HandL)',
        'handl_landing_page_base (HandL)',
        'handl_ip (HandL)',
        'handl_ref (HandL)',
        'handl_ref_domain (HandL)',
        'handl_url (HandL)',
        'handl_url_base (HandL)',
        'handlID (HandL)',
        '_fbp (HandL)',
    ];

    /**
     * Columns read somewhere other than the {@see DIRECT} map.
     *
     * The status, the timestamps, the landing and referrer URLs and the provenance values all
     * need a decision made about them rather than a straight copy, so they are handled in code.
     * They are still listed, because {@see unmappedHeaders()} has to be able to tell a column
     * that is deliberately handled from one that appeared without anyone noticing.
     *
     * @var array<int, string>
     */
    private const HANDLED_IN_CODE = [
        'Entry Id', 'Entry Date', 'Date Updated', 'submission_type',
        'Consent (Consent)', 'timestamp', 'Source Url', 'referrer_rewardful_id',
        'Business Email (Zerobounce Result)', 'User Agent', 'user_agent (HandL)',
    ];

    /**
     * Columns deliberately carried nowhere.
     *
     * Every one is either empty across the whole backfill (the payment set — this form takes no
     * money), duplicated by a column already mapped (the phone breakdown, all derivable from the
     * E.164 value), or the consent boilerplate: a 500-character legal paragraph identical on
     * every row, which belongs in the form definition rather than 4,000 times in a JSON column.
     *
     * @var array<int, string>
     */
    private const DROPPED = [
        'Name (Prefix)', 'Name (Middle)', 'Name (Suffix)',
        'Consent (Text)', 'Consent (Description)',
        'Transaction Id', 'Payment Amount', 'Payment Date', 'Payment Status',
        'Post Id', 'Created By (User Id)', 'Submission Speed (ms)',
        'Phone (Country Dialing Code)', 'Phone (Type)', 'Phone (National Number)',
        'Phone (Raw)', 'Phone (Description)', 'Phone (Carrier)',
    ];

    /**
     * Map one row.
     *
     * @param  array<string, string>  $row
     * @return array{
     *     entry_id: int,
     *     form: string,
     *     email: string,
     *     submitted_at: CarbonImmutable,
     *     booked: bool,
     *     consent: bool,
     *     columns: array<string, string>,
     *     attribution: array<string, mixed>
     * }|null
     */
    public function map(array $row, string $form): ?array
    {
        $email = strtolower($this->value($row, 'Business Email'));
        $entryId = (int) $this->value($row, 'Entry Id');

        // A lead is a person we can reach. Without an email there is nothing to merge on and
        // nothing to follow up, so the row is not a lead.
        if ($email === '' || $entryId === 0) {
            return null;
        }

        $columns = [];

        foreach (self::DIRECT as $source => $column) {
            $value = $this->value($row, $source);

            if ($value === '') {
                foreach (self::FALLBACKS[$column] ?? [] as $fallback) {
                    $value = $this->value($row, $fallback);

                    if ($value !== '') {
                        break;
                    }
                }
            }

            if ($value !== '') {
                $columns[$column] = $value;
            }
        }

        $columns['email'] = $email;
        $columns['name'] = trim(($columns['first_name'] ?? '').' '.($columns['last_name'] ?? ''));

        // See the class docblock: `Source Url` is where the POST landed, which for every Partial
        // is a REST endpoint no visitor ever saw. HandL knows the page they actually arrived on.
        $landingPage = $this->value($row, 'handl_landing_page (HandL)');
        $landingBase = $this->value($row, 'handl_landing_page_base (HandL)');

        if ($landingPage !== '') {
            $columns['landing_url'] = $landingPage;
        }

        $columns['landing_page_base'] = $landingBase !== '' ? $landingBase : ($landingPage ?: null);

        if ($columns['landing_page_base'] === null) {
            unset($columns['landing_page_base']);
        }

        $referrer = $this->value($row, 'handl_original_ref (HandL)') ?: $this->value($row, 'handl_ref (HandL)');

        if ($referrer !== '') {
            $columns['referrer_url'] = $referrer;
        }

        return [
            'entry_id' => $entryId,
            'form' => $form,
            'email' => $email,
            'submitted_at' => $this->submittedAt($row),
            'booked' => $this->value($row, 'submission_type') === 'Final',
            'consent' => $this->value($row, 'Consent (Consent)') === 'Checked',
            'columns' => $columns,
            'attribution' => $this->attribution($row, $entryId, $form),
        ];
    }

    /**
     * Headers in this export that the mapper neither carries nor knowingly drops.
     *
     * Marketing adds fields to this form, and a silently dropped column is how an import looks
     * like it worked while losing whatever was added last. The command prints these.
     *
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    public function unmappedHeaders(array $headers): array
    {
        $known = array_merge(
            array_keys(self::DIRECT),
            array_merge(...array_values(self::FALLBACKS)),
            self::HANDL,
            self::HANDLED_IN_CODE,
            self::DROPPED,
        );

        return array_values(array_diff($headers, $known));
    }

    /**
     * When the entry was actually created, in UTC.
     *
     * See the class docblock for why this is `Date Updated` and not `Entry Date`. `Entry Date` is
     * the fallback only so that a row missing the good column still lands somewhere plausible
     * rather than at the Unix epoch.
     *
     * @param  array<string, string>  $row
     */
    private function submittedAt(array $row): CarbonImmutable
    {
        $stamp = $this->value($row, 'Date Updated') ?: $this->value($row, 'Entry Date');

        if ($stamp === '') {
            return CarbonImmutable::now('UTC');
        }

        return CarbonImmutable::parse($stamp, 'UTC');
    }

    /**
     * The `attribution` JSON blob, in the shape `AttributionCollector` writes it.
     *
     * Same `handl` key, same `user_agent` key, plus a `gravity` key that exists only on imported
     * rows. That key is the import's own provenance — which entries a lead was built from, and
     * the values Gravity holds that have no column here (the booked slot, the Zerobounce
     * verdict). It is also how a re-run tells its own rows apart from leads the live form
     * captured, so a backfill never rewrites a real capture.
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function attribution(array $row, int $entryId, string $form): array
    {
        $handl = [];

        foreach (self::HANDL as $header) {
            $value = $this->value($row, $header);

            if ($value !== '') {
                // Store under HandL's own parameter name, not the export's decorated label.
                $handl[str_replace(' (HandL)', '', $header)] = $value;
            }
        }

        $gravity = array_filter([
            'form' => $form,
            'entry_ids' => [$entryId],
            'submission_types' => [$this->value($row, 'submission_type')],
            // Kept because it is what anyone cross-checking against the Gravity admin will
            // search on, even though it is the shifted local-time value. See the class docblock.
            'entry_date_local' => $this->value($row, 'Entry Date'),
            'booked_slot' => $this->value($row, 'timestamp'),
            'zerobounce' => $this->value($row, 'Business Email (Zerobounce Result)'),
            'rewardful_id' => $this->rewardfulId($row),
            'source_url' => $this->value($row, 'Source Url'),
        ], static fn ($value): bool => $value !== '' && $value !== null && $value !== []);

        $attribution = ['gravity' => $gravity];

        if ($handl !== []) {
            $attribution['handl'] = $handl;
        }

        $userAgent = $this->value($row, 'User Agent') ?: $this->value($row, 'user_agent (HandL)');

        if ($userAgent !== '') {
            $attribution['user_agent'] = $userAgent;
        }

        return $attribution;
    }

    /**
     * Rewardful's referrer id, or null for the sentinel Gravity writes when there is none.
     *
     * The field is never blank — it holds the literal string "No Rewardful ID set" — so storing
     * it unchecked would give every organic lead a referral id that is really a error message.
     *
     * @param  array<string, string>  $row
     */
    private function rewardfulId(array $row): ?string
    {
        $value = $this->value($row, 'referrer_rewardful_id');

        if ($value === '' || stripos($value, 'no rewardful id') !== false) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function value(array $row, string $key): string
    {
        return trim((string) ($row[$key] ?? ''));
    }
}
