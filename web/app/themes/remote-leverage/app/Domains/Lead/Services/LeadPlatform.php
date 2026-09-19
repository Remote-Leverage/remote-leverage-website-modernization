<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use App\Domains\Lead\Models\Lead;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which platform a lead arrived from, folded out of `utm_source` and, failing that, its click ID.
 *
 * `utm_source` is written by whoever built the link, so the same platform arrives under several
 * spellings — Meta alone shows up as `facebook`, `fb` and `ig`. Filtering on the raw column
 * means a list headed "Facebook" that silently omits the 115 leads tagged `fb` or `ig`, which is
 * worse than no filter: it reads as a complete answer.
 *
 * So the screen filters on a platform, and each platform owns the spellings that mean it. The
 * aliases are deliberately broader than what is in the table today — a new link built with
 * `google` rather than `adwords` should land in Google Ads the day it is created, not the day
 * someone notices it was missing.
 *
 * It lives here rather than in the admin screen because the CSV export filters on the same axis,
 * and two copies of this map drift into two different answers about the same lead.
 *
 * ## The click-ID fallback
 *
 * Added 2026-09-19 for the marketing cost alert. A UTM is a string somebody remembered to put on
 * a link; a click ID is stamped by the ad platform itself. Redirects, link shorteners, an app's
 * in-app browser and a hand-built ad all lose UTMs routinely, and every lead that loses them
 * lands in `direct` or `other` — indistinguishable from organic. That bucket was 21.4% of
 * bookings in the alert this was built from, and every one of those bookings is a paid booking
 * whose cost is being spread across the platforms that did tag their links.
 *
 * So: `utm_source` first, and only when it resolves to nothing recognisable, the click ID.
 *
 * **A click ID never overrides a recognised `utm_source`.** Explicit tagging is a statement of
 * intent and wins; the click ID is there for the leads that have no statement at all.
 *
 * ### Why the precedence order is what it is
 *
 * A lead can carry more than one click ID — someone clicks a Facebook ad today and a Google ad
 * on Thursday, and both land on a first-party cookie. Something has to break the tie, and
 * {@see self::CLICK_ID_PRECEDENCE} does, worst signal last:
 *
 * `gclid`, `msclkid` and `li_fat_id` are stamped **only** on a paid click. `fbclid` is not —
 * Meta appends it to any link opened from Facebook or Instagram, including an organic post, a
 * comment and a DM. Attributing an organic Meta click to the ad account inflates Meta's booking
 * count, which *understates* Meta's cost per booking. That is the dangerous direction for a
 * number someone allocates budget on, so `fbclid` resolves last and only when nothing else does.
 *
 * {@see self::resolution()} reports whether a lead was resolved by UTM or by click ID, so the
 * alert can show the split rather than quietly presenting the two as equally solid.
 */
class LeadPlatform
{
    /** Leads carrying no `utm_source` and no click ID at all. */
    public const DIRECT = 'direct';

    /** Leads carrying a `utm_source` that matches no known platform, and no click ID. */
    public const OTHER = 'other';

    /** {@see self::resolution()}: the platform came from `utm_source`. */
    public const BY_UTM = 'utm';

    /** {@see self::resolution()}: the platform came from a click ID, with no usable `utm_source`. */
    public const BY_CLICK_ID = 'click_id';

    /**
     * Known platforms, each with the `utm_source` spellings that mean it and the click-ID column
     * that proves it.
     *
     * Aliases are matched lowercased and trimmed, so only lowercase belongs here. `click_id` is
     * null for a platform that does not stamp one — those resolve by `utm_source` or not at all.
     *
     * @var array<string, array{label: string, aliases: array<int, string>, click_id: ?string, click_id_proves_paid: bool}>
     */
    private const PLATFORMS = [
        'meta' => [
            'label' => 'Meta (Facebook / Instagram)',
            'aliases' => ['facebook', 'facebook.com', 'fb', 'meta', 'ig', 'instagram', 'instagram.com'],
            'click_id' => 'fbclid',
            'click_id_proves_paid' => false,
        ],
        'google' => [
            'label' => 'Google Ads',
            'aliases' => ['adwords', 'google', 'google.com', 'googleads', 'google-ads', 'google_ads', 'gads'],
            'click_id' => 'gclid',
            'click_id_proves_paid' => true,
        ],
        'microsoft' => [
            'label' => 'Microsoft / Bing',
            'aliases' => ['bing', 'bing.com', 'msn', 'microsoft'],
            'click_id' => 'msclkid',
            'click_id_proves_paid' => true,
        ],
        'linkedin' => [
            'label' => 'LinkedIn',
            'aliases' => ['linkedin', 'linkedin.com', 'li'],
            'click_id' => 'li_fat_id',
            'click_id_proves_paid' => true,
        ],
        'customerio' => [
            'label' => 'Customer.io',
            'aliases' => ['customerio', 'customer.io', 'customer_io'],
            'click_id' => null,
            'click_id_proves_paid' => false,
        ],
        'chatgpt' => [
            'label' => 'ChatGPT',
            'aliases' => ['chatgpt', 'chatgpt.com', 'openai', 'openai.com'],
            'click_id' => null,
            'click_id_proves_paid' => false,
        ],
        'trustpilot' => [
            'label' => 'Trustpilot',
            'aliases' => ['trustpilot', 'trustpilot.com'],
            'click_id' => null,
            'click_id_proves_paid' => false,
        ],
    ];

    /**
     * Which click ID wins when a lead carries several. Strongest evidence first.
     *
     * See the class docblock for why `meta` is last. Changing this order changes which platform
     * gets credited for a multi-touch lead, and therefore its cost per booking — it is a
     * measurement decision, not a detail.
     *
     * @var array<int, string>
     */
    private const CLICK_ID_PRECEDENCE = ['google', 'microsoft', 'linkedin', 'meta'];

    /**
     * Every selectable platform, slug => label, in the order the dropdown shows them.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::PLATFORMS as $slug => $platform) {
            $options[$slug] = $platform['label'];
        }

        $options[self::DIRECT] = 'Direct / no UTM';
        $options[self::OTHER] = 'Other tagged source';

        return $options;
    }

    /** Is this a slug the filter recognises? Anything else is treated as no filter at all. */
    public static function isKnown(string $slug): bool
    {
        return array_key_exists(strtolower(trim($slug)), self::options());
    }

    /**
     * Narrow a lead query to one platform. An unknown or empty slug is a no-op, not an
     * empty result — a filter nobody selected must not silently hide the whole table.
     *
     * The buckets partition the table: every lead is in exactly one, which
     * `LeadAttributionFilterTest` asserts by summing them back to `Lead::count()`. That property
     * is what lets the cost alert add per-platform bookings to the unattributed ones and get the
     * total, rather than reporting three numbers that do not meet.
     *
     * @param  Builder  $query
     */
    public static function apply($query, string $slug): void
    {
        $slug = strtolower(trim($slug));

        if ($slug === '' || ! self::isKnown($slug)) {
            return;
        }

        if ($slug === self::DIRECT) {
            $query->whereRaw(self::normalised()." = ''");
            self::withoutAnyClickId($query);

            return;
        }

        if ($slug === self::OTHER) {
            $aliases = self::allAliases();

            $query->whereRaw(self::normalised()." <> ''")
                ->whereRaw(self::normalised().' NOT IN ('.self::placeholders($aliases).')', $aliases);
            self::withoutAnyClickId($query);

            return;
        }

        $aliases = self::PLATFORMS[$slug]['aliases'];

        $query->where(function ($outer) use ($slug, $aliases) {
            $outer->whereRaw(self::normalised().' IN ('.self::placeholders($aliases).')', $aliases)
                ->orWhere(fn ($fallback) => self::clickIdFallback($fallback, $slug));
        });
    }

    /**
     * The platform a single lead belongs to, for the badge on the list row.
     *
     * The PHP here and the SQL in {@see self::apply()} answer the same question and are pinned
     * against each other in LeadAttributionFilterTest.
     */
    public static function for(Lead $lead): string
    {
        $bySource = self::forSource($lead->utm_source);

        if ($bySource !== self::DIRECT && $bySource !== self::OTHER) {
            return $bySource;
        }

        return self::forClickIds($lead) ?? $bySource;
    }

    /**
     * How {@see self::for()} reached its answer: {@see self::BY_UTM}, {@see self::BY_CLICK_ID},
     * or null for a lead that resolved to no platform at all.
     *
     * The cost alert prints the click-ID share per platform. Without it, a Meta booking count
     * propped up by `fbclid` — which an organic share also carries — looks exactly like one
     * built from tagged campaign links, and the resulting cost per booking looks better than it
     * is with nothing on the message to say so.
     */
    public static function resolution(Lead $lead): ?string
    {
        $bySource = self::forSource($lead->utm_source);

        if ($bySource !== self::DIRECT && $bySource !== self::OTHER) {
            return self::BY_UTM;
        }

        return self::forClickIds($lead) !== null ? self::BY_CLICK_ID : null;
    }

    /**
     * The platform named by `utm_source` alone, ignoring click IDs.
     *
     * Kept separate from {@see self::for()} because "what does the tag say" and "where did this
     * lead actually come from" are different questions, and the fallback is only correct as an
     * answer to the second one.
     */
    public static function forSource(?string $utmSource): string
    {
        $source = strtolower(trim((string) $utmSource));

        if ($source === '') {
            return self::DIRECT;
        }

        foreach (self::PLATFORMS as $slug => $platform) {
            if (in_array($source, $platform['aliases'], true)) {
                return $slug;
            }
        }

        return self::OTHER;
    }

    /** The dropdown label for a platform slug, or the slug itself if it is not one. */
    public static function label(string $slug): string
    {
        return self::options()[strtolower(trim($slug))] ?? $slug;
    }

    /**
     * Does this platform's click ID, on its own, prove somebody paid for the click?
     *
     * True for `gclid`, `msclkid` and `li_fat_id`, which only exist on a paid click. False for
     * `fbclid`, which Meta stamps on organic links as well — measured on 2026-09-19, only 12 of
     * the 41 booked leads the fallback newly credits to Meta were actually paid. The cost alert
     * uses this to decide what may go in a cost-per-booking denominator; see LeadChannel.
     */
    public static function clickIdProvesPaid(string $slug): bool
    {
        return (bool) (self::PLATFORMS[strtolower(trim($slug))]['click_id_proves_paid'] ?? false);
    }

    /**
     * The platform whose click ID this lead carries, in precedence order, or null for none.
     *
     * The mirror of {@see self::clickIdFallback()}. These two are the pair most at risk of
     * drifting, which is why the parity matrix walks click-ID shapes as well as `utm_source`
     * ones.
     */
    private static function forClickIds(Lead $lead): ?string
    {
        foreach (self::CLICK_ID_PRECEDENCE as $slug) {
            $column = self::PLATFORMS[$slug]['click_id'] ?? null;

            if ($column !== null && trim((string) $lead->{$column}) !== '') {
                return $slug;
            }
        }

        return null;
    }

    /**
     * The SQL half of the click-ID fallback, for one platform.
     *
     * Three conditions, all necessary: `utm_source` names no known platform (an explicit tag
     * wins), this platform's click ID is present, and no higher-precedence platform's click ID
     * is — otherwise a lead carrying both `gclid` and `fbclid` would be counted twice and the
     * buckets would stop partitioning the table.
     *
     * @param  Builder  $query
     */
    private static function clickIdFallback($query, string $slug): void
    {
        $column = self::PLATFORMS[$slug]['click_id'] ?? null;

        if ($column === null) {
            // A platform that stamps no click ID has no fallback. `1 = 0` rather than leaving
            // the branch empty: an empty `orWhere` closure matches every row.
            $query->whereRaw('1 = 0');

            return;
        }

        $aliases = self::allAliases();

        $query->whereRaw(self::normalised().' NOT IN ('.self::placeholders($aliases).')', $aliases)
            ->whereRaw(self::present($column));

        foreach (self::CLICK_ID_PRECEDENCE as $stronger) {
            if ($stronger === $slug) {
                break;
            }

            $query->whereRaw(self::absent((string) self::PLATFORMS[$stronger]['click_id']));
        }
    }

    /**
     * Narrow to leads carrying none of the click IDs — what `direct` and `other` now mean.
     *
     * @param  Builder  $query
     */
    private static function withoutAnyClickId($query): void
    {
        foreach (self::CLICK_ID_PRECEDENCE as $slug) {
            $query->whereRaw(self::absent((string) self::PLATFORMS[$slug]['click_id']));
        }
    }

    /** @return array<int, string> Every alias of every known platform. */
    private static function allAliases(): array
    {
        return array_merge(...array_column(self::PLATFORMS, 'aliases'));
    }

    /**
     * `utm_source` reduced to the form the aliases are written in.
     *
     * TRIM as well as LOWER because a link built by hand arrives with a trailing space often
     * enough, and ` facebook` matching nothing is the exact failure this class exists to stop.
     *
     * COALESCE because `direct` is now expressed as `= ''` rather than a separate `whereNull`
     * branch, and every comparison against a NULL column is NULL rather than true or false. A
     * null `utm_source` would fall out of every bucket, which is the one thing the partition
     * property does not survive.
     */
    private static function normalised(): string
    {
        return "LOWER(TRIM(COALESCE(utm_source, '')))";
    }

    /** SQL for "this click-ID column holds something". */
    private static function present(string $column): string
    {
        return "TRIM(COALESCE({$column}, '')) <> ''";
    }

    /** SQL for "this click-ID column is empty or absent". */
    private static function absent(string $column): string
    {
        return "TRIM(COALESCE({$column}, '')) = ''";
    }

    /** @param array<int, string> $values */
    private static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }
}
