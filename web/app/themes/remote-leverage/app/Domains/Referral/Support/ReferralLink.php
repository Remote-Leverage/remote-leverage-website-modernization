<?php

declare(strict_types=1);

namespace App\Domains\Referral\Support;

/**
 * The pages a referrer can share, and the links that point at them.
 *
 * `/hire-va-4/` stays the default — it is what the portal shows and what every share button
 * uses until the referrer deliberately picks something else. The alternatives are a curated
 * list rather than every published page: the site has around forty, most of them retired
 * experiments or internal variants, and a referrer handed that list would inevitably send a
 * prospect somewhere with no offer on it.
 */
class ReferralLink
{
    /**
     * Default destination, used whenever no path is named.
     */
    public const DESTINATION_PATH = 'hire-va-4';

    /**
     * The query parameter carrying the referral code. `ref` and `r` are accepted inbound
     * (see AttributionEngine) but never generated.
     */
    public const PARAM = 'via';

    /**
     * Marks a preview opened from the portal so it is not counted as a real click.
     */
    public const PREVIEW_PARAM = 'rl_preview';

    /**
     * Pages offered in the portal's picker, in display order — homepage first.
     *
     * `path` is relative to the site root; an empty path is the homepage. `name` is written for
     * a referrer rather than taken from `post_title`: two of these share the title "We're Here
     * To Steal Your Job", and the others are SEO titles far too long for a tile.
     *
     * `image` resolves under `public/images/referral-links/` via BlockDefaults::pageImg(); the
     * sources live in `resources/images/pages/referral-links/` and are hero screenshots.
     *
     * @return array<int, array{path: string, name: string, blurb: string, image: string}>
     */
    public static function destinations(): array
    {
        return [
            [
                'path' => '',
                'name' => 'Homepage',
                'blurb' => 'The main site. Best for a warm introduction.',
                'image' => 'home.jpg',
            ],
            [
                'path' => self::DESTINATION_PATH,
                'name' => 'Hire a VA',
                'blurb' => 'The default. Straight to the hiring offer.',
                'image' => 'hire-va-4.jpg',
            ],
            [
                'path' => 'hire-va',
                'name' => 'Hire Virtual Assistants',
                'blurb' => 'The longer pitch, with more detail.',
                'image' => 'hire-va.jpg',
            ],
            [
                'path' => 'stealing-jobs-lp',
                'name' => 'Steal Your Job — Landing',
                'blurb' => 'Campaign landing page.',
                'image' => 'stealing-jobs-lp.jpg',
            ],
            [
                'path' => 'stealing-jobs',
                'name' => 'Steal Your Job',
                'blurb' => 'The campaign page itself.',
                'image' => 'stealing-jobs.jpg',
            ],
            [
                /*
                 * Linked directly rather than through the `/cor/` vanity URL. That redirect does
                 * preserve the query string — checked — so `?via=` would survive it, but a
                 * shared link should not spend a round trip it does not need.
                 */
                'path' => 'contractor-management',
                'name' => 'Contractor of Record',
                'blurb' => 'For prospects hiring contractors, not assistants.',
                'image' => 'contractor-management.jpg',
            ],
        ];
    }

    /**
     * Whether a path is one this class is willing to build a link for.
     *
     * The portal sends the chosen path up from the browser, so it is untrusted: without this
     * an arbitrary string would be concatenated into the link a referrer then shares.
     */
    public static function isAllowed(string $path): bool
    {
        return in_array(trim($path, '/'), array_column(self::destinations(), 'path'), true);
    }

    /**
     * The shareable link for a referral code, optionally to a non-default page.
     */
    public static function for(string $referralCode, ?string $path = null): string
    {
        return self::url($path).'?'.self::PARAM.'='.rawurlencode(trim($referralCode));
    }

    /**
     * The same link, flagged so opening it from the portal does not record a click.
     *
     * Signed rather than a bare `?rl_preview=1`: a visitor who could guess the flag could
     * suppress their own click and quietly cost the referrer the attribution. The token is an
     * HMAC over the referral code, so it is per-referrer and cannot be forged without the
     * site's salt — and cannot be lifted from one referrer's preview to another's link.
     */
    public static function preview(string $referralCode, ?string $path = null): string
    {
        return self::for($referralCode, $path)
            .'&'.self::PREVIEW_PARAM.'='.self::previewToken($referralCode);
    }

    /**
     * Constant-time check of a preview token against a referral code.
     */
    public static function isValidPreviewToken(string $referralCode, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        return hash_equals(self::previewToken($referralCode), $token);
    }

    /**
     * Absolute URL of a destination path.
     */
    protected static function url(?string $path): string
    {
        $path = trim((string) ($path ?? self::DESTINATION_PATH), '/');

        if ($path !== '' && ! self::isAllowed($path)) {
            $path = self::DESTINATION_PATH;
        }

        $base = rtrim(self::siteUrl(), '/');

        return $path === '' ? $base.'/' : $base.'/'.$path.'/';
    }

    /**
     * Truncated to 32 hex characters: still 128 bits, and short enough that the link a
     * referrer sees in the preview button is not visibly longer than the one they share.
     */
    protected static function previewToken(string $referralCode): string
    {
        return substr(hash_hmac('sha256', 'referral-preview|'.trim($referralCode), self::signingSalt()), 0, 32);
    }

    /**
     * A secret that is stable for the installation and never shipped to the browser.
     */
    protected static function signingSalt(): string
    {
        if (function_exists('wp_salt')) {
            return (string) wp_salt('auth');
        }

        return (string) (config('app.key') ?: 'rl-referral-preview-fallback-salt');
    }

    /**
     * Site root, from WordPress where it is available and from config otherwise.
     *
     * Not hardcoded to the production domain: a referrer opening the portal on staging must get
     * a staging link, or testing the funnel sends real traffic to production.
     */
    protected static function siteUrl(): string
    {
        if (function_exists('home_url')) {
            return (string) home_url('/');
        }

        return (string) (config('app.url') ?: 'https://remoteleverage.com');
    }
}
