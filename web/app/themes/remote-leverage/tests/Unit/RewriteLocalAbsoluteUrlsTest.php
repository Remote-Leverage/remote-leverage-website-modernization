<?php

declare(strict_types=1);

use App\Support\BlockDefaults;

test('rewrites local Herd and Docker hosts to the current home URL', function () {
    $home = 'https://staging.remoteleverage.com';

    expect(BlockDefaults::rewriteLocalAbsoluteUrls(
        "url('http://remoteleverage-v2.test/app/themes/remote-leverage/public/images/home/Map.webp')",
        $home,
    ))->toBe("url('https://staging.remoteleverage.com/app/themes/remote-leverage/public/images/home/Map.webp')");

    expect(BlockDefaults::rewriteLocalAbsoluteUrls(
        'http://127.0.0.1:8080/app/uploads/2026/09/photo.png',
        $home,
    ))->toBe('https://staging.remoteleverage.com/app/uploads/2026/09/photo.png');
});

test('upgrades http to https on the current public host', function () {
    expect(BlockDefaults::rewriteLocalAbsoluteUrls(
        'http://staging.remoteleverage.com/app/uploads/x.webp',
        'https://staging.remoteleverage.com',
    ))->toBe('https://staging.remoteleverage.com/app/uploads/x.webp');
});

test('leaves local http home URLs unchanged when developing without TLS', function () {
    $html = 'http://remoteleverage-v2.test/app/uploads/x.webp';

    expect(BlockDefaults::rewriteLocalAbsoluteUrls($html, 'http://remoteleverage-v2.test'))
        ->toBe($html);
});

test('adds role=img so aria-label is valid on the saved star-rating div', function () {
    $html = '<div style="display:flex;gap:4px;color:#9F53E7;margin-bottom:1rem;" aria-label="5 out of 5 stars">stars</div>';

    expect(BlockDefaults::repairStarRatingAria($html))
        ->toBe('<div style="display:flex;gap:4px;color:#9F53E7;margin-bottom:1rem;" role="img" aria-label="5 out of 5 stars">stars</div>');
});

test('does not duplicate role=img when the star-rating markup is already valid', function () {
    $html = '<div style="display:flex;gap:4px;color:#9F53E7;margin-bottom:1rem;" role="img" aria-label="5 out of 5 stars">stars</div>';

    expect(BlockDefaults::repairStarRatingAria($html))->toBe($html);
});
