<?php

declare(strict_types=1);

/**
 * Guards the `resources/images/pages/**` -> `public/images/**` build pipeline.
 *
 * `public/images/` is gitignored and regenerated from scratch by `themeImages()`
 * (vite/theme-images.js) in the Docker assets stage. An image that only ever existed under
 * `public/` therefore vanishes on deploy — and the failure is invisible locally, because the
 * file is still sitting on the developer's disk. That is exactly how the 2026-09-15 audit found
 * 604 untracked images serving fine in dev and 404ing on staging.
 *
 * Static analysis of the call sites cannot catch this: almost every filename reaches
 * `BlockDefaults::pageImg()` as a variable out of a config array, not as a literal. So instead
 * this asserts the inverse, which is exact: nothing may exist in `public/images/` that the
 * build did not put there.
 */
$theme = dirname(__DIR__, 2);

$relativeFiles = static function (string $dir): array {
    if (! is_dir($dir)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getFilename() !== '.gitkeep') {
            $files[] = str_replace($dir.'/', '', $file->getPathname());
        }
    }

    sort($files);

    return $files;
};

it('tracks page art as source in resources/images/pages', function () use ($relativeFiles, $theme) {
    expect($relativeFiles($theme.'/resources/images/pages'))
        ->not->toBeEmpty('resources/images/pages is empty — page art is not tracked in git');
});

it('builds every file in public/images from a tracked source', function () use ($relativeFiles, $theme) {
    $built = $relativeFiles($theme.'/public/images');

    if ($built === []) {
        $this->markTestSkipped('public/images is empty — run `npm run build`');
    }

    // Each source yields itself, and each raster additionally yields a sibling .webp.
    $expected = [];

    foreach ($relativeFiles($theme.'/resources/images/pages') as $source) {
        $expected[$source] = true;

        if (preg_match('/\.(png|jpe?g)$/i', $source) === 1) {
            $expected[preg_replace('/\.[^.]+$/', '.webp', $source)] = true;
        }
    }

    $orphans = array_values(array_diff($built, array_keys($expected)));

    expect($orphans)->toBe([], "These files are in public/images but no build step produces them, so they are lost on the next deploy.\nMove each one to resources/images/pages/ and run `npm run build`:\n  ".implode("\n  ", $orphans));
});

it('produces an output for every tracked source', function () use ($relativeFiles, $theme) {
    $built = $relativeFiles($theme.'/public/images');

    if ($built === []) {
        $this->markTestSkipped('public/images is empty — run `npm run build`');
    }

    $missing = array_values(array_diff($relativeFiles($theme.'/resources/images/pages'), $built));

    expect($missing)->toBe([], "These sources are tracked but produced no output — the build did not handle their file type:\n  ".implode("\n  ", $missing));
});

it('keeps page art out of public/ in the repository', function () use ($theme) {
    // A file committed under public/images/ would be silently overwritten by the build output,
    // so the two copies could drift without anyone noticing.
    $tracked = [];
    exec('git -C '.escapeshellarg($theme).' ls-files public/images 2>/dev/null', $tracked);

    expect($tracked)->toBe([], 'public/images is build output and must not be committed; move these to resources/images/pages/.');
});
