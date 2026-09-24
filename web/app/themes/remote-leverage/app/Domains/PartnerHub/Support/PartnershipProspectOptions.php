<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Support;

/**
 * The three multiple-choice questions on the `/become-a-partner/` form, as `slug => label`.
 *
 * One class, plain arrays, so changing the wording of an answer is an edit here and nothing
 * else — the form renders from these, the action validates against them, and the admin screen
 * and the Slack card translate stored slugs back through them.
 *
 * ## The slug is the stored value, and it must not change
 *
 * `rl_partnership_prospects` stores the slug, never the label. That is what lets the label be
 * reworded freely: every row already written reads back under the new wording. The cost is the
 * other direction — renaming or removing a slug orphans the rows that carry it, and they then
 * render as the raw slug (see label()). Add a new answer rather than repurposing an old one.
 *
 * Deliberately not `LeadQualification::REVENUE_BANDS`. Those bands are sized for a small
 * business hiring one assistant ($0 to $5k … $100k+) and drive Calendly tier routing; these are
 * sized for the companies that would send us clients, and nothing routes on them.
 */
final class PartnershipProspectOptions
{
    /** "What type of organization are you?" */
    public const ORGANIZATION_TYPES = [
        'consultancy' => 'Consultancy or advisory firm',
        'agency' => 'Agency',
        'technology' => 'Technology / SaaS company',
        'community' => 'Community or association',
        'other' => 'Other',
    ];

    /** "What's your company's monthly revenue?" */
    public const MONTHLY_REVENUE = [
        'under_50k' => 'Under $50k',
        '50k_250k' => '$50k – $250k',
        '250k_1m' => '$250k – $1M',
        '1m_5m' => '$1M – $5M',
        '5m_plus' => '$5M+',
    ];

    /** "Approximately how many businesses do you work with or reach?" */
    public const BUSINESSES_REACHED = [
        'under_50' => 'Under 50',
        '50_250' => '50 – 250',
        '250_1000' => '250 – 1,000',
        '1000_10000' => '1,000 – 10,000',
        '10000_plus' => '10,000+',
    ];

    /**
     * Every list, keyed by the `rl_partnership_prospects` column it fills.
     *
     * @return array<string, array<string, string>>
     */
    public static function all(): array
    {
        return [
            'organization_type' => self::ORGANIZATION_TYPES,
            'monthly_revenue' => self::MONTHLY_REVENUE,
            'businesses_reached' => self::BUSINESSES_REACHED,
        ];
    }

    /**
     * The slugs a column accepts. What the action validates against.
     *
     * @return array<int, string>
     */
    public static function slugs(string $field): array
    {
        return array_keys(self::all()[$field] ?? []);
    }

    /**
     * The label for a stored slug.
     *
     * An unknown slug comes back as itself rather than as an empty string: a row written before
     * an answer was removed still says *something* on the admin screen and in Slack, and an
     * empty value bound to a Slack text object costs the whole message.
     */
    public static function label(string $field, ?string $slug): string
    {
        $slug = trim((string) $slug);

        return self::all()[$field][$slug] ?? $slug;
    }
}
