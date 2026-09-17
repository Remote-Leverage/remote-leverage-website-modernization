<?php

declare(strict_types=1);

namespace App\Domains\Lead\Import;

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\IdentityResolver;
use App\Domains\Lead\Services\LeadColumnLimits;
use App\Domains\Referral\Services\AttributionEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backfills historical Gravity Forms entries into `rl_leads`.
 *
 * ## Why this does not go through CaptureLeadAction
 *
 * That action is the live path, and it dispatches `LeadCreated` — which fans out to Slack, the
 * HubSpot sync, the outbound webhook and the notification mailer. Replaying three months of
 * entries through it would post thousands of alerts about leads that were handled in July, sync
 * stale contacts over live ones, and mail people about a consultation they have already had.
 * A backfill writes rows; it does not re-run history.
 *
 * What it *does* borrow from that action is its precedence rules, because imported rows and
 * captured rows end up in the same dashboard and must mean the same thing:
 *
 *  - **Columns: last non-empty wins.** A later submission that carries a value replaces an
 *    earlier one; a later submission that is blank leaves the earlier value alone.
 *  - **The `attribution` blob: first wins.** The first value seen is the acquisition one.
 *  - **`consent_at` is stamped once and never cleared** — a later submission without the tick
 *    must not erase consent already given.
 *
 * ## One lead per person
 *
 * Gravity records an entry per submission; this table records a person. In a typical backfill
 * 7,397 entries are 3,969 people — most of the difference being the Partial that Gravity writes
 * when the form validates followed by the Final it writes when the visitor books, which are one
 * person converting, not two leads. Importing per entry would double every conversion in the
 * dashboard. Entries are therefore folded by email, oldest first, so the fold applies the rules
 * above in the order the submissions actually happened.
 *
 * ## Re-running
 *
 * Imported rows carry `attribution->gravity`, which nothing in the live path writes. That is
 * what makes a second run safe: a lead with that key is one of ours and gets updated, a lead
 * without it was captured by the live form and is left strictly alone. A backfill that
 * overwrites a real capture is worse than one that skips it, because the real capture is the
 * only record of what the customer actually did.
 */
final class GravityLeadImporter
{
    /** Rows per INSERT. Large enough to be fast, small enough to stay well inside max_allowed_packet. */
    private const CHUNK = 250;

    /**
     * Every column this importer writes, in a fixed order.
     *
     * A multi-row `insert()` takes its column list from the first row and pairs every later row
     * against it positionally. Leads do not all carry the same fields — one has a `gclid`, the
     * next has none — so handing the builder rows with differing key sets does not error, it
     * writes one lead's landing URL into another lead's `scheduler_link`. Normalising against
     * this list, with a null for whatever a person did not have, is what stops that.
     *
     * `is_blocked` and `booking_retry_count` are absent deliberately: both are NOT NULL with a
     * database default, so naming them here would buy nothing and assert a schema detail the
     * importer does not need to know.
     *
     * @var array<int, string>
     */
    private const INSERT_COLUMNS = [
        'uuid', 'name', 'first_name', 'last_name', 'email', 'phone', 'phone_country',
        'monthly_revenue', 'timezone', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term',
        'utm_content', 'utm_id', 'gclid', 'fbclid', 'li_fat_id', 'fbc', 'oppref', 'partner',
        'intake_form', 'data_source', 'submission_type', 'scheduler_link', 'ip_address',
        'landing_url', 'landing_page_base', 'referrer_url', 'referral_code', 'consent_at',
        'source_type', 'source_id', 'status', 'attribution', 'created_at', 'updated_at',
    ];

    public function __construct(
        private AttributionEngine $attributionEngine,
        private IdentityResolver $identityResolver,
    ) {}

    /**
     * Fold every mapped entry into one record per person.
     *
     * @param  iterable<int, array<string, mixed>>  $entries  As returned by {@see GravityLeadMapper::map()}
     * @return array<string, array<string, mixed>> Keyed by email.
     */
    public function fold(iterable $entries): array
    {
        $byEmail = [];

        foreach ($entries as $entry) {
            $byEmail[$entry['email']][] = $entry;
        }

        $leads = [];

        foreach ($byEmail as $email => $rows) {
            usort($rows, static fn (array $a, array $b): int => [$a['submitted_at']->getTimestamp(), $a['entry_id']]
                <=> [$b['submitted_at']->getTimestamp(), $b['entry_id']]);

            $leads[$email] = $this->merge($rows);
        }

        return $leads;
    }

    /**
     * Write folded leads, creating or updating as the `gravity` marker dictates.
     *
     * @param  array<string, array<string, mixed>>  $leads
     * @return array{created: int, updated: int, skipped_live: int}
     */
    public function persist(array $leads): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped_live' => 0];
        $insertBuffer = [];

        foreach (array_chunk($leads, self::CHUNK, true) as $chunk) {
            $existing = Lead::query()
                ->withTrashed()
                ->whereIn('email', array_keys($chunk))
                ->get(['id', 'email', 'attribution'])
                ->keyBy('email');

            foreach ($chunk as $email => $lead) {
                $match = $existing->get($email);

                if ($match === null) {
                    $insertBuffer[] = $this->forInsert($lead);

                    $result['created']++;

                    continue;
                }

                // Captured by the live form, not by us. Leave it exactly as it is.
                if (! is_array($match->attribution) || ! array_key_exists('gravity', $match->attribution)) {
                    $result['skipped_live']++;

                    continue;
                }

                Lead::query()->withTrashed()->whereKey($match->id)->update($this->forUpdate($lead));

                $result['updated']++;
            }

            if (count($insertBuffer) >= self::CHUNK) {
                DB::table((new Lead)->getTable())->insert($insertBuffer);
                $insertBuffer = [];
            }
        }

        if ($insertBuffer !== []) {
            DB::table((new Lead)->getTable())->insert($insertBuffer);
        }

        return $result;
    }

    /**
     * Attach every imported lead to an identity profile.
     *
     * Same resolver the live path runs, for the same reason: `rl_leads.uuid` is minted per row,
     * so it cannot tell you that the person who submitted in July is the person who submitted
     * again in September. Email and phone can, and the profile is where a block would later be
     * recorded against them.
     *
     * Safe to run over a backfill because the resolver only ever links — it never bans. See
     * {@see IdentityResolver}.
     *
     * @param  array<string, array<string, mixed>>  $leads  The folded set, for timestamp repair.
     * @param  callable(int): void|null  $onProgress  Called with the number resolved so far.
     */
    public function resolveIdentities(array $leads, ?callable $onProgress = null): int
    {
        $resolved = 0;
        $startedAt = CarbonImmutable::now('UTC');

        // chunkById supplies its own ordering and pages on `id > last`, so leads dropping out of
        // the filter as they are resolved cannot cause a page to be skipped.
        Lead::query()
            ->whereNull('profile_id')
            ->chunkById(self::CHUNK, function ($leads) use (&$resolved, $onProgress): void {
                foreach ($leads as $lead) {
                    /*
                     * The resolver writes `profile_id` and `is_blocked` back with `saveQuietly()`,
                     * which still touches `updated_at`. On a backfill that is silently
                     * destructive: every lead's `updated_at` becomes the moment of the import
                     * rather than the moment of their last submission, so "leads updated this
                     * week" answers a question about the import job. Timestamps off for the
                     * instance the resolver is handed keeps the imported value.
                     */
                    $lead->timestamps = false;

                    $this->identityResolver->resolve($lead);
                    $resolved++;
                }

                if ($onProgress !== null) {
                    $onProgress($resolved);
                }
            });

        $this->restoreTimestamps($leads, $startedAt);

        return $resolved;
    }

    /**
     * Put back the timestamps that a profile merge stamped over.
     *
     * Disabling timestamps on the instance handed to the resolver covers the common path, but
     * not the merge one: re-pointing the losing profile's leads at the winner is a bulk
     * `Lead::query()->...->update()`, and an Eloquent builder update sets `updated_at` on its own.
     * Only leads whose profile actually merged are affected — 2 of 3,969 in a typical backfill,
     * which is precisely the size of problem that never gets noticed and quietly makes
     * "submitted this week" wrong for the leads with the most interesting histories.
     *
     * Repaired here rather than in {@see IdentityResolver} because that update is on the live
     * path, where stamping is harmless, and a backfill should not change what capture does.
     *
     * @param  array<string, array<string, mixed>>  $leads
     */
    private function restoreTimestamps(array $leads, CarbonImmutable $startedAt): void
    {
        Lead::query()
            ->where('updated_at', '>', $startedAt->toDateTimeString())
            ->get(['id', 'email'])
            ->each(function (Lead $lead) use ($leads): void {
                $folded = $leads[$lead->email] ?? null;

                if ($folded === null) {
                    return;
                }

                Lead::query()->whereKey($lead->id)->toBase()->update([
                    'created_at' => $folded['created_at'],
                    'updated_at' => $folded['updated_at'],
                ]);
            });
    }

    /**
     * Fold one person's entries, oldest first.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function merge(array $rows): array
    {
        $columns = [];
        $handl = [];
        $userAgent = null;
        $consentAt = null;
        $booked = false;

        foreach ($rows as $row) {
            // Last non-empty wins, matching CaptureLeadAction.
            foreach ($row['columns'] as $column => $value) {
                if ($value !== '' && $value !== null) {
                    $columns[$column] = $value;
                }
            }

            // First wins: the acquisition value is the one worth keeping.
            $handl += $row['attribution']['handl'] ?? [];
            $userAgent ??= $row['attribution']['user_agent'] ?? null;

            // Stamped once, never cleared.
            $consentAt ??= $row['consent'] ? $row['submitted_at'] : null;

            $booked = $booked || $row['booked'];
        }

        $first = $rows[0];
        $last = $rows[count($rows) - 1];

        $attribution = ['gravity' => $this->provenance($rows)];

        if ($handl !== []) {
            $attribution['handl'] = $handl;
        }

        if ($userAgent !== null) {
            $attribution['user_agent'] = $userAgent;
        }

        /*
         * Resolved from the merged values rather than per entry, so the source reflects the
         * person's whole history the way it would have if every submission had run through the
         * live path against one growing row.
         */
        $source = $this->attributionEngine->resolveLeadSource(
            referralSlug: $columns['oppref'] ?? null,
            utmSource: $columns['utm_source'] ?? null,
            utmCampaign: $columns['utm_campaign'] ?? null,
        );

        $columns['source_type'] = $source['sourceType'] ?: 'organic';
        $columns['source_id'] = $source['sourceID'];

        // CaptureLeadAction writes the resolved source id here too, so an imported row and a
        // captured row filter identically in the admin even though for paid traffic the value
        // is a campaign rather than a referrer.
        $columns['referral_code'] = $source['sourceID'] ?? ($columns['oppref'] ?? null);

        $columns['status'] = $booked ? 'booked' : 'abandoned';
        $columns['consent_at'] = $consentAt?->toDateTimeString();
        $columns['attribution'] = $attribution;
        $columns['created_at'] = $first['submitted_at']->toDateTimeString();
        $columns['updated_at'] = $last['submitted_at']->toDateTimeString();

        return LeadColumnLimits::clamp($columns);
    }

    /**
     * What this lead was built from, and the Gravity values with no column of their own.
     *
     * The entry ids are the audit trail back to the Gravity admin, and the Partial/Final counts
     * are what make the fold checkable after the fact: a lead showing 3 entries and 1 final is a
     * person who abandoned twice before booking, which no single column here records.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function provenance(array $rows): array
    {
        $gravity = array_map(static fn (array $row): array => $row['attribution']['gravity'] ?? [], $rows);

        $finals = count(array_filter($rows, static fn (array $row): bool => (bool) $row['booked']));

        $firstOf = static function (string $key) use ($gravity): ?string {
            foreach ($gravity as $entry) {
                if (! empty($entry[$key]) && is_string($entry[$key])) {
                    return $entry[$key];
                }
            }

            return null;
        };

        return array_filter([
            'forms' => array_values(array_unique(array_column($rows, 'form'))),
            'entry_ids' => array_values(array_column($rows, 'entry_id')),
            'entries' => count($rows),
            'finals' => $finals,
            'partials' => count($rows) - $finals,
            'entry_date_local' => $firstOf('entry_date_local'),
            'booked_slot' => $firstOf('booked_slot'),
            'zerobounce' => $firstOf('zerobounce'),
            'rewardful_id' => $firstOf('rewardful_id'),
            'source_url' => $firstOf('source_url'),
            'imported_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ], static fn ($value): bool => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $lead
     * @return array<string, mixed>
     */
    private function forInsert(array $lead): array
    {
        $lead = array_merge($this->forUpdate($lead), [
            'uuid' => (string) Str::uuid(),
        ]);

        $row = [];

        foreach (self::INSERT_COLUMNS as $column) {
            $row[$column] = $lead[$column] ?? null;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $lead
     * @return array<string, mixed>
     */
    private function forUpdate(array $lead): array
    {
        $lead['attribution'] = json_encode($lead['attribution'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Unlike an insert, an update sends only the keys present, so a column this person never
        // filled keeps whatever an earlier run wrote rather than being nulled back out. That is
        // the same last-non-empty-wins rule the fold applies, carried across runs.
        return $lead;
    }
}
