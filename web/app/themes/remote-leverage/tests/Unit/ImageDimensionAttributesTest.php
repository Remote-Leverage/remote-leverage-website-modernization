<?php

declare(strict_types=1);

/**
 * Guards `<img>` `width`/`height` attributes against the file they actually point at.
 *
 * The 2026-09-15 header overflow was this bug: `resources/images/logo.svg` is 154x18
 * (8.556:1) while three templates declared `width="168" height="28"` (6:1) or nothing at
 * all. `h-6 w-auto` sizes from the *intrinsic* ratio, so the declared pair never controlled
 * the rendered width — it only governed the box reserved before the asset loaded. A wrong
 * ratio is therefore two defects at once:
 *
 *   1. the pre-load reservation is the wrong shape, which is a silent CLS source; and
 *   2. any layout that budgets from the declared numbers (as the mobile header's flex row
 *      did) is budgeting against a width the element will never have.
 *
 * Both are invisible in review and invisible in a screenshot taken after load. Only a
 * comparison against the bytes on disk catches them, which is what this does.
 *
 * ## The rule
 *
 * A declared pair must be *proportional* to the file, not necessarily equal to it.
 * Declaring `36x36` for an 88x88 avatar is correct and common — the reservation is the right
 * shape, just pre-scaled. Declaring `340x340` for a 262x317 globe is not. Proportionality is
 * checked by integer cross-multiplication (`declW * trueH === declH * trueW`) so there is no
 * float tolerance to tune and no rounding slop to argue about.
 *
 * ## What is in scope
 *
 * Only tags whose `src` contains a quoted filename that resolves to exactly one known set of
 * dimensions under `resources/images/`. That is deliberately conservative — an unresolvable
 * or ambiguous `src` is skipped, never failed:
 *
 *   - ACF fields, repeater rows, `wp_get_attachment_image()`, post thumbnails and other
 *     database-resident images have no dimensions at render time and are correctly ignored.
 *   - So is art that lives only in `web/app/uploads/` (`BlockDefaults::homeImg()` prefers
 *     uploads over the theme). Those files are not in git and do not exist in CI, so this
 *     test cannot see them — `globe.webp` is the known example. Check those by hand.
 *
 * If a filename matches several files that all share one size (`rl-26-logo.svg` sits in three
 * page folders, byte-identical in each), that is still unambiguous and is checked.
 */
$theme = dirname(__DIR__, 2);

const RL_IMG_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'avif'];

/**
 * Intrinsic pixel dimensions of an image file, or null if they cannot be read.
 *
 * @return array{0: int, 1: int}|null
 */
$dimensions = static function (string $path): ?array {
    if (! is_file($path)) {
        return null;
    }

    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg') {
        // Only the root element matters, and it is always at the top of the file.
        $head = (string) file_get_contents($path, false, null, 0, 4096);

        if (preg_match('/<svg\b[^>]*>/i', $head, $tag) !== 1) {
            return null;
        }

        $w = preg_match('/\bwidth\s*=\s*"([\d.]+)(?:px)?"/i', $tag[0], $m) === 1 ? (float) $m[1] : null;
        $h = preg_match('/\bheight\s*=\s*"([\d.]+)(?:px)?"/i', $tag[0], $m) === 1 ? (float) $m[1] : null;

        // An SVG with no width/height (or with percentage units, which the patterns above
        // deliberately do not match) takes its aspect ratio from the viewBox instead.
        if (($w === null || $h === null) && preg_match('/\bviewBox\s*=\s*"\s*[\d.eE+-]+[,\s]+[\d.eE+-]+[,\s]+([\d.eE+-]+)[,\s]+([\d.eE+-]+)\s*"/i', $tag[0], $m) === 1) {
            $w = (float) $m[1];
            $h = (float) $m[2];
        }

        return ($w > 0 && $h > 0) ? [(int) round($w), (int) round($h)] : null;
    }

    $size = @getimagesize($path);

    return ($size && $size[0] > 0 && $size[1] > 0) ? [(int) $size[0], (int) $size[1]] : null;
};

/** Every image file under resources/images/, indexed by its path relative to that root. */
$imageIndex = static function (string $theme) use ($dimensions): array {
    $root = $theme.'/resources/images';

    if (! is_dir($root)) {
        return [];
    }

    $index = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        if (! in_array(strtolower($file->getExtension()), RL_IMG_EXTENSIONS, true)) {
            continue;
        }

        $relative = str_replace($root.'/', '', $file->getPathname());
        $size = $dimensions($file->getPathname());

        if ($size !== null) {
            $index[$relative] = $size;
        }
    }

    return $index;
};

/** Theme files that can emit an `<img>`. */
$sourceFiles = static function (string $theme): array {
    $files = [];

    foreach (['resources/views', 'app'] as $dir) {
        if (! is_dir($theme.'/'.$dir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme.'/'.$dir, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[] = $file->getPathname();
            }
        }
    }

    $files = array_merge(
        $files,
        glob($theme.'/patterns/*.php') ?: [],
        glob($theme.'/resources/patterns/*.php') ?: [],
    );

    sort($files);

    return $files;
};

/**
 * Every `<img>` tag in a source file, with its raw src/width/height text and 1-based line.
 *
 * @return array<int, array{line: int, tag: string, src: string, width: ?string, height: ?string}>
 */
$imgTags = static function (string $source): array {
    // Blank out comments so prose examples in docblocks are not mistaken for real markup.
    // Replacing with spaces of equal length keeps every byte offset (and so every line
    // number) exactly where it was.
    $blank = static fn (array $m): string => preg_replace('/[^\n]/', ' ', $m[0]);

    $source = preg_replace_callback('/\{\{--.*?--\}\}/s', $blank, $source) ?? $source;
    $source = preg_replace_callback('/<!--.*?-->/s', $blank, $source) ?? $source;
    $source = preg_replace_callback('#/\*.*?\*/#s', $blank, $source) ?? $source;
    $source = preg_replace_callback('#^\s*(?://|\*)[^\n]*#m', $blank, $source) ?? $source;

    /*
     * A regex cannot delimit these tags. A src written as a PHP short echo closes with a `>`
     * *inside* the attribute value, so the obvious `<img\b[^>]*>` stops at that `>`: it loses
     * the width/height that follow and, because the truncation also eats the closing quote,
     * drops the tag silently rather than failing loudly. (That bug hid every PHP-echo img in
     * patterns/ on the first run of this file.) Scan instead, stepping over PHP and Blade spans.
     *
     * Note this comment is a block comment on purpose — a `?` immediately followed by a `>` in
     * a `//` comment ends PHP mode.
     */
    $length = strlen($source);

    /** Index just past the span opening at $i, or $i if nothing opens there. */
    $skipSpan = static function (int $i) use ($source, $length): int {
        foreach (['<?' => '?>', '{{' => '}}', '{!!' => '!!}'] as $open => $close) {
            if (substr($source, $i, strlen($open)) !== $open) {
                continue;
            }

            $end = strpos($source, $close, $i + strlen($open));

            return $end === false ? $length : $end + strlen($close);
        }

        return $i;
    };

    $tags = [];
    $offset = 0;

    while (($start = stripos($source, '<img', $offset)) !== false) {
        $offset = $start + 4;

        // `<image`, `<imgfoo` — not an img tag.
        if ($start + 4 < $length && preg_match('/[a-z0-9_-]/i', $source[$start + 4]) === 1) {
            continue;
        }

        $i = $start + 4;
        $end = null;

        while ($i < $length) {
            $skipped = $skipSpan($i);

            if ($skipped !== $i) {
                $i = $skipped;

                continue;
            }

            $c = $source[$i];

            if ($c === '"' || $c === "'") {
                // Attribute value: run to the matching quote, stepping over any PHP or Blade
                // span inside it (that is where the stray `>` lives).
                $i++;

                while ($i < $length && $source[$i] !== $c) {
                    $skipped = $skipSpan($i);
                    $i = $skipped !== $i ? $skipped : $i + 1;
                }

                $i++;

                continue;
            }

            if ($c === '>') {
                $end = $i;

                break;
            }

            $i++;
        }

        if ($end === null) {
            continue;
        }

        $tag = substr($source, $start, $end - $start + 1);

        // Same quote-aware walk, to pull one attribute's raw value out of the tag text.
        $attr = static function (string $name) use ($tag, $skipSpan, $start, $source): ?string {
            if (preg_match('/\b'.$name.'\s*=\s*(["\'])/i', $tag, $m, PREG_OFFSET_CAPTURE) !== 1) {
                return null;
            }

            $quote = $m[1][0];
            $from = $start + $m[1][1] + 1;
            $i = $from;

            while ($i < strlen($source) && $source[$i] !== $quote) {
                $skipped = $skipSpan($i);
                $i = $skipped !== $i ? $skipped : $i + 1;
            }

            return substr($source, $from, $i - $from);
        };

        $src = $attr('src');

        if ($src === null) {
            continue;
        }

        $tags[] = [
            'line' => substr_count($source, "\n", 0, $start) + 1,
            'tag' => $tag,
            'src' => $src,
            'width' => $attr('width'),
            'height' => $attr('height'),
        ];
    }

    return $tags;
};

/**
 * Resolve a raw `src` expression to one set of intrinsic dimensions, or null.
 *
 * Every helper the theme uses to build an image URL — `Vite::asset()`, `BlockDefaults::pageImg()`,
 * `themeImg()`, `homeImg()`, the per-pattern `$img()` closures — ultimately takes the filename as
 * a quoted literal. So rather than re-implementing each helper (which would rot the moment one
 * changes), pull every quoted path out of the expression and match it against the image index by
 * path suffix. A literal that matches nothing, or matches files of differing sizes, yields null.
 *
 * @param  array<string, array{0: int, 1: int}>  $index
 * @return array{dims: array{0: int, 1: int}, file: string}|null
 */
$resolve = static function (string $src, array $index, string $sourceFile = ''): ?array {
    if (preg_match_all('/["\']([^"\']*\.(?:'.implode('|', RL_IMG_EXTENSIONS).'))["\']/i', $src, $matches) === false) {
        return null;
    }

    $stem = static fn (string $path): string => preg_replace('/\.[^.\/]+$/', '', str_replace('\\', '/', $path)) ?? $path;

    $candidates = [];

    foreach ($matches[1] as $literal) {
        // Match on the stem, not the full filename. `BlockDefaults::homeImg()` takes a name and
        // tries webp/png/jpg/jpeg/svg in turn, and `preferWebp()` swaps a raster for the sibling
        // .webp the Vite plugin generated — so `$img('person_01.webp')` legitimately serves
        // person_01.png and vice versa. The generated .webp is never resized, so same-stem files
        // agree on dimensions; where they genuinely do not (Frame-76-5.jpg and Frame-76-5.png are
        // different pictures) the disagreement below makes this ambiguous and the tag is skipped.
        // `Vite::asset()` and `get_theme_file_uri()` are handed a theme-root-relative path
        // ("resources/images/logo.svg"), while the index is keyed relative to resources/images
        // ("logo.svg"). Strip the asset roots so both spellings meet in the middle — without
        // this the logo that motivated the whole test resolves to nothing and is skipped.
        $literal = preg_replace('#^/?(?:resources|public)/images/#', '', str_replace('\\', '/', $literal)) ?? $literal;

        $needle = '/'.ltrim($stem($literal), '/');

        foreach ($index as $relative => $dims) {
            if (str_ends_with('/'.$stem($relative), $needle)) {
                $candidates[$relative] = $dims;
            }
        }
    }

    if ($candidates === []) {
        return null;
    }

    // A pattern's `$img()` closure is bound to that pattern's own page folder, and the two are
    // named alike (patterns/contractor-payments.php -> resources/images/pages/contractor-payments).
    // Where that folder holds a match, it is *the* match — which is what separates the 45x45
    // `Person_04.png` on the contractor-payments page from the 88x88 one on the home page. Without
    // this, every filename reused across pages at different sizes reads as ambiguous and is skipped.
    $ownFolder = 'pages/'.pathinfo($sourceFile, PATHINFO_FILENAME).'/';
    $local = array_filter(
        $candidates,
        static fn (string $relative): bool => str_starts_with($relative, $ownFolder),
        ARRAY_FILTER_USE_KEY,
    );

    if ($local !== []) {
        $candidates = $local;
    }

    // Ambiguous only if the matches disagree about size; identical copies in several page
    // folders are still a definite answer.
    $distinct = array_unique(array_map(static fn (array $d): string => $d[0].'x'.$d[1], $candidates));

    if (count($distinct) !== 1) {
        return null;
    }

    $file = array_key_first($candidates);

    return ['dims' => $candidates[$file], 'file' => $file];
};

/**
 * @return array<int, array{file: string, line: int, src: string, resolved: string, declared: ?array{0: int, 1: int}, true: array{0: int, 1: int}}>
 */
$findings = static function (string $theme) use ($sourceFiles, $imageIndex, $imgTags, $resolve): array {
    $index = $imageIndex($theme);
    $out = ['mismatched' => [], 'missing' => []];

    foreach ($sourceFiles($theme) as $path) {
        $source = (string) file_get_contents($path);

        if (! str_contains($source, '<img')) {
            continue;
        }

        $relativePath = str_replace($theme.'/', '', $path);

        foreach ($imgTags($source) as $tag) {
            $hit = $resolve($tag['src'], $index, $relativePath);

            if ($hit === null) {
                continue;
            }

            $numeric = static fn (?string $v): ?int => ($v !== null && preg_match('/^\s*(\d+)\s*$/', $v, $m) === 1) ? (int) $m[1] : null;

            $w = $numeric($tag['width']);
            $h = $numeric($tag['height']);

            $row = [
                'file' => $relativePath,
                'line' => $tag['line'],
                'resolved' => $hit['file'],
                'true' => $hit['dims'],
                'declared' => ($w !== null && $h !== null) ? [$w, $h] : null,
            ];

            // A non-numeric width/height is a computed value; leave it alone.
            if (($tag['width'] !== null && $w === null) || ($tag['height'] !== null && $h === null)) {
                continue;
            }

            if ($w === null || $h === null) {
                $out['missing'][] = $row;

                continue;
            }

            [$trueW, $trueH] = $hit['dims'];

            if ($w * $trueH !== $h * $trueW) {
                $out['mismatched'][] = $row;
            }
        }
    }

    return $out;
};

it('declares width and height proportional to the file each <img> points at', function () use ($findings, $theme) {
    $rows = $findings($theme)['mismatched'];

    $report = array_map(static function (array $r): string {
        [$dw, $dh] = $r['declared'];
        [$tw, $th] = $r['true'];

        return sprintf(
            '%s:%d declares %dx%d (%.4f) but %s is %dx%d (%.4f)',
            $r['file'], $r['line'], $dw, $dh, $dw / $dh, $r['resolved'], $tw, $th, $tw / $th,
        );
    }, $rows);

    expect($report)->toBe([], "These <img> tags declare a shape the asset does not have. `w-auto`/`h-auto` size from the\nintrinsic ratio, so the declared pair only ever sets the pre-load reservation — a wrong ratio is a\nsilent layout shift, and any layout that budgets from it is budgeting a width the element never has.\nSet each pair to the file's real dimensions (or an exact scale of them):\n  ".implode("\n  ", $report));
});

it('declares width and height on every <img> whose file is known at build time', function () use ($findings, $theme) {
    $rows = $findings($theme)['missing'];

    $report = array_map(
        static fn (array $r): string => sprintf(
            '%s:%d has no width/height; %s is %dx%d',
            $r['file'], $r['line'], $r['resolved'], $r['true'][0], $r['true'][1],
        ),
        $rows,
    );

    expect($report)->toBe([], "These <img> tags point at a file whose size is known on disk but reserve no space for it,\nso the page reflows when each one loads. Add the dimensions shown:\n  ".implode("\n  ", $report));
});

it('can read the dimensions of the assets it is meant to guard', function () use ($imageIndex, $dimensions, $theme) {
    // Without this, a resolver that silently reads nothing would make both tests above pass
    // vacuously — which is the only way this file can fail to do its job.
    $index = $imageIndex($theme);

    expect(count($index))->toBeGreaterThan(50, 'resources/images yielded almost no readable files — the dimension reader is broken, not the theme.');

    // The asset behind the bug this test exists for, in the format that regressed.
    expect($dimensions($theme.'/resources/images/logo.svg'))->toBe([154, 18]);
});
