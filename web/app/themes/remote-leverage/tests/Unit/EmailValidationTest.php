<?php

declare(strict_types=1);

use App\Domains\Lead\Services\EmailValidationService;
use App\Domains\Lead\Services\LeadSettingsService;

/*
 * Email gatekeeping, ported from three Gravity Forms plugins over one field.
 *
 * The property that matters most here is **fail-open**: a ZeroBounce outage must accept the
 * address, because rejecting a real buyer costs far more than letting one bad address through.
 * A verifier that fails closed turns a third-party incident into lost revenue, silently.
 */

/** A service over fixed settings, so tests never touch wp_options. */
function emailValidator(array $settings = []): EmailValidationService
{
    $stub = new class($settings) extends LeadSettingsService
    {
        public function __construct(private array $overrides) {}

        public function get(): array
        {
            return array_merge(self::defaults(), $this->overrides);
        }
    };

    return new EmailValidationService($stub);
}

describe('local lists', function () {
    test('a malformed address is rejected without consulting anything', function () {
        expect(emailValidator()->validate('not-an-email')['valid'])->toBeFalse();
    });

    test('a blacklisted address is rejected', function () {
        $result = emailValidator([
            'blacklisted_emails' => 'mzml.usa5@gmail.com, gualax247y@gmail.com',
        ])->validate('MZML.USA5@gmail.com');   // case must not matter

        expect($result['valid'])->toBeFalse()
            ->and($result['checked_by'])->toBe('blacklist');
    });

    test('block mode rejects a listed domain', function () {
        $result = emailValidator([
            'domain_validator_mode' => 'block',
            'email_domains' => "cuvox.de\narmyspy.com\ndayrep.com",
        ])->validate('someone@armyspy.com');

        expect($result['valid'])->toBeFalse()
            ->and($result['reason'])->toBe('blocked_domain');
    });

    test('block mode passes an unlisted domain', function () {
        $result = emailValidator([
            'domain_validator_mode' => 'block',
            'email_domains' => "cuvox.de\narmyspy.com",
        ])->validate('buyer@realcompany.com');

        expect($result['valid'])->toBeTrue();
    });

    test('allow mode is the inverse', function () {
        $validator = emailValidator([
            'domain_validator_mode' => 'allow',
            'email_domains' => 'realcompany.com',
        ]);

        expect($validator->validate('buyer@realcompany.com')['valid'])->toBeTrue()
            ->and($validator->validate('buyer@elsewhere.com')['valid'])->toBeFalse();
    });

    test('none mode disables the domain rule entirely', function () {
        $result = emailValidator([
            'domain_validator_mode' => 'none',
            'email_domains' => 'armyspy.com',
        ])->validate('someone@armyspy.com');

        expect($result['valid'])->toBeTrue();
    });

    test('the rejection message never says which list matched', function () {
        // Telling a spammer "that domain is blocked" tells them exactly what to change.
        $result = emailValidator([
            'domain_validator_mode' => 'block',
            'email_domains' => 'armyspy.com',
            'email_validation_message' => 'Please use a valid business email address.',
        ])->validate('someone@armyspy.com');

        expect($result['message'])->toBe('Please use a valid business email address.')
            ->and($result['message'])->not->toContain('domain');
    });
});

describe('ZeroBounce', function () {
    /** A validator whose ZeroBounce call returns a fixed status, or throws. */
    function zeroBounceValidator(?string $status, array $settings = []): EmailValidationService
    {
        $stub = new class(array_merge(['zerobounce_enabled' => true, 'zerobounce_api_key' => 'k'], $settings)) extends LeadSettingsService
        {
            public function __construct(private array $overrides) {}

            public function get(): array
            {
                return array_merge(self::defaults(), $this->overrides);
            }
        };

        return new class($stub, $status) extends EmailValidationService
        {
            public function __construct(LeadSettingsService $settings, private ?string $status)
            {
                parent::__construct($settings);
            }

            protected function checkZeroBounce(string $email, ?string $ip, array $settings): array
            {
                if ($this->status === null) {
                    return $this->accept('zerobounce_unavailable');   // outage path
                }

                return in_array($this->status, self::REJECTED_STATUSES, true)
                    ? $this->reject('zerobounce_'.$this->status, $this->message($settings), 'zerobounce')
                    : $this->accept('zerobounce');
            }
        };
    }

    test('undeliverable statuses are rejected', function () {
        foreach (EmailValidationService::REJECTED_STATUSES as $status) {
            expect(zeroBounceValidator($status)->validate('someone@example.com')['valid'])
                ->toBeFalse();
        }
    });

    test('catch-all, unknown and do_not_mail all pass', function () {
        // catch-all/unknown: ZeroBounce could not decide. That is not evidence against the
        // address, and treating it as such rejects every lead behind a catch-all corporate mail
        // server. do_not_mail is a deliverable mailbox (role accounts, known complainers) that we
        // deliberately no longer turn away at the form.
        foreach (['valid', 'catch-all', 'unknown', 'do_not_mail'] as $status) {
            expect(zeroBounceValidator($status)->validate('someone@example.com')['valid'])
                ->toBeTrue();
        }
    });

    test('an outage fails OPEN', function () {
        $result = zeroBounceValidator(null)->validate('someone@example.com');

        expect($result['valid'])->toBeTrue()
            ->and($result['checked_by'])->toBe('zerobounce_unavailable');
    });

    test('the local lists still apply during an outage', function () {
        $result = zeroBounceValidator(null, [
            'domain_validator_mode' => 'block',
            'email_domains' => 'armyspy.com',
        ])->validate('someone@armyspy.com');

        expect($result['valid'])->toBeFalse()
            ->and($result['checked_by'])->toBe('domain_validator');
    });

    test('it is skipped entirely when disabled', function () {
        $result = emailValidator(['zerobounce_enabled' => false, 'zerobounce_api_key' => 'k'])
            ->validate('someone@example.com');

        expect($result['valid'])->toBeTrue()
            ->and($result['checked_by'])->toBeNull();
    });
});

describe('list parsing', function () {
    test('accepts newlines, commas and arrays alike', function () {
        // The two plugin UIs use different separators, so both shapes reach this.
        $validator = emailValidator();

        expect($validator->toList("a.com\nb.com\r\nc.com"))->toBe(['a.com', 'b.com', 'c.com'])
            ->and($validator->toList('a.com, b.com ,c.com'))->toBe(['a.com', 'b.com', 'c.com'])
            ->and($validator->toList(['A.com', ' b.com ']))->toBe(['a.com', 'b.com'])
            ->and($validator->toList("a.com\n\n\n"))->toBe(['a.com']);
    });
});

describe('configuration defaults', function () {
    test('ZeroBounce is ON by default, so a configured key actually verifies', function () {
        // The regression this pins: `zerobounce_enabled` defaulted to false, so a valid key
        // with credits verified nothing — and an unverified address looked exactly like a
        // verified one. A capability that silently does nothing is worse than one that is off.
        expect(LeadSettingsService::defaults()['zerobounce_enabled'])
            ->toBeTrue();
    });

    test('an explicit opt-out still disables it', function () {
        $result = emailValidator([
            'zerobounce_enabled' => false,
            'zerobounce_api_key' => 'k',
        ])->validate('someone@example.com');

        expect($result['valid'])->toBeTrue()
            ->and($result['checked_by'])->toBeNull();
    });
});

describe('form field binding', function () {
    test('no booking input syncs mid-typing', function () {
        // Every `.live` text field adds a request that can be in flight while the visitor types
        // or ticks something else; that response is built from a stale snapshot and the DOM
        // patch reverts their input. Deferred fields sync with the button click instead, which
        // is the only moment their value is needed.
        $blade = file_get_contents(
            __DIR__.'/../../resources/views/livewire/booking/multistep-booking-wizard.blade.php'
        );

        foreach (['email', 'firstName', 'lastName', 'phone', 'consent'] as $field) {
            expect($blade)->not->toContain('wire:model.live="'.$field.'"')
                ->and($blade)->not->toContain('wire:model.live.debounce.300ms="'.$field.'"');
        }
    });

    test('the fields that drive server-side routing stay live', function () {
        // monthlyRevenue selects the Calendly event type and the pricing warning; timezone
        // reloads availability. Those must round-trip when they change.
        $blade = file_get_contents(
            __DIR__.'/../../resources/views/livewire/booking/multistep-booking-wizard.blade.php'
        );

        expect($blade)->toContain('wire:model.live="monthlyRevenue"')
            ->and($blade)->toContain('wire:model.live="timezone"');
    });
});

describe('HubSpot property value types', function () {
    test('intake_form is sent as a booleancheckbox value, not the legacy "yes"', function () {
        // HubSpot's intake_form accepts only 'true'/'false'. Sending 'yes' 400s the whole
        // request — every other property on it lost too, which is how a complete sync failed
        // on one field. Verified against the portal's property schema on 2026-09-16.
        $source = file_get_contents(
            __DIR__.'/../../app/Domains/Lead/Services/HubSpotGateway.php'
        );

        expect($source)->toContain("'intake_form' => \$lead->intake_form ? 'true' : null")
            ->and($source)->not->toContain("'intake_form' => \$lead->intake_form,");
    });
});
