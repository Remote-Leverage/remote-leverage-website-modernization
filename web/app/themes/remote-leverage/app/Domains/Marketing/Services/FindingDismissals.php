<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Data\Finding;
use Carbon\CarbonImmutable;

/**
 * Which findings somebody has said they have dealt with.
 *
 * ## Why this exists
 *
 * A finding that cannot be silenced gets silenced anyway — by the reader, who stops reading the
 * warnings. The phantom-booking finding is the one that forced this: it names somebody whose
 * meeting does not exist, ringing them is the fix, and nothing about ringing them changes the row
 * it is read from. So it would repeat on every hourly card until midnight, long after it had been
 * dealt with, teaching everyone in the channel that the red text at the top is noise. That is the
 * exact failure {@see AlertReconciler} was built to prevent, arriving by a different route.
 *
 * ## What a dismissal may and may not silence
 *
 * The scope is the finding's, not the dismisser's — see {@see Finding}. A `permanent` finding is
 * about something that already happened and cannot recur, so dismissing it settles it for good. A
 * daily one describes the state of a day, so the dismissal is stamped with that day and tomorrow's
 * version of the same condition is announced again.
 *
 * Nobody is asked to choose between those, because the person clearing a warning at 09:00 is the
 * last person who should be deciding whether next month's recurrence gets swallowed.
 *
 * ## Why the option and not a table
 *
 * There are at most a handful of live findings at a time and dismissals of daily ones are pruned
 * as they expire, so this stays small — tens of rows, not thousands. A table would be a migration,
 * a model and a purge policy for something the option API already does, and it would still be read
 * on every snapshot.
 */
class FindingDismissals
{
    public const OPTION = 'rl_marketing_finding_dismissals';

    /**
     * Record that a finding has been dealt with.
     *
     * Stores the wording as it stood, which is not used for matching — matching is on the key —
     * but is what lets the dashboard show a dismissed finding as the sentence somebody actually
     * read before they dismissed it, rather than a bare key.
     */
    public function dismiss(Finding $finding, ?CarbonImmutable $now = null): void
    {
        $now = $now ?? CarbonImmutable::now($this->timezone());

        $all = $this->all();

        $all[$finding->key] = [
            'text' => $finding->text,
            'permanent' => $finding->permanent,
            'date' => $now->toDateString(),
            'at' => $now->toIso8601String(),
            'by' => $this->currentUserName(),
        ];

        $this->put($all);
    }

    /** Undo a dismissal, so the finding is announced again on the next run. */
    public function restore(string $key): void
    {
        $all = $this->all();

        unset($all[$key]);

        $this->put($all);
    }

    /**
     * Split findings into the ones still worth saying and the ones already dealt with.
     *
     * Expired daily dismissals are dropped from storage as they are passed over, so the option
     * does not accumulate a row per condition per day forever.
     *
     * @param  array<int, Finding>  $findings
     * @return array{live: array<int, Finding>, dismissed: array<int, array<string, mixed>>}
     */
    public function partition(array $findings, ?CarbonImmutable $now = null): array
    {
        $now = $now ?? CarbonImmutable::now($this->timezone());
        $all = $this->all();
        $today = $now->toDateString();

        $live = [];
        $dismissed = [];
        $expired = false;

        foreach ($findings as $finding) {
            $record = $all[$finding->key] ?? null;

            if ($record === null) {
                $live[] = $finding;

                continue;
            }

            /*
             * A daily dismissal covers the day it was made and nothing else. The finding's own
             * scope is trusted over the stored flag, so changing a finding from daily to settled
             * (or back) takes effect immediately rather than being frozen by old rows.
             */
            if (! $finding->permanent && ($record['date'] ?? '') !== $today) {
                unset($all[$finding->key]);
                $expired = true;
                $live[] = $finding;

                continue;
            }

            $dismissed[] = [
                'key' => $finding->key,
                'text' => $finding->text,
                'at' => (string) ($record['at'] ?? ''),
                'by' => (string) ($record['by'] ?? ''),
                'permanent' => (bool) ($record['permanent'] ?? $finding->permanent),
            ];
        }

        if ($expired) {
            $this->put($all);
        }

        return ['live' => $live, 'dismissed' => $dismissed];
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        if (! function_exists('get_option')) {
            return [];
        }

        $stored = get_option(self::OPTION, []);

        return is_array($stored) ? $stored : [];
    }

    /** @param  array<string, array<string, mixed>>  $all */
    private function put(array $all): void
    {
        if (function_exists('update_option')) {
            update_option(self::OPTION, $all, false);
        }
    }

    /**
     * Who dismissed it, for the dashboard's muted line.
     *
     * A display name rather than an id: the point of recording it is that somebody reading the
     * dashboard tomorrow can tell who to ask, and an id makes them go and look it up.
     */
    private function currentUserName(): string
    {
        if (! function_exists('wp_get_current_user')) {
            return '';
        }

        $user = wp_get_current_user();

        $name = trim((string) ($user->display_name ?? ''));

        return $name !== '' ? $name : trim((string) ($user->user_login ?? ''));
    }

    private function timezone(): string
    {
        return (string) config('marketing.cost_alert.timezone', 'UTC');
    }
}
