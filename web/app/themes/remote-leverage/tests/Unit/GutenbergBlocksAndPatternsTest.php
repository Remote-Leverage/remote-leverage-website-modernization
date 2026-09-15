<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BlockDefaults;

describe('Gutenberg Blocks & Pattern Library QA (WR-93 Subtasks)', function () {
    test('every ACF Composer block class instantiates and defines a rich example preview', function () {
        // Derived from the filesystem rather than a hardcoded list: a hardcoded list rots the
        // moment a block is added or removed, and fails for the wrong reason.
        $blockClasses = array_map(
            static fn (string $file): string => 'App\\Blocks\\'.basename($file, '.php'),
            glob(dirname(__DIR__, 2).'/app/Blocks/*Block.php') ?: [],
        );

        expect(count($blockClasses))->toBeGreaterThanOrEqual(19);

        foreach ($blockClasses as $className) {
            expect(class_exists($className))->toBeTrue("Class {$className} must exist");

            $reflection = new \ReflectionClass($className);
            expect($reflection->hasProperty('name'))->toBeTrue();
            expect($reflection->hasProperty('slug'))->toBeTrue();
            expect($reflection->hasProperty('category'))->toBeTrue();
            expect($reflection->hasProperty('example'))->toBeTrue("Class {$className} must define public \$example for block inserter previews (WR-110)");

            $defaultProperties = $reflection->getDefaultProperties();
            expect($defaultProperties['example'])->toBeArray()
                ->and($defaultProperties['example'])->toHaveKey('attributes')
                ->and($defaultProperties['example']['attributes'])->toHaveKey('data');
        }
    });

    test('every block class has a matching Blade view', function () {
        $missing = [];

        foreach (glob(dirname(__DIR__, 2).'/app/Blocks/*Block.php') ?: [] as $file) {
            $slug = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', basename($file, 'Block.php')));

            if (! is_file(dirname(__DIR__, 2).'/resources/views/blocks/'.$slug.'.blade.php')) {
                $missing[] = $slug;
            }
        }

        expect($missing)->toBe([]);
    });

    test('all registered Gutenberg pattern files exist and contain valid metadata headers', function () {
        $patternsDir = dirname(__DIR__, 2).'/patterns';
        $patternFiles = glob($patternsDir.'/*.php');

        expect($patternFiles)->not->toBeEmpty()
            ->and(count($patternFiles))->toBeGreaterThanOrEqual(28);

        $requiredPatterns = [
            'guide-table-of-contents.php',
            'guide-key-takeaways.php',
            'guide-author-bio.php',
            'guide-related-articles.php',
            'partner-profile-header.php',
            'consultation-schedule-split.php',
            'testimonials-video-modal.php',
            'hire-va-4-hero.php',
            'hire-va-4-full.php',
        ];

        foreach ($requiredPatterns as $requiredFile) {
            $path = $patternsDir.'/'.$requiredFile;
            expect(file_exists($path))->toBeTrue("Pattern file {$requiredFile} must exist");

            $content = file_get_contents($path);
            expect($content)->toContain('Title:')
                ->and($content)->toContain('Slug:')
                ->and($content)->toContain('Categories:');
        }
    });

    test('editorial and secondary landing patterns strictly contain zero unicode emojis', function () {
        $patternsDir = dirname(__DIR__, 2).'/patterns';
        $filesToCheck = [
            'guide-table-of-contents.php',
            'guide-key-takeaways.php',
            'guide-author-bio.php',
            'guide-related-articles.php',
            'partner-profile-header.php',
            'consultation-schedule-split.php',
            'testimonials-video-modal.php',
        ];

        $emojiRegex = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';

        foreach ($filesToCheck as $file) {
            $content = file_get_contents($patternsDir.'/'.$file);
            expect(preg_match($emojiRegex, $content))->toBe(0, "Pattern {$file} must use vector SVGs instead of unicode emojis");
        }
    });

    test('theme editor styles are built and present in manifest.json', function () {
        $manifestPath = dirname(__DIR__, 2).'/public/build/manifest.json';
        if (! file_exists($manifestPath)) {
            $this->markTestSkipped('Vite build output is not present; run npm run build.');
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        expect($manifest)->toHaveKey('resources/css/editor.css');

        $compiledEditorCss = dirname(__DIR__, 2).'/public/build/'.$manifest['resources/css/editor.css']['file'];
        expect(file_exists($compiledEditorCss))->toBeTrue();

        $cssContent = file_get_contents($compiledEditorCss);
        expect($cssContent)->toContain('editor-styles-wrapper')
            ->and($cssContent)->toContain('is-style-pill-purple')
            ->and($cssContent)->toContain('rl-editorial-toc')
            ->and($cssContent)->toContain('rl-key-takeaways');
    });
});

describe('BlockDefaults::encodeRepeater nested rows', function () {
    test('a sub-field that is a list of arrays is encoded as its own repeater', function () {
        $data = [];
        BlockDefaults::encodeRepeater('cards', 'field_x_cards', [[
            'name' => 'Andrés Molina',
            'tools' => [
                ['src' => 'https://example.test/epic.png', 'alt' => 'Epic'],
                ['src' => 'https://example.test/zoom.png', 'alt' => 'Zoom'],
            ],
        ]], $data);

        expect($data['cards'])->toBe(1)
            ->and($data['cards_0_name'])->toBe('Andrés Molina')
            // The nested repeater carries its own count and key...
            ->and($data['cards_0_tools'])->toBe(2)
            ->and($data['_cards_0_tools'])->toBe('field_x_cards_tools')
            // ...and each nested row is addressed and keyed individually.
            ->and($data['cards_0_tools_0_src'])->toBe('https://example.test/epic.png')
            ->and($data['_cards_0_tools_0_src'])->toBe('field_x_cards_tools_src')
            ->and($data['cards_0_tools_1_alt'])->toBe('Zoom')
            ->and($data['_cards_0_tools_1_alt'])->toBe('field_x_cards_tools_alt');
    });

    test('an associative ACF image array is NOT mistaken for nested rows', function () {
        $data = [];
        BlockDefaults::encodeRepeater('cards', 'field_x_cards', [[
            'image' => ['url' => 'https://example.test/a.png', 'id' => 7],
        ]], $data);

        expect($data['cards_0_image'])->toBe(['url' => 'https://example.test/a.png', 'id' => 7])
            ->and($data['_cards_0_image'])->toBe('field_x_cards_image')
            ->and($data)->not->toHaveKey('cards_0_image_0_url');
    });

    test('flat rows are encoded exactly as before', function () {
        $data = [];
        BlockDefaults::encodeRepeater('rows', 'field_x_rows', [
            ['feature' => 'Time to hire', 'diy' => '4 - 8 weeks', 'rl' => '72 hrs'],
        ], $data);

        expect($data)->toBe([
            'rows' => 1,
            '_rows' => 'field_x_rows',
            'rows_0_feature' => 'Time to hire',
            '_rows_0_feature' => 'field_x_rows_feature',
            'rows_0_diy' => '4 - 8 weeks',
            '_rows_0_diy' => 'field_x_rows_diy',
            'rows_0_rl' => '72 hrs',
            '_rows_0_rl' => 'field_x_rows_rl',
        ]);
    });

    test('an empty nested list falls through to a scalar write rather than recursing', function () {
        $data = [];
        BlockDefaults::encodeRepeater('cards', 'field_x_cards', [['tools' => []]], $data);

        expect($data['cards_0_tools'])->toBe([])
            ->and($data['_cards_0_tools'])->toBe('field_x_cards_tools');
    });
});
