<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health\Checks;

use App\Domains\Lead\Services\LeadSettingsService;
use App\Infrastructure\Observability\Health\IntegrationHealthCheck;

class ZeroBounceHealthCheck implements IntegrationHealthCheck
{
    public function __construct(
        protected LeadSettingsService $settings = new LeadSettingsService,
    ) {}

    public function integration(): string
    {
        return 'zerobounce';
    }

    public function label(): string
    {
        return 'ZeroBounce';
    }

    public function alias(): string
    {
        return 'email_validation';
    }

    /**
     * Same admin-first, env-fallback precedence as `EmailValidationService::validate()`.
     */
    public function isConfigured(): bool
    {
        $apiKey = (string) ($this->settings->get()['zerobounce_api_key'] ?: config('services.zerobounce.api_key', ''));

        return trim($apiKey) !== '';
    }
}
