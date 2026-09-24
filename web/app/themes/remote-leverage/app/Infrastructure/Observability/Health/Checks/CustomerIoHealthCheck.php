<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health\Checks;

use App\Infrastructure\Observability\Health\IntegrationHealthCheck;

class CustomerIoHealthCheck implements IntegrationHealthCheck
{
    public function integration(): string
    {
        return 'customerio';
    }

    public function label(): string
    {
        return 'Customer.io';
    }

    public function alias(): string
    {
        return 'email_marketing';
    }

    public function isConfigured(): bool
    {
        return trim((string) config('services.customer_io.site_id', '')) !== ''
            && trim((string) config('services.customer_io.api_key', '')) !== '';
    }
}
