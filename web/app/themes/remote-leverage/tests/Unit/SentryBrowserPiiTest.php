<?php

declare(strict_types=1);

/*
 * `config/sentry.php`'s `send_default_pii` (SENTRY_SEND_DEFAULT_PII) only ever configured the PHP
 * SDK. The browser SDK's own `sendDefaultPii` option is a separate flag that `Sentry.init()` in
 * app.js never set, so flipping the env var on a task definition had zero effect client-side.
 *
 * `app.blade.php` now mirrors the PHP config into `window.SENTRY_SEND_DEFAULT_PII`, the same way it
 * already does for `window.SENTRY_DSN` and `window.APP_VERSION`, and app.js forwards that into
 * `Sentry.init()`. These tests pin both halves of that wire so one side cannot regress unnoticed
 * while the other still looks correct.
 *
 * This is independent of `window.SENTRY_USER`'s `ip_address: '{{auto}}'` sentinel — Relay resolves
 * that regardless of `sendDefaultPii` — so it is not a fix for "the browser IP isn't tracked" on
 * its own, only for the PHP/JS parity gap.
 */
test('the layout mirrors the PHP send_default_pii flag into window, gated on the DSN being set', function () {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/app.blade.php');

    // Must live inside the same `@if (config('sentry.dsn'))` block as SENTRY_DSN/SENTRY_USER —
    // exposing it unconditionally would print `window.SENTRY_SEND_DEFAULT_PII` even when Sentry
    // never initialises at all.
    preg_match(
        "/@if \(config\('sentry\.dsn'\)\)(.*?)@endif/s",
        $layout,
        $matches,
    );

    expect($matches[1] ?? null)
        ->not->toBeNull()
        ->toContain("window.SENTRY_SEND_DEFAULT_PII = @js((bool) config('sentry.send_default_pii'));")
        ->toContain('window.SENTRY_DSN')
        ->toContain('window.SENTRY_USER');
});

test('app.js forwards window.SENTRY_SEND_DEFAULT_PII into Sentry.init()', function () {
    $js = file_get_contents(dirname(__DIR__, 2).'/resources/js/app.js');

    expect($js)->toContain('sendDefaultPii: window.SENTRY_SEND_DEFAULT_PII === true');

    // Strict equality against the boolean literal, not a truthiness check — window properties are
    // `undefined` on any page where the DSN is unset (see gating test above), and `undefined ===
    // true` is false, which is the safe default. A bare truthy check would also pass for the
    // string `"false"`, which `@js()` never emits but a hand-edited value could.
    expect($js)->not->toContain('sendDefaultPii: window.SENTRY_SEND_DEFAULT_PII,')
        ->not->toContain('sendDefaultPii: !!window.SENTRY_SEND_DEFAULT_PII');

    // Must be inside the same Sentry.init({...}) call as the other options, not set on some other
    // object or after the call returns, where it would have no effect.
    $initStart = strpos($js, 'Sentry.init({');
    $piiPosition = strpos($js, 'sendDefaultPii: window.SENTRY_SEND_DEFAULT_PII === true');
    $initEnd = strpos($js, '});', $initStart);

    expect($initStart)->not->toBeFalse()
        ->and($piiPosition)->toBeGreaterThan($initStart)
        ->and($piiPosition)->toBeLessThan($initEnd);
});
