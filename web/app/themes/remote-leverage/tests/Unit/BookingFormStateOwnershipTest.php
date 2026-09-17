<?php

declare(strict_types=1);

/**
 * Guards the fix for the step-1 field wipe of 2026-09-17.
 *
 * Two separate mistakes combined to lose whatever the visitor was typing whenever a Livewire
 * round trip landed:
 *
 *  1. The wizard's `x-data` seeded Alpine from live server state. Livewire re-renders that
 *     attribute on every response, and Alpine treats a changed `x-data` as a new component —
 *     so it destroyed the scope and rebuilt it from values the server captured *before* the
 *     visitor carried on typing.
 *  2. Inputs carried both `wire:model` and a shadow `x-model`. Livewire's own state kept the
 *     typed value; the rebuilt shadow copy did not, and it was the copy that owned the DOM.
 *
 * Either alone is latent. Together they wiped the name field, reproducibly, whenever someone
 * picked a revenue band (the one `.live` field, and slow because it calls Calendly) and kept
 * typing. These are string assertions on the source for the same reason as the neighbouring
 * suites: the failure is in markup, and a browser test for it needs a real round trip.
 */
$blade = __DIR__.'/../../resources/views/livewire/booking/multistep-booking-wizard.blade.php';

describe('booking wizard state ownership', function () use ($blade) {
    test('no element carries both wire:model and x-model', function () use ($blade) {
        $markup = file_get_contents($blade);

        // Each tag as one string, so "both on the same element" is actually what we test.
        preg_match_all('/<(input|select|textarea)\b[^>]*>/si', $markup, $matches);

        $conflicted = array_values(array_filter(
            $matches[0],
            static fn (string $tag) => str_contains($tag, 'wire:model') && str_contains($tag, 'x-model')
        ));

        expect($conflicted)->toBe([]);
    });

    test('the isolated-wizard x-data seeds no field values', function () use ($blade) {
        $markup = file_get_contents($blade);

        preg_match('/x-data="rlBookingWizardIsolated\((.*?)\)"/s', $markup, $m);

        expect($m)->not->toBeEmpty('the isolated wizard x-data attribute should still exist');

        // Anything mutable here re-seeds Alpine from a stale snapshot on every render.
        foreach (['email', 'firstName', 'lastName', 'phone', 'monthlyRevenue', 'consent'] as $field) {
            expect($m[1])->not->toContain($field);
        }
    });

    test('the Alpine reveal reads Livewire state rather than copies of it', function () {
        $js = file_get_contents(__DIR__.'/../../resources/js/app.js');

        foreach (['emailVal:', 'firstNameVal:', 'lastNameVal:', 'monthlyRevenueVal:'] as $shadow) {
            expect($js)->not->toContain($shadow);
        }

        expect($js)->toContain("wireString('email')")
            ->and($js)->toContain("wireString('firstName')")
            ->and($js)->toContain("wireString('monthlyRevenue')");
    });

    /*
     * The step change shrinks the card (step 1 is ~720px, the calendar ~306px) without moving
     * the scroll position, which on a phone leaves the form above the viewport and the footer
     * filling the screen.
     */
    test('the wizard scrolls itself back into view on a step change', function () use ($blade) {
        $markup = file_get_contents($blade);
        $js = file_get_contents(__DIR__.'/../../resources/js/app.js');

        expect($markup)->toContain('x-data="rlBookingStepScroll()"')
            ->and($js)->toContain('rlBookingStepScroll')
            ->and($js)->toContain("\$watch('\$wire.currentStep'");
    });
});
