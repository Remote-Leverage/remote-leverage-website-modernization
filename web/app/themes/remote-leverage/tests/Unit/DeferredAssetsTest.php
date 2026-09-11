<?php

declare(strict_types=1);

test('homepage layout defers Livewire scripts and does not preload a missing Map.webp', function () {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/app.blade.php');

    expect($layout)
        ->toContain('id="rl-livewire-scripts"')
        ->toContain('@livewireScripts')
        ->not->toContain('Map.webp')
        ->not->toContain('Map.png')
        ->not->toContain('rel="preload" as="font"')
        ->not->toContain('fonts.googleapis.com');
});

test('app.css does not eagerly fetch intl-tel-input flag sprites', function () {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

    expect($css)
        ->not->toContain('@import "intl-tel-input')
        ->not->toContain('cdn.jsdelivr.net/npm/intl-tel-input');
});

test('app.js boots Livewire on intersection and lazy-loads intl-tel-input', function () {
    $js = file_get_contents(dirname(__DIR__, 2).'/resources/js/app.js');

    expect($js)
        ->toContain('function bootLivewire')
        ->toContain('function scheduleLivewire')
        ->toContain("import('intl-tel-input/intlTelInputWithUtils')")
        ->toContain("import('intl-tel-input/build/css/intlTelInput.css')")
        ->toContain('whenVisible(this.$el')
        ->toContain('script.async = false');
});

test('header and nav walkers work without Alpine so Livewire can stay deferred', function () {
    $theme = dirname(__DIR__, 2);
    $header = file_get_contents($theme.'/resources/views/sections/header.blade.php');
    $nav = file_get_contents($theme.'/app/View/NavWalker.php');
    $mobile = file_get_contents($theme.'/app/View/MobileNavWalker.php');
    $vite = file_get_contents($theme.'/vite.config.js');

    expect($header)
        ->toContain('data-rl-nav-toggle')
        ->toContain('id="rl-mobile-nav"')
        ->toContain('rl-desktop-nav')
        ->not->toContain('x-data')
        ->not->toContain('x-show');

    expect($nav)
        ->not->toContain('x-data')
        ->not->toContain('x-show')
        ->toContain("'group'");

    expect($mobile)
        ->toContain('<details')
        ->toContain('<summary')
        ->not->toContain('x-data');

    expect($vite)->toContain('modulePreload: false');
});
