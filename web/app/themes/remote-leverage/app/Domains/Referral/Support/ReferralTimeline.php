<?php

declare(strict_types=1);

namespace App\Domains\Referral\Support;

use App\Domains\Lead\Models\LeadActivityLog;
use Illuminate\Support\Collection;

/**
 * Turn a lead's internal activity log into something a referrer may read.
 *
 * `rl_lead_activity_logs` is an engineering record: it carries failed HubSpot syncs, Slack
 * delivery outcomes, identity merges and dual-logged dispatch/consumption pairs for the same
 * event. A referrer is an **external party** — showing them that raw feed would leak which
 * vendors are in the stack and which of them are failing, and would read as noise besides.
 *
 * So this is an allowlist, not a filter: an event type with no entry in MAP is dropped. That
 * way a new internal event added anywhere in the codebase is invisible here by default rather
 * than appearing on a public dashboard the day it ships.
 */
class ReferralTimeline
{
    /**
     * Event types a referrer may see, in the words they should see them in.
     *
     * Keyed by event type; `label` may use `:stage` for the new HubSpot stage.
     *
     * @var array<string, array{label: string, tone: string}>
     */
    public const MAP = [
        'LeadCreated' => ['label' => 'Referral received', 'tone' => 'info'],
        'LeadBookingCompleted' => ['label' => 'Consultation booked', 'tone' => 'success'],
        'LeadBookingCanceled' => ['label' => 'Consultation canceled', 'tone' => 'error'],
        'LeadAbandoned' => ['label' => 'No response yet', 'tone' => 'warn'],
        'HubSpotLifecycleChanged' => ['label' => 'Moved to :stage', 'tone' => 'info'],
        'ReferralFulfilled' => ['label' => 'Deal closed — reward earned', 'tone' => 'success'],
    ];

    /**
     * Build the referrer-facing timeline for one lead's activity logs.
     *
     * @param  Collection<int, LeadActivityLog>  $logs
     * @return array<int, array{at: string, iso: string, label: string, tone: string}>
     */
    public function build(Collection $logs): array
    {
        $entries = [];
        $lastLabel = null;

        foreach ($logs->sortBy('created_at') as $log) {
            $spec = self::MAP[$log->event_type] ?? null;

            if ($spec === null) {
                continue;
            }

            /*
             * A failed listener is an internal problem, not a status update. Surfacing it
             * would tell a referrer their deal regressed when in fact a vendor call errored.
             */
            if ($log->outcome === 'failed') {
                continue;
            }

            $label = $this->label($spec['label'], $log);

            /*
             * Dual logging writes a dispatch row and a consumption row for the same event, and
             * several domains each write their own consumption row for one event — so a single
             * booking can produce four identical entries. Collapsing consecutive duplicates is
             * what makes this read as a story rather than a log.
             */
            if ($label === $lastLabel) {
                continue;
            }

            $lastLabel = $label;

            $entries[] = [
                'at' => $log->created_at?->format('M j, Y') ?? '',
                'iso' => $log->created_at?->toIso8601String() ?? '',
                'label' => $label,
                'tone' => $spec['tone'],
            ];
        }

        return $entries;
    }

    /**
     * Resolve `:stage` against the log's payload, humanising HubSpot's internal value.
     */
    protected function label(string $template, LeadActivityLog $log): string
    {
        if (! str_contains($template, ':stage')) {
            return $template;
        }

        $stage = $log->payload['to'] ?? null;

        if (! is_string($stage) || trim($stage) === '') {
            return 'Status updated';
        }

        return str_replace(':stage', self::humaniseStage($stage), $template);
    }

    /**
     * HubSpot stores lifecycle stages as lowercase machine values — `salesqualifiedlead`,
     * `opportunity`, `customer`. Portals also allow custom stages whose value is a numeric id,
     * which has no meaning to anyone reading it, so those degrade to a neutral phrase.
     */
    public static function humaniseStage(string $stage): string
    {
        $stage = trim($stage);

        if ($stage === '' || ctype_digit($stage)) {
            return 'the next stage';
        }

        $known = [
            'subscriber' => 'Subscriber',
            'lead' => 'Lead',
            'marketingqualifiedlead' => 'Marketing Qualified',
            'salesqualifiedlead' => 'Sales Qualified',
            'opportunity' => 'Opportunity',
            'customer' => 'Customer',
            'evangelist' => 'Evangelist',
            'other' => 'Other',
        ];

        return $known[strtolower($stage)] ?? ucfirst(str_replace('_', ' ', $stage));
    }
}
