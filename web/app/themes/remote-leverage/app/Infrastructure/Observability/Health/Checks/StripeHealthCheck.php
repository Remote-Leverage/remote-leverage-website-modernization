<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health\Checks;

use App\Domains\Payment\Services\StripePaymentIntentGateway;
use App\Infrastructure\Observability\Health\IntegrationHealthCheck;

class StripeHealthCheck implements IntegrationHealthCheck
{
    public function __construct(
        protected StripePaymentIntentGateway $gateway = new StripePaymentIntentGateway,
    ) {}

    public function integration(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Stripe';
    }

    public function isConfigured(): bool
    {
        return $this->gateway->isConfigured();
    }
}
