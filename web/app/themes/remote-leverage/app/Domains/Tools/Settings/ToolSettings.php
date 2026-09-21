<?php

declare(strict_types=1);

namespace App\Domains\Tools\Settings;

/**
 * The Tools domain's own settings, stored as discrete WordPress options.
 *
 * Deliberately not a corner of `LeadSettingsService`. That blob is where every admin-set
 * credential in this codebase has ended up — Slack, HubSpot, ZeroBounce, BigQuery — because it
 * was the first screen to need one, and a key for the legacy tool pages has nothing to do with
 * lead capture. A domain's settings belong to that domain; the alternative is one option row
 * that everything reaches into, which is how a settings screen ends up owning credentials for
 * subsystems its own code never calls.
 *
 * Discrete options rather than a blob of our own, following `SocialKitAdmin`: with a single key
 * there is nothing to group, and a plain option works with the Settings API without a custom
 * save path.
 */
final class ToolSettings
{
    /**
     * The OpenAI credential behind `/wp-json/jobwidget/v1/*`.
     *
     * In the environment sync whitelist (`config/rl-sync.php`) so it can be set locally and
     * pushed, rather than typed into an environment nobody has a shell on.
     */
    public const OPENAI_KEY_OPTION = 'rl_tools_openai_api_key';

    /**
     * Read the stored key.
     *
     * Read per call rather than cached: this exists so a pasted key takes effect on the next
     * request, and a cache would defeat exactly that. It is one autoloaded option.
     *
     * Guarded for the same reason the caller is — this is reached from `rest_api_init`, which
     * runs for every REST request including on an install where nothing has written the option.
     */
    public static function openAiApiKey(): string
    {
        if (! function_exists('get_option')) {
            return '';
        }

        return trim((string) get_option(self::OPENAI_KEY_OPTION, ''));
    }
}
