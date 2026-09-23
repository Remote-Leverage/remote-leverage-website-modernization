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
    /**
     * What blocking defaults to when nobody has chosen.
     *
     * These three mean the mail will not arrive. `do_not_mail` is deliberately absent — it is a
     * deliverable mailbox (role accounts, known complainers) and blocking it turned real buyers
     * away at the form.
     */
    public const DEFAULT_REJECTED_STATUSES = ['invalid', 'spamtrap', 'abuse'];

    /**
     * Every status an admin may switch blocking on for.
     *
     * `valid` is not here and must never be: a list that can block it can lock out everybody.
     * `catch-all` and `unknown` are here but off by default and warned about on the screen —
     * they mean ZeroBounce could not determine the answer, so blocking them rejects every lead
     * behind a corporate catch-all mail server, which is most enterprise buyers.
     */
    public const TOGGLEABLE_STATUSES = ['invalid', 'spamtrap', 'abuse', 'do_not_mail', 'catch-all', 'unknown'];

    /**
     * The statuses blocking is actually on for, given a settings array.
     *
     * Pure and static, because the admin screen and the form must give the same answer; two
     * readers of the same option drift. A stored value that is not a list falls back to the
     * default rather than to "block nothing" — a corrupt option must not silently open the gate,
     * which looks exactly like verification passing.
     *
     * @param  array<string, mixed>  $settings
     * @return string[]
     */
    public static function rejectedStatusesFor(array $settings): array
    {
        $stored = $settings['zerobounce_blocked_statuses'] ?? null;

        if (! is_array($stored)) {
            return self::DEFAULT_REJECTED_STATUSES;
        }

        // An empty list is a real choice — "verify, but never reject on the result" — so it is
        // honoured. Unknown entries are dropped rather than trusted.
        return array_values(array_intersect(
            array_map(static fn ($status) => strtolower(trim((string) $status)), $stored),
            self::TOGGLEABLE_STATUSES,
        ));
    }

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

            /*
             * Which statuses block is an admin setting now, toggled on the Bounced Leads screen
             * — the screen that shows what the rule did is the one that can change it. It was a
             * constant until `do_not_mail` had to be removed by deploy. `valid` can never be on
             * the list; see rejectedStatusesFor().
             */
            if (in_array($status, self::rejectedStatusesFor($settings), true)) {
                return $this->reject('zerobounce_'.$status, $this->message($settings), 'zerobounce');
            }

            // Anything not on the list passes. By default that is `valid`, plus `catch-all` and
            // `unknown` (ZeroBounce could not determine the answer, which is not evidence
            // against the address) and `do_not_mail` (a deliverable mailbox: role accounts and
            // known complainers).
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
