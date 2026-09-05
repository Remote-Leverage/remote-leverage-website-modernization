<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Actions;

use App\Domains\Tracking\Data\AnalyticsEventData;
use App\Domains\Tracking\Gateways\CustomerIOClient;
use App\Domains\Tracking\Gateways\PostHogClient;

class RecordBehaviorEventAction
{
    public function __construct(
        protected PostHogClient $postHogClient,
        protected CustomerIOClient $customerIOClient,
    ) {}

    /**
     * Dual-dispatch behavioral event to PostHog and Customer.io.
     */
    public function execute(AnalyticsEventData $event): void
    {
        // Dispatch to PostHog for product analytics & funnel analysis
        $this->postHogClient->capture($event);

        // Dispatch to Customer.io for behavioral lifecycle communication
        $this->customerIOClient->track($event);
    }
}
