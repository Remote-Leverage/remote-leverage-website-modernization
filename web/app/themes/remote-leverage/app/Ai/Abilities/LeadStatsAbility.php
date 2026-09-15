<?php

declare(strict_types=1);

namespace App\Ai\Abilities;

use App\Ai\InsightsCapability;
use App\Ai\Support\LeadQuery;
use Illuminate\Support\Facades\DB;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * Answers "is this landing page working?" without exposing a single contact
 * detail — the aggregate is what the page decision actually rests on, so it is
 * worth being able to grant on its own.
 */
class LeadStatsAbility extends Ability
{
    public function __construct(private LeadQuery $query) {}

    public function label(): string
    {
        return 'Lead Statistics';
    }

    public function description(): string
    {
        return 'Counts leads grouped by a dimension — landing_url, utm_source, utm_medium, utm_campaign, '.
            'utm_content, utm_term, referral_code, source_type or status — with an optional date range and '.
            'filters. Use this to compare how landing pages or campaigns are performing before deciding what '.
            'to build or change. Returns counts only, never contact details. Group by landing_url to tie '.
            'leads back to the page that produced them.';
    }

    public function execute(array $input): mixed
    {
        $dimension = $input['group_by'] ?? 'landing_url';

        if (! in_array($dimension, LeadQuery::DIMENSIONS, true)) {
            return new WP_Error(
                'invalid_dimension',
                "Unknown group_by \"{$dimension}\". Valid values: ".implode(', ', LeadQuery::DIMENSIONS).'.'
            );
        }

        $rows = $this->query->build($input)
            ->groupBy($dimension)
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(min((int) ($input['limit'] ?? 50), 200))
            ->get([
                DB::raw("{$dimension} as `value`"),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) as booked"),
                DB::raw("SUM(CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END) as abandoned"),
            ]);

        return [
            'group_by' => $dimension,
            'total_leads' => (int) $this->query->build($input)->count(),
            'groups' => $rows->map(fn ($row) => [
                'value' => $row->value,
                'total' => (int) $row->total,
                'booked' => (int) $row->booked,
                'abandoned' => (int) $row->abandoned,
            ])->all(),
        ];
    }

    public function permission(): bool|WP_Error
    {
        if (! InsightsCapability::currentUserCan()) {
            return new WP_Error('forbidden', 'The '.InsightsCapability::NAME.' capability is required.');
        }

        return true;
    }

    public function category(): ?string
    {
        return 'site';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group_by' => [
                    'type' => 'string',
                    'enum' => LeadQuery::DIMENSIONS,
                    'default' => 'landing_url',
                    'description' => 'The dimension to group counts by.',
                ],
                'since' => [
                    'type' => 'string',
                    'description' => 'Only count leads created at or after this datetime (e.g. "2026-09-01").',
                ],
                'until' => [
                    'type' => 'string',
                    'description' => 'Only count leads created at or before this datetime.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['captured', 'booking_pending', 'booked', 'abandoned', 'canceled'],
                    'description' => 'Only count leads with this lifecycle status.',
                ],
                'source_type' => [
                    'type' => 'string',
                    'enum' => ['ad', 'organic', 'referral_hub', 'partnership'],
                    'description' => 'Only count leads with this attribution source type.',
                ],
                'landing_url_contains' => [
                    'type' => 'string',
                    'description' => 'Only count leads whose landing URL contains this substring, '.
                        'e.g. "/compare-athena".',
                ],
                'utm_source' => ['type' => 'string'],
                'utm_medium' => ['type' => 'string'],
                'utm_campaign' => ['type' => 'string'],
                'limit' => [
                    'type' => 'integer',
                    'default' => 50,
                    'description' => 'Maximum groups to return (capped at 200).',
                ],
            ],
        ];
    }

    public function meta(): array
    {
        return ['mcp' => ['public' => true]];
    }
}
