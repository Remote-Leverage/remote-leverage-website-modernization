<?php

declare(strict_types=1);

/**
 * How `MARKETING_COST_ALERT_ENABLED` resolves, which is the difference between an hourly alert
 * and a day of firing it by hand.
 *
 * The flag wins outright over the environment list, and `AlertWindow::enabledHere()` reading false
 * does not skip a run — MarketingServiceProvider unschedules the WP-Cron event and only re-adds it
 * when the flag reads true. So one boot with a wrong value removes the job permanently and silently:
 * `execute()` is never called again, every log line inside it is never written, and the cron list
 * has no entry to suggest anything is missing.
 *
 * The entry read `env(...) !== null`, and an empty string is not null. An ECS task definition
 * mapping a Secrets Manager key that happens to be blank produced `filter_var('', BOOL)` — false —
 * an explicit "off" nobody had asked for.
 */
function resolveEnabled(): ?bool
{
    $raw = env('MARKETING_COST_ALERT_ENABLED');

    if ($raw === null || (is_string($raw) && trim($raw) === '')) {
        return null;
    }

    return filter_var($raw, FILTER_VALIDATE_BOOL);
}

function withEnabledEnv(?string $value, callable $assert): void
{
    if ($value === null) {
        putenv('MARKETING_COST_ALERT_ENABLED');
        unset($_ENV['MARKETING_COST_ALERT_ENABLED']);
    } else {
        putenv("MARKETING_COST_ALERT_ENABLED={$value}");
        $_ENV['MARKETING_COST_ALERT_ENABLED'] = $value;
    }

    try {
        $assert(resolveEnabled());
    } finally {
        putenv('MARKETING_COST_ALERT_ENABLED');
        unset($_ENV['MARKETING_COST_ALERT_ENABLED']);
    }
}

test('a blank value defers to the environment list rather than switching the alert off', function () {
    // The 2026-09-21 case: a variable mapped with no value behind it.
    withEnabledEnv('', fn (?bool $r) => expect($r)->toBeNull());
    withEnabledEnv('   ', fn (?bool $r) => expect($r)->toBeNull());
});

test('an unset variable defers, which is the normal state', function () {
    withEnabledEnv(null, fn (?bool $r) => expect($r)->toBeNull());
});

test('a deliberate off switch still switches it off', function () {
    /*
     * The trap in the obvious fix. `env()` casts, so `=false` comes back as the boolean false and
     * `(string) false` is `''` — a blank test written without `is_string` treats the deliberate
     * off switch as unset and re-enables the alert somewhere it was switched off on purpose.
     */
    withEnabledEnv('false', fn (?bool $r) => expect($r)->toBeFalse());
    withEnabledEnv('0', fn (?bool $r) => expect($r)->toBeFalse());
});

test('a deliberate on switch still switches it on', function () {
    withEnabledEnv('true', fn (?bool $r) => expect($r)->toBeTrue());
    withEnabledEnv('1', fn (?bool $r) => expect($r)->toBeTrue());
});
