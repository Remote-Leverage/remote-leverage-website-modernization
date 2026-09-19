<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Gateways;

use App\Domains\Marketing\Contracts\AdSpendSource;
use App\Domains\Marketing\Data\AdSpendReading;
use App\Domains\Marketing\Support\AdPlatformCredentials;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * What the Meta ad account spent today, and whether Meta thinks it is healthy.
 *
 * The read counterpart to `MetaConversionsApiClient`, which only ever pushed conversions out.
 * Nothing in this codebase has ever read back from an ad platform before this class.
 *
 * ## Two calls, not one
 *
 * `/insights` returns spend. It does **not** return the account's timezone, its status, or why it
 * was disabled — and each of those changes how the spend figure should be read:
 *
 *  - **Timezone.** Meta sums `time_range` in the *ad account's* timezone, not UTC and not ours.
 *    If the account is on `America/Los_Angeles` and this application is reporting a
 *    `America/New_York` day, three hours of spend land in the wrong bucket every day, and the
 *    discrepancy is largest first thing in the morning and last thing at night. Reading
 *    `timezone_name` and comparing it is the only way to notice; guessing right once and never
 *    checking again is how this breaks six months later when somebody changes the account.
 *  - **Status.** A disabled or unsettled account reports spend of 0.00 with a perfectly
 *    successful response. Without `account_status`, "Meta spent nothing today" and "Meta's
 *    account is suspended" are the same two hundred OK, and the first reading is the one people
 *    make.
 *
 * So the account fields are fetched alongside, and both are folded into one {@see AdSpendReading}.
 * Two calls an hour against an API that throttles in the thousands is not a cost worth optimising.
 *
 * ## It never throws
 *
 * This runs inside an hourly reporting job and, through the cached snapshot, behind a wp-admin
 * dashboard widget. Every failure comes back as an unreachable reading carrying its reason, and
 * the alert reports which platform it could not reach rather than quietly dividing by a total
 * that is missing one. The HTTP calls go through Laravel's client, so `ObservabilityServiceProvider`
 * records them in `rl_integration_calls` without anything being asked of this class.
 */
class MetaInsightsClient implements AdSpendSource
{
    /** Meta throttles hard on slow endpoints; a reporting job must not hold a request open. */
    private const TIMEOUT_SECONDS = 10;

    public function platform(): string
    {
        return 'meta';
    }

    public function isConfigured(): bool
    {
        return $this->token() !== '' && $this->accountId() !== '';
    }

    public function read(CarbonImmutable $day): AdSpendReading
    {
        if (! $this->isConfigured()) {
            return AdSpendReading::unreachable('meta', 'no access token or ad account id configured');
        }

        try {
            $account = $this->fetchAccount();
            $spend = $this->fetchSpend($day);
        } catch (\Throwable $e) {
            Log::warning('MetaInsightsClient: could not read the ad account', ['error' => $e->getMessage()]);

            return AdSpendReading::unreachable('meta', $e->getMessage());
        }

        if ($spend === null) {
            return AdSpendReading::unreachable('meta', 'the insights endpoint returned no spend field');
        }

        return AdSpendReading::of(
            platform: 'meta',
            spend: $spend,
            currency: $account['currency'] ?? null,
            timezone: $account['timezone_name'] ?? null,
            accountIssue: $this->accountIssue($account),
        );
    }

    /**
     * Today's spend, as a float.
     *
     * `time_range` rather than `date_preset=today`, because the preset is resolved against the
     * account timezone with no way to see what day it picked. An explicit range is a range this
     * code can reason about and the reading can report.
     *
     * An empty `data` array is a real answer meaning nothing was spent, and is returned as 0.0 —
     * not null. Null is reserved for a response whose shape this does not recognise, because that
     * is a different problem and must not be presented as a quiet day.
     */
    private function fetchSpend(CarbonImmutable $day): ?float
    {
        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->get($this->endpoint('insights'), [
                'fields' => 'spend',
                'level' => 'account',
                'time_range' => json_encode([
                    'since' => $day->toDateString(),
                    'until' => $day->toDateString(),
                ]),
                'access_token' => $this->token(),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorFrom($response->json(), $response->status()));
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            return null;
        }

        // No rows at all is Meta's way of saying nothing ran. That is zero, and it is a fact.
        if ($data === []) {
            return 0.0;
        }

        $spend = $data[0]['spend'] ?? null;

        // Meta sends it as a decimal string.
        return is_numeric($spend) ? (float) $spend : null;
    }

    /** @return array<string, mixed> */
    private function fetchAccount(): array
    {
        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->get($this->endpoint(), [
                'fields' => 'currency,timezone_name,account_status,disable_reason',
                'access_token' => $this->token(),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorFrom($response->json(), $response->status()));
        }

        return (array) $response->json();
    }

    /**
     * Meta's own verdict on the account, or null when it is fine.
     *
     * `account_status` 1 is active and 2 is disabled; the rest are various flavours of
     * unavailable. The numbers are reported alongside the words because Meta's own interface
     * shows the words and its API docs show the numbers, and whoever is debugging this at 8am
     * will have one of the two in front of them.
     *
     * @param  array<string, mixed>  $account
     */
    private function accountIssue(array $account): ?string
    {
        $status = (int) ($account['account_status'] ?? 1);

        if ($status === 1) {
            return null;
        }

        $labels = [
            2 => 'disabled',
            3 => 'unsettled',
            7 => 'pending risk review',
            8 => 'pending settlement',
            9 => 'in grace period',
            100 => 'pending closure',
            101 => 'closed',
            201 => 'any active review',
            202 => 'any closed',
        ];

        $issue = sprintf('account_status %d (%s)', $status, $labels[$status] ?? 'unknown');

        $reason = (int) ($account['disable_reason'] ?? 0);

        return $reason > 0 ? $issue.sprintf(', disable_reason %d', $reason) : $issue;
    }

    /**
     * Meta's error message, or something honest when it did not send one.
     *
     * The message is worth surfacing verbatim: Meta distinguishes an expired token, a token
     * missing `ads_read` and an ad account the token cannot see, and those need three different
     * fixes. Collapsing them into "Meta request failed" costs an afternoon.
     *
     * @param  mixed  $body
     */
    private function errorFrom($body, int $status): string
    {
        $message = is_array($body) ? ($body['error']['message'] ?? null) : null;

        return is_string($message) && $message !== ''
            ? $message
            : sprintf('Meta returned HTTP %d with no error message', $status);
    }

    private function endpoint(string $edge = ''): string
    {
        $version = AdPlatformCredentials::get('meta', 'api_version') ?: 'v21.0';
        $account = $this->accountId();

        // `act_` is Meta's ad-account prefix; tolerate it already being there.
        $account = str_starts_with($account, 'act_') ? $account : 'act_'.$account;

        return rtrim("https://graph.facebook.com/{$version}/{$account}/{$edge}", '/');
    }

    private function token(): string
    {
        return AdPlatformCredentials::get('meta', 'access_token');
    }

    private function accountId(): string
    {
        return AdPlatformCredentials::get('meta', 'ad_account_id');
    }
}
