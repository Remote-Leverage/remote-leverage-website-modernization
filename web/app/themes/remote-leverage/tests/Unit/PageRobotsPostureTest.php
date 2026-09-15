<?php

declare(strict_types=1);

use App\Support\PageRobots;

/*
 * Production serves three different robots postures across the migrated pages, and locally
 * you cannot see any of them: `bedrock-disallow-indexing` noindexes every non-production
 * environment, so every page renders `noindex, nofollow` regardless of its own config. That
 * blanket lifts the moment the site is production — which is exactly when an un-marked
 * internal ops tool would become publicly indexable, with nothing to catch it.
 *
 * So these assert the marker in the pattern, not the rendered tag.
 */

$expected = [
    // noindex, nofollow — internal collateral, no link equity passed
    'hmchecklists' => PageRobots::NOINDEX_MARKER,
    'saleschecklists' => PageRobots::NOINDEX_MARKER,
    'sales-talents' => PageRobots::NOINDEX_MARKER,
    'services' => PageRobots::NOINDEX_MARKER,
    'store' => PageRobots::NOINDEX_MARKER,
    // noindex, follow — production still passes link equity through these
    'recruiterchecklists' => PageRobots::NOINDEX_FOLLOW_MARKER,
    'onboardingguide' => PageRobots::NOINDEX_FOLLOW_MARKER,
    'contractoragreement' => PageRobots::NOINDEX_FOLLOW_MARKER,
    // index, follow — deliberately absent from both lists
    'spanish' => null,
    'hire-for-less' => null,
    'vaonboardingguide' => null,
];

describe('robots posture matches production per page', function () use ($expected) {
    test('each pattern declares the posture production serves', function (string $slug, ?string $marker) {
        // A page's content is one pattern reference, but that pattern may itself reference
        // section patterns (/spanish/ is nine files, /hire-for-less/ four). PageRobots walks
        // the registry; without WordPress booted, resolve the same way off disk so a marker
        // in any referenced file counts.
        $patternsDir = dirname(__DIR__, 2).'/patterns';
        $read = static function (string $name) use ($patternsDir): string {
            foreach ([$name, $name.'-full'] as $candidate) {
                $file = $patternsDir.'/'.$candidate.'.php';

                if (is_file($file)) {
                    return (string) file_get_contents($file);
                }
            }

            return '';
        };

        $pattern = $read($slug);
        expect($pattern)->not->toBe('', "No pattern file found for /{$slug}/.");

        // Follow one level of pattern references — enough for every page here.
        preg_match_all('/remote-leverage\/([a-z0-9-]+)/', $pattern, $refs);
        foreach (array_unique($refs[1] ?? []) as $ref) {
            $pattern .= $read($ref);
        }

        // Strip PHP comments before looking. PageChrome reads the pattern's *registered
        // content* — its output — so a marker written as a comment is invisible at runtime
        // while still satisfying a naive file search. Several of these patterns also discuss
        // the markers in their header docblock, which would otherwise match the wrong one.
        $emitted = preg_replace(['~/\*.*?\*/~s', '~^\s*(//|\#).*$~m'], '', $pattern) ?? $pattern;

        $hasFollow = str_contains($emitted, PageRobots::NOINDEX_FOLLOW_MARKER);
        // `rl:noindex` is a prefix of `rl:noindex-follow`, so a plain contains() is only a
        // plain-marker match when the follow variant is absent.
        $hasPlain = str_contains($emitted, PageRobots::NOINDEX_MARKER) && ! $hasFollow;

        $actual = match (true) {
            $hasFollow => PageRobots::NOINDEX_FOLLOW_MARKER,
            $hasPlain => PageRobots::NOINDEX_MARKER,
            default => null,
        };

        expect($actual)->toBe(
            $marker,
            "/{$slug}/ declares ".($actual ?? 'no marker').' but production serves '
            .($marker ?? 'index, follow').'. An un-marked page becomes publicly indexable '
            .'the moment this ships to production.'
        );
    })->with(array_map(
        static fn (string $slug, ?string $marker): array => [$slug, $marker],
        array_keys($expected),
        array_values($expected),
    ));

    test('the two markers are distinguishable despite one being a prefix of the other', function () {
        expect(PageRobots::NOINDEX_FOLLOW_MARKER)->toStartWith(PageRobots::NOINDEX_MARKER)
            ->and(PageRobots::NOINDEX_FOLLOW_MARKER)->not->toBe(PageRobots::NOINDEX_MARKER);
    });
});
