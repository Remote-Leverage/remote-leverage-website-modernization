<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health\Checks;

use App\Infrastructure\Observability\Health\IntegrationHealthCheck;
use App\Infrastructure\Slack\SlackCredentials;

class SlackHealthCheck implements IntegrationHealthCheck
{
    public function integration(): string
    {
        return 'slack';
    }

    public function label(): string
    {
        return 'Slack';
    }

    public function isConfigured(): bool
    {
        return SlackCredentials::botToken() !== '';
    }
}
