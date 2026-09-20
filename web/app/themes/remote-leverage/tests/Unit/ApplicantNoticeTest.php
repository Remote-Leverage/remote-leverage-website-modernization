<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;
use App\Domains\Lead\Models\Lead;
use Illuminate\Support\Str;

/**
 * Who sees the "looking for VA work?" interstitial.
 *
 * The point of these tests is that this component decides nothing. LeadAudience already answers
 * "possible VA" for the badge, the filter and the export on the leads dashboard, and the widget
 * asks it rather than scoring the visitor a second way — a second definition would drift, and
 * the two would disagree about the same person on the same screen.
 */
function noticeLead(array $overrides = []): Lead
{
    return Lead::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'name' => 'Susan Ornstein',
        'email' => 'susan@example.com',
        'phone' => '+13125550142',
        'phone_country' => 'US',
        'status' => 'captured',
    ], $overrides));
}

function wizardFor(Lead $lead): MultistepBookingWizard
{
    $wizard = new MultistepBookingWizard;
    $wizard->leadId = $lead->id;

    return $wizard;
}

beforeEach(function () {
    Lead::truncate();
    config(['booking' => require __DIR__.'/../../config/booking.php']);
});

describe('the VA applicant notice', function () {
    test('a phone country outside US/CA shows it', function () {
        // The case that started this: a Colombian number on the booking footer form.
        $lead = noticeLead(['phone' => '+573001234567', 'phone_country' => 'CO']);

        expect(wizardFor($lead)->applicantNotice())
            ->not->toBeNull()
            ->and(wizardFor($lead)->applicantNotice()['jobs_url'])
            ->toBe('https://remoteleveragejobs.com');
    });

    test('a US number does not', function () {
        expect(wizardFor(noticeLead())->applicantNotice())->toBeNull();
    });

    test('a referred lead is a client however they dial in', function () {
        /*
         * LeadAudience's override, inherited rather than restated. A partner sending business
         * from overseas is the opposite of a job applicant, and telling them to go apply for a
         * VA role is the worst version of getting this wrong.
         */
        $lead = noticeLead([
            'phone' => '+573001234567',
            'phone_country' => 'CO',
            'referral_code' => 'PARTNER-42',
        ]);

        expect(wizardFor($lead)->applicantNotice())->toBeNull();
    });

    test('no captured lead means no notice', function () {
        // capturePartialLead() skips on an incomplete step 1 and swallows its own failures.
        // With nothing to ask about, the notice stays down rather than guessing.
        $wizard = new MultistepBookingWizard;

        expect($wizard->applicantNotice())->toBeNull();
    });

    test('it can be switched off without touching code', function () {
        $lead = noticeLead(['phone' => '+573001234567', 'phone_country' => 'CO']);

        config(['booking.applicant_notice.enabled' => false]);

        expect(wizardFor($lead)->applicantNotice())->toBeNull();
    });
});
