<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Export;

use App\Domains\PartnerHub\Models\PartnershipProspect;
use App\Domains\PartnerHub\Support\PartnershipProspectOptions;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every column of `rl_partnership_prospects`, as a CSV the partnerships team opens in a
 * spreadsheet.
 *
 * Streamed in id order a chunk at a time, like ReferralAdminDashboard::exportReferrersCsv(),
 * rather than batched through the browser like the lead export: this table is a few hundred rows
 * where that one is tens of thousands, and one request comfortably writes all of it.
 *
 * Three things differ from the referrer export, each for a reason:
 *
 *  - **The answers are written as their labels**, the words the prospect picked, not the stored
 *    slugs. This file is read by people; nothing imports it back.
 *  - **Cells that a spreadsheet would run as a formula are defused.** Most of these values were
 *    typed by a stranger into a public form, and a company name of `=HYPERLINK(...)` is a live
 *    link in Excel the moment the file is opened. See cell().
 *  - **A UTF-8 byte-order mark goes first and PHP's backslash escape is off**, for the reasons
 *    LeadExportRunner gives: Excel otherwise misreads every accented name, and a message ending
 *    in a backslash would otherwise swallow the rest of the file.
 */
final class PartnershipProspectCsv
{
    private const CHUNK = 200;

    /**
     * @return array<int, string>
     */
    public static function headers(): array
    {
        return [
            'ID',
            'Submitted (UTC)',
            'Updated (UTC)',
            'Status',
            'Source',
            'First name',
            'Last name',
            'Email',
            'Company',
            'Role',
            'Organization type',
            'Monthly revenue',
            'Businesses reached',
            'Message',
            'Internal notes',
            'Call booked (UTC)',
            'Calendly event URI',
            'Calendly invitee URI',
            'Landing URL',
            'Referrer URL',
            'UTM source',
            'UTM medium',
            'UTM campaign',
            'UTM term',
            'UTM content',
            'IP address',
            'Context (JSON)',
        ];
    }

    /**
     * One prospect, in the order headers() names the columns, every cell already defused.
     *
     * @return array<int, string>
     */
    public static function row(PartnershipProspect $prospect): array
    {
        $context = is_array($prospect->context) && $prospect->context !== []
            ? (string) json_encode($prospect->context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : '';

        $values = [
            $prospect->id,
            self::utc($prospect->created_at),
            self::utc($prospect->updated_at),
            $prospect->status,
            $prospect->sourceLabel(),
            $prospect->first_name,
            $prospect->last_name,
            $prospect->email,
            $prospect->company,
            $prospect->role,
            PartnershipProspectOptions::label('organization_type', $prospect->organization_type),
            PartnershipProspectOptions::label('monthly_revenue', $prospect->monthly_revenue),
            PartnershipProspectOptions::label('businesses_reached', $prospect->businesses_reached),
            $prospect->message,
            $prospect->notes,
            self::utc($prospect->booked_at),
            $prospect->calendly_event_uri,
            $prospect->calendly_invitee_uri,
            $prospect->landing_url,
            $prospect->referrer_url,
            $prospect->utm_source,
            $prospect->utm_medium,
            $prospect->utm_campaign,
            $prospect->utm_term,
            $prospect->utm_content,
            $prospect->ip_address,
            $context,
        ];

        return array_map(self::cell(...), $values);
    }

    /**
     * A value as a spreadsheet will read it, with formula injection defused.
     *
     * Excel, Sheets and Numbers all evaluate a cell that starts with `=`, `+`, `-` or `@` (and
     * Excel one that starts with a tab or carriage return) as a formula. The accepted defence,
     * OWASP's, is a leading apostrophe: every spreadsheet hides it and shows the text as typed.
     * The one visible cost is on a message that opens with a hyphenated list, which now shows
     * the apostrophe in a plain-text editor, and that is the right way round.
     */
    public static function cell(mixed $value): string
    {
        $value = $value === null ? '' : (string) $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Write the whole export to an open handle: the byte-order mark, the header row, then every
     * prospect the query selects, oldest first.
     *
     * @param  resource  $handle
     * @param  Builder<PartnershipProspect>  $query  Already narrowed to what should be exported.
     * @return int The number of prospects written.
     */
    public static function write($handle, Builder $query): int
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::headers(), ',', '"', '');

        $written = 0;

        $query->chunkById(self::CHUNK, function ($prospects) use ($handle, &$written) {
            foreach ($prospects as $prospect) {
                fputcsv($handle, self::row($prospect), ',', '"', '');
                $written++;
            }
        });

        return $written;
    }

    public static function filename(?CarbonInterface $now = null): string
    {
        return 'remoteleverage-partnership-prospects-'.($now ?? now())->format('Y-m-d-His').'.csv';
    }

    private static function utc(?CarbonInterface $at): string
    {
        return $at?->copy()->utc()->toDateTimeString() ?? '';
    }
}
