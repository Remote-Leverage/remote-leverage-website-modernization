<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;

/**
 * Phone was presented as required and enforced by nothing.
 *
 * The label carries a red asterisk and aria-required="true", but the input has no native
 * `required` attribute, the wizard has no <form> for the browser to validate against, the
 * isolated sub-step gate returned true for phone unconditionally, and the server rule read
 * `nullable`. On 2026-09-21 a lead reached `booked` having never given a number.
 */
describe('the phone field', function () {
    test('is required in the step 1 rules', function () {
        $rules = (fn () => $this->validationRules)->call(new MultistepBookingWizard);

        expect($rules[1]['phone'])->toContain('required')
            ->and($rules[1]['phone'])->not->toContain('nullable');
    });

    test('every asterisked label has a matching server rule', function () {
        $blade = file_get_contents(__DIR__.'/../../resources/views/livewire/booking/multistep-booking-wizard.blade.php');
        $rules = (fn () => $this->validationRules)->call(new MultistepBookingWizard);
        $step1 = $rules[1];

        // The desktop step-1 labels that carry the red asterisk, and the property each one writes.
        $asterisked = [
            'monthlyRevenue' => 'current monthly revenue? <span class="text-[#EF4444]">*</span>',
            'firstName' => 'Name <span class="text-[#EF4444]">*</span>',
            'email' => 'Business Email <span class="text-[#EF4444]">*</span>',
            'phone' => 'Phone <span class="text-[#EF4444]">*</span>',
        ];

        foreach ($asterisked as $property => $label) {
            expect($blade)->toContain($label);
            expect($step1)->toHaveKey($property);
            expect($step1[$property])->toContain('required');
        }
    });

    test('the required message accounts for a value that never reached the server', function () {
        $messages = (fn () => $this->validationMessages)->call(new MultistepBookingWizard);

        expect($messages)->toHaveKey('phone.required')
            ->and($messages['phone.required'])->toContain('automatically');
    });

    test('the client gate reads the input rather than trusting the wire property', function () {
        $js = file_get_contents(__DIR__.'/../../resources/js/app.js');

        expect($js)
            ->toContain("document.getElementById('booking-phone-input')")
            ->not->toContain('// Always true. The phone input sits inside');
    });
});
