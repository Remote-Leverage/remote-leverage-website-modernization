<?php

declare(strict_types=1);

use App\Support\BlockDefaults;

/**
 * Covers BlockDefaults::mediaUrl(), added after stakeholder QA on 2026-09-15
 * reported /samples/, /case-study/ and /hire-for-less/ as "nothing loads".
 *
 * The presets carry ~109 paths captured from production in Bedrock form. Those
 * literal strings resolve on no host: production is a classic install serving
 * /wp-content/uploads/, and staging was never given the files at all.
 */
describe('mediaUrl resolves legacy uploads paths', function () {
    test('an empty path stays empty rather than becoming a bare uploads URL', function () {
        expect(BlockDefaults::mediaUrl(''))->toBe('');
    });

    test('an unknown file resolves to empty, not a dead URL', function () {
        // A dead URL renders as a broken player that looks identical to a
        // working one in a screenshot; '' is detectable.
        expect(BlockDefaults::mediaUrl('/app/uploads/2099/01/nope-not-real.mp3'))->toBe('');
    });

    test('both legacy prefixes are accepted', function () {
        // Production writes /wp-content/uploads/, Bedrock writes /app/uploads/.
        // Neither is trusted; only the trailing path matters.
        $bedrock = BlockDefaults::mediaUrl('/app/uploads/2099/01/nope.png');
        $classic = BlockDefaults::mediaUrl('/wp-content/uploads/2099/01/nope.png');

        expect($bedrock)->toBe($classic);
    });

    test('a full URL is reduced to its path before resolving', function () {
        expect(BlockDefaults::mediaUrl('https://remoteleverage.com/wp-content/uploads/2099/01/nope.png'))
            ->toBe(BlockDefaults::mediaUrl('/app/uploads/2099/01/nope.png'));
    });

    test('the cache can be flushed', function () {
        BlockDefaults::flushMediaCache();

        expect(BlockDefaults::mediaUrl('/app/uploads/2099/01/nope.png'))->toBe('');
    });

    test('both sample templates route their media through the resolver', function () {
        foreach (['sample-applicant-audio', 'sample-applicant-videos'] as $view) {
            $blade = file_get_contents(__DIR__."/../../resources/views/blocks/{$view}.blade.php");

            expect($blade)->toContain('BlockDefaults::mediaUrl(')
                ->and($blade)->not->toMatch('/\$(audioSrc|videoSrc) = \$(item|card)\[/');
        }
    });

    test('an in-page anchor is passed through untouched, not resolved as a file', function () {
        foreach (['sample-applicant-audio', 'sample-applicant-videos'] as $view) {
            $blade = file_get_contents(__DIR__."/../../resources/views/blocks/{$view}.blade.php");

            expect($blade)->toContain("str_starts_with(\$resumeRaw, '#')");
        }
    });
});
