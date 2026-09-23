<?php

declare(strict_types=1);

namespace App\Domains\Lead\Actions;

use App\Domains\Lead\Models\BouncedLead;
use App\Domains\Lead\Services\EmailValidationService;
use App\Domains\Lead\Services\FbcResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Record a booking-form submission that the email check refused.
 *
 * Called from the rejection branch of the form rather than from inside
 * {@see EmailValidationService}, for two reasons: the service is a
 * pure validator with no database of its own (its unit tests run without a connection), and the
 * details worth keeping — name, phone, company, attribution — are the caller's, not the
 * validator's. Any new caller of `validate()` that can reject a visitor should call this too.
 *
 * **Never throws.** A visitor who has already been told their address was refused must not then
 * meet a 500 because the write failed; the same fail-open reasoning as the validator itself.
 */
class RecordBouncedLeadAction
{
    /**
     * How long repeat attempts fold into one row.
     *
     * Someone refused at step one retypes the address and is refused again. Each retry is the
     * same turned-away person, and counting them separately would overstate exactly the number
     * this table exists to report. Matches the wizard's own resume window.
     */
    public const COLLAPSE_WINDOW_MINUTES = 30;

    /**
     * @param  array{valid: bool, reason: ?string, message: ?string, checked_by: ?string}  $verdict
     * @param  array<string, mixed>  $context  Step-one fields and attribution from the caller.
     */
    public function execute(string $email, array $verdict, array $context = []): ?BouncedLead
    {
        if (($verdict['valid'] ?? true) === true) {
            return null;
        }

        $payload = self::payloadFor($email, $verdict, $context);

        if ($payload === null) {
            return null;
        }

        try {
            $existing = BouncedLead::query()
                ->where('email', $payload['email'])
                ->where('reason', $payload['reason'])
                ->where('last_seen_at', '>=', Carbon::now()->subMinutes(self::COLLAPSE_WINDOW_MINUTES))
                ->latest('id')
                ->first();

            if ($existing !== null) {
                $existing->forceFill([
                    'attempts' => $existing->attempts + 1,
                    'last_seen_at' => Carbon::now(),

                    // A retry often carries fields the first attempt did not — somebody fills
                    // the phone in after being bounced. Keep whatever is now known.
                    'name' => $payload['name'] ?: $existing->name,
                    'phone' => $payload['phone'] ?: $existing->phone,
                    'company' => $payload['company'] ?: $existing->company,
                ])->save();

                return $existing;
            }

            return BouncedLead::create($payload);
        } catch (\Throwable $e) {
            Log::warning('RecordBouncedLeadAction: could not record a bounced lead. '.$e->getMessage());

            return null;
        }
    }

    /**
     * The Meta click cookie for this submission, through the same rules a real lead gets.
     *
     * Not `$context['fbc']` taken at face value. The wizard collects attribution once in
     * `mount()`, before Meta's pixel JS has run, so a visitor arriving on a bare `fbclid` has a
     * *synthetic* `fbc` frozen into that array; by the time they are refused at step one the
     * genuine cookie usually exists. {@see FbcResolver} prefers the live cookie, rejects one
     * belonging to a different click, and reports which kind it returned. `CaptureLeadAction`
     * asks it the same question for leads that are not refused.
     *
     * @param  array<string, mixed>  $context
     * @return array{value: string, synthetic: bool}|null
     */
    protected static function resolveFbc(array $context): ?array
    {
        $named = is_array($context['attribution_named'] ?? null) ? $context['attribution_named'] : [];
        $blob = is_array($context['attribution'] ?? null) ? $context['attribution'] : [];

        // A caller that passed a bare `fbc` and no collected attribution still gets the rules
        // applied rather than ignored — `resolve()` keys off the presence of the `fbc` key.
        if (! array_key_exists('fbc', $named) && array_key_exists('fbc', $context)) {
            $named['fbc'] = $context['fbc'];
            $blob['fbc_synthetic'] = (bool) ($context['fbc_synthetic'] ?? false);
        }

        $fbclid = trim((string) ($context['fbclid'] ?? ''));

        return (new FbcResolver)->resolve($named, $blob, $fbclid !== '' ? $fbclid : null);
    }

    /**
     * Shape a verdict plus caller context into a row.
     *
     * Pure and static so the mapping is testable without a database — the write above is the
     * only part that needs one.
     *
     * @param  array{valid: bool, reason: ?string, message: ?string, checked_by: ?string}  $verdict
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null Null when there is nothing worth recording.
     */
    public static function payloadFor(string $email, array $verdict, array $context = []): ?array
    {
        $email = strtolower(trim($email));
        $reason = trim((string) ($verdict['reason'] ?? ''));

        // No address means no row: the screen is a list of people to look up, and a blank one
        // cannot be looked up. A rejection always carries a reason; a missing one is a bug
        // upstream rather than something to file under an empty string.
        if ($email === '' || $reason === '') {
            return null;
        }

        $string = static function (mixed $value, int $limit): ?string {
            $value = trim((string) $value);

            return $value === '' ? null : mb_substr($value, 0, $limit);
        };

        $known = ['name', 'phone', 'company', 'ip_address', 'posthog_session_id',
            'utm_source', 'utm_medium', 'utm_campaign', 'referral_code',
            'gclid', 'fbclid', 'msclkid', 'landing_url',
            // Consumed by resolveFbc() below rather than copied through.
            'fbc', 'fbc_synthetic', 'attribution_named', 'attribution'];

        $fbc = self::resolveFbc($context);

        $now = Carbon::now();

        return [
            'email' => mb_substr($email, 0, 255),
            'name' => $string($context['name'] ?? null, 255),
            'phone' => $string($context['phone'] ?? null, 32),
            'company' => $string($context['company'] ?? null, 255),
            'reason' => mb_substr($reason, 0, 64),
            'checked_by' => mb_substr((string) ($verdict['checked_by'] ?? 'unknown'), 0, 32),
            'ip_address' => $string($context['ip_address'] ?? null, 45),
            'posthog_session_id' => $string($context['posthog_session_id'] ?? null, 255),
            'utm_source' => $string($context['utm_source'] ?? null, 255),
            'utm_medium' => $string($context['utm_medium'] ?? null, 255),
            'utm_campaign' => $string($context['utm_campaign'] ?? null, 255),
            'referral_code' => $string($context['referral_code'] ?? null, 255),

            /*
             * Click identifiers, so a refused submission can be tied back to the ad that paid
             * for it. Widths match `rl_leads`. Without these the screen could say somebody was
             * turned away but not that a paid click was.
             */
            'gclid' => $string($context['gclid'] ?? null, 512),
            'fbclid' => $string($context['fbclid'] ?? null, 512),
            'msclkid' => $string($context['msclkid'] ?? null, 150),
            'fbc' => $fbc === null ? null : mb_substr($fbc['value'], 0, 512),
            'fbc_synthetic' => $fbc === null ? null : $fbc['synthetic'],
            'landing_url' => $string($context['landing_url'] ?? null, 2000),

            /*
             * Whatever else the caller passed — role needed, hours — plus the attribution blob
             * itself, kept whole so a question nobody has asked yet does not need a migration.
             *
             * The blob is not optional cargo: `_fbp` has no column here (exactly as it has none
             * on `rl_leads`) and the Conversions API reads it, as do `wbraid`/`gbraid`. Dropping
             * it because `attribution` happens to be consumed by resolveFbc() would quietly cost
             * the identifiers Meta matches on.
             */
            'context' => array_filter(
                array_merge(
                    array_diff_key($context, array_flip($known)),
                    array_filter(['attribution' => array_diff_key(
                        is_array($context['attribution'] ?? null) ? $context['attribution'] : [],
                        array_flip(['fbc_synthetic']),   // bookkeeping, now a column of its own
                    )]),
                ),
                static fn ($value) => $value !== null && $value !== '' && $value !== [],
            ) ?: null,

            'attempts' => 1,
            'created_at' => $now,
            'last_seen_at' => $now,
        ];
    }
}
