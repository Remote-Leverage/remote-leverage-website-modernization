<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use App\Domains\Lead\Models\Lead;

/**
 * Whether a lead's visit was paid, and if not, what kind of free it was.
 *
 * A different question from {@see LeadPlatform}, and the two are easy to conflate. LeadPlatform
 * answers *which company sent this traffic* — Meta, Google, Microsoft. This answers *did we pay
 * for it*. A booking from an organic Instagram post and a booking from an Instagram ad are both
 * "Meta"; only one of them belongs in the denominator of Meta's cost per booking.
 *
 * ## `utm_medium` is the signal, and HandL is the fallback
 *
 * This class first read HandL's `traffic_source` as the authority. That was wrong, and the live
 * table says so plainly. Cross-tabulating booked leads on 2026-09-19:
 *
 *     Meta,   utm_medium = paid-social (1,784)   HandL says paid on   471
 *     Google, utm_medium = ppc         (  101)   HandL says paid on   101
 *
 * Google's paid clicks carry a `gclid`, which HandL recognises, so the two agree perfectly. Meta's
 * carry an `fbclid`, which HandL does not treat as proof of payment — it classifies by referring
 * host instead, and facebook.com is "social" whether or not money changed hands. So HandL cannot
 * see paid Meta traffic at all, and trusting it would have thrown 1,313 genuinely paid bookings
 * out of Meta's cost denominator and inflated its cost per booking roughly fourfold.
 *
 * `utm_medium` does not have that blind spot, because it is not inferred. Somebody building a
 * campaign link wrote `paid-social`, and that is a statement about money. The live values are a
 * short, clean set — `paid-social`, `ppc`, `paid`, `social`, `email` — with no noise in them.
 *
 * So the order is: a click ID that only exists on a paid click, then what `utm_medium` declares,
 * then HandL, then the referring host. Each step is consulted only when the one before it has
 * nothing to say.
 *
 * ## Where HandL still earns its place
 *
 * The leads carrying no `utm_medium` at all — 446 of the booked rows, including every one the
 * `fbclid` fallback rescues. There is no declaration to read, so HandL's guess is the best signal
 * available, and it is the only one that can distinguish a paid Facebook click from an organic
 * one when nothing else survived. Applied there it is an improvement; applied to a link that
 * already says `paid-social` it is an override of fact by inference.
 *
 * ## Why the referrer can never say "paid"
 *
 * `referrer_url` is present and informative and is the last fallback, but a host cannot tell a
 * paid Facebook click from an organic one — both arrive from facebook.com. It is used to name a
 * channel and never to claim something was paid for.
 */
final class LeadChannel
{
    public const PAID = 'paid';

    public const ORGANIC = 'organic';

    public const SOCIAL = 'social';

    public const REFERRAL = 'referral';

    public const DIRECT = 'direct';

    /** No first-touch data and no usable referrer. Three of 395 booked leads, on current data. */
    public const UNKNOWN = 'unknown';

    /** Ordered for display: the ones worth acting on first. */
    public const ALL = [self::PAID, self::ORGANIC, self::SOCIAL, self::REFERRAL, self::DIRECT, self::UNKNOWN];

    /** Human labels, for a message that has to read as a sentence. */
    private const LABELS = [
        self::PAID => 'paid',
        self::ORGANIC => 'organic search',
        self::SOCIAL => 'social',
        self::REFERRAL => 'referral',
        self::DIRECT => 'direct',
        self::UNKNOWN => 'unknown',
    ];

    /**
     * `utm_medium` values that declare the click was bought.
     *
     * Deliberately a list of what *is* paid rather than what is not: an unrecognised medium falls
     * through to HandL rather than being assumed paid, so a new campaign convention nobody told
     * this class about understates the denominator instead of inflating it. Understating means a
     * cost per booking that reads too high, which is the direction that gets questioned.
     *
     * @var array<int, string>
     */
    private const PAID_MEDIA = [
        'cpc', 'ppc', 'paid', 'paidsocial', 'paid-social', 'paid_social',
        'paidsearch', 'paid-search', 'paid_search', 'display', 'banner', 'retargeting',
    ];

    /**
     * `utm_medium` values that declare the click was not bought, and what channel it was.
     *
     * @var array<string, string>
     */
    private const FREE_MEDIA = [
        'social' => self::SOCIAL,
        'organic' => self::ORGANIC,
        'email' => self::REFERRAL,
        'newsletter' => self::REFERRAL,
        'referral' => self::REFERRAL,
        'affiliate' => self::REFERRAL,
        'none' => self::DIRECT,
        '(none)' => self::DIRECT,
        'direct' => self::DIRECT,
    ];

    /** Referrer hosts that mean a search engine, matched as a suffix on the host. */
    private const SEARCH_HOSTS = ['google.', 'bing.', 'duckduckgo.', 'yahoo.', 'ecosia.', 'brave.'];

    /** Referrer hosts that mean a social network, matched as a suffix on the host. */
    private const SOCIAL_HOSTS = ['facebook.', 'instagram.', 'linkedin.', 'twitter.', 'x.com', 't.co', 'tiktok.', 'reddit.', 'youtube.'];

    /**
     * What kind of visit this was.
     *
     * HandL's own classification first, because it is the only source that distinguishes a paid
     * click from an organic one on the same host. The referrer is consulted only when the blob
     * has nothing to say, and it can never return {@see self::PAID}.
     */
    public static function for(Lead $lead): string
    {
        /*
         * A click ID that only exists on a paid click settles it outright. `fbclid` is not one of
         * those and is deliberately absent from this check — see LeadPlatform::clickIdProvesPaid().
         */
        if (self::carriesPaidCertainClickId($lead)) {
            return self::PAID;
        }

        return self::fromMedium($lead)
            ?? self::fromFirstTouch($lead)
            ?? self::fromReferrer($lead)
            ?? self::UNKNOWN;
    }

    /** Did somebody pay for this click? */
    public static function isPaid(Lead $lead): bool
    {
        return self::for($lead) === self::PAID;
    }

    /**
     * Does this lead carry a click ID that is only ever stamped on a paid click?
     *
     * `gclid`, `msclkid` and `li_fat_id`. Not `fbclid`, which Meta puts on organic links too and
     * which is the whole reason the rest of this class exists.
     */
    private static function carriesPaidCertainClickId(Lead $lead): bool
    {
        foreach (['gclid' => 'google', 'msclkid' => 'microsoft', 'li_fat_id' => 'linkedin'] as $column => $slug) {
            if (trim((string) $lead->{$column}) !== '' && LeadPlatform::clickIdProvesPaid($slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the link's own `utm_medium` declares, or null when it declares nothing this recognises.
     *
     * Null rather than a guess for an unknown medium: falling through to HandL is a better answer
     * than treating an unfamiliar campaign convention as either paid or free.
     */
    private static function fromMedium(Lead $lead): ?string
    {
        $medium = strtolower(trim((string) $lead->utm_medium));

        if ($medium === '') {
            return null;
        }

        if (in_array($medium, self::PAID_MEDIA, true)) {
            return self::PAID;
        }

        return self::FREE_MEDIA[$medium] ?? null;
    }

    public static function label(string $channel): string
    {
        return self::LABELS[$channel] ?? $channel;
    }

    /**
     * HandL's `traffic_source`, falling back to `organic_source`.
     *
     * Both arrive as a bare word most of the time — `organic`, `social`, `paid`, `direct`,
     * `referral` — but sometimes as a URL (`https://facebook.com/`), which is why an
     * unrecognised value is run through the host matcher rather than discarded.
     */
    private static function fromFirstTouch(Lead $lead): ?string
    {
        $attribution = $lead->attribution;
        $handl = is_array($attribution) && is_array($attribution['handl'] ?? null)
            ? $attribution['handl']
            : [];

        foreach (['traffic_source', 'first_traffic_source', 'organic_source'] as $key) {
            $value = strtolower(trim((string) ($handl[$key] ?? '')));

            if ($value === '') {
                continue;
            }

            if (in_array($value, self::ALL, true)) {
                return $value;
            }

            $byHost = self::classifyHost($value);

            if ($byHost !== null) {
                return $byHost;
            }
        }

        return null;
    }

    /**
     * The referring host, when first-touch data is absent.
     *
     * Never returns {@see self::PAID}: a host cannot tell us who paid. A referrer pointing at our
     * own site is an internal navigation, which means whatever brought them here originally was
     * not recorded — {@see self::DIRECT} is the honest answer, not "referral".
     */
    private static function fromReferrer(Lead $lead): ?string
    {
        $referrer = trim((string) $lead->referrer_url);

        if ($referrer === '') {
            return null;
        }

        return self::classifyHost($referrer);
    }

    /** @param string $value A URL, or a bare host. */
    private static function classifyHost(string $value): ?string
    {
        $host = strtolower((string) (parse_url($value, PHP_URL_HOST) ?: $value));
        $host = ltrim(preg_replace('/^(www|m|l|lm)\./', '', $host) ?? $host, '.');

        if ($host === '') {
            return null;
        }

        foreach (self::SEARCH_HOSTS as $search) {
            if (str_starts_with($host, $search)) {
                return self::ORGANIC;
            }
        }

        foreach (self::SOCIAL_HOSTS as $social) {
            if (str_starts_with($host, $social)) {
                return self::SOCIAL;
            }
        }

        /*
         * Our own domain. They were already on the site, so this navigation says nothing about
         * where they came from in the first place.
         */
        if (str_contains($host, 'remoteleverage.')) {
            return self::DIRECT;
        }

        /*
         * A bare word is not a host.
         *
         * This matters because `fromFirstTouch()` runs HandL values that are not one of the known
         * channels through here — they are sometimes URLs. Without this guard a `traffic_source`
         * of `cpc` or `(none)` would come back as "referral", a confident wrong answer that also
         * stops the referrer fallback from ever running. A hostname has a dot in it.
         */
        if (! str_contains($host, '.')) {
            return null;
        }

        return self::REFERRAL;
    }
}
