<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack;

use App\Domains\Lead\Services\LeadSettingsService;

/**
 * Where the Slack credentials come from, and in what order.
 *
 * **Environment first, admin setting second** — the same precedence `HubSpotGateway` and the
 * ZeroBounce check use. The admin setting is not a convenience: ECS maps Secrets Manager keys to
 * environment variables one at a time in the task definition, so a newly added credential cannot
 * reach staging any other way until that changes. The Lead settings blob is in the environment
 * sync whitelist (`config/rl-sync.php`) for this exact reason, which is what lets a credential
 * be set locally and pushed to staging without a deploy.
 *
 * This class exists because the rule was about to be written a third and fourth time. The
 * transport needed it for the bot token and channel; the interaction endpoint and the button
 * gating both need it for the signing secret, and three copies of a precedence rule is three
 * chances for one of them to disagree about which environment is configured.
 */
final class SlackCredentials
{
    public static function botToken(): string
    {
        return self::resolve('services.slack.bot_token', 'slack_bot_token');
    }

    public static function channel(): string
    {
        return self::resolve('services.slack.channel', 'slack_channel');
    }

    /**
     * Verifies inbound interactions, and gates whether the action buttons render at all.
     *
     * Read by `SlackInteractionController` and by the lead alert's button gating. Named in prose
     * rather than an `@see` tag on purpose: a tag here becomes a real `use` statement under this
     * codebase's Pint rules, and infrastructure importing a controller is a dependency pointing
     * the wrong way for the sake of a comment.
     */
    public static function signingSecret(): string
    {
        return self::resolve('services.slack.signing_secret', 'slack_signing_secret');
    }

    /**
     * Read the settings blob once per call rather than caching it.
     *
     * The blob is a single WordPress option, so this is one already-autoloaded read; caching it
     * would mean a value edited in wp-admin did not take effect until the next request, which is
     * the opposite of why the setting exists.
     */
    private static function resolve(string $configKey, string $settingKey): string
    {
        $fromEnvironment = trim((string) (config($configKey) ?? ''));

        if ($fromEnvironment !== '') {
            return $fromEnvironment;
        }

        $settings = (new LeadSettingsService)->get();

        return trim((string) ($settings[$settingKey] ?? ''));
    }
}
