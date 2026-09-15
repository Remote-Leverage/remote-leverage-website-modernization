<?php

declare(strict_types=1);

namespace Tests\Unit;

/**
 * Guards the two pages migrated on 2026-09-15: /sales-talents/ and /onboardingguide/.
 *
 * WordPress is not booted in the unit suite, so these assert what can be asserted from the
 * pattern sources — which is exactly where both pages' regressions would land, because the
 * page rows in the database hold nothing but a one-line pattern reference.
 *
 * The regression that matters most is silent: /onboardingguide/ is 28 Vimeo training videos
 * and nothing else of substance. Dropping one, or dropping the `h=` privacy hash off an
 * unlisted one, breaks the embed with no error anywhere — the player just refuses to load.
 */
$theme = dirname(__DIR__, 2);

/** Every Vimeo video on production's /onboardingguide/, in document order. */
$onboardingVimeo = [
    '951742336' => '968877cb59',   // hero: Getting Started
    '905813420' => '',             // Part 1 — Step 1: Onboarding Form
    '944244329' => 'a4b6309539',   // Part 1 — Step 2: Weekly Mastermind
    '950376154' => 'c690371637',   // Part 1 — Step 3: Pull Data from Mojo
    '983685984' => 'a758f2b579',   // Part 2 — Time Doctor
    '980965431' => '1401ab0b9d',   // Part 2 — Wise
    '983685143' => '00e5f1fc1f',   // Part 2 — Work Schedule
    '983685750' => '0a74ac3eed',   // Part 2 — Payroll Sheet
    '894198188' => '',             // Part 2 — Upload New Contacts to Mojo
    '894703523' => '',             // Part 3 — Mojo: Quick Overview
    '894697064' => '',             // Part 3 — Mojo: Reading Contact Cards
    '894698569' => '',             // Part 3 — Mojo: Take Notes on Leads
    '894700918' => '',             // Part 3 — Mojo: Call & Session Reports
    '894717489' => '',             // Part 3 — Mojo: Calendar
    '895915016' => '',             // Part 4 — Follow up System: Overview
    '895701975' => '',             // Part 4 — Follow up: Demo
    '894233375' => '',             // Part 4 — Letters & Postcards 1
    '921883297' => '168b991e0a',   // Part 4 — Letters & Postcards 2
    '894226762' => '',             // Part 4 — Seller Follow up: Script Training
    '894226779' => '',             // Part 4 — Seller Follow up: Leaving Voicemails
    '894226803' => '',             // Part 4 — Seller Follow up: As Is Script
    '894226818' => '',             // Part 4 — Seller Follow up: 90 Days Script
    '894226835' => '',             // Part 4 — Seller Follow up: Setting the Appointment
    '895414254' => '',             // Part 4 — Build a Team of Cold Callers
    '897343399' => '',             // Part 5 — Follow up & Conversion Training
    '900419772' => '',             // Part 5 — Tips & Tricks with Mojo
    '902369729' => '',             // Part 5 — Follow up Script Training
    '931561846' => '91af288604',   // Part 5 — Auditing & Improving Your Results
];

$source = static fn (string $slug): string => (string) file_get_contents(dirname(__DIR__, 2).'/patterns/'.$slug.'.php');

/** Image filenames a pattern hands to BlockDefaults::pageImg(), however they are composed. */
$referencedImages = static function (string $php): array {
    preg_match_all('/[\'"]([A-Za-z0-9._\/-]+\.(?:png|jpe?g|svg|webp|gif))[\'"]/i', $php, $m);

    return array_values(array_unique(array_map('basename', $m[1])));
};

/** Every tracked source filename under resources/images/pages/<page>/. */
$trackedImages = static function (string $page) use ($theme): array {
    $dir = $theme.'/resources/images/pages/'.$page;

    if (! is_dir($dir)) {
        return [];
    }

    $names = [];
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

    foreach ($it as $file) {
        if ($file->isFile()) {
            $names[] = $file->getFilename();
        }
    }

    return $names;
};

describe('/onboardingguide/', function () use ($source, $onboardingVimeo, $referencedImages, $trackedImages) {
    test('the pattern is registered under the slug the page points at', function () use ($source) {
        $php = $source('onboardingguide');

        expect($php)->toContain('Slug: remote-leverage/onboardingguide')
            ->and($php)->toContain('Title: ')
            ->and($php)->toContain('Categories: remote-leverage');
    });

    test('all 28 production Vimeo videos survive the migration', function () use ($source, $onboardingVimeo) {
        $php = $source('onboardingguide');

        // The ids as the pattern actually lists them, deduplicated — a copy/paste that repeats
        // one id twice would otherwise pass a naive "contains" check while a video is missing.
        preg_match_all('/\'(\d{9})\'/', $php, $m);
        $found = array_values(array_unique($m[1]));

        sort($found);
        // PHP casts numeric-string array keys to int, so cast back before a strict compare.
        $expected = array_map('strval', array_keys($onboardingVimeo));
        sort($expected);

        expect($found)->toBe($expected)->and($found)->toHaveCount(28);
    });

    test('every unlisted video keeps its privacy hash', function () use ($source, $onboardingVimeo) {
        $php = $source('onboardingguide');

        foreach ($onboardingVimeo as $rawId => $hash) {
            $id = (string) $rawId;
            if ($hash === '') {
                continue;
            }

            // Without the `h=` token an unlisted Vimeo embed silently refuses to play.
            expect(str_contains($php, "'".$id."', '".$hash."'"))
                ->toBeTrue("Video {$id} lost its h= privacy hash.");
        }
    });

    test('the player URL keeps production\'s query string', function () use ($source) {
        expect($source('onboardingguide'))
            ->toContain('https://player.vimeo.com/video/')
            ->toContain('?color&autopause=0&loop=0&muted=0&title=1&portrait=1&byline=1');
    });

    test('production copy is transcribed, typos included', function () use ($source) {
        $php = $source('onboardingguide');

        // "apart of this program" is production's; so is the duplicated Part 3 caption.
        expect($php)->toContain('should be apart of this program')
            // Twice in the video rows, once more in the header note that explains why.
            ->and(substr_count($php, 'How to Call a Specific List of Leads Frequently'))->toBe(3)
            ->and($php)->toContain('TimeDoctor -VA Screen Monitoring');
    });

    test('every image it references is tracked page art', function () use ($source, $referencedImages, $trackedImages) {
        $tracked = $trackedImages('onboardingguide');

        expect($tracked)->not->toBeEmpty();
        expect(array_values(array_diff($referencedImages($source('onboardingguide')), $tracked)))->toBe([]);
    });
});

describe('/sales-talents/', function () use ($source, $referencedImages, $trackedImages) {
    test('the pattern is registered under the slug the page points at', function () use ($source) {
        $php = $source('sales-talents');

        expect($php)->toContain('Slug: remote-leverage/sales-talents')
            ->and($php)->toContain('Categories: remote-leverage');
    });

    test('it composes the existing blocks rather than re-implementing them', function () use ($source) {
        $php = $source('sales-talents');

        foreach ([
            'client-logos-marquee',       // trusted-by strip
            'talent-dossier-carousel',    // "Setters Ready To Book From Day 1"
            'feature-cards',              // pipeline + vetting grids
            'accordion-faq',              // FAQ
            'booking-footer',             // "Let's find your setter"
        ] as $slug) {
            expect(str_contains($php, $slug))->toBeTrue("acf/{$slug} should be reused here.");
        }

        expect($php)->toContain('renderTestimonials(');
    });

    test('repeater rows go through the ACF encoder, never as raw overrides', function () use ($source) {
        $php = $source('sales-talents');

        // A raw array passed as a plain override is silently ignored and the block falls back
        // to its presets — the page then looks wired up while showing someone else's content.
        expect($php)->toContain('renderEcom(\'talent-dossier-carousel\'')
            ->and($php)->toContain('renderEcom(\'client-logos-marquee\'')
            ->and($php)->toContain('renderBlockWithRepeater(\'accordion-faq\'')
            ->and($php)->toContain('renderFeatureCards(');
    });

    test('the eight dossier cards carry production\'s roles verbatim', function () use ($source) {
        $php = $source('sales-talents');

        // Production sells appointment setters here but ships medical dossiers. Transcribed,
        // not corrected — if someone "fixes" this, it stops matching the live page.
        foreach ([
            'Andrés Villalobos', 'Estefanía Peña', 'Lucía Fernández', 'Camila Torres',
            'Rafael Duarte', 'Juliana Ferreira', 'Marco Antonio Reyes', 'Sherika Campbell',
        ] as $name) {
            expect($php)->toContain($name);
        }
    });

    test('the four tool-stack rows are complete', function () use ($source) {
        $php = $source('sales-talents');

        // 45 pills across four marquee rows; "PandoDoc" is production's spelling.
        foreach (['GoHighLevel', 'LinkedIn Sales Navigator', 'PandoDoc', 'Seamless.ai', 'Regie.ai', 'Clearbit', 'Chime'] as $label) {
            expect($php)->toContain($label);
        }
    });

    test('hand-written sections declare why no block fit', function () use ($source) {
        $php = $source('sales-talents');

        // The tool marquee and the hero's isolated email step are the only two bespoke pieces.
        expect(substr_count($php, '@bespoke:'))->toBeGreaterThanOrEqual(2);
    });

    test('every image it references is tracked page art', function () use ($source, $referencedImages, $trackedImages) {
        $tracked = $trackedImages('sales-talents');

        expect($tracked)->not->toBeEmpty();
        expect(array_values(array_diff($referencedImages($source('sales-talents')), $tracked)))->toBe([]);
    });
});

test('neither page exceeds the theme\'s heaviest allowed weight', function () use ($source) {
    // font-bold is the maximum in this theme; extrabold/black are off the type scale.
    foreach (['sales-talents', 'onboardingguide'] as $slug) {
        expect($source($slug))
            ->not->toContain('font-extrabold')
            ->not->toContain('font-black');
    }
});
