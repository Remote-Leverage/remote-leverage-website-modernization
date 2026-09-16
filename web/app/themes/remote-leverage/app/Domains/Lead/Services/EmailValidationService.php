<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Email gatekeeping for the lead forms, ported from the Gravity Forms stack.
 *
 * Production ran three separate plugins over the same field, and each catches something the
 * others do not:
 *
 *  - **Email Blacklist** — specific addresses that have abused the form.
 *  - **Domain Validator** — whole domains, in block or allow mode. The production list is
 *    disposable-mailbox providers (`cuvox.de`, `armyspy.com`, `dayrep.com` …), which is how
 *    most junk arrives: a fresh address on a throwaway domain, so an address blacklist never
 *    catches it twice.
 *  - **ZeroBounce** — the address is syntactically fine and the domain is real, but the mailbox
 *    does not exist. Neither list can know that.
 *
 * Order matters and is deliberate: the two local lists run first and cost nothing, so a known
 * bad address never spends a ZeroBounce credit or a network round trip.
 *
 * **Fails open.** If ZeroBounce is unreachable, slow, or out of credits, the address is
 * accepted. Rejecting a real buyer because a third-party API had a bad minute is far more
 * expensive than letting one bad address through, and the local lists still apply. The same
 * reasoning as `HubSpotGateway` returning null rather than guessing.
 */
class EmailValidationService
{
    /** Validation outcomes ZeroBounce reports that we treat as undeliverable. */
    public const REJECTED_STATUSES = ['invalid', 'spamtrap', 'abuse', 'do_not_mail'];

    public function __construct(
        protected ?LeadSettingsService $settings = null,
    ) {
        $this->settings ??= new LeadSettingsService;
    }

    /**
     * Validate an address.
     *
     * @return array{valid: bool, reason: ?string, message: ?string, checked_by: ?string}
     */
    public function validate(string $email, ?string $ipAddress = null): array
    {
        $email = strtolower(trim($email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->reject('malformed', 'Please enter a valid email address.', 'format');
        }

        $settings = $this->settings->get();
        $domain = substr(strrchr($email, '@') ?: '', 1);

        if ($this->isBlacklistedAddress($email, $settings)) {
            return $this->reject('blacklisted_email', $this->message($settings), 'blacklist');
        }

        $domainVerdict = $this->checkDomain($domain, $settings);

        if ($domainVerdict !== null) {
            return $this->reject($domainVerdict, $this->message($settings), 'domain_validator');
        }

        return $this->checkZeroBounce($email, $ipAddress, $settings);
    }

    /**
     * Is this exact address on the blacklist?
     */
    protected function isBlacklistedAddress(string $email, array $settings): bool
    {
        $blacklist = $this->toList($settings['blacklisted_emails'] ?? []);

        return in_array($email, array_map('strtolower', $blacklist), true);
    }

    /**
     * Apply the domain validator, returning a rejection reason or null to continue.
     *
     * Production runs this in `block` mode. `allow` mode is the inverse — only listed domains
     * pass — and is here because the plugin offers it and a future gated form may want it.
     */
    protected function checkDomain(string $domain, array $settings): ?string
    {
        $mode = (string) ($settings['domain_validator_mode'] ?? 'none');

        if ($mode === 'none' || $domain === '') {
            return null;
        }

        $domains = array_map('strtolower', $this->toList($settings['email_domains'] ?? []));
        $listed = in_array($domain, $domains, true);

        if ($mode === 'block' && $listed) {
            return 'blocked_domain';
        }

        if ($mode === 'allow' && ! $listed) {
            return 'domain_not_allowed';
        }

        return null;
    }

    /**
     * Ask ZeroBounce whether the mailbox actually exists.
     *
     * @return array{valid: bool, reason: ?string, message: ?string, checked_by: ?string}
     */
    protected function checkZeroBounce(string $email, ?string $ipAddress, array $settings): array
    {
        $apiKey = (string) ($settings['zerobounce_api_key'] ?: config('services.zerobounce.api_key', ''));

        if ($apiKey === '') {
            return $this->accept(null);
        }

        /*
         * A configured key means on unless someone has explicitly switched it off.
         *
         * `zerobounce_enabled` defaults to true (LeadSettingsService::defaults). It defaulted to
         * false, which meant a valid key with credits verified nothing and looked identical to
         * verification passing — the failure mode this whole service exists to avoid.
         */
        if (! filter_var($settings['zerobounce_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return $this->accept(null);
        }

        try {
            // Short timeouts: this runs in the form's validation path, and a slow verifier must
            // not become a slow form. Anything that does not answer promptly fails open.
            $response = Http::timeout(4)
                ->connectTimeout(2)
                ->get('https://api.zerobounce.net/v2/validate', array_filter([
                    'api_key' => $apiKey,
                    'email' => $email,
                    'ip_address' => $ipAddress,
                ]));

            if (! $response->successful()) {
                Log::warning('EmailValidationService: ZeroBounce returned an error; accepting the address.', [
                    'status' => $response->status(),
                ]);

                return $this->accept('zerobounce_unavailable');
            }

            $status = strtolower((string) $response->json('status', ''));

            if (in_array($status, self::REJECTED_STATUSES, true)) {
                return $this->reject('zerobounce_'.$status, $this->message($settings), 'zerobounce');
            }

            // `valid`, `catch-all` and `unknown` all pass. catch-all and unknown mean ZeroBounce
            // could not determine the answer, which is not evidence against the address.
            return $this->accept('zerobounce');
        } catch (\Throwable $e) {
            Log::warning('EmailValidationService: ZeroBounce call failed; accepting the address. '.$e->getMessage());

            return $this->accept('zerobounce_unavailable');
        }
    }

    protected function message(array $settings): string
    {
        $message = trim((string) ($settings['email_validation_message'] ?? ''));

        return $message !== ''
            ? $message
            : 'Please use a valid business email address.';
    }

    /**
     * @return array{valid: bool, reason: ?string, message: ?string, checked_by: ?string}
     */
    protected function accept(?string $checkedBy): array
    {
        return ['valid' => true, 'reason' => null, 'message' => null, 'checked_by' => $checkedBy];
    }

    /**
     * @return array{valid: bool, reason: ?string, message: ?string, checked_by: ?string}
     */
    protected function reject(string $reason, string $message, string $checkedBy): array
    {
        return ['valid' => false, 'reason' => $reason, 'message' => $message, 'checked_by' => $checkedBy];
    }

    /**
     * Accept a textarea blob, a comma-separated string, or an array.
     *
     * The admin screens present these as free text — one per line for domains, comma separated
     * for addresses, matching the two plugins' own UIs — so both shapes arrive here.
     *
     * @return string[]
     */
    public function toList(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\r\n,]+/', (string) $value) ?: [];
        }

        return array_values(array_filter(array_map(
            static fn ($item) => strtolower(trim((string) $item)),
            $items,
        ), static fn ($item) => $item !== ''));
    }
}
