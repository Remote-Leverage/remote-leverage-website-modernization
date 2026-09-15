<?php

declare(strict_types=1);

namespace App\Ai\Abilities;

use App\Ai\InsightsCapability;
use App\Ai\Support\LeadQuery;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * Individual lead rows, contact details included.
 *
 * This is the one ability in the set that returns customer PII, which is why
 * it sits behind InsightsCapability rather than edit_pages and why lead-stats
 * exists alongside it — most "how is this page doing" questions are answerable
 * from the aggregate, and should be.
 */
class QueryLeadsAbility extends Ability
{
    public function __construct(private LeadQuery $query) {}

    public function label(): string
    {
        return 'Query Leads';
    }

    public function description(): string
    {
        return 'Returns individual lead records matching a filter — including name, email and phone, which are '.
            'real customer contact details. Prefer lead-stats when a count or comparison would answer the '.
            'question, and use this only when the individual records are genuinely needed. Filter by date '.
            'range, status, source type, landing URL substring and UTM parameters.';
    }

    public function execute(array $input): mixed
    {
        $limit = min((int) ($input['limit'] ?? 25), 100);

        $leads = $this->query->build($input)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->offset(max((int) ($input['offset'] ?? 0), 0))
            ->get();

        return [
            'total_matching' => (int) $this->query->build($input)->count(),
            'returned' => $leads->count(),
            'leads' => $leads->map(fn ($lead) => [
                'id' => (int) $lead->id,
                'created_at' => (string) $lead->created_at,
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'company' => $lead->company,
                'role_needed' => $lead->role_needed,
                'status' => $lead->status,
                'source_type' => $lead->source_type,
                'landing_url' => $lead->landing_url,
                'utm_source' => $lead->utm_source,
                'utm_medium' => $lead->utm_medium,
                'utm_campaign' => $lead->utm_campaign,
                'referral_code' => $lead->referral_code,
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
                'since' => [
                    'type' => 'string',
                    'description' => 'Only leads created at or after this datetime (e.g. "2026-09-01").',
                ],
                'until' => [
                    'type' => 'string',
                    'description' => 'Only leads created at or before this datetime.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['captured', 'booking_pending', 'booked', 'abandoned', 'canceled'],
                ],
                'source_type' => [
                    'type' => 'string',
                    'enum' => ['ad', 'organic', 'referral_hub', 'partnership'],
                ],
                'landing_url_contains' => [
                    'type' => 'string',
                    'description' => 'Only leads whose landing URL contains this substring.',
                ],
                'utm_source' => ['type' => 'string'],
                'utm_medium' => ['type' => 'string'],
                'utm_campaign' => ['type' => 'string'],
                'limit' => [
                    'type' => 'integer',
                    'default' => 25,
                    'description' => 'Maximum leads to return (capped at 100).',
                ],
                'offset' => [
                    'type' => 'integer',
                    'default' => 0,
                    'description' => 'Rows to skip, for paging through a large result.',
                ],
            ],
        ];
    }

    public function meta(): array
    {
        return ['mcp' => ['public' => true]];
    }
}
