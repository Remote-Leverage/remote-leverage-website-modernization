<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Actions;

use App\Domains\PartnerHub\Models\PartnershipProspect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Change what the partnerships team keeps on a prospect: its status and their notes.
 *
 * The rules are RecordPartnershipProspectAction's, taken from it rather than restated, so a
 * status or a note that can be entered on a new prospect can be saved on an existing one and
 * nothing else can — the Add form and the prospect's own screen cannot drift into accepting
 * different things for the same two columns.
 *
 * Only the keys that are passed are touched. The prospect's screen posts both; a caller that
 * only changes the status, as the list's row control does, leaves the notes as they were
 * instead of blanking them because it did not send any.
 *
 * Nothing reacts to either change. A status is the team's record of where the conversation has
 * got to, not a trigger, and notes are read by nobody but the team.
 */
class UpdatePartnershipProspectAction
{
    /** The two columns this may write, in the order the screen shows them. */
    public const FIELDS = ['status', 'notes'];

    /**
     * Validate and save.
     *
     * A save that changes nothing does not write. Every save of a prospect drops the Overview's
     * cached figures (see PartnershipProspect::booted()), and Eloquent fires `saved` even for a
     * model with nothing dirty, so pressing Save on an untouched form would otherwise throw away
     * three minutes of cache for no reason.
     *
     * @param  array<string, mixed>  $input  `status` and/or `notes`, as typed.
     * @return array<int, string> The columns that changed; empty when nothing did.
     *
     * @throws ValidationException
     */
    public function execute(PartnershipProspect $prospect, array $input): array
    {
        $fields = [];

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $fields[$field] = $this->text($input[$field]);
            }
        }

        if ($fields === []) {
            return [];
        }

        Validator::make(
            $fields,
            array_intersect_key(RecordPartnershipProspectAction::rules(), $fields),
            RecordPartnershipProspectAction::messages(),
        )->validate();

        // Cleared notes are no notes, the way RecordPartnershipProspectAction stores them.
        if (array_key_exists('notes', $fields) && $fields['notes'] === '') {
            $fields['notes'] = null;
        }

        $prospect->fill($fields);

        $changed = array_values(array_filter(
            self::FIELDS,
            static fn (string $field) => $prospect->isDirty($field),
        ));

        if ($changed !== []) {
            $prospect->save();
        }

        return $changed;
    }

    /**
     * Trimmed, with a browser's CRLF line endings folded to LF so the length the validator
     * counts is the length that is stored, and a note re-saved unchanged is not "changed".
     */
    protected function text(mixed $value): string
    {
        return is_scalar($value) ? trim(str_replace("\r\n", "\n", (string) $value)) : '';
    }
}
