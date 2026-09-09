<?php

declare(strict_types=1);

namespace App\Application\Http\Middleware;

use App\Domains\Referral\Actions\TrackReferralClickAction;
use App\Domains\Referral\Services\AttributionEngine;
use Closure;
use Illuminate\Http\Request;

class ReferralAttributionMiddleware
{
    public function __construct(
        protected AttributionEngine $attributionEngine,
        protected TrackReferralClickAction $trackClickAction,
    ) {}

    /**
     * Inspect incoming request for ?via=, ?ref=, ?r= or cookies.
     * Ported from RL_Referral_Tracker::handle_referral_query().
     */
    public function handle(Request $request, Closure $next)
    {
        $viaParam = $request->query('via');
        $refParam = $request->query('ref');
        $rParam = $request->query('r');
        $cookie = $request->cookie(AttributionEngine::COOKIE_NAME);
        $referer = $request->header('referer');

        $resolvedSlug = $this->attributionEngine->resolveReferralSlug(
            viaParam: $viaParam,
            refParam: $refParam,
            rParam: $rParam,
            cookie: $cookie,
            httpReferer: $referer,
        );

        $response = $next($request);

        // If a new query parameter was present and matches a valid referrer
        $hasQuery = ! empty($viaParam) || ! empty($refParam) || ! empty($rParam);
        if ($resolvedSlug && $hasQuery) {
            $isValidReferrer = $this->attributionEngine->findReferrerByReferralCode($resolvedSlug)
                || $this->attributionEngine->findReferrerUser($resolvedSlug);

            if ($isValidReferrer) {
                // Record click in database
                $this->trackClickAction->execute($resolvedSlug, [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'landing_page' => $request->fullUrl(),
                    'referer_url' => $referer,
                ]);

                // Attach persistent rl_referrer cookie
                if (method_exists($response, 'withCookie')) {
                    $maxAgeMinutes = (int) round($this->attributionEngine->getCookieMaxAge() / 60);
                    $response->withCookie(cookie(
                        name: AttributionEngine::COOKIE_NAME,
                        value: $resolvedSlug,
                        minutes: $maxAgeMinutes,
                        path: '/',
                        secure: $request->isSecure(),
                        httpOnly: false, // Accessible to JavaScript for form autofill
                        sameSite: 'lax',
                    ));
                }
            }
        }

        return $response;
    }
}
