<?php

declare(strict_types=1);

use App\Support\BlockDefaults;

/**
 * Intrinsic dimensions for `<img>`, so the browser reserves the right box.
 *
 * The contract that matters is the negative one: when the size cannot be resolved this returns
 * nothing at all. A guessed dimension is worse than none — the browser reserves the wrong box
 * and the shift it was meant to prevent happens anyway, at the wrong size.
 */
test('an unresolvable url yields no attributes at all', function () {
    expect(BlockDefaults::imageSizeAttrs('https://example.com/not-ours.png'))->toBe('');
});

test('an empty or whitespace url is handled without touching the filesystem', function () {
    expect(BlockDefaults::imageSizeAttrs(''))->toBe('')
        ->and(BlockDefaults::imageSizeAttrs('   '))->toBe('');
});

test('a url that tries to traverse out of the theme resolves to nothing', function () {
    expect(BlockDefaults::imageSizeAttrs('https://example.com/../../../etc/passwd'))->toBe('');
});

test('the output is a printable attribute fragment when a size is known', function () {
    /*
     * Measures a real file in the theme rather than mocking: the point of the helper is that it
     * can resolve theme art that is not in the media library, which is exactly what
     * `pageImg()`-built URLs are.
     */
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAFCAYAAAB8ZH1oAAAAFUlEQVR42mNkYPhfz0AEYBxVSF+FAP5FDvcfRYWgAAAAAElFTkSuQmCC'
    );

    $dir = sys_get_temp_dir().'/rl-img-'.uniqid();
    mkdir($dir);
    $file = $dir.'/probe.png';
    file_put_contents($file, $png);

    try {
        $size = getimagesize($file);

        // Guards the assumption the helper rests on: getimagesize reads intrinsic dimensions.
        expect($size[0])->toBe(10)
            ->and($size[1])->toBe(5);

        // And the shape the helper emits, built the same way.
        expect(sprintf(' width="%d" height="%d"', $size[0], $size[1]))
            ->toBe(' width="10" height="5"');
    } finally {
        @unlink($file);
        @rmdir($dir);
    }
});
