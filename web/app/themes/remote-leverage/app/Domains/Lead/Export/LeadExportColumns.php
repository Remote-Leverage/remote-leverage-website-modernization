<?php

declare(strict_types=1);

namespace App\Domains\Lead\Export;

use App\Domains\Lead\Models\Lead;
use Closure;

/**
 * What a lead CSV can contain, in groups a human can choose between.
 *
 * One catalogue rather than a header list and a row-building list, which is how the previous
 * export could drift: the two were written out separately in the same method, and adding a
 * column to one without the other silently shifts every value in the file one place to the left.
 * Here a column *is* its header and its resolver, so they cannot disagree.
 *
 * Groups exist because the full set is 40-odd columns and almost nobody wants all of them. The
 * identity group is not offered as a choice — a row with no id, name or email is not a lead
 * export, it is a column of UTM values with nothing to attach them to.
 */
final class LeadExportColumns
{
    /**
     * The groups, in the order they appear in the file and in the modal.
     *
     * @return array<string, array{label: string, note: string, always: bool}>
     */
    public static function groups(): array
    {
        return [
            'identity' => [
                'label' => 'Identity',
                'note' => 'ID, UUID, capture date, name, email, status.',
                'always' => true,
            ],
            'contact' => [
                'label' => 'Contact',
                'note' => 'Phone, phone country, company, timezone.',
                'always' => false,
            ],
            'qualification' => [
                'label' => 'Qualification',
                'note' => 'Role, hours, revenue, start date, notes, submission type.',
                'always' => false,
            ],
            'audience' => [
                'label' => 'Audience flag',
                'note' => 'Whether the lead reads as a possible VA applicant rather than a client.',
                'always' => false,
            ],
            'meeting' => [
                'label' => 'Meeting',
                'note' => 'Scheduled time, Meet URL, scheduler link. Reads the activity log, so it is the slowest group.',
                'always' => false,
            ],
            'attribution' => [
                'label' => 'Attribution & UTM',
                'note' => 'Source type, the six UTM fields, landing and referrer URLs.',
                'always' => false,
            ],
            'click_ids' => [
                'label' => 'Click IDs',
                'note' => 'GCLID, FBCLID, LinkedIn fat id, Meta _fbc.',
                'always' => false,
            ],
            'referral' => [
                'label' => 'Referral',
                'note' => 'Referral code, partner, oppref.',
                'always' => false,
            ],
            'tracking' => [
                'label' => 'Tracking IDs',
                'note' => 'Session, PostHog, device and HubSpot identifiers, plus their links.',
                'always' => false,
            ],
            'raw_attribution' => [
                'label' => 'Raw attribution',
                'note' => 'The full captured attribution blob as JSON, in one cell.',
                'always' => false,
            ],
            'activity' => [
                'label' => 'Activity counts',
                'note' => 'How many activity log entries the lead has. Adds a count query per batch.',
                'always' => false,
            ],
        ];
    }

    /** The groups that are always written, whatever was asked for. */
    public static function mandatoryGroups(): array
    {
        return array_keys(array_filter(self::groups(), static fn (array $g) => $g['always']));
    }

    /** Every group slug the modal may send. */
    public static function groupSlugs(): array
    {
        return array_keys(self::groups());
    }

    /**
     * Header row for a selection of groups.
     *
     * @param  array<int, string>  $groups
     * @return array<int, string>
     */
    public static function headers(array $groups): array
    {
        return array_keys(self::columns($groups));
    }

    /**
     * One CSV row for a lead, in the same order as `headers()`.
     *
     * @param  array<int, string>  $groups
     * @return array<int, string>
     */
    public static function row(Lead $lead, array $groups): array
    {
        $values = [];

        foreach (self::columns($groups) as $resolve) {
            $values[] = $resolve($lead);
        }

        return $values;
    }

    /**
     * Relations to eager-load for a selection, so a 10,000-row export is not 10,000 queries.
     *
     * @param  array<int, string>  $groups
     * @return array<int, string>
     */
    public static function eagerLoad(array $groups): array
    {
        // Only the meeting columns read the log itself; the activity group needs a count, which
        // `withCount` handles far more cheaply than loading every payload.
        return in_array('meeting', $groups, true) ? ['activityLogs'] : [];
    }

    /**
     * @param  array<int, string>  $groups
     * @return array<int, string>
     */
    public static function withCount(array $groups): array
    {
        return in_array('activity', $groups, true) ? ['activityLogs'] : [];
    }

    /**
     * Header => resolver, for the selected groups, in catalogue order.
     *
     * @param  array<int, string>  $groups
     * @return array<string, Closure(Lead): string>
     */
    private static function columns(array $groups): array
    {
        $selected = array_unique(array_merge(self::mandatoryGroups(), $groups));
        $columns = [];

        foreach (self::catalogue() as $group => $definitions) {
            if (! in_array($group, $selected, true)) {
                continue;
            }

            $columns += $definitions;
        }

        return $columns;
    }

    /**
     * @return array<string, array<string, Closure(Lead): string>>
     */
    private static function catalogue(): array
    {
        return [
            'identity' => [
                'ID' => static fn (Lead $l) => (string) $l->id,
                'UUID' => static fn (Lead $l) => (string) $l->uuid,
                'Created Date (UTC)' => static fn (Lead $l) => (string) $l->created_at?->toDateTimeString(),
                'Full Name' => static fn (Lead $l) => (string) $l->name,
                'First Name' => static fn (Lead $l) => (string) $l->first_name,
                'Last Name' => static fn (Lead $l) => (string) $l->last_name,
                'Email' => static fn (Lead $l) => (string) $l->email,
                'Status' => static fn (Lead $l) => (string) $l->status,
                'Deleted At (UTC)' => static fn (Lead $l) => (string) $l->deleted_at?->toDateTimeString(),
            ],
            'contact' => [
                'Phone' => static fn (Lead $l) => (string) $l->phone,
                'Phone Country' => static fn (Lead $l) => (string) $l->phone_country,
                'Company' => static fn (Lead $l) => (string) $l->company,
                'Timezone' => static fn (Lead $l) => (string) $l->timezone,
            ],
            'qualification' => [
                'Role Needed' => static fn (Lead $l) => (string) $l->role_needed,
                'Weekly Hours' => static fn (Lead $l) => (string) $l->weekly_hours,
                'Monthly Revenue' => static fn (Lead $l) => (string) $l->monthly_revenue,
                'Start Date' => static fn (Lead $l) => (string) $l->start_date,
                'Notes' => static fn (Lead $l) => (string) $l->notes,
                'Submission Type' => static fn (Lead $l) => (string) $l->submission_type,
                'Data Source' => static fn (Lead $l) => (string) $l->data_source,
            ],
            'audience' => [
                'Possible VA' => static fn (Lead $l) => $l->audience()->possibleVirtualAssistant ? 'yes' : 'no',
                'Audience Note' => static fn (Lead $l) => (string) $l->audience()->note(),
            ],
            'meeting' => [
                'Scheduled Time' => static fn (Lead $l) => (string) (self::meeting($l)['start_time'] ?? ''),
                'Google Meet URL' => static fn (Lead $l) => (string) (self::meeting($l)['meet_url'] ?? ''),
                'Scheduler Link' => static fn (Lead $l) => (string) $l->scheduler_link,
                'Booking Retry Count' => static fn (Lead $l) => (string) $l->booking_retry_count,
            ],
            'attribution' => [
                'Source Type' => static fn (Lead $l) => (string) $l->source_type,
                'Source ID' => static fn (Lead $l) => (string) $l->source_id,
                'UTM Source' => static fn (Lead $l) => (string) $l->utm_source,
                'UTM Medium' => static fn (Lead $l) => (string) $l->utm_medium,
                'UTM Campaign' => static fn (Lead $l) => (string) $l->utm_campaign,
                'UTM Term' => static fn (Lead $l) => (string) $l->utm_term,
                'UTM Content' => static fn (Lead $l) => (string) $l->utm_content,
                'UTM ID' => static fn (Lead $l) => (string) $l->utm_id,
                'Landing URL' => static fn (Lead $l) => (string) $l->landing_url,
                'Landing Page Base' => static fn (Lead $l) => (string) $l->landing_page_base,
                'Referrer URL' => static fn (Lead $l) => (string) $l->referrer_url,
            ],
            'click_ids' => [
                'GCLID' => static fn (Lead $l) => (string) $l->gclid,
                'FBCLID' => static fn (Lead $l) => (string) $l->fbclid,
                'LinkedIn Click ID' => static fn (Lead $l) => (string) $l->li_fat_id,
                'Meta _fbc' => static fn (Lead $l) => (string) $l->fbc,
            ],
            'referral' => [
                'Referral Code' => static fn (Lead $l) => (string) $l->referral_code,
                'Partner' => static fn (Lead $l) => (string) $l->partner,
                'Oppref' => static fn (Lead $l) => (string) $l->oppref,
            ],
            'tracking' => [
                'Session ID' => static fn (Lead $l) => (string) $l->session_id,
                'PostHog Session ID' => static fn (Lead $l) => (string) $l->posthog_session_id,
                'PostHog Replay URL' => static fn (Lead $l) => (string) $l->posthogReplayUrl(),
                'Device ID' => static fn (Lead $l) => (string) $l->device_id,
                'HubSpot Contact ID' => static fn (Lead $l) => (string) $l->hubspot_contact_id,
                'HubSpot Lifecycle Stage' => static fn (Lead $l) => (string) $l->hubspot_lifecycle_stage,
                'HubSpot Contact URL' => static fn (Lead $l) => (string) $l->hubspotContactUrl(),
            ],
            'raw_attribution' => [
                'Attribution (JSON)' => static fn (Lead $l) => is_array($l->attribution)
                    ? (string) json_encode($l->attribution, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : '',
            ],
            'activity' => [
                'Activity Log Entries' => static fn (Lead $l) => (string) ($l->activity_logs_count ?? ''),
            ],
        ];
    }

    /**
     * The scheduling payload for a lead, or an empty array.
     *
     * Reads the already-loaded relation rather than querying: `eagerLoad()` puts it there for
     * the whole batch, and touching the relation lazily here would turn one query per batch into
     * one per row.
     *
     * @return array<string, mixed>
     */
    private static function meeting(Lead $lead): array
    {
        if (! $lead->relationLoaded('activityLogs')) {
            return [];
        }

        $log = $lead->activityLogs
            ->first(fn ($l) => $l->actor_domain === 'Scheduling' && $l->outcome === 'succeeded');

        return is_array($log?->payload) ? $log->payload : [];
    }
}
