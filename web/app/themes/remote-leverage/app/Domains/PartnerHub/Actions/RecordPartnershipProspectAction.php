<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Actions;

use App\Domains\PartnerHub\Models\PartnershipProspect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Record a prospect the partnerships team met somewhere other than `/become-a-partner/`: an
 * email to the team, a conversation at an event, an introduction.
 *
 * A separate action rather than a flag on SubmitPartnershipProspectAction, because the two
 * differ in exactly the parts that have side effects, and a flag is one forgotten argument away
 * from firing them. What this leaves out, and why:
 *
 *  - **No PartnershipProspectSubmitted, so no Slack card.** The card tells the channel that
 *    someone new has arrived. A hand-entered prospect has not arrived; they are already known
 *    to the person typing them in, and a card would read to everyone else as a fresh inbound.
 *  - **No email gate, so no ZeroBounce call.** The gate keeps a stranger's throwaway address
 *    out of the form. An address copied from a business card or an email thread is not that,
 *    and checking it would spend a credit to second-guess a colleague. The `email` rule still
 *    checks it is an address at all.
 *
 * Everything else is the submission's own rules, normalisation and answer lists, so a manual
 * row satisfies everything a submitted row does and nothing downstream — the list, the detail
 * screen, the export, the Overview — meets a shape only one path produces. It is marked
 * `source = manual`, which is what every one of those screens shows, and `context` records which
 * admin entered it, the way ReferralAdminDashboard::createReferrer stamps `created_via`.
 */
class RecordPartnershipProspectAction
{
    public const NOTES_MAX = 10000;

    /**
     * The submission's rules, plus the two things only the admin sets on creation.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            ...SubmitPartnershipProspectAction::rules(),
            'status' => ['required', Rule::in(PartnershipProspect::STATUSES)],
            'notes' => ['nullable', 'string', 'max:'.self::NOTES_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            ...SubmitPartnershipProspectAction::messages(),
            'status.required' => 'Choose a status.',
            'status.in' => 'Choose a status.',
            'notes.max' => 'Please keep the notes under 10,000 characters.',
        ];
    }

    /**
     * Validate and store one hand-entered prospect.
     *
     * @param  array<string, mixed>  $input  Keyed by column name: the form's fields, `status`
     *                                       and `notes`.
     * @param  int|null  $createdBy  The WordPress user entering it.
     *
     * @throws ValidationException
     */
    public function execute(array $input, ?int $createdBy = null): PartnershipProspect
    {
        $fields = [
            ...SubmitPartnershipProspectAction::normalise($input),
            'status' => $this->text($input, 'status') ?: 'new',
            'notes' => $this->text($input, 'notes'),
        ];

        Validator::make($fields, self::rules(), self::messages())->validate();

        return PartnershipProspect::query()->create([
            ...$fields,
            'message' => $fields['message'] !== '' ? $fields['message'] : null,
            'notes' => $fields['notes'] !== '' ? $fields['notes'] : null,
            'source' => 'manual',
            'context' => array_filter([
                'created_via' => 'wp_admin',
                'created_by' => $createdBy,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function text(array $input, string $key): string
    {
        $value = $input[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
