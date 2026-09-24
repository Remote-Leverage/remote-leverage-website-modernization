<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Actions;

use App\Domains\Lead\Services\EmailValidationService;
use App\Domains\PartnerHub\Data\PartnershipProspectData;
use App\Domains\PartnerHub\Events\PartnershipProspectSubmitted;
use App\Domains\PartnerHub\Models\PartnershipProspect;
use App\Domains\PartnerHub\Support\PartnershipProspectOptions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Record a `/become-a-partner/` submission and announce it.
 *
 * ## What this deliberately does not touch
 *
 * Everything a *lead* goes through. No `CaptureLeadAction`, no `rl_leads` row, no `LeadCreated`
 * — so no HubSpot sync, no Meta or Google conversion, no n8n webhooks and no card in the sales
 * channel. The people filling this in are companies that might send us clients, and every one
 * of those systems would count them as a buyer: a Meta `Lead` for an agency owner trains the ad
 * account on the wrong audience, and a card in `#new-appts` puts a partnership conversation in
 * front of a sales rep who will phone them about hiring a VA. The one thing shared with the lead
 * path is the email gate, below.
 *
 * ## Validation lives here, not in the component
 *
 * Laravel's validator rather than the hand-rolled checks SubmitReferredLeadAction uses, because
 * this form reports errors under each field rather than in one banner, and a ValidationException
 * carries exactly that shape. It is still called from the action rather than through Livewire's
 * `$this->validate()`, for the reason that class gives: the test harness does not provide the
 * `livewire` binding that method's failure path resolves, so rules written that way cannot be
 * tested at the point they reject. The component translates the keys; see
 * PartnershipProspectForm::FIELDS.
 *
 * The three choice fields are a whitelist, not a hint. The value arrives from the browser and is
 * stored verbatim, so anything outside PartnershipProspectOptions would be a row the admin screen
 * and the Slack card could only print as a raw string.
 */
class SubmitPartnershipProspectAction
{
    /**
     * Column widths for the attribution strings, from the migration.
     *
     * Enforced by truncation rather than validation because the visitor did not type these — a
     * campaign tag longer than its column is marketing's URL, not the prospect's mistake, and on
     * MySQL's strict mode an over-long value is a failed insert. That is precisely how a 212-
     * character `fbclid` once dropped every paid-social lead.
     */
    protected const CONTEXT_WIDTHS = [
        'utm_source' => 100,
        'utm_medium' => 100,
        'utm_campaign' => 150,
        'utm_term' => 150,
        'utm_content' => 150,
        'ip_address' => 45,
    ];

    public function __construct(
        protected ?EmailValidationService $emails = null,
    ) {
        $this->emails ??= new EmailValidationService;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'company' => ['required', 'string', 'max:191'],
            'role' => ['required', 'string', 'max:150'],
            'organization_type' => ['required', Rule::in(PartnershipProspectOptions::slugs('organization_type'))],
            'monthly_revenue' => ['required', Rule::in(PartnershipProspectOptions::slugs('monthly_revenue'))],
            'businesses_reached' => ['required', Rule::in(PartnershipProspectOptions::slugs('businesses_reached'))],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Every message spelled out, so what the visitor reads does not depend on which language
     * files a given environment happens to have loaded.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'first_name.required' => 'Please enter your first name.',
            'first_name.max' => 'That first name is too long.',
            'last_name.required' => 'Please enter your last name.',
            'last_name.max' => 'That last name is too long.',
            'email.required' => 'Please enter your business email.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'That email address is too long.',
            'company.required' => 'Please enter your company name.',
            'company.max' => 'That company name is too long.',
            'role.required' => 'Please enter your role.',
            'role.max' => 'That role is too long.',
            'organization_type.required' => 'Please select the type of organization.',
            'organization_type.in' => 'Please select the type of organization.',
            'monthly_revenue.required' => 'Please select your monthly revenue.',
            'monthly_revenue.in' => 'Please select your monthly revenue.',
            'businesses_reached.required' => 'Please select how many businesses you reach.',
            'businesses_reached.in' => 'Please select how many businesses you reach.',
            'message.max' => 'Please keep this under 2,000 characters.',
        ];
    }

    /**
     * Validate, store and announce one submission.
     *
     * @param  array<string, mixed>  $input  What the visitor typed, keyed by column name.
     * @param  array<string, mixed>  $context  Where they came from: `landing_url`, `referrer_url`,
     *                                         the five UTMs, `ip_address`, and anything else under
     *                                         `context`. Collected by the caller, never typed.
     *
     * @throws ValidationException
     */
    public function execute(array $input, array $context = []): PartnershipProspect
    {
        $fields = self::normalise($input);

        Validator::make($fields, self::rules(), self::messages())->validate();

        /*
         * The same gate the booking form puts in front of a lead: the address blacklist, the
         * disposable-domain list and ZeroBounce, configured once on the Leads settings screen.
         * Reused rather than reimplemented because "which addresses do we refuse" is one
         * decision, and a second copy of it would disagree with the first the day somebody
         * edits the list.
         *
         * Only after the rules pass, so a form with three empty fields does not spend a
         * ZeroBounce credit on an address it was going to reject anyway. A refusal is *not*
         * written to `rl_bounced_leads`: that table counts turned-away buyers, and these are not.
         */
        $verdict = $this->emails->validate($fields['email'], $this->context($context, 'ip_address'));

        if (! $verdict['valid']) {
            throw ValidationException::withMessages([
                'email' => (string) ($verdict['message'] ?: 'Please use a valid business email address.'),
            ]);
        }

        $data = PartnershipProspectData::fromArray([
            ...$fields,
            'message' => $fields['message'] !== '' ? $fields['message'] : null,
            'landing_url' => $this->context($context, 'landing_url'),
            'referrer_url' => $this->context($context, 'referrer_url'),
            'utm_source' => $this->context($context, 'utm_source'),
            'utm_medium' => $this->context($context, 'utm_medium'),
            'utm_campaign' => $this->context($context, 'utm_campaign'),
            'utm_term' => $this->context($context, 'utm_term'),
            'utm_content' => $this->context($context, 'utm_content'),
            'ip_address' => $this->context($context, 'ip_address'),
            'context' => array_filter(
                (array) ($context['context'] ?? []),
                static fn ($value) => $value !== null && $value !== '' && $value !== [],
            ),
        ]);

        $prospect = PartnershipProspect::query()->create($data->toArray());

        Event::dispatch(new PartnershipProspectSubmitted($prospect));

        return $prospect;
    }

    /**
     * Trim everything, lowercase the address, and keep only the fields this form has.
     *
     * Public and static so RecordPartnershipProspectAction shapes a hand-entered prospect exactly
     * the way a submission is shaped, before the same rules see it.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function normalise(array $input): array
    {
        $fields = [];

        foreach (array_keys(self::rules()) as $key) {
            $value = $input[$key] ?? '';
            $fields[$key] = is_scalar($value) ? trim((string) $value) : '';
        }

        $fields['email'] = strtolower($fields['email']);

        return $fields;
    }

    /**
     * One attribution string, blank as null and cut to its column.
     *
     * @param  array<string, mixed>  $context
     */
    protected function context(array $context, string $key): ?string
    {
        $value = $context[$key] ?? null;

        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);
        $width = self::CONTEXT_WIDTHS[$key] ?? null;

        return $width === null ? $value : mb_substr($value, 0, $width);
    }
}
