<?php

declare(strict_types=1);

namespace App\Domains\Referral\Services;

use App\Domains\Referral\Actions\TrackReferralClickAction;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Support\ReferralLink;
use Illuminate\Support\Facades\Log;

/**
 * Who referred the person making this request, resolved once per request.
 *
 * **This replaces `ReferralAttributionMiddleware`, which never ran.** That class was registered
 * nowhere, and being Laravel middleware it could not have covered the pages that matter even if
 * it had been: `RouteServiceProvider` applies middleware to `routes/web.php` and
 * `routes/api.php`, and `/hire-va-4/` — the only page a referral link points at — is rendered by
 * WordPress, not by a Laravel route. So `?via=` never set the attribution cookie and no click
 * was ever recorded from real traffic; the referrer portal's "link clicks" figure could only
 * ever have been zero in production.
 *
 * Resolution therefore hangs off `template_redirect` (see ReferralServiceProvider), which runs
 * for every front-end request, WordPress-rendered or not, and still runs before any output so
 * the cookie can be set.
 */
class ReferralVisitorContext
{
    /**
     * Query parameters that carry a referral code inbound. Only `via` is ever generated.
     */
    protected const PARAMS = [ReferralLink::PARAM, 'ref', 'r'];

    protected bool $resolved = false;

    protected ?Referrer $referrer = null;

    /**
     * Whether this specific request carried a referral code in its URL, as opposed to merely
     * being a later page view by someone who still holds the cookie. The welcome notice keys
     * off this so it appears on arrival rather than following the visitor around the site.
     */
    protected bool $arrivedFromLink = false;

    /**
     * Whether this request is the referrer previewing their own link from the portal.
     */
    protected bool $isPreview = false;

    public function __construct(
        protected AttributionEngine $attributionEngine,
        protected TrackReferralClickAction $trackClickAction,
    ) {}

    /**
     * Resolve the referrer for this request, recording the click and setting the cookie.
     *
     * Safe to call more than once; only the first call does any work. Never throws — a broken
     * referral lookup must not take down the landing page it is decorating.
     */
    public function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        $this->resolved = true;

        try {
            $fromQuery = $this->codeFromQuery();
            $cookie = $_COOKIE[AttributionEngine::COOKIE_NAME] ?? null;

            $slug = $this->attributionEngine->resolveReferralSlug(
                viaParam: $fromQuery,
                cookie: is_string($cookie) ? $cookie : null,
            );

            if ($slug === null || $slug === '') {
                return;
            }

            $referrer = $this->attributionEngine->findReferrerByReferralCode($slug);

            if (! $referrer) {
                return;
            }

            $this->referrer = $referrer;

            if ($fromQuery === null) {
                // A returning visitor holding the cookie. Nothing new to record, and no notice.
                return;
            }

            $this->arrivedFromLink = true;

            /*
             * A preview opened from the referrer's own portal still resolves and still shows
             * the welcome notice — that is the point of previewing — but must not be counted.
             * Without this a referrer checking their own links would inflate the "link clicks"
             * figure their dashboard reports back to them.
             *
             * The flag is an HMAC over the referral code rather than a literal, so a visitor
             * cannot append it to suppress a click that should have been recorded.
             *
             * Returning here also skips the attribution cookie, deliberately: a referrer who
             * previewed their own link would otherwise be attributed to themselves for the next
             * 60 days, and any form they later filled in would arrive as their own referral.
             */
            $previewToken = $_GET[ReferralLink::PREVIEW_PARAM] ?? null;

            if (is_string($previewToken) && ReferralLink::isValidPreviewToken($slug, $previewToken)) {
                $this->isPreview = true;

                return;
            }

            $this->trackClickAction->execute($slug, [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'landing_page' => $_SERVER['REQUEST_URI'] ?? '/',
                'referer_url' => $_SERVER['HTTP_REFERER'] ?? null,
            ]);

            $this->rememberInCookie($slug);
        } catch (\Throwable $e) {
            Log::error('ReferralVisitorContext: could not resolve referral for this request: '.$e->getMessage());
        }
    }

    /**
     * True when the referrer is previewing their own link rather than a prospect arriving.
     */
    public function isPreview(): bool
    {
        $this->resolve();

        return $this->isPreview;
    }

    public function referrer(): ?Referrer
    {
        $this->resolve();

        return $this->referrer;
    }

    /**
     * True only on the request that carried the referral code in its URL.
     */
    public function arrivedFromLink(): bool
    {
        $this->resolve();

        return $this->arrivedFromLink;
    }

    /**
     * The referral code attributed to this visitor, from the URL or the cookie.
     */
    public function code(): ?string
    {
        return $this->referrer()?->referral_code;
    }

    /**
     * Read a referral code off the current request's query string.
     */
    protected function codeFromQuery(): ?string
    {
        foreach (self::PARAMS as $param) {
            $value = $_GET[$param] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * Persist the attribution so it survives the visitor navigating away from the landing page.
     *
     * `httponly: false` deliberately — the booking wizard reads this from JavaScript to
     * pre-fill the referral code, which is the behaviour the retired middleware documented.
     */
    protected function rememberInCookie(string $slug): void
    {
        if (headers_sent()) {
            // Nothing to be done, and warning about it on every request would be noise. The
            // hook this runs on fires before output, so this is a genuine edge case only.
            return;
        }

        setcookie(AttributionEngine::COOKIE_NAME, $slug, [
            'expires' => time() + $this->attributionEngine->getCookieMaxAge(),
            'path' => '/',
            'secure' => ! empty($_SERVER['HTTPS']),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        // So anything resolving later in this same request sees it rather than waiting for the
        // browser to send it back on the next one.
        $_COOKIE[AttributionEngine::COOKIE_NAME] = $slug;
    }
}
