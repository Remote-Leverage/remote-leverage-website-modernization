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
            $m = new ReflectionMethod(BlockDefaults::class, 'isUsableFile');
            $m->setAccessible(true);

            return $m->invoke(null, $path);
        };

        expect($isUsable($empty))->toBeFalse('a zero-byte file must read as absent');
        expect($isUsable($real))->toBeTrue('a file with bytes must read as present');
        expect($isUsable($this->dir.'/missing.webp'))->toBeFalse('a missing file must read as absent');
    });

    test('the directory-searching resolvers all use the predicate', function () {
        // Precise rather than a count of is_file() calls across the file: preferWebp() and
        // generateWebp() legitimately probe a conversion they are about to make or replace, and
        // already handle the empty case themselves. The invariant that matters is narrower —
        // a resolver that walks a list of candidate directories and returns the first hit must
        // not accept an empty file, or one bad upload shadows every fallback behind it.
        //
        // Reflection gives exact line ranges; bounding a method body by scanning for the next
        // `function` keyword quietly swallowed the predicate's own is_file() and reported a
        // failure that was not there.
        $lines = file(dirname(__DIR__, 2).'/app/Support/BlockDefaults.php');

        // video() joined the list on 2026-09-17: it searches uploads before the theme for the
        // same reason homeImg() does, so a zero-byte upload would shadow the built copy there
        // too — and an empty MP4 fails as a dead player, which reads as "has not started yet".
        foreach (['homeImg', 'themeImg', 'resolveImageUrl', 'video'] as $method) {
            $r = new ReflectionMethod(BlockDefaults::class, $method);
            $body = implode('', array_slice(
                $lines,
                $r->getStartLine() - 1,
                $r->getEndLine() - $r->getStartLine() + 1
            ));

            expect($body)->not->toMatch(
                '/(?<!isUsable)\bis_file\(/',
                "BlockDefaults::{$method}() probes the filesystem with a bare is_file(). Use "
                    .'self::isUsableFile() so a zero-byte file falls through to the next candidate.'
            );
        }
    });
});
