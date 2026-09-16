<?php

declare(strict_types=1);

use App\Support\BlockDefaults;

/**
 * Covers the zero-byte asset shadowing fixed on 2026-09-16.
 *
 * Staging's EFS uploads have repeatedly carried a zero-byte `home/Frame-1092.webp`. It answers
 * 200 with `content-length: 0`, so the roles-grid "Lead Generation" card rendered alt text on
 * both `/` and `/hire-va-4/` while a perfectly good copy sat in the theme at
 * `public/images/hire-va-4/Frame-1092.webp` and served fine.
 *
 * The resolvers searched uploads before the theme and asked only `is_file()`, so the empty file
 * won every time. Nothing else catches this:
 *
 *   · a 200 response looks healthy to any link checker;
 *   · `is_file()` is true, so an existence audit reports the asset present;
 *   · ThemeImageSourcesTest guards the build pipeline, not what uploads shadows at runtime;
 *   · and a screenshot shows a blank area, which is indistinguishable from a white card —
 *     exactly the trap CLAUDE.md warns about under "sanity-check the geometry".
 *
 * So the rule is asserted directly against the bytes: a candidate with no content is not a
 * candidate, and the search falls through to one that has some.
 */
describe('zero-byte files never shadow a working asset', function () {
    beforeEach(function () {
        $this->dir = sys_get_temp_dir().'/rl-zero-byte-'.bin2hex(random_bytes(4));
        mkdir($this->dir, 0777, true);
    });

    afterEach(function () {
        foreach (glob($this->dir.'/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->dir);
    });

    test('an empty file is not usable, a file with bytes is', function () {
        $empty = $this->dir.'/empty.webp';
        $real = $this->dir.'/real.webp';
        file_put_contents($empty, '');
        file_put_contents($real, 'RIFF....WEBP');

        // The predicate the three resolvers share. Reflection rather than a public method:
        // this is an internal detail of how a path is judged, not part of the class's surface.
        $isUsable = function (string $path): bool {
            $m = new ReflectionMethod(BlockDefaults::class, 'isUsableImage');
            $m->setAccessible(true);

            return $m->invoke(null, $path);
        };

        expect($isUsable($empty))->toBeFalse('a zero-byte file must read as absent');
        expect($isUsable($real))->toBeTrue('a file with bytes must read as present');
        expect($isUsable($this->dir.'/missing.webp'))->toBeFalse('a missing file must read as absent');
    });

    test('every resolver that probes the filesystem uses the predicate', function () {
        // Guards against a fourth resolver being added later with a bare is_file() probe,
        // which is how this bug reached staging in the first place: preferWebp() had the
        // zero-byte guard all along and the other three simply never got it.
        $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/BlockDefaults.php');

        preg_match_all('/^\s*(?:if \()?.*\bis_file\(/m', $source, $matches);

        $bare = array_values(array_filter(
            $matches[0],
            fn (string $line) => ! str_contains($line, 'isUsableImage')
        ));

        // preferWebp() keeps its own two probes: it checks a conversion it is about to make or
        // replace, and deletes an empty one rather than falling through to another directory.
        expect(count($bare))->toBeLessThanOrEqual(
            3,
            "New is_file() probe(s) found in BlockDefaults without the zero-byte guard:\n  "
                .implode("\n  ", array_map('trim', $bare))
                ."\nUse self::isUsableImage() so an empty file falls through to the next candidate."
        );
    });
});
