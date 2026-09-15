<?php

declare(strict_types=1);

/**
 * Guards against re-implementing an existing block as hand-written markup inside a pattern.
 *
 * The theme ships 40+ blocks (see docs/block-inventory.md). The failure mode this catches is
 * a pattern author writing a fresh card grid / carousel / comparison table because they did
 * not check what already existed — which duplicates markup, forks the design system, and
 * means a fix to the block never reaches the copy.
 *
 * The heuristic is deliberately narrow: a PHP loop inside a pattern that emits BOTH an
 * <img> and a heading is a card-grid shape, and the theme already has blocks for that
 * (feature-cards, image-card-grid, department-cards, roles-carousel, talent-grid, …).
 *
 * If a section genuinely has no block, say so in the pattern with:
 *
 *     // @bespoke: <reason — name the blocks you checked and why none fit>
 *
 * placed within 12 lines above the loop. That keeps the exception visible in review
 * instead of invisible in 60 lines of markup.
 */
$patternFiles = static function (): array {
    $theme = dirname(__DIR__, 2);

    return array_merge(
        glob($theme . '/patterns/*.php') ?: [],
        glob($theme . '/resources/patterns/*.php') ?: [],
    );
};

/**
 * @return array<int, array{line: int, snippet: string}>
 */
$unjustifiedCardLoops = static function (string $source): array {
    $lines = explode("\n", $source);
    $findings = [];

    foreach ($lines as $i => $line) {
        if (preg_match('/\b(foreach|for)\s*\(/', $line) !== 1) {
            continue;
        }

        // Body = until the loop's closing brace at the same indent, capped so a runaway
        // scan cannot swallow the rest of the file.
        $body = implode("\n", array_slice($lines, $i, 40));
        $body = preg_split('/<\?php\s*\}\s*\?>|^\s*<\?php\s*\}/m', $body)[0] ?? $body;

        $hasImage = preg_match('/<img\b|background-image:url|bg-cover/', $body) === 1;
        $hasHeading = preg_match('/<h[1-6]\b/', $body) === 1;

        if (! $hasImage || ! $hasHeading) {
            continue;
        }

        $preamble = implode("\n", array_slice($lines, max(0, $i - 12), min(12, $i)));

        if (preg_match('/@bespoke:\s*\S/', $preamble) === 1) {
            continue;
        }

        $findings[] = ['line' => $i + 1, 'snippet' => trim($line)];
    }

    return $findings;
};

describe('pattern authors reuse existing blocks', function () use ($patternFiles, $unjustifiedCardLoops) {
    test('no pattern hand-rolls a card grid that an existing block already renders', function () use ($patternFiles, $unjustifiedCardLoops) {
        $offenders = [];

        foreach ($patternFiles() as $file) {
            foreach ($unjustifiedCardLoops((string) file_get_contents($file)) as $finding) {
                $offenders[] = sprintf('%s:%d  %s', basename($file), $finding['line'], $finding['snippet']);
            }
        }

        expect($offenders)->toBe([], implode("\n", array_merge(
            ['Hand-written card markup found in a pattern. Check docs/block-inventory.md for a block that already renders it.'],
            ['If none fits, add "// @bespoke: <reason>" above the loop.'],
            [''],
            $offenders,
        )));
    });

    test('every block referenced by a pattern actually exists', function () use ($patternFiles) {
        $theme = dirname(__DIR__, 2);
        $missing = [];

        foreach ($patternFiles() as $file) {
            preg_match_all('#acf/([a-z0-9-]+)#', (string) file_get_contents($file), $m);

            foreach (array_unique($m[1]) as $slug) {
                if (! is_file($theme . '/resources/views/blocks/' . $slug . '.blade.php')) {
                    $missing[] = basename($file) . ' → acf/' . $slug;
                }
            }
        }

        // A typo here renders nothing at all and is invisible without this check —
        // exactly how contractor-management shipped an FAQ that never appeared.
        expect($missing)->toBe([]);
    });

    test('the committed block inventory lists every block', function () {
        $theme = dirname(__DIR__, 2);
        $inventory = (string) file_get_contents($theme . '/docs/block-inventory.md');
        $missing = [];

        foreach (glob($theme . '/app/Blocks/*Block.php') ?: [] as $blockFile) {
            $slug = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', basename($blockFile, 'Block.php')));

            if (! str_contains($inventory, '`acf/' . $slug . '`')) {
                $missing[] = $slug;
            }
        }

        expect($missing)->toBe([], 'docs/block-inventory.md is stale. Run: wp acorn blocks:inventory');
    });
});
