<?php

declare(strict_types=1);

namespace App\Domains\Referral\Services;

use App\Domains\Referral\Models\Referrer;

class AttributionEngine
{
    public const COOKIE_NAME = 'rl_referrer';

    public const DEFAULT_COOKIE_DAYS = 60;

    /**
     * Resolve the active referrer slug from query parameters, cookies, or HTTP Referer.
     * Ported from RL_Referral_Tracker::get_active_referrer_slug().
     */
    public function resolveReferralSlug(?string $viaParam = null, ?string $refParam = null, ?string $rParam = null, ?string $cookie = null, ?string $httpReferer = null): ?string
    {
        // 1. Direct request parameters take priority
        foreach ([$viaParam, $refParam, $rParam] as $param) {
            if ($param && trim($param) !== '') {
                return $this->sanitizeSlug($param);
            }
        }

        // 2. Cookie check
        if ($cookie && trim($cookie) !== '') {
            return $this->sanitizeSlug($cookie);
        }

        // 3. Fallback: Parse HTTP_REFERER query string
        if ($httpReferer && trim($httpReferer) !== '') {
            $queryString = parse_url($httpReferer, PHP_URL_QUERY);
            if ($queryString) {
                parse_str($queryString, $queryParams);
                $fromReferer = $queryParams['via'] ?? $queryParams['ref'] ?? $queryParams['r'] ?? null;
                if ($fromReferer) {
                    return $this->sanitizeSlug((string) $fromReferer);
                }
            }
        }

        return null;
    }

    /**
     * Resolve and stamp canonical lead source attribution per ADR-0008.
     * Evaluates referral slugs, cookies, and ad tracking signals to categorize into
     * sourceType ('ad', 'organic', 'referral_hub', 'partnership') and sourceID.
     *
     * @return array{sourceType: string, sourceID: ?string}
     */
    public function resolveLeadSource(
        ?string $referralSlug = null,
        ?string $utmSource = null,
        ?string $utmCampaign = null,
        ?string $cookie = null
    ): array {
        // 1. Resolve active referral slug
        $resolvedSlug = $this->resolveReferralSlug(viaParam: $referralSlug, cookie: $cookie);

        if ($resolvedSlug) {
            // Distinguish strategic partnership vs referral hub
            $isPartnership = str_starts_with($resolvedSlug, 'partner-') ||
                             str_starts_with($resolvedSlug, 'co-') ||
                             str_contains($resolvedSlug, 'strategic');

            return [
                'sourceType' => $isPartnership ? 'partnership' : 'referral_hub',
                'sourceID' => $resolvedSlug,
            ];
        }

        // 2. Paid Ads detection
        $adSources = ['google', 'facebook', 'linkedin', 'meta', 'twitter', 'tiktok', 'bing', 'cpc'];
        $isPaidSource = ($utmSource && in_array(strtolower($utmSource), $adSources, true)) ||
                        ($utmCampaign && trim($utmCampaign) !== '');

        if ($isPaidSource) {
            return [
                'sourceType' => 'ad',
                'sourceID' => $utmCampaign ?: $utmSource,
            ];
        }

        // 3. Fallback to organic
        return [
            'sourceType' => 'organic',
            'sourceID' => null,
        ];
    }

    /**
     * Validate if a referral slug belongs to a valid registered referrer WP user.
     * Ported from RL_Referral_Tracker::handle_referral_query().
     */
    public function findReferrerUser(string $slug): ?\WP_User
    {
        if (! function_exists('get_user_by')) {
            return null;
        }

        $user = get_user_by('login', $slug) ?: get_user_by('slug', $slug);
        if (! $user) {
            return null;
        }

        $isReferrer = in_array('rl_referrer', (array) $user->roles, true) ||
                      (function_exists('user_can') && user_can($user, 'rl_access_referral_dashboard'));

        return $isReferrer ? $user : null;
    }

    /**
     * Resolve a registered Referrer by their referral code.
     * This is the primary attribution path for the referral program.
     */
    public function findReferrerByReferralCode(string $slug): ?Referrer
    {
        return Referrer::query()->where('referral_code', $slug)->first();
    }

    /**
     * Check if a 24-hour click deduplication window has passed for this IP and referrer.
     */
    public function isRecentClick(int $referrerUserId, string $ipAddress): bool
    {
        global $wpdb;
        if (! $wpdb) {
            return false;
        }

        $table = $wpdb->prefix.'rl_referral_clicks';

        $recent = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE referrer_user_id = %d
               AND ip_address = %s
               AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             LIMIT 1",
            $referrerUserId,
            $ipAddress
        ));

        return ! empty($recent);
    }

    /**
     * Calculate cookie lifetime in seconds based on WordPress option.
     */
    public function getCookieMaxAge(): int
    {
        $days = function_exists('get_option') ? (int) get_option('rl_ref_cookie_days', self::DEFAULT_COOKIE_DAYS) : self::DEFAULT_COOKIE_DAYS;

        return ($days > 0 ? $days : self::DEFAULT_COOKIE_DAYS) * 86400;
    }

    protected function sanitizeSlug(string $slug): string
    {
        $cleaned = function_exists('\\sanitize_text_field') ? \sanitize_text_field($slug) : strip_tags($slug);

        return trim((string) $cleaned);
    }
}
