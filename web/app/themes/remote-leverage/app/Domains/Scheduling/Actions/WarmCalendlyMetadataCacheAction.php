<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Services\CalendlyEventTypeDiscoveryService;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;
use Illuminate\Support\Facades\Log;

class WarmCalendlyMetadataCacheAction
{
    public function __construct(
        protected CalendlyEventTypeRoleResolver $eventTypeRoleResolver,
        protected CalendlyEventTypeDiscoveryService $discoveryService,
    ) {}

    /**
     * Stagger a background refresh (both event-details and questions caches) for
     * every known event-type URI, mirroring legacy's twice-daily cache warmer.
     */
    public function execute(): int
    {
        $uris = array_values(array_unique(array_merge(
            array_values(array_filter($this->eventTypeRoleResolver->all())),
            $this->discoveredUris()
        )));

        foreach ($uris as $uri) {
            if (function_exists('wp_schedule_single_event')) {
                wp_schedule_single_event(time() + random_int(1, 60), 'rl_calendly_refresh_questions', [$uri]);
            }
        }

        return count($uris);
    }

    /**
     * @return string[]
     */
    protected function discoveredUris(): array
    {
        try {
            return array_keys($this->discoveryService->discoverEventTypes());
        } catch (\Throwable $e) {
            Log::warning('WarmCalendlyMetadataCacheAction: discovery failed, falling back to configured roles only: '.$e->getMessage());

            return [];
        }
    }
}
