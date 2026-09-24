<?php

declare(strict_types=1);

namespace App\Domains\Lead\Api;

use App\Ai\InsightsCapability;
use App\Domains\Lead\Export\LeadExportColumns;
use Illuminate\Support\Facades\Log;
use WP_Error;
use WP_REST_Request;

/**
 * `GET /wp-json/rl-data/v1/{leads,bookings}` — leads and bookings over a window, for the data team.
 *
 * ## Why WordPress REST and not routes/api.php
 *
 * The caller authenticates with an Application Password, and WordPress only honours those on
 * REST (and XML-RPC) requests. On an Acorn route the same `Authorization` header is ignored and
 * every call reads as logged out.
 *
 * ## Who can call it
 *
 * Whoever holds {@see InsightsCapability}, the same gate as the MCP lead abilities. The rows are
 * customer PII — names, emails, phone numbers — so the credential is issued per consumer from
 * Settings → Data API, and every pull is logged with who made it and what it covered.
 *
 * Nothing but wiring and parameter checking lives here: {@see LeadDataWindow} reads the window,
 * {@see LeadDataFeed} builds the page.
 */
class LeadDataRestRoutes
{
    public const NAMESPACE = 'rl-data/v1';

    public const RESOURCES = ['leads', 'bookings'];

    public function __construct(protected LeadDataFeed $feed) {}

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            foreach (self::RESOURCES as $resource) {
                register_rest_route(self::NAMESPACE, '/'.$resource, [
                    'methods' => 'GET',
                    'callback' => fn (WP_REST_Request $request) => $this->handle($resource, $request->get_query_params()),
                    'permission_callback' => fn () => $this->authorize(),
                ]);
            }
        });
    }

    public function authorize(): bool|WP_Error
    {
        if (InsightsCapability::currentUserCan()) {
            return true;
        }

        // 401 asks for a credential, 403 says the one presented is not enough. Collapsing them
        // sends whoever is debugging a rotated password off to ask for a permission they have.
        return new WP_Error(
            'rl_data_forbidden',
            'This endpoint needs a Data API credential (Settings → Data API).',
            ['status' => is_user_logged_in() ? 403 : 401],
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|WP_Error
     */
    public function handle(string $resource, array $params): array|WP_Error
    {
        $window = LeadDataWindow::parse($params['from'] ?? null, $params['to'] ?? null);

        if ($window instanceof WP_Error) {
            return $window;
        }

        $groups = $this->groups($params['groups'] ?? null);

        if ($groups instanceof WP_Error) {
            return $groups;
        }

        $cursor = max(0, (int) ($params['cursor'] ?? 0));
        $limit = (int) ($params['limit'] ?? 0);
        $limit = $limit > 0 ? $limit : LeadDataFeed::DEFAULT_LIMIT;

        $page = $resource === 'bookings'
            ? $this->feed->bookings($window, $groups, $cursor, $limit)
            : $this->feed->leads($window, $groups, $cursor, $limit);

        Log::info('Data API: '.$resource.' served', [
            'user' => wp_get_current_user()->user_login ?? null,
            'window' => $page['window'],
            'cursor' => $cursor,
            'rows' => $page['count'],
        ]);

        return $page;
    }

    /**
     * Every group unless the caller narrowed it.
     *
     * An unknown group is refused rather than dropped, unlike the admin export's modal. A human
     * sees a missing column in the file they just downloaded; a loader writes nulls into it every
     * night and nobody looks until the dashboard built on it is wrong.
     *
     * @return array<int, string>|WP_Error
     */
    private function groups(mixed $requested): array|WP_Error
    {
        if ($requested === null || $requested === '') {
            return LeadExportColumns::groupSlugs();
        }

        $requested = is_array($requested) ? $requested : explode(',', (string) $requested);
        $requested = array_filter(array_map(static fn ($g): string => is_scalar($g) ? trim((string) $g) : '', $requested));
        $unknown = array_diff($requested, LeadExportColumns::groupSlugs());

        if ($unknown !== []) {
            return new WP_Error(
                'rl_data_unknown_group',
                'Unknown group: '.implode(', ', $unknown).'. Valid groups are '.implode(', ', LeadExportColumns::groupSlugs()).'.',
                ['status' => 400],
            );
        }

        return array_values($requested);
    }
}
