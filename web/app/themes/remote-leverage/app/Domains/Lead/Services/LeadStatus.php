<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

/**
 * The statuses a lead can actually hold, and how each one is shown.
 *
 * `rl_leads.status` is an ENUM, so this list is not a convention — it is the set of values the
 * column will accept. Keeping it in one place is what stops the two failures that were live on
 * the leads screen before it existed:
 *
 * - **Offering a status that cannot exist.** The toolbar and the export both listed "Partial"
 *   and "Qualified", neither of which is in the enum. Filtering on one returned an empty list
 *   that looked like an answer. Partial is a `submission_type`, not a status — see
 *   {@see LeadSubmission}.
 * - **Hiding a status that can.** `booking_pending` and `booking_failed` were in the column and
 *   in neither dropdown, so a booking that failed could not be looked up at all.
 *
 * The badge class is here for a third reason: the list renders `rl-badge-{$status}`, which for
 * `booking_pending` is a class no stylesheet defines. Mapping it keeps a newly-selectable status
 * from rendering as an unstyled word.
 */
class LeadStatus
{
    /**
     * Every value of the `status` enum, in lifecycle order, with its label and badge class.
     *
     * @var array<string, array{label: string, badge: string}>
     */
    private const STATUSES = [
        'captured' => ['label' => 'Captured', 'badge' => 'rl-badge-captured'],
        'booking_pending' => ['label' => 'Booking pending', 'badge' => 'rl-badge-pending'],
        'booked' => ['label' => 'Booked', 'badge' => 'rl-badge-booked'],
        'booking_failed' => ['label' => 'Booking failed', 'badge' => 'rl-badge-failed'],
        'abandoned' => ['label' => 'Abandoned', 'badge' => 'rl-badge-abandoned'],
        'canceled' => ['label' => 'Canceled', 'badge' => 'rl-badge-canceled'],
    ];

    /**
     * Slug => label, in the order a dropdown should show them.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_map(static fn (array $status): string => $status['label'], self::STATUSES);
    }

    /** @return array<int, string> */
    public static function slugs(): array
    {
        return array_keys(self::STATUSES);
    }

    /** Is this a value the column can hold? */
    public static function isKnown(string $status): bool
    {
        return array_key_exists(strtolower(trim($status)), self::STATUSES);
    }

    /** The human label, falling back to the raw value so an unmapped status is still legible. */
    public static function label(string $status): string
    {
        $status = strtolower(trim($status));

        return self::STATUSES[$status]['label'] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /** The badge class for a status, defaulting to the neutral one. */
    public static function badgeClass(string $status): string
    {
        return self::STATUSES[strtolower(trim($status))]['badge'] ?? 'rl-badge';
    }
}
