<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;
use Illuminate\Validation\ValidationException;

/**
 * Guards the two form defaults reported by stakeholder QA on 2026-09-15
 * (transfer-list row 1, the isolated-form variants).
 *
 * Neither was cosmetic. The revenue bracket drives Calendly routing via
 * isUnder10kMrr() and filters job applicants via isJobSeeker(), so a
 * pre-selected value silently misclassified every lead that never opened the
 * control. The consent box carried a hardcoded `checked` attribute, no binding
 * to any property, and a `required` attribute that never fired because the
 * wizard submits through wire:click rather than a native <form> — so it was
 * pre-ticked, unenforced, and never reached the server.
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

    test('step one rejects unticked consent', function () use ($arrange) {
        $wizard = $arrange(new MultistepBookingWizard);
        $wizard->mount('test', 'glass');
        $wizard->monthlyRevenue = '$10k to $50k Per Month';
        $wizard->consent = false;

        expect(fn () => $wizard->goToStep(2))->toThrow(ValidationException::class);
    });

    test('step one passes once both are answered', function () use ($arrange) {
        $wizard = $arrange(new MultistepBookingWizard);
        $wizard->mount('test', 'glass');
        $wizard->monthlyRevenue = '$10k to $50k Per Month';
        $wizard->consent = true;

        $wizard->goToStep(2);

        expect($wizard->currentStep)->toBe(2);
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
});
