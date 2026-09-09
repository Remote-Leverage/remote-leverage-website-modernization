<?php

declare(strict_types=1);

use App\Domains\Referral\Actions\TrackReferralClickAction;
use App\Domains\Referral\Models\ReferralClick;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Services\AttributionEngine;

function makeAttributionTestReferrer(array $overrides = []): Referrer
{
    return Referrer::query()->create(array_merge([
        'name' => 'Attribution Test Referrer',
        'email' => 'attribution-'.uniqid().'@venture.com',
        'referral_code' => 'attr-'.uniqid(),
        'status' => 'active',
    ], $overrides));
}

describe('AttributionEngine::findReferrerByReferralCode', function () {
    test('resolves a Referrer by referral code', function () {
        $referrer = makeAttributionTestReferrer();
        $engine = new AttributionEngine;

        $found = $engine->findReferrerByReferralCode($referrer->referral_code);

        expect($found)->not->toBeNull()
            ->and($found->id)->toBe($referrer->id);
    });

    test('returns null for an unknown referral code', function () {
        $engine = new AttributionEngine;

        expect($engine->findReferrerByReferralCode('no-such-code-'.uniqid()))->toBeNull();
    });
});

describe('TrackReferralClickAction Referrer attribution', function () {
    test('records a click against the matching Referrer', function () {
        $referrer = makeAttributionTestReferrer();
        $action = new TrackReferralClickAction(new AttributionEngine);

        $click = $action->execute($referrer->referral_code, [
            'ip_address' => '203.0.113.10',
            'landing_page' => '/book-consultation',
        ]);

        expect($click)->not->toBeNull()
            ->and($click->referrer_id)->toBe($referrer->id)
            ->and($click->referrer_user_id)->toBeNull();
    });

    test('deduplicates clicks from the same IP within 24 hours', function () {
        $referrer = makeAttributionTestReferrer();
        $action = new TrackReferralClickAction(new AttributionEngine);

        $first = $action->execute($referrer->referral_code, ['ip_address' => '203.0.113.20']);
        $second = $action->execute($referrer->referral_code, ['ip_address' => '203.0.113.20']);

        expect($first)->not->toBeNull()
            ->and($second)->toBeNull()
            ->and(ReferralClick::where('referrer_id', $referrer->id)->where('ip_address', '203.0.113.20')->count())->toBe(1);
    });

    test('records separately for different IPs', function () {
        $referrer = makeAttributionTestReferrer();
        $action = new TrackReferralClickAction(new AttributionEngine);

        $first = $action->execute($referrer->referral_code, ['ip_address' => '203.0.113.30']);
        $second = $action->execute($referrer->referral_code, ['ip_address' => '203.0.113.31']);

        expect($first)->not->toBeNull()
            ->and($second)->not->toBeNull();
    });

    test('returns null for a slug matching neither a Referrer nor a legacy referrer', function () {
        $action = new TrackReferralClickAction(new AttributionEngine);

        expect($action->execute('unknown-slug-'.uniqid(), ['ip_address' => '203.0.113.40']))->toBeNull();
    });
});
