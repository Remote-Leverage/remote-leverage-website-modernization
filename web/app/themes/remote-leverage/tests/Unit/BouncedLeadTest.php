<?php

declare(strict_types=1);

use App\Domains\Lead\Actions\RecordBouncedLeadAction;
use App\Domains\Lead\Models\BouncedLead;
use App\Domains\Lead\Services\EmailValidationService;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as Capsule;

/*
 * The record of a submission the email check refused.
 *
 * These rows are the only trace a refused visitor leaves — they never reach
 * capturePartialLead(), so they are on no other screen. The mapping is tested rather than the
 * write: this suite runs without a database, and payloadFor() is pure for exactly that reason.
 */

describe('bounced lead payload', function () {
    test('a valid verdict records nothing', function () {
        $verdict = ['valid' => true, 'reason' => null, 'message' => null, 'checked_by' => 'zerobounce'];

        expect((new RecordBouncedLeadAction)->execute('someone@example.com', $verdict))->toBeNull();
    });

    test('it keeps the step-one contact details, so a refused buyer can be called back', function () {
        $payload = RecordBouncedLeadAction::payloadFor(
            '  Someone@Example.COM ',
            ['valid' => false, 'reason' => 'zerobounce_invalid', 'message' => 'nope', 'checked_by' => 'zerobounce'],
            ['name' => 'Ada Lovelace', 'phone' => '+1 555 0100', 'company' => 'Analytical Engines', 'ip_address' => '203.0.113.9'],
        );

        expect($payload['email'])->toBe('someone@example.com')   // trimmed and lowercased
            ->and($payload['name'])->toBe('Ada Lovelace')
            ->and($payload['phone'])->toBe('+1 555 0100')
            ->and($payload['company'])->toBe('Analytical Engines')
            ->and($payload['ip_address'])->toBe('203.0.113.9')
            ->and($payload['reason'])->toBe('zerobounce_invalid')
            ->and($payload['checked_by'])->toBe('zerobounce')
            ->and($payload['attempts'])->toBe(1);
    });

    test('unrecognised context is kept whole rather than dropped', function () {
        $payload = RecordBouncedLeadAction::payloadFor(
            'someone@example.com',
            ['valid' => false, 'reason' => 'blocked_domain', 'message' => 'nope', 'checked_by' => 'domain_validator'],
            ['utm_source' => 'google', 'role_needed' => 'Executive Assistant', 'monthly_revenue' => '50k'],
        );

        // Promoted columns stay out of the JSON blob; everything else survives in it.
        expect($payload['utm_source'])->toBe('google')
            ->and($payload['context'])->toBe(['role_needed' => 'Executive Assistant', 'monthly_revenue' => '50k']);
    });

    test('a row with no address is not worth keeping', function () {
        $verdict = ['valid' => false, 'reason' => 'malformed', 'message' => 'nope', 'checked_by' => 'format'];

        expect(RecordBouncedLeadAction::payloadFor('   ', $verdict))->toBeNull();
    });

    test('long values are truncated to their column width', function () {
        $payload = RecordBouncedLeadAction::payloadFor(
            'someone@example.com',
            ['valid' => false, 'reason' => 'malformed', 'message' => 'nope', 'checked_by' => 'format'],
            ['phone' => str_repeat('9', 80), 'name' => str_repeat('a', 400)],
        );

        expect(strlen($payload['phone']))->toBe(32)
            ->and(strlen($payload['name']))->toBe(255);
    });
});

describe('bounced lead labels', function () {
    test('every reason the validator can emit reads as English', function () {
        $reasons = array_merge(
            array_keys(BouncedLead::REASON_LABELS),
            array_map(
                static fn (string $status) => 'zerobounce_'.$status,
                EmailValidationService::TOGGLEABLE_STATUSES,
            ),
        );

        foreach ($reasons as $reason) {
            $label = (new BouncedLead(['reason' => $reason]))->reasonLabel();

            expect($label)->not->toBe('')
                ->and($label)->not->toContain('_');   // no raw slug leaking into the table
        }
    });

    test('a ZeroBounce status nobody has labelled still reads sensibly', function () {
        // The point of deriving these: adding a status to the blockable list must not require
        // touching the model, and must never render a blank cell.
        expect((new BouncedLead(['reason' => 'zerobounce_greylisted']))->reasonLabel())
            ->toBe('ZeroBounce: greylisted');
    });

    test('every gate the validator reports has a label', function () {
        foreach (['format', 'blacklist', 'domain_validator', 'zerobounce'] as $gate) {
            expect(BouncedLead::GATE_LABELS)->toHaveKey($gate);
        }
    });
});

describe('recording a bounce', function () {
    beforeEach(function () {
        BouncedLead::query()->delete();
    });

    $refusal = static fn (string $reason = 'zerobounce_invalid', string $gate = 'zerobounce') => [
        'valid' => false, 'reason' => $reason, 'message' => 'Please use a valid business email address.', 'checked_by' => $gate,
    ];

    test('a refused submission is recorded with its reason', function () use ($refusal) {
        $row = (new RecordBouncedLeadAction)->execute('refused@example.com', $refusal(), ['name' => 'Ada', 'phone' => '555']);

        expect($row)->not->toBeNull()
            ->and($row->email)->toBe('refused@example.com')
            ->and($row->name)->toBe('Ada')
            ->and($row->reason)->toBe('zerobounce_invalid')
            ->and($row->attempts)->toBe(1)
            ->and(BouncedLead::count())->toBe(1);
    });

    test('retyping the same address does not invent a second turned-away person', function () use ($refusal) {
        $action = new RecordBouncedLeadAction;

        $first = $action->execute('refused@example.com', $refusal());
        $second = $action->execute('refused@example.com', $refusal());
        $third = $action->execute('refused@example.com', $refusal());

        expect(BouncedLead::count())->toBe(1)
            ->and($second->id)->toBe($first->id)
            ->and($third->attempts)->toBe(3);
    });

    test('a retry fills in details the first attempt did not carry', function () use ($refusal) {
        $action = new RecordBouncedLeadAction;

        $action->execute('refused@example.com', $refusal(), ['name' => 'Ada']);
        $row = $action->execute('refused@example.com', $refusal(), ['name' => 'Ada', 'phone' => '+1 555 0100']);

        expect($row->phone)->toBe('+1 555 0100')
            ->and($row->name)->toBe('Ada');
    });

    test('a different gate is a separate row, because it is a different diagnosis', function () use ($refusal) {
        $action = new RecordBouncedLeadAction;

        $action->execute('refused@example.com', $refusal());
        $action->execute('refused@example.com', $refusal('blocked_domain', 'domain_validator'));

        expect(BouncedLead::count())->toBe(2);
    });

    test('an attempt outside the collapse window starts a new row', function () use ($refusal) {
        $action = new RecordBouncedLeadAction;

        $first = $action->execute('refused@example.com', $refusal());
        $first->forceFill([
            'last_seen_at' => Carbon::now()->subMinutes(RecordBouncedLeadAction::COLLAPSE_WINDOW_MINUTES + 5),
        ])->save();

        $action->execute('refused@example.com', $refusal());

        expect(BouncedLead::count())->toBe(2);
    });

    test('a write failure never reaches the visitor', function () use ($refusal) {
        // Somebody already told their address was refused must not then meet a 500 on top of
        // it. Same fail-open reasoning as the validator: the row is the nice-to-have here.
        //
        // Renamed rather than dropped, and restored in the finally, because the schema is built
        // once for the whole suite — a dropped table here would fail whichever test runs next.
        Capsule::schema()->rename('rl_bounced_leads', 'rl_bounced_leads_hidden');

        try {
            expect(fn () => (new RecordBouncedLeadAction)->execute('refused@example.com', $refusal()))
                ->not->toThrow(Throwable::class);
        } finally {
            Capsule::schema()->rename('rl_bounced_leads_hidden', 'rl_bounced_leads');
        }
    });
});

describe('ad attribution on a bounced submission', function () {
    /*
     * Hyller's review catch: the first cut of this recorded no Meta or Google identifiers at
     * all, so a refused *paid* click was indistinguishable from a refused organic one, and the
     * question "which campaign is buying leads we then turn away" had no answer.
     */
    beforeEach(function () {
        BouncedLead::query()->delete();
        unset($_COOKIE['_fbc'], $_COOKIE['fbc']);
    });

    afterEach(function () {
        unset($_COOKIE['_fbc'], $_COOKIE['fbc']);
    });

    $refused = ['valid' => false, 'reason' => 'zerobounce_invalid', 'message' => 'x', 'checked_by' => 'zerobounce'];

    test('click identifiers are promoted to columns, not buried in the JSON blob', function () use ($refused) {
        $payload = RecordBouncedLeadAction::payloadFor('refused@example.com', $refused, [
            'gclid' => 'g-123', 'fbclid' => 'f-456', 'msclkid' => 'm-789', 'landing_url' => 'https://example.com/lp',
        ]);

        expect($payload['gclid'])->toBe('g-123')
            ->and($payload['fbclid'])->toBe('f-456')
            ->and($payload['msclkid'])->toBe('m-789')
            ->and($payload['landing_url'])->toBe('https://example.com/lp')
            ->and($payload['context'])->toBeNull();   // nothing left over to hide in
    });

    test('_fbp survives in the blob, because Meta matches on it and it has no column', function () use ($refused) {
        $payload = RecordBouncedLeadAction::payloadFor('refused@example.com', $refused, [
            'attribution_named' => ['fbc' => null],
            'attribution' => ['_fbp' => 'fb.1.1699.999', 'wbraid' => 'w-1', 'fbc_synthetic' => true],
        ]);

        // fbc_synthetic is dropped from the blob — it is a column now, and two copies drift.
        expect($payload['context']['attribution'])->toBe(['_fbp' => 'fb.1.1699.999', 'wbraid' => 'w-1']);
    });

    test('the live cookie wins over the wizard\'s mount-time synthetic fbc', function () use ($refused) {
        // The whole point of routing through FbcResolver rather than reading $context['fbc']:
        // by the time somebody is refused at step one, Meta's real cookie has landed.
        $_COOKIE['_fbc'] = 'fb.1.1700000000000.f-456';

        $payload = RecordBouncedLeadAction::payloadFor('refused@example.com', $refused, [
            'fbclid' => 'f-456',
            'attribution_named' => ['fbc' => 'fb.1.1699999999999.f-456'],
            'attribution' => ['fbc_synthetic' => true],
        ]);

        expect($payload['fbc'])->toBe('fb.1.1700000000000.f-456')
            ->and($payload['fbc_synthetic'])->toBeFalse();
    });

    test('a synthetic fbc is recorded as synthetic, never as Meta\'s own', function () use ($refused) {
        $payload = RecordBouncedLeadAction::payloadFor('refused@example.com', $refused, [
            'fbclid' => 'f-456',
            'attribution_named' => ['fbc' => 'fb.1.1699999999999.f-456'],
            'attribution' => ['fbc_synthetic' => true],
        ]);

        expect($payload['fbc'])->toBe('fb.1.1699999999999.f-456')
            ->and($payload['fbc_synthetic'])->toBeTrue();
    });

    test('a stray cookie is not attached to a submission that collected no attribution', function () use ($refused) {
        $_COOKIE['_fbc'] = 'fb.1.1700000000000.someoneelse';

        $payload = RecordBouncedLeadAction::payloadFor('refused@example.com', $refused, ['name' => 'Ada']);

        expect($payload['fbc'])->toBeNull()
            ->and($payload['fbc_synthetic'])->toBeNull();
    });

    test('the identifiers survive the round trip to the database', function () use ($refused) {
        $_COOKIE['_fbc'] = 'fb.1.1700000000000.f-456';

        (new RecordBouncedLeadAction)->execute('refused@example.com', $refused, [
            'gclid' => 'g-123',
            'fbclid' => 'f-456',
            'attribution_named' => ['fbc' => 'fb.1.1699999999999.f-456'],
            'attribution' => ['fbc_synthetic' => true],
        ]);

        $row = BouncedLead::query()->where('email', 'refused@example.com')->first();

        expect($row->gclid)->toBe('g-123')
            ->and($row->fbc)->toBe('fb.1.1700000000000.f-456')
            ->and($row->fbc_synthetic)->toBeFalse();
    });
});
