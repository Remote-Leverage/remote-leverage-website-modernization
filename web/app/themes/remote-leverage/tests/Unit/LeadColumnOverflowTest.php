<?php

declare(strict_types=1);

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Referral\Services\AttributionEngine;

/**
 * Guards the lead-loss bug of 2026-09-17.
 *
 * Facebook's `fbclid` outgrew `varchar(150)` — a real click arrived at 212 characters. Because
 * MySQL runs with `STRICT_TRANS_TABLES` that is SQLSTATE[22001], not a truncation, so the
 * INSERT aborted and no lead row was created at all. `submitBooking()` caught it and showed
 * "An error occurred processing your consultation"; `capturePartialLead()` caught the same
 * thing one step earlier and only wrote a `Log::warning`. Net effect: every visitor arriving
 * from paid social was dropped, with nothing in the dashboard to show they had ever existed.
 *
 * The migration widens the columns. This covers the other half — that an even longer value
 * still costs at most the tail of a click id, never the customer.
 */
test('an oversized click id is trimmed rather than allowed to abort the insert', function () {
    Lead::truncate();

    $action = new CaptureLeadAction(
        new AttributionEngine,
        new PhoneValidationService,
        new LeadActivityLogger,
    );

    // Longer than the widened column, so this exercises the clamp and not the new width.
    $absurdFbclid = 'IwY2xjawA'.str_repeat('a', 900);

    $lead = $action->execute(LeadCaptureData::fromArray([
        'name' => 'Overflow Probe',
        'email' => 'overflow-probe@example.com',
        'fbclid' => $absurdFbclid,
        'attribution_named' => ['fbc' => 'fb.1.1785350683256.'.$absurdFbclid],
    ]));

    expect($lead->exists)->toBeTrue()
        ->and(mb_strlen((string) $lead->fbclid))->toBeLessThanOrEqual(512)
        ->and($lead->fbclid)->toStartWith('IwY2xjawA');
});

/*
 * The value that actually broke it, at the width that actually broke it. If someone narrows
 * `fbclid` again this fails on the real-world case rather than on a synthetic one.
 */
test('the 212-character fbclid that broke production is stored whole', function () {
    Lead::truncate();

    $action = new CaptureLeadAction(
        new AttributionEngine,
        new PhoneValidationService,
        new LeadActivityLogger,
    );

    $realWorldFbclid = 'PAdGRleATXKWNwZG9mAmZkaWQWULfk8xmg3OwPKs'.str_repeat('x', 172);
    expect(mb_strlen($realWorldFbclid))->toBe(212);

    $lead = $action->execute(LeadCaptureData::fromArray([
        'name' => 'Paid Social Visitor',
        'email' => 'paid-social@example.com',
        'fbclid' => $realWorldFbclid,
    ]));

    expect($lead->fbclid)->toBe($realWorldFbclid);
});
