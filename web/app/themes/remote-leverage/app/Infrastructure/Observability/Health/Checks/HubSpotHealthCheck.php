<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health\Checks;

use App\Domains\Lead\Services\HubSpotGateway;
use App\Infrastructure\Observability\Health\IntegrationHealthCheck;

class HubSpotHealthCheck implements IntegrationHealthCheck
{
    public function __construct(
        protected HubSpotGateway $gateway = new HubSpotGateway,
    ) {}

    public function integration(): string
    {
        return 'hubspot';
    }

    public function label(): string
    {
        return 'HubSpot';
    }

    public function isConfigured(): bool
    {
        return $this->gateway->isConfigured();
    }
}
