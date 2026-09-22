<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Support;

use App\Domains\Referral\Support\ReferralLink;
use WP_Post;
use WP_Query;

/**
 * The tracked link a partner shares, and the partner behind a code that comes back.
 *
 * ## Why `?partner=` and not a new parameter
 *
 * `partner` already has a column on `rl_leads` and an entry in `AttributionCollector::NAMED`,
 * because the legacy Gravity Form fed it from the query string. What it carried was free text —
 * a campaign tag someone typed — which is why `HubSpotGateway` maps it to `partner_name` and
 * nothing joins on it.
 *
 * Putting the partner *code* on that same parameter turns the existing pipeline into the one
 * WR-73 asks for without a migration: the value arrives, gets stamped on the lead, and reaches
 * HubSpot through a path that has been carrying it for years. A new `partnership_id` parameter
 * would have meant a column, a `NAMED` entry, and two fields meaning almost the same thing.
 *
 * The cost is that `partner` now holds two kinds of value — a code for traffic from a hub link,
 * free text for anything older or hand-built. `nameForCode()` is what tells them apart: a value
 * that resolves to an `rl_partner` post is an id, and anything else is left as the label it
 * always was.
 *
 * ## Why `via=` was not reused
 *
 * `via` belongs to the Referral domain and is read by `AttributionEngine`, which classifies a
 * slug into `partnership` or `referral_hub` by prefix — `partner-`, `co-`, `strategic`. A code
 * like `RL-OYSTER` matches none of them and would be stamped `referral_hub`, i.e. the wrong
 * source type on every partner lead. `via` is also in `AttributionCollector::IGNORED`. Fixing
 * both to borrow the parameter would have been a larger change than not borrowing it.
 */
class PartnerLink
{
    /**
     * The query parameter carrying the partner code.
     */
    public const PARAM = 'partner';

    /**
     * The `rl_partner` meta key holding the code, as `PartnerHubFields` defines it.
     */
    public const META_KEY = '_rl_partner_code';

    /**
     * Resolved codes for this request, so a hub page rendering its own link and a lead sync
     * resolving the same code do not each run the query.
     *
     * @var array<string, ?int>
     */
    protected static array $resolved = [];

    /**
     * The shareable link for a partner code, optionally to a non-default page.
     *
     * The destination allowlist is `ReferralLink`'s rather than a second copy: which pages
     * carry an offer worth sending a prospect to is one fact about the site, and a partner
     * sending traffic to a retired experiment is the same mistake as a referrer doing it.
     */
    public static function for(string $partnerCode, ?string $path = null): string
    {
        $code = trim($partnerCode);
        $path = trim((string) ($path ?? ReferralLink::DESTINATION_PATH), '/');

        if ($path !== '' && ! ReferralLink::isAllowed($path)) {
            $path = ReferralLink::DESTINATION_PATH;
        }

        $base = rtrim(self::siteUrl(), '/');
        $url = $path === '' ? $base.'/' : $base.'/'.$path.'/';

        return $url.'?'.self::PARAM.'='.rawurlencode($code);
    }

    /**
     * The `rl_partner` post for a code, or null when the code belongs to no partner.
     */
    public static function findByCode(string $code): ?WP_Post
    {
        $code = trim($code);

        if ($code === '' || ! class_exists(WP_Query::class)) {
            return null;
        }

        if (! array_key_exists($code, self::$resolved)) {
            $query = new WP_Query([
                'post_type' => 'rl_partner',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'update_post_term_cache' => false,
                'meta_query' => [
                    [
                        'key' => self::META_KEY,
                        'value' => $code,
                        'compare' => '=',
                    ],
                ],
            ]);

            $ids = $query->posts;
            self::$resolved[$code] = $ids === [] ? null : (int) $ids[0];
        }

        $postId = self::$resolved[$code];

        if ($postId === null) {
            return null;
        }

        $post = get_post($postId);

        return $post instanceof WP_Post ? $post : null;
    }

    /**
     * The partner's display name for a code, or null when the code belongs to no partner.
     *
     * Null is the signal that a value on `?partner=` is *not* a partnership id — see the class
     * docblock. Callers use it to decide whether to treat the value as an identifier or as the
     * free-text label the parameter used to carry.
     */
    public static function nameForCode(string $code): ?string
    {
        $post = self::findByCode($code);

        if (! $post instanceof WP_Post) {
            return null;
        }

        $name = (string) get_post_meta($post->ID, '_rl_partner_name', true);

        return $name !== '' ? $name : $post->post_title;
    }

    /**
     * Forget resolved codes. For tests, which create partners between assertions.
     */
    public static function flush(): void
    {
        self::$resolved = [];
    }

    /**
     * Site root, from WordPress where it is available and from config otherwise.
     *
     * Same reasoning as `ReferralLink::siteUrl()`: a partner opening their hub on staging must
     * get a staging link, or testing the funnel sends real traffic to production.
     */
    protected static function siteUrl(): string
    {
        if (function_exists('home_url')) {
            return (string) home_url('/');
        }

        return (string) (config('app.url') ?: 'https://remoteleverage.com');
    }
}
