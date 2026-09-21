<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Data;

/**
 * One reconciliation finding, and the identity that lets somebody dismiss it.
 *
 * ## Why a finding needs a key at all
 *
 * Findings are recomputed from live data every hour; nothing about them persists. So "I have
 * dealt with this one" has to be recorded against something stable, and the sentence itself is
 * not stable — half of them carry numbers that move between runs ("40 bookings", "95 minutes"),
 * so a dismissal keyed on the text would silently stop matching the moment the figure changed.
 *
 * The key is the *subject*, not the wording: `warehouse-ahead` however far ahead it is,
 * `booking-no-meeting:4343` for that lead and no other.
 *
 * ## Why some dismissals outlive the day and others must not
 *
 * A finding naming a person is about something that already happened. Marvin Rodriguez booked a
 * meeting that does not exist; ringing him does not change the row, so the finding would return
 * on every card until midnight however many times it was dismissed. Those are `permanent`.
 *
 * Everything else describes the state of a day — the warehouse disagreeing, spend still settling,
 * a platform still reporting. Dismissing one of those for good would mean the next occurrence,
 * next month, in a completely different situation, is silently swallowed. So they expire, and
 * tomorrow's version of the same condition is announced again.
 *
 * That distinction is the whole safety model of the dismissal feature, which is why it lives on
 * the finding rather than being chosen in the admin screen: the person dismissing it should not
 * have to decide how dangerous their own dismissal is.
 */
readonly class Finding
{
    /**
     * @param  string  $key  Stable identity of the subject, never of the wording.
     * @param  string  $text  The sentence as it appears on the card.
     * @param  bool  $permanent  True when the thing it describes cannot recur — see above.
     */
    public function __construct(
        public string $key,
        public string $text,
        public bool $permanent = false,
    ) {}

    /** A finding about a day, which must be announced again the next time it happens. */
    public static function daily(string $key, string $text): self
    {
        return new self($key, $text, false);
    }

    /** A finding about something already done, which dismissing settles for good. */
    public static function settled(string $key, string $text): self
    {
        return new self($key, $text, true);
    }
}
