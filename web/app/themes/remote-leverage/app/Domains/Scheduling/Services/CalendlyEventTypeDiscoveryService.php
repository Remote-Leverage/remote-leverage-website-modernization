<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Services;

use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Gateways\CalendlyTokenPool;

class CalendlyEventTypeDiscoveryService
{
    public const BACKUP_OPTION_KEY = 'rl_calendly_event_types_backup';

    public function __construct(
        protected CalendlyTokenPool $tokenPool,
        protected CalendlyClient $client,
    ) {}

    /**
     * Discover every event type reachable by any pooled token, across that
     * token's user/organization/team scopes. Not circuit-breaker-gated (context
     * null), matching legacy's get_event_types_list_force_refresh(), which also
     * addresses every enabled token regardless of its metadata-failure history.
     *
     * @return array<string, array{name: string, duration: ?int, active: bool, label: string}>
     */
    public function discoverEventTypes(): array
    {
        $discovered = [];

        foreach ($this->tokenPool->getEligibleTokens(null) as $item) {
            $token = $item['token'];

            $user = $this->client->getForToken($token, 'https://api.calendly.com/users/me');
            if (! $user || ! $user->successful()) {
                continue;
            }

            $userUri = $user->json('resource.uri');
            $orgUri = $user->json('resource.current_organization');
            if (! $userUri || ! $orgUri) {
                continue;
            }

            $endpoints = [
                ['event_types', ['user' => $userUri]],
                ['event_types', ['organization' => $orgUri]],
                ['managed_event_types', ['organization' => $orgUri]],
            ];

            $memberships = $this->client->getForToken($token, 'https://api.calendly.com/organization_memberships', ['user' => $userUri]);
            if ($memberships && $memberships->successful()) {
                foreach ($memberships->json('collection', []) as $membership) {
                    $teamUri = $membership['organization_memberships'][0]['team']['uri'] ?? null;
                    if ($teamUri) {
                        $endpoints[] = ['event_types', ['team' => $teamUri]];
                    }
                }
            }

            foreach ($endpoints as [$path, $query]) {
                $this->collectFromEndpoint($token, "https://api.calendly.com/{$path}", $query, $discovered);
            }
        }

        if (! empty($discovered) && function_exists('update_option')) {
            update_option(self::BACKUP_OPTION_KEY, $discovered);
        }

        return $discovered;
    }

    /**
     * Return the last successfully discovered set, without hitting the API.
     *
     * @return array<string, array{name: string, duration: ?int, active: bool, label: string}>
     */
    public function backup(): array
    {
        $backup = function_exists('get_option') ? get_option(self::BACKUP_OPTION_KEY, []) : [];

        return is_array($backup) ? $backup : [];
    }

    protected function collectFromEndpoint(string $token, string $url, array $query, array &$discovered): void
    {
        $query['count'] = 100;

        do {
            $response = $this->client->getForToken($token, $url, $query);
            if (! $response || ! $response->successful()) {
                return;
            }

            foreach ($response->json('collection', []) as $event) {
                $uri = $event['uri'] ?? null;
                if (! $uri || isset($discovered[$uri])) {
                    continue;
                }

                $discovered[$uri] = [
                    'name' => $event['name'] ?? '',
                    'duration' => $event['duration'] ?? null,
                    'active' => (bool) ($event['active'] ?? false),
                    'label' => $this->formatLabel($event),
                ];
            }

            $url = $response->json('pagination.next_page');
            $query = [];
        } while ($url !== null);
    }

    protected function formatLabel(array $event): string
    {
        $duration = isset($event['duration']) ? " ({$event['duration']}m)" : '';
        $suffix = empty($event['active']) ? ' (Inactive)' : '';

        return ($event['name'] ?? '').$duration.$suffix;
    }
}
