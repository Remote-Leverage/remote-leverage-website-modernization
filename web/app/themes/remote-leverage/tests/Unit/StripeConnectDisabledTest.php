<?php

declare(strict_types=1);

use App\Domains\Referral\Models\Payout;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Services\StripeConnectGateway;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Referrer payouts via Stripe Connect are shelved (2026-09-16).
 *
 * The switch is a config default rather than a deleted code path, which is exactly the kind of
 * thing that comes back on by accident — a stray `STRIPE_CONNECT_ENABLED` in one environment's
 * task definition is enough, and the failure mode is real money moving. These tests assert the
 * default is off and, more importantly, that "off" means **no outbound call is attempted**
 * rather than a call that happens to fail.
 */
beforeEach(function () {
    // The Http facade caches its Factory for the life of the process, and fake() binds the
    // factory into the container too — so without a hard swap every test inherits the previous
    // test's stub closures and the first catch-all registered wins for the whole file.
    Facade::clearResolvedInstance(HttpFactory::class);
    Http::swap(new HttpFactory);

    $GLOBALS['_app_config'] = [
        'services' => [
            'stripe' => [
                'secret' => 'sk_test_should_never_be_used',
                'client_id' => 'ca_test',
                // Deliberately unset: this is what config/services.php resolves to when
                // STRIPE_CONNECT_ENABLED is absent from the environment.
            ],
        ],
    ];
});

it('is disabled when the environment does not opt in', function () {
    expect((new StripeConnectGateway)->enabled())->toBeFalse();
});

it('creates no Stripe account and no onboarding link while disabled', function () {
    Http::fake();

    $referrer = Referrer::query()->create([
        'name' => 'Shelved Payouts',
        'email' => 'shelved-'.uniqid().'@agency.com',
        'referral_code' => 'shelved-'.uniqid(),
        'status' => 'active',
    ]);

    $link = (new StripeConnectGateway)->createOnboardingLink(
        $referrer,
        'https://remoteleverage.com/return',
        'https://remoteleverage.com/refresh',
    );

    expect($link)->toBeNull();
    Http::assertNothingSent();

    // Nothing was created upstream, so nothing may be recorded locally either — a stored
    // account id would make the referrer look onboarded the next time this is switched on.
    expect($referrer->fresh()->stripe_account_id)->toBeNull();
});

it('transfers nothing while disabled', function () {
    Http::fake();

    $referrer = Referrer::query()->create([
        'name' => 'Shelved Transfer',
        'email' => 'transfer-'.uniqid().'@agency.com',
        'referral_code' => 'transfer-'.uniqid(),
        'status' => 'active',
        'stripe_account_id' => 'acct_'.Str::random(16),
    ]);

    $payout = Payout::query()->create([
        'referrer_id' => $referrer->id,
        'amount' => 250.00,
        'currency' => 'USD',
        'status' => 'pending',
    ]);

    expect((new StripeConnectGateway)->transferPayout($payout))->toBeNull();
    Http::assertNothingSent();

    // Still pending, and with no transfer id: the payout must not read as completed.
    $payout->refresh();
    expect($payout->status)->toBe('pending')
        ->and($payout->stripe_transfer_id)->toBeNull();
});

it('only reaches Stripe when explicitly switched on', function () {
    // The counterpart assertion: proves the tests above are measuring the flag rather than a
    // missing credential or a gateway that never calls out at all.
    config(['services.stripe.connect_enabled' => true]);

    Http::fake(function ($request) {
        return str_contains($request->url(), '/account_links')
            ? Http::response(['url' => 'https://connect.stripe.com/setup/x'], 200)
            : Http::response(['id' => 'acct_live'], 200);
    });

    $referrer = Referrer::query()->create([
        'name' => 'Enabled Path',
        'email' => 'enabled-'.uniqid().'@agency.com',
        'referral_code' => 'enabled-'.uniqid(),
        'status' => 'active',
    ]);

    $link = (new StripeConnectGateway)->createOnboardingLink(
        $referrer,
        'https://remoteleverage.com/return',
        'https://remoteleverage.com/refresh',
    );

    expect($link)->toBe('https://connect.stripe.com/setup/x');
});
