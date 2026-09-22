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
 *
 * ## Not every submission is a sales enquiry
 *
 * The gated download (acf/impact-report-hero) captures a name and an email in exchange for a
 * PDF. That is a real lead and it belongs in the table, but it is not somebody asking to be
 * called: there is no phone number, no revenue band, no role and no hours, because the form
 * never asked. {@see self::isSalesEnquiry()} is what the Slack alert and the outgoing webhooks
 * gate on, and it lives here because "which submissions reach sales" is a property of the
 * submission type rather than of any one listener.
 */
class LeadSubmission
{
    /** Step one validated, step two never completed. */
    public const PARTIAL = 'partial';

    /** The form was completed. */
    public const FINAL = 'final';

    /**
     * An email handed over for a file — the 2026 Impact Report and anything else in
     * `config/gated-assets.php`. Never a request to be contacted.
     */
    public const GATED_DOWNLOAD = 'gated_download';

    /**
     * The submission types that must not reach sales.
     *
     * A blacklist, for the same reason {@see LeadQualification::SUB_T10_BANDS} is one: a new
     * form that starts writing a type nobody has heard of should alert, and be silenced
     * deliberately, rather than be swallowed because it was not on a whitelist.
     *
     * @var array<int, string>
     */
    private const NOT_SALES_ENQUIRIES = [self::GATED_DOWNLOAD];

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
        self::GATED_DOWNLOAD => ['label' => 'Gated download (report, no call asked for)', 'stored' => 'Gated Download'],
    ];

    /**
     * The value a writer should put in the column for a slug.
     *
     * Exposed so a writer — `GatedDownloadController` today — stamps the same string this class
     * filters and gates on, instead of keeping a second copy of the literal that can be
     * corrected in one place and not the other.
     */
    public static function stored(string $slug): string
    {
        return self::TYPES[strtolower(trim($slug))]['stored'] ?? $slug;
    }

    /**
     * Is this a submission a salesperson can act on?
     *
     * Anything unrecognised — including an empty column, which is most of the imported rows —
     * is a yes. Silence is the expensive failure here: a lead nobody is told about is a lead
     * nobody calls, whereas one alert too many costs a glance.
     */
    public static function isSalesEnquiry(?string $submissionType): bool
    {
        return ! in_array(self::slugOf($submissionType), self::NOT_SALES_ENQUIRIES, true);
    }

    /**
     * The slug a stored value corresponds to.
     *
     * Spaces fold to underscores so `Gated Download` and `gated_download` are the same type;
     * the column is free text, and the stored spelling is the one thing a writer is likely to
     * get almost-right.
     */
    private static function slugOf(?string $value): string
    {
        return str_replace(' ', '_', strtolower(trim((string) $value)));
    }

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

        // The stored spelling lowercased, not the slug: they coincide for `partial` and
        // `final` and do not for `gated_download`, whose column value carries a space.
        $query->whereRaw(
            "LOWER(TRIM(COALESCE(submission_type, ''))) = ?",
            [strtolower(self::TYPES[$slug]['stored'])],
        );
    }
}
