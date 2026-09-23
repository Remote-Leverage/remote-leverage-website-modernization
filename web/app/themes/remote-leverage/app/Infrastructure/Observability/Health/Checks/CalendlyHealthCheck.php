<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health\Checks;

use App\Domains\Scheduling\Gateways\CalendlyTokenPool;
use App\Infrastructure\Observability\Health\IntegrationHealthCheck;

class CalendlyHealthCheck implements IntegrationHealthCheck
{
    public function __construct(
        protected CalendlyTokenPool $tokens = new CalendlyTokenPool,
    ) {}

    public function integration(): string
    {
        return 'calendly';
    }

    public function label(): string
    {
        return 'Calendly';
    }

    public function isConfigured(): bool
    {
        return $this->tokens->getEligibleTokens() !== [];
    }
}
