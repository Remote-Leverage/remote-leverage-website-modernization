<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * How far a lead got through the form: a step-one drop-off, or a completed submission.
 *
 * This lives in `submission_type`, not in `status`, and the distinction is what the dashboard's
 * "Partial Form Drops" card got wrong. It counted `status = 'partial'` — a value the status enum
 * does not contain and nothing writes — so it read 0 forever while 1,401 drop-offs sat in the
 * table. A card that cannot be right is worse than a missing one; it answers the question.
 *
 * Both sources agree on this column, which is what makes it safe to filter on:
 *
 * - The booking wizard writes `Partial` when step one validates, then `Final` when the booking
 *   completes, so a lead that converts stops being a drop-off.
 * - The Gravity import maps its own Final/Partial straight across, because Gravity only marks an
 *   entry Final once the visitor has booked.
 *
 * `status` answers what happened to the lead afterwards; this answers whether they finished the
 * form. They are independent, which is why both filters exist: 25 leads are `Partial` **and**
 * `booked` — people who abandoned once and came back.
 */
class LeadSubmission
{
    /** Step one validated, step two never completed. */
    public const PARTIAL = 'partial';

    /** The form was completed. */
    public const FINAL = 'final';

    /**
     * Slug => label for the dropdown, and slug => stored value for the query.
     *
     * The stored values are capitalised because that is how both writers spell them; the
     * comparison below is case-insensitive anyway, so this is about writing the column, not
     * reading it.
     *
     * @var array<string, array{label: string, stored: string}>
     */
    private const TYPES = [
        self::PARTIAL => ['label' => 'Partial (step 1 drop-off)', 'stored' => 'Partial'],
        self::FINAL => ['label' => 'Final (completed form)', 'stored' => 'Final'],
    ];

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_map(static fn (array $type): string => $type['label'], self::TYPES);
    }

    /** Is this a slug the filter recognises? */
    public static function isKnown(string $slug): bool
    {
        return array_key_exists(strtolower(trim($slug)), self::TYPES);
    }

    /** The label for a slug, or the slug itself if it is not one. */
    public static function label(string $slug): string
    {
        return self::TYPES[strtolower(trim($slug))]['label'] ?? $slug;
    }

    /**
     * Narrow a lead query to one submission type. An empty or unknown slug is a no-op.
     *
     * Compared lowercased and trimmed: the column is free text rather than an enum, so nothing
     * at the database level stops a future writer from spelling it `partial` or ` Partial`.
     *
     * @param  Builder  $query
     */
    public static function apply($query, string $slug): void
    {
        $slug = strtolower(trim($slug));

        if (! self::isKnown($slug)) {
            return;
        }

        $query->whereRaw("LOWER(TRIM(COALESCE(submission_type, ''))) = ?", [$slug]);
    }
}
