<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Which platform a lead arrived from, folded out of `utm_source`.
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
 */
class LeadPlatform
{
    /** Leads carrying no `utm_source` at all. */
    public const DIRECT = 'direct';

    /** Leads carrying a `utm_source` that matches no known platform. */
    public const OTHER = 'other';

    /**
     * Known platforms, each with the `utm_source` spellings that mean it.
     *
     * Aliases are matched lowercased and trimmed, so only lowercase belongs here.
     *
     * @var array<string, array{label: string, aliases: array<int, string>}>
     */
    private const PLATFORMS = [
        'meta' => [
            'label' => 'Meta (Facebook / Instagram)',
            'aliases' => ['facebook', 'facebook.com', 'fb', 'meta', 'ig', 'instagram', 'instagram.com'],
        ],
        'google' => [
            'label' => 'Google Ads',
            'aliases' => ['adwords', 'google', 'google.com', 'googleads', 'google-ads', 'google_ads', 'gads'],
        ],
        'microsoft' => [
            'label' => 'Microsoft / Bing',
            'aliases' => ['bing', 'bing.com', 'msn', 'microsoft'],
        ],
        'linkedin' => [
            'label' => 'LinkedIn',
            'aliases' => ['linkedin', 'linkedin.com', 'li'],
        ],
        'customerio' => [
            'label' => 'Customer.io',
            'aliases' => ['customerio', 'customer.io', 'customer_io'],
        ],
        'chatgpt' => [
            'label' => 'ChatGPT',
            'aliases' => ['chatgpt', 'chatgpt.com', 'openai', 'openai.com'],
        ],
        'trustpilot' => [
            'label' => 'Trustpilot',
            'aliases' => ['trustpilot', 'trustpilot.com'],
        ],
    ];

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
     * @param  Builder  $query
     */
    public static function apply($query, string $slug): void
    {
        $slug = strtolower(trim($slug));

        if ($slug === '' || ! self::isKnown($slug)) {
            return;
        }

        if ($slug === self::DIRECT) {
            $query->where(fn ($q) => $q->whereNull('utm_source')->orWhere('utm_source', ''));

            return;
        }

        if ($slug === self::OTHER) {
            $query->whereNotNull('utm_source')
                ->where('utm_source', '!=', '')
                ->whereRaw(self::normalised().' NOT IN ('.self::placeholders(self::allAliases()).')', self::allAliases());

            return;
        }

        $aliases = self::PLATFORMS[$slug]['aliases'];

        $query->whereRaw(self::normalised().' IN ('.self::placeholders($aliases).')', $aliases);
    }

    /**
     * The platform a single lead belongs to, for the badge on the list row.
     *
     * The PHP here and the SQL in {@see self::apply()} answer the same question and are pinned
     * against each other in LeadAttributionFilterTest.
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
     */
    private static function normalised(): string
    {
        return 'LOWER(TRIM(utm_source))';
    }

    /** @param array<int, string> $values */
    private static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }
}
