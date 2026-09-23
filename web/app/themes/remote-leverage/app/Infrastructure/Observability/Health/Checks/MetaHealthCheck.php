<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health\Checks;

use App\Domains\Tracking\Gateways\MetaConversionsApiClient;
use App\Infrastructure\Observability\Health\IntegrationHealthCheck;

class MetaHealthCheck implements IntegrationHealthCheck
{
    public function __construct(
        protected MetaConversionsApiClient $client = new MetaConversionsApiClient,
    ) {}

    public function integration(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return 'Meta Conversions API';
    }

    public function isConfigured(): bool
    {
        return $this->client->enabled();
    }
}
