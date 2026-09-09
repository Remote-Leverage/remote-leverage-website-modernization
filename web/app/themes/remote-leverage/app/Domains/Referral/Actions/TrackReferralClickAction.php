<?php

declare(strict_types=1);

namespace App\Domains\Referral\Actions;

use App\Domains\Referral\Models\ReferralClick;
use App\Domains\Referral\Services\AttributionEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TrackReferralClickAction
{
    public function __construct(
        protected AttributionEngine $attributionEngine
    ) {}

    /**
     * Record a referral click with 24-hour IP deduplication.
     *
     * Resolves the slug against a registered Referrer first (the current
     * attribution system); falls back to the legacy WP `rl_referrer`-role user lookup
     * so nothing regresses if that path is ever exercised.
     */
    public function execute(string $referrerSlug, array $context = []): ?ReferralClick
    {
        $ipAddress = $context['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');

        $referrer = $this->attributionEngine->findReferrerByReferralCode($referrerSlug);

        if ($referrer) {
            if ($this->isRecentReferrerClick($referrer->id, $ipAddress)) {
                Log::debug("Referral click ignored for {$referrerSlug}: already recorded within 24h from {$ipAddress}");

                return null;
            }

            return ReferralClick::query()->create([
                'referrer_id' => $referrer->id,
                'referrer_slug' => $referrerSlug,
                'landing_page' => $context['landing_page'] ?? ($_SERVER['REQUEST_URI'] ?? '/'),
                'ip_address' => $ipAddress,
                'user_agent' => $context['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'referer_url' => $context['referer_url'] ?? ($_SERVER['HTTP_REFERER'] ?? null),
                'created_at' => now(),
            ]);
        }

        $user = $this->attributionEngine->findReferrerUser($referrerSlug);
        if (! $user) {
            return null;
        }

        // 24h IP deduplication check from legacy engine
        if ($this->attributionEngine->isRecentClick($user->ID, $ipAddress)) {
            Log::debug("Referral click ignored for {$referrerSlug}: already recorded within 24h from {$ipAddress}");

            return null;
        }

        return ReferralClick::query()->create([
            'referrer_user_id' => $user->ID,
            'referrer_slug' => $referrerSlug,
            'landing_page' => $context['landing_page'] ?? ($_SERVER['REQUEST_URI'] ?? '/'),
            'ip_address' => $ipAddress,
            'user_agent' => $context['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'referer_url' => $context['referer_url'] ?? ($_SERVER['HTTP_REFERER'] ?? null),
            'created_at' => now(),
        ]);
    }

    /**
     * Check if a click for this referrer/IP pair was recorded within the last 24 hours.
     */
    protected function isRecentReferrerClick(int $referrerId, string $ipAddress): bool
    {
        return ReferralClick::query()
            ->where('referrer_id', $referrerId)
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->exists();
    }
}
