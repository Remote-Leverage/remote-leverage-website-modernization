<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Import\GravityLeadImporter;
use App\Domains\Lead\Models\Lead;
use Illuminate\Support\Facades\Schema;

/**
 * How much of a third-party value the leads table can actually hold.
 *
 * Ad platforms decide how long their click ids are and change that without notice. Under
 * `STRICT_TRANS_TABLES` an overlong value does not truncate, it aborts the INSERT — so an
 * unbounded third-party string is not a tracking inconvenience, it is a lost customer. A
 * 212-character `fbclid` against `varchar(150)` was dropping every paid-social lead on
 * 2026-09-17, partial capture included, with no row left behind to show for it.
 *
 * The 2026_09_17_000001 migration widens those columns; clamping is the half that keeps working
 * when the next value outgrows the new width too. Losing the tail of a click id costs one
 * attribution join. Losing the row costs the lead.
 *
 * This lives apart from {@see CaptureLeadAction} because it is no
 * longer only the live form that writes these columns — the Gravity backfill
 * ({@see GravityLeadImporter}) writes the same third-party values from
 * a CSV, and a second copy of the map is a second thing to forget to widen.
 */
final class LeadColumnLimits
{
    /**
     * Widths of the columns holding values we do not author.
     *
     * Only the fallback floor — {@see limits()} reads the real widths off the table and prefers
     * those, so widening a column later needs no edit here and a column added without being
     * listed here is still protected.
     *
     * Only bounded columns appear here. `landing_url`, `referrer_url`, `notes`, `scheduler_link`
     * and `landing_page_base` are TEXT and cannot overflow this way.
     *
     * @var array<string, int>
     */
    public const FALLBACK = [
        'fbclid' => 512,
        'gclid' => 512,
        'fbc' => 512,
        'li_fat_id' => 255,
        'utm_source' => 255,
        'utm_medium' => 255,
        'utm_campaign' => 255,
        'utm_term' => 255,
        'utm_content' => 255,
        'utm_id' => 255,
        'oppref' => 255,
        'partner' => 255,
        'referral_code' => 100,
        'session_id' => 100,
        'monthly_revenue' => 100,
        'phone_country' => 5,
        'data_source' => 100,
        'intake_form' => 50,
        'ip_address' => 45,
        'timezone' => 64,
        'submission_type' => 50,
        'device_id' => 64,
        'posthog_session_id' => 100,
    ];

    /**
     * Resolved once per process, because it costs an information_schema query.
     *
     * @var array<string, int>|null
     */
    private static ?array $resolved = null;

    /**
     * The real character limit of every bounded column on the leads table.
     *
     * Read from the table rather than hardcoded, so this cannot drift away from the schema —
     * which is the failure mode that produced the original bug in a slower form: the code knew
     * `fbclid` existed and had no idea how much of it would fit. Whatever the column actually is
     * today is what gets enforced, so widening it again later needs no change here, and a new
     * attribution column is covered the moment it is added.
     *
     * Falls back to {@see FALLBACK} where introspection is unavailable — notably the bare
     * container the unit tests build, which has no live connection.
     *
     * @return array<string, int>
     */
    public static function limits(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $limits = self::FALLBACK;

        try {
            foreach (Schema::getColumns((new Lead)->getTable()) as $column) {
                // MySQL reports the full type, e.g. "varchar(512)". TEXT columns have no length
                // here and need no clamp.
                if (preg_match('/^(?:var)?char\((\d+)\)/i', (string) ($column['type'] ?? ''), $matches)) {
                    $limits[(string) $column['name']] = (int) $matches[1];
                }
            }
        } catch (\Throwable) {
            // Introspection is best-effort; the constant map is the floor, not the only source.
        }

        return self::$resolved = $limits;
    }

    /**
     * Trim every bounded column to what its schema can actually hold.
     *
     * Multibyte-aware: the limits are column *character* lengths, and `mb_substr` is what matches
     * how MySQL counts them. Cutting on bytes would both over-trim a UTF-8 campaign name and
     * risk splitting a character.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function clamp(array $attributes): array
    {
        foreach (self::limits() as $column => $limit) {
            if (! isset($attributes[$column]) || ! is_string($attributes[$column])) {
                continue;
            }

            if (mb_strlen($attributes[$column]) > $limit) {
                $attributes[$column] = mb_substr($attributes[$column], 0, $limit);
            }
        }

        return $attributes;
    }

    /**
     * Drop the memoised widths, so a test that changes the schema is not answered from cache.
     */
    public static function flush(): void
    {
        self::$resolved = null;
    }
}
