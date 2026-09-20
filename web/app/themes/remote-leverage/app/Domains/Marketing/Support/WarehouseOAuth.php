<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Support;

use App\Domains\Lead\Services\LeadSettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * "Connect with Google" for the marketing warehouse.
 *
 * The settings screen used to ask for a refresh token as a value to paste. That was the wrong ask:
 * an OAuth flow does not hand anybody a refresh token to copy — it hands back a code, once, to a
 * redirect URI, and the token is what you exchange that code for. Anyone filling that field in
 * would have had to run `gcloud` on a laptop or dig through another tool's credential store.
 *
 * So the screen runs the flow. An admin presses a button, consents as whoever should own the
 * connection, and the refresh token is exchanged and stored without being seen.
 *
 * ## What still has to be set up once
 *
 * An OAuth client in Google Cloud, which is where the client id and secret come from, and this
 * site's callback registered on it as an authorised redirect URI. There is no way around that —
 * Google will not issue a token to a redirect it has not been told about. {@see self::redirectUri()}
 * is the exact string to paste.
 *
 * ## `prompt=consent`, deliberately
 *
 * Google issues a refresh token only on the *first* consent for a given client and account. An
 * admin who has already authorised this client gets an access token and no refresh token, and the
 * connection appears to work until the hour is up. Forcing the consent screen every time costs one
 * extra click and removes a failure that would otherwise show up an hour later, once.
 */
final class WarehouseOAuth
{
    public const START_ACTION = 'bigquery_oauth_start';

    public const CALLBACK_ACTION = 'bigquery_oauth_callback';

    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /**
     * Not `bigquery.readonly`, which cannot run a query — see BigQueryClient::SCOPE.
     *
     * `email` comes along so the screen can say which account is connected. A connection nobody
     * can attribute is a connection nobody dares revoke.
     */
    private const SCOPE = 'https://www.googleapis.com/auth/bigquery email';

    /** Where Google sends the admin back. Must be registered on the OAuth client, exactly. */
    public static function redirectUri(): string
    {
        return admin_url('admin.php?page=rl-leads-settings&rl_action='.self::CALLBACK_ACTION);
    }

    /** Is there an OAuth client to run a flow with? */
    public static function isReady(): bool
    {
        return self::clientId() !== '' && self::clientSecret() !== '';
    }

    /** The consent URL to send the admin to. */
    public static function authorizationUrl(): string
    {
        return self::AUTH_ENDPOINT.'?'.http_build_query([
            'client_id' => self::clientId(),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,

            // Without this Google issues no refresh token at all, only an hour of access.
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => wp_create_nonce(self::CALLBACK_ACTION),
        ]);
    }

    /**
     * Exchange the code Google sent back, and store the refresh token.
     *
     * @return string A message for the admin, empty on success.
     */
    public static function completeFromRequest(): string
    {
        $state = sanitize_text_field((string) ($_GET['state'] ?? ''));

        /*
         * `state` is the CSRF guard on a redirect nobody clicked from inside wp-admin. Checked
         * before the code is spent, because exchanging it is the irreversible half.
         */
        if (! wp_verify_nonce($state, self::CALLBACK_ACTION)) {
            return 'The Google sign-in could not be verified. Start it again from this page.';
        }

        $error = sanitize_text_field((string) ($_GET['error'] ?? ''));

        if ($error !== '') {
            return 'Google returned: '.$error;
        }

        $code = sanitize_text_field((string) ($_GET['code'] ?? ''));

        if ($code === '') {
            return 'Google sent no authorisation code back.';
        }

        try {
            $response = Http::asForm()->timeout(15)->post(self::TOKEN_ENDPOINT, [
                'code' => $code,
                'client_id' => self::clientId(),
                'client_secret' => self::clientSecret(),
                'redirect_uri' => self::redirectUri(),
                'grant_type' => 'authorization_code',
            ]);
        } catch (\Throwable $e) {
            Log::error('WarehouseOAuth: token exchange failed', ['error' => $e->getMessage()]);

            return 'Could not reach Google to complete the sign-in.';
        }

        $refreshToken = (string) ($response->json('refresh_token') ?? '');

        if (! $response->successful() || $refreshToken === '') {
            $reason = $response->json('error_description') ?? $response->json('error') ?? 'no refresh token returned';

            return 'Google refused the sign-in: '.$reason;
        }

        self::store($refreshToken, self::accountEmail((string) ($response->json('access_token') ?? '')));

        return '';
    }

    /** Forget the stored token. The grant itself is revoked from the Google account, not here. */
    public static function disconnect(): void
    {
        self::store('', '');
    }

    /** Which Google account the stored token belongs to, or empty. */
    public static function connectedAccount(): string
    {
        return trim((string) (self::settings()['bigquery_connected_email'] ?? ''));
    }

    public static function isConnected(): bool
    {
        return trim((string) (self::settings()['bigquery_refresh_token'] ?? '')) !== ''
            || trim((string) config('marketing.warehouse.refresh_token', '')) !== '';
    }

    /**
     * Who consented, for the screen to show.
     *
     * Best effort: a failure here loses a label, not the connection, so it must not be allowed to
     * fail the sign-in that has already succeeded.
     */
    private static function accountEmail(string $accessToken): string
    {
        if ($accessToken === '') {
            return '';
        }

        try {
            $response = Http::withToken($accessToken)->timeout(10)
                ->get('https://openidconnect.googleapis.com/v1/userinfo');

            return $response->successful() ? (string) ($response->json('email') ?? '') : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private static function store(string $refreshToken, string $email): void
    {
        $settings = self::settings();
        $settings['bigquery_refresh_token'] = $refreshToken;
        $settings['bigquery_connected_email'] = $email;

        update_option(LeadSettingsService::OPTION_KEY, $settings);
    }

    /** @return array<string, mixed> */
    private static function settings(): array
    {
        return (new LeadSettingsService)->get();
    }

    private static function clientId(): string
    {
        return self::resolve('marketing.warehouse.client_id', 'bigquery_client_id');
    }

    private static function clientSecret(): string
    {
        return self::resolve('marketing.warehouse.client_secret', 'bigquery_client_secret');
    }

    private static function resolve(string $configKey, string $settingKey): string
    {
        $fromEnvironment = trim((string) (config($configKey) ?? ''));

        return $fromEnvironment !== ''
            ? $fromEnvironment
            : trim((string) (self::settings()[$settingKey] ?? ''));
    }
}
