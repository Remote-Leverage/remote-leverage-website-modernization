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

    test('catch-all and unknown pass, because they mean undetermined', function () {
        // ZeroBounce could not decide. That is not evidence against the address, and treating
        // it as such rejects every lead behind a catch-all corporate mail server.
        foreach (['valid', 'catch-all', 'unknown'] as $status) {
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
