<?php

declare(strict_types=1);

test('homepage layout defers Livewire scripts and does not preload a missing Map.webp', function () {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/app.blade.php');

    expect($layout)
        ->toContain('id="rl-livewire-scripts"')
        ->toContain('@livewireScripts')
        ->not->toContain('Map.webp')
        ->not->toContain('Map.png')
        ->not->toContain('fonts.googleapis.com')
        ->toContain('PageChrome::usesHireVaHero()')
        ->toContain('rel="preload" as="image"')
        ->toContain("\$hireVaLcp['mobile']")
        ->not->toContain('(max-width: 1023px)')
        ->not->toContain('(min-width: 1024px)');
});

/*
 * This assertion used to be `->not->toContain('rel="preload" as="font"')`, alongside the
 * Map.webp and Google Fonts ones. Its intent was "do not preload a remote or unused asset"
 * (it was added in 24d73ca, which removed a preload for a Map.webp that no longer existed),
 * not "never preload a font" — self-hosted subsetted faces were deliberately preloaded on
 * 2026-09-15 to remove the swap-reflow, and the blanket assertion then read as a prohibition
 * on the fix. Replaced with the narrower guards below, which pin what actually matters.
 */
test('font preloads stay self-hosted, manifest-resolved, and limited to the two faces every page uses', function () {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/app.blade.php');

    preg_match_all('/<link rel="preload" as="font".*?>/s', $layout, $matches);
    $preloads = $matches[0];

    // Preloading the whole face set would push ~125KB of never-parsed bytes onto the critical
    // path of every visit — a worse regression than the swap-reflow the preload removes. The
    // reasoning for exactly these two is in the comment above them in the layout.
    expect($preloads)->toHaveCount(
        2,
        'Expected exactly two font preloads. Adding one means proving the face is requested '
        .'above the fold on the pages measured in the layout comment; removing one reintroduces '
        .'a fallback paint and reflow on every cold visit.'
    );

    foreach ($preloads as $tag) {
        expect($tag)
            // A font preload without crossorigin is fetched twice — once for the preload and
            // once for the real request — which makes it a pure regression.
            ->toContain('crossorigin')
            ->toContain('type="font/woff2"')
            // Content-hashed build output: a hardcoded hash would 404 while still looking
            // correct in the markup, which is the failure mode worth guarding.
            ->toContain('Vite::asset(')
            ->not->toContain('http://')
            ->not->toContain('https://');
    }

    expect($preloads[0])
        ->toContain('inter-display-latin.woff2')
        ->toContain('fetchpriority="high"');
    expect($preloads[1])
        ->toContain('inter-latin-wght-normal.woff2')
        ->not->toContain('fetchpriority');

    // Homepage LCP is the Inter Display <h1>. A preload after `@php(wp_head())` loses
    // the network race to Meta and the Google tag, which is the 1.2 s FCP / 5.4 s LCP
    // gap a 2026-09-22 PSI mobile run measured. Match the Blade call, not the bare
    // `wp_head()` substring — that also appears in the comments that explain why
    // these tags have to sit above it.
    expect(strpos($layout, 'rel="preload" as="font"'))
        ->toBeLessThan(strpos($layout, '@php(wp_head())'));
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
