<?php

declare(strict_types=1);

use App\Domains\Lead\Services\FbpResolver;

/*
 * The `_fbp` rules — the same forced-live-update treatment FbcResolverTest covers for `fbc`.
 *
 * The wizard freezes attribution in mount(), before Meta's pixel JS necessarily runs, so its
 * copy of `_fbp` can be missing even when the browser gets the real cookie a moment later. This
 * resolver re-reads it live on every persist so the real value is not stuck missing for the rest
 * of the session.
 */

beforeEach(function () {
    unset($_COOKIE['_fbp']);
});

afterEach(function () {
    unset($_COOKIE['_fbp']);
});

test('a caller that collected no attribution gets nothing, not the ambient cookie', function () {
    // A referral typed in by hand or a gated download must not be attributed to whoever
    // happens to be in that browser — a sales rep on the phone, say.
    $_COOKIE['_fbp'] = 'fb.1.1700000000000.someoneelse';

    expect((new FbpResolver)->resolve([], []))->toBeNull();
});

test('a live cookie beats the mount-time blob value', function () {
    $_COOKIE['_fbp'] = 'fb.1.1700000000000.abc123';

    $resolved = (new FbpResolver)->resolve(
        ['utm_source' => 'facebook'],
        ['handl' => ['_fbp' => 'fb.1.1699999999999.stale']],
    );

    expect($resolved)->toBe('fb.1.1700000000000.abc123');
});

test('falls back to the freshly-collected blob value when there is no live cookie', function () {
    $resolved = (new FbpResolver)->resolve(
        ['utm_source' => 'facebook'],
        ['handl' => ['_fbp' => 'fb.1.1699999999999.fromblob']],
    );

    expect($resolved)->toBe('fb.1.1699999999999.fromblob');
});

test('falls back to the stored value when this request has neither', function () {
    $resolved = (new FbpResolver)->resolve(
        ['utm_source' => 'facebook'],
        [],
        storedFbp: 'fb.1.1600000000000.stored',
    );

    expect($resolved)->toBe('fb.1.1600000000000.stored');
});

test('a submission with some attribution but nothing fbp-shaped resolves to null', function () {
    $resolved = (new FbpResolver)->resolve(['utm_source' => 'facebook'], []);

    expect($resolved)->toBeNull();
});

test('liveCookie reads _fbp straight off the superglobal', function () {
    $_COOKIE['_fbp'] = 'fb.1.1700000000000.abc123';

    expect((new FbpResolver)->liveCookie())->toBe('fb.1.1700000000000.abc123');
});

test('liveCookie is null when the cookie is absent', function () {
    expect((new FbpResolver)->liveCookie())->toBeNull();
});
