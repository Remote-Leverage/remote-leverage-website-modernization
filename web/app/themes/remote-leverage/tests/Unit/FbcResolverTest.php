<?php

declare(strict_types=1);

use App\Domains\Lead\Services\FbcResolver;

/*
 * The `fbc` rules, extracted from CaptureLeadAction when a second write path needed them.
 *
 * The property that matters: a value the Conversions API will treat as Meta's own click record
 * must never be something this codebase invented. The wizard freezes attribution in mount(),
 * before Meta's pixel JS runs, so its copy is routinely a synthetic stand-in — and a refused
 * visitor is recorded from exactly that frozen array.
 */

beforeEach(function () {
    unset($_COOKIE['_fbc'], $_COOKIE['fbc']);
});

afterEach(function () {
    unset($_COOKIE['_fbc'], $_COOKIE['fbc']);
});

test('a caller that collected no attribution gets nothing, not the ambient cookie', function () {
    // A referral typed in by hand or a gated download must not be attributed to whoever
    // happens to be in that browser — a sales rep on the phone, say.
    $_COOKIE['_fbc'] = 'fb.1.1700000000000.someoneelse';

    expect((new FbcResolver)->resolve([], [], null))->toBeNull();
});

test('a live cookie beats the mount-time synthetic value', function () {
    $_COOKIE['_fbc'] = 'fb.1.1700000000000.abc123';

    $resolved = (new FbcResolver)->resolve(
        ['fbc' => 'fb.1.1699999999999.abc123'],   // synthetic, built at mount
        ['fbc_synthetic' => true],
        'abc123',
    );

    expect($resolved['value'])->toBe('fb.1.1700000000000.abc123')
        ->and($resolved['synthetic'])->toBeFalse();
});

test('a cookie from a different click is not evidence about this lead', function () {
    // A stale _fbc from an older ad click in the same browser. Falls through to the
    // mount-time value, which does agree with the click being attributed.
    $_COOKIE['_fbc'] = 'fb.1.1700000000000.otherclick';

    $resolved = (new FbcResolver)->resolve(
        ['fbc' => 'fb.1.1699999999999.abc123'],
        ['fbc_synthetic' => true],
        'abc123',
    );

    expect($resolved['value'])->toBe('fb.1.1699999999999.abc123')
        ->and($resolved['synthetic'])->toBeTrue();
});

test('no fbclid to compare against is not a mismatch', function () {
    // _fbc outlives the landing page for someone who browses before converting.
    $_COOKIE['_fbc'] = 'fb.1.1700000000000.abc123';

    expect((new FbcResolver)->resolve(['fbc' => null], [], null)['value'])
        ->toBe('fb.1.1700000000000.abc123');
});

test('a confirmed real value already stored is never replaced', function () {
    $_COOKIE['_fbc'] = 'fb.1.1700000000000.abc123';

    $resolved = (new FbcResolver)->resolve(
        ['fbc' => 'fb.1.1699999999999.abc123'],
        [],
        'abc123',
        storedFbc: 'fb.1.1600000000000.abc123',
        storedIsSynthetic: false,
    );

    expect($resolved)->toBeNull();
});

test('a stored synthetic value is still upgradeable', function () {
    $_COOKIE['_fbc'] = 'fb.1.1700000000000.abc123';

    $resolved = (new FbcResolver)->resolve(
        ['fbc' => 'fb.1.1699999999999.abc123'],
        [],
        'abc123',
        storedFbc: 'fb.1.1699999999999.abc123',
        storedIsSynthetic: true,
    );

    expect($resolved['value'])->toBe('fb.1.1700000000000.abc123')
        ->and($resolved['synthetic'])->toBeFalse();
});

test('matchesClick reads the click id out of Meta\'s format', function () {
    $resolver = new FbcResolver;

    expect($resolver->matchesClick('fb.1.1700000000000.abc123', 'abc123'))->toBeTrue()
        ->and($resolver->matchesClick('fb.1.1700000000000.abc123', 'different'))->toBeFalse()
        ->and($resolver->matchesClick('not-meta-shaped', 'abc123'))->toBeFalse()
        ->and($resolver->matchesClick('fb.1.1700000000000.abc123', null))->toBeTrue();
});
