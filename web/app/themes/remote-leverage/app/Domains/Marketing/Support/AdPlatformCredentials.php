<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Support;

use App\Domains\Lead\Services\LeadSettingsService;

/**
 * Where the ad platform read credentials come from, and in what order.
 *
 * **Environment first, admin setting second** — the same precedence `SlackCredentials` and
 * `HubSpotGateway` use, and for the same reason: ECS maps Secrets Manager keys to environment
 * variables one at a time in the task definition, so a newly added credential cannot reach
 * staging any other way until that changes. The settings blob is in the environment sync
 * whitelist (`config/rl-sync.php`), which is what lets a credential be set locally and pushed to
 * staging without a deploy.
 *
 * ## Meta is live; Google and Microsoft are not
 *
 * `MetaInsightsClient` reads these the moment `access_token` and `ad_account_id` are both set.
 * Google and Microsoft have no client yet — their keys exist so the tokens can be pasted and
 * synced between environments ahead of the clients, because the slow part of those phases is not
 * the code: Google's developer token is issued against an MCC and approved by Google, and the
 * wait is measured in days.
 *
 * ## The Meta token defaults to the Conversions API one
 *
 * This site already holds `META_CAPI_ACCESS_TOKEN`, and a Business Manager system user token can
 * carry both `ads_read` and the Conversions API permission — so in the common case one
 * credential covers both and there is nothing new to paste. It is still its own key, because the
 * two are different grants. A CAPI-only token authenticates fine and returns no insights, and
 * that failure looks exactly like an ad account that spent nothing rather than like a missing
 * scope. {@see self::metaTokenIsShared()} is how a settings screen can say so out loud.
 */
final class AdPlatformCredentials
{
    /** The platforms this resolves for, and the settings-key prefix each uses. */
    public const PLATFORMS = ['google', 'meta', 'microsoft'];

    /**
     * Every credential each platform takes.
     *
     * A constant rather than `array_keys(config(...))`, which is what this was first, because
     * `LeadSettingsService::defaults()` needs the list and is called in contexts where the config
     * repository is not booted — a settings blob that silently loses every ad credential outside
     * a full application is not a trade worth the elegance.
     *
     * It is still exactly the config file's keys, and `MarketingCostAlertTest` fails if the two
     * ever disagree. That is the same shape as the other duplicated definitions in this codebase:
     * two representations are allowed, drifting apart is not.
     *
     * @var array<string, array<int, string>>
     */
    public const KEYS = [
        'google' => ['developer_token', 'client_id', 'client_secret', 'refresh_token', 'customer_id', 'login_customer_id'],
        'meta' => ['access_token', 'ad_account_id', 'api_version'],
        'microsoft' => ['developer_token', 'client_id', 'client_secret', 'refresh_token', 'customer_id', 'account_id'],
    ];

    /**
     * One credential, environment first.
     *
     * @param  string  $platform  One of {@see self::PLATFORMS}.
     * @param  string  $key  The key within that platform's config block, e.g. `developer_token`.
     */
    public static function get(string $platform, string $key): string
    {
        $platform = strtolower(trim($platform));

        if (! in_array($platform, self::PLATFORMS, true)) {
            return '';
        }

        $fromEnvironment = trim((string) (config("marketing.ads.{$platform}.{$key}") ?? ''));

        if ($fromEnvironment !== '') {
            return $fromEnvironment;
        }

        $settings = (new LeadSettingsService)->get();

        return trim((string) ($settings[self::settingKey($platform, $key)] ?? ''));
    }

    /**
     * The key this credential is stored under in the Lead settings blob.
     *
     * Prefixed rather than bare because the blob is shared with HubSpot, Slack and ZeroBounce,
     * and `client_id` on its own would collide the moment a second OAuth integration arrives.
     */
    public static function settingKey(string $platform, string $key): string
    {
        return "ads_{$platform}_{$key}";
    }

    /**
     * Every setting key this class can read, for a settings form to render and persist.
     *
     * Driven off {@see self::KEYS}, so a credential added there appears on the settings screen
     * without a second edit — the drift between "what the code reads" and "what the form offers"
     * being the failure that leaves a token with nowhere to be pasted.
     *
     * @return array<string, array<int, string>> platform => setting keys
     */
    public static function settingKeys(): array
    {
        $keys = [];

        foreach (self::KEYS as $platform => $credentials) {
            $keys[$platform] = array_map(
                static fn (string $key): string => self::settingKey($platform, $key),
                $credentials,
            );
        }

        return $keys;
    }

    /**
     * Is every credential this platform needs present?
     *
     * Every key in its config block, because a partial set is the worst state: it authenticates
     * far enough to fail somewhere specific and unhelpful.
     */
    public static function configured(string $platform): bool
    {
        $keys = self::KEYS[strtolower(trim($platform))] ?? [];

        if ($keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            /*
             * Optional on a single-account setup: Google only needs a login customer id when the
             * call is made through a manager account on behalf of a child.
             */
            if ($key === 'login_customer_id') {
                continue;
            }

            if (self::get($platform, (string) $key) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Is Meta's read token the same string as the Conversions API token?
     *
     * Worth surfacing on the settings screen. It is the expected and fine case, but it means the
     * scopes were never checked separately, so "Meta spent nothing today" should be read as
     * "this token may not have `ads_read`" until someone has confirmed otherwise once.
     */
    public static function metaTokenIsShared(): bool
    {
        $capi = trim((string) (config('services.meta.capi_access_token') ?? env('META_CAPI_ACCESS_TOKEN', '')));

        return $capi !== '' && self::get('meta', 'access_token') === $capi;
    }
}
