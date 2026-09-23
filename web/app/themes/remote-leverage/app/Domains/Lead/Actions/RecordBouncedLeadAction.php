<?php

declare(strict_types=1);

namespace App\Domains\Lead\Actions;

use App\Domains\Lead\Models\BouncedLead;
use App\Domains\Lead\Services\EmailValidationService;
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
            'utm_source', 'utm_medium', 'utm_campaign', 'referral_code'];

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

            // Whatever else the caller passed — page URL, role needed, hours — kept whole so a
            // question nobody has asked yet does not need a migration to answer.
            'context' => array_filter(
                array_diff_key($context, array_flip($known)),
                static fn ($value) => $value !== null && $value !== '' && $value !== [],
            ) ?: null,

            'attempts' => 1,
            'created_at' => $now,
            'last_seen_at' => $now,
        ];
    }
}
