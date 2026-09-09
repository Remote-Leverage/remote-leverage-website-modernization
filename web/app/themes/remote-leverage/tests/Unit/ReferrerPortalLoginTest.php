<?php

declare(strict_types=1);

use App\Application\Livewire\Referrer\ReferrerPortalDashboard;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Repositories\EloquentReferrerRepository;
use App\Domains\Referral\Repositories\ReferrerRepositoryInterface;

beforeEach(function () {
    app()->bind(ReferrerRepositoryInterface::class, EloquentReferrerRepository::class);
    $GLOBALS['_test_session'] = [];
    $GLOBALS['_test_request_ip'] = '198.51.100.'.random_int(1, 254);
});

function makeLoginTestReferrer(string $password): Referrer
{
    return Referrer::query()->create([
        'name' => 'Login Test Referrer',
        'email' => 'login-'.uniqid().'@venture.com',
        'referral_code' => 'login-'.uniqid(),
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'status' => 'active',
    ]);
}

describe('ReferrerPortalDashboard authentication', function () {
    test('rejects login with the wrong password', function () {
        $referrer = makeLoginTestReferrer('correct-password');

        $dashboard = new ReferrerPortalDashboard;
        $dashboard->mount();
        $dashboard->lookupCode = $referrer->referral_code;
        $dashboard->password = 'wrong-password';
        $dashboard->authenticateReferrer();

        expect($dashboard->isAuthenticated)->toBeFalse()
            ->and($dashboard->loginError)->not->toBeNull();
    });

    test('accepts login with the correct password', function () {
        $referrer = makeLoginTestReferrer('correct-password');

        $dashboard = new ReferrerPortalDashboard;
        $dashboard->mount();
        $dashboard->lookupCode = $referrer->referral_code;
        $dashboard->password = 'correct-password';
        $dashboard->authenticateReferrer();

        expect($dashboard->isAuthenticated)->toBeTrue()
            ->and($dashboard->referrer?->id)->toBe($referrer->id);
    });

    test('locks out after repeated failed attempts from the same IP', function () {
        $referrer = makeLoginTestReferrer('correct-password');

        for ($i = 0; $i < 5; $i++) {
            $dashboard = new ReferrerPortalDashboard;
            $dashboard->mount();
            $dashboard->lookupCode = $referrer->referral_code;
            $dashboard->password = 'wrong-password';
            $dashboard->authenticateReferrer();
        }

        // Even the correct password is now rejected until the lockout window expires.
        $dashboard = new ReferrerPortalDashboard;
        $dashboard->mount();
        $dashboard->lookupCode = $referrer->referral_code;
        $dashboard->password = 'correct-password';
        $dashboard->authenticateReferrer();

        expect($dashboard->isAuthenticated)->toBeFalse()
            ->and($dashboard->loginError)->toContain('Too many failed login attempts');
    });

    test('a bare ?referrer= code pre-fills the form but does not authenticate', function () {
        $referrer = makeLoginTestReferrer('correct-password');

        $dashboard = new ReferrerPortalDashboard;
        $dashboard->mount($referrer->referral_code);

        expect($dashboard->isAuthenticated)->toBeFalse()
            ->and($dashboard->lookupCode)->toBe($referrer->referral_code);
    });
});
