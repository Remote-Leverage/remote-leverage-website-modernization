<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;
use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Referral\Services\AttributionEngine;
use Illuminate\Validation\ValidationException;

/**
 * Guards the two form defaults reported by stakeholder QA on 2026-09-15
 * (transfer-list row 1, the isolated-form variants).
 *
 * Neither was cosmetic. The revenue bracket drives Calendly routing via
 * isUnder10kMrr() and filters job applicants via isJobSeeker(), so a
 * pre-selected value silently misclassified every lead that never opened the
 * control — it is therefore required.
 *
 * Consent is the opposite: recorded, never blocking. It previously carried a
 * hardcoded `checked` attribute and no binding to any property, so it was
 * pre-ticked and never reached the server. It now starts unticked and is
 * persisted as `consent_at`, but an unticked box must never stop a booking.
 */
describe('Booking form defaults express a real choice', function () {
    $arrange = function (MultistepBookingWizard $wizard): MultistepBookingWizard {
        $wizard->email = 'founder@acme.com';
        $wizard->firstName = 'Alice';
        $wizard->lastName = 'Smith';
        $wizard->phone = '+1 305 555 0199';

        return $wizard;
    };

    test('no revenue bracket is pre-selected', function () {
        expect((new MultistepBookingWizard)->monthlyRevenue)->toBe('');
    });

    test('consent starts unticked', function () {
        expect((new MultistepBookingWizard)->consent)->toBeFalse();
    });

    test('step one rejects an untouched revenue control', function () use ($arrange) {
        $wizard = $arrange(new MultistepBookingWizard);
        $wizard->mount('test', 'glass');
        $wizard->consent = true;

        expect(fn () => $wizard->goToStep(2))->toThrow(ValidationException::class);
    });

    test('unticked consent never blocks the booking', function () use ($arrange) {
        $wizard = $arrange(new MultistepBookingWizard);
        $wizard->mount('test', 'glass');
        $wizard->monthlyRevenue = '$10k to $50k Per Month';
        $wizard->consent = false;

        $wizard->goToStep(2);

        expect($wizard->currentStep)->toBe(2);
    });

    test('consent reaches the capture payload as given', function () {
        expect(LeadCaptureData::fromArray(['email' => 'a@b.com', 'name' => 'A B'])->consent)->toBeFalse()
            ->and(LeadCaptureData::fromArray(['email' => 'a@b.com', 'name' => 'A B', 'consent' => true])->consent)->toBeTrue();
    });

    test('the consent checkbox is not advertised as required', function () {
        $blade = file_get_contents(
            __DIR__.'/../../resources/views/livewire/booking/multistep-booking-wizard.blade.php'
        );

        foreach (explode('name="consent"', $blade) as $i => $chunk) {
            if ($i === 0) {
                continue;
            }

            expect(substr($chunk, 0, (int) strpos($chunk, '>')))->not->toMatch('/\brequired\b/');
        }
    });

    test('the Alpine sub-step gate does not hold shut on unticked consent', function () {
        $js = file_get_contents(__DIR__.'/../../resources/js/app.js');

        expect($js)->not->toContain('return this.consentChecked === true;');
    });

    test('neither consent checkbox ships a hardcoded checked attribute', function () {
        $blade = file_get_contents(
            __DIR__.'/../../resources/views/livewire/booking/multistep-booking-wizard.blade.php'
        );

        // Both skins (glass, and default/naked) render exactly one consent box.
        expect(substr_count($blade, 'name="consent"'))->toBe(2);

        foreach (explode('name="consent"', $blade) as $i => $chunk) {
            if ($i === 0) {
                continue;
            }

            $input = substr($chunk, 0, (int) strpos($chunk, '>'));

            expect($input)->not->toMatch('/\bchecked\b/')
                ->and($input)->toContain('wire:model.live="consent"');
        }
    });

    test('the Alpine isolated-wizard mirror also starts unticked', function () {
        $js = file_get_contents(__DIR__.'/../../resources/js/app.js');

        expect($js)->toContain('consentChecked: false,')
            ->and($js)->not->toContain('consentChecked: true,');
    });

    test('consent is stamped once and never cleared by a later submission', function () {
        Lead::truncate();

        $action = new CaptureLeadAction(
            new AttributionEngine,
            new PhoneValidationService,
            new LeadActivityLogger
        );

        $base = [
            'name' => 'Sarah Jenkins',
            'email' => 'sarah@growthco.io',
            'monthly_revenue' => '$10k to $50k Per Month',
        ];

        // Step 1, consent given.
        $lead = $action->execute(LeadCaptureData::fromArray($base + ['consent' => true]));
        expect($lead->consent_at)->not->toBeNull();

        $stampedAt = $lead->consent_at;

        // A later submission that omits the tick must not erase it.
        $again = $action->execute(LeadCaptureData::fromArray(
            $base + ['consent' => false, 'extra_data' => ['lead_id' => $lead->id]]
        ));

        expect($again->id)->toBe($lead->id)
            ->and($again->consent_at?->timestamp)->toBe($stampedAt->timestamp);
    });

    test('a lead who never ticks the box carries no consent record', function () {
        Lead::truncate();

        $action = new CaptureLeadAction(
            new AttributionEngine,
            new PhoneValidationService,
            new LeadActivityLogger
        );

        $lead = $action->execute(LeadCaptureData::fromArray([
            'name' => 'Marcus Aurelius',
            'email' => 'marcus@rome.org',
            'monthly_revenue' => '$10k to $50k Per Month',
        ]));

        expect($lead->consent_at)->toBeNull();
    });
});
