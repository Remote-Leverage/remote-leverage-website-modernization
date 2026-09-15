<?php

declare(strict_types=1);

/*
 * Livewire components used to be registered twice — once namespaced
 * (`booking.multistep-booking-wizard`) and once bare (`multistep-booking-wizard`).
 * With no canonical name, a grep for usage found only half the call sites. The bare
 * aliases were dropped on 2026-09-15; these guards stop them coming back and stop a
 * template referencing a name that is not registered.
 */

/** @return list<string> Every component name LivewireServiceProvider registers. */
function rlRegisteredLivewireComponents(): array
{
    $provider = file_get_contents(
        dirname(__DIR__, 2).'/app/Infrastructure/Providers/LivewireServiceProvider.php'
    );

    preg_match_all("/Livewire::component\(\s*'([^']+)'/", $provider, $matches);

    return $matches[1];
}

test('every Livewire component is registered under exactly one, namespaced name', function () {
    $names = rlRegisteredLivewireComponents();

    expect($names)->not->toBeEmpty()
        ->and($names)->toEqual(array_unique($names));

    foreach ($names as $name) {
        // A bare name has no dot. Namespaced names are canonical.
        expect($name)->toContain('.');
    }
});

test('every <livewire:…> tag in the theme resolves to a registered component name', function () {
    $theme = dirname(__DIR__, 2);
    $registered = rlRegisteredLivewireComponents();

    $files = array_merge(
        glob($theme.'/resources/views/**/*.blade.php') ?: [],
        glob($theme.'/resources/views/*.blade.php') ?: [],
        glob($theme.'/resources/views/**/**/*.blade.php') ?: [],
        glob($theme.'/patterns/*.php') ?: [],
        glob($theme.'/resources/patterns/*.php') ?: [],
    );

    $used = [];

    foreach ($files as $file) {
        preg_match_all('/<livewire:([A-Za-z0-9._-]+)/', (string) file_get_contents($file), $matches);

        foreach ($matches[1] as $name) {
            $used[$name][] = str_replace($theme.'/', '', $file);
        }
    }

    expect($used)->not->toBeEmpty();

    // Reported as a name => files map so a failure names the offending template.
    $unregistered = [];

    foreach ($used as $name => $where) {
        if (! in_array($name, $registered, true)) {
            $unregistered[$name] = array_values(array_unique($where));
        }
    }

    expect($unregistered)->toBe([]);
});
