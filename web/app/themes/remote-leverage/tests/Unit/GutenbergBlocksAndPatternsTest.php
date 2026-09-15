<?php

declare(strict_types=1);

namespace Tests\Unit;

describe('Gutenberg Blocks & Pattern Library QA (WR-93 Subtasks)', function () {
    test('every ACF Composer block class instantiates and defines a rich example preview', function () {
        // Derived from the filesystem rather than a hardcoded list: a hardcoded list rots the
        // moment a block is added or removed, and fails for the wrong reason.
        $blockClasses = array_map(
            static fn(string $file): string => 'App\\Blocks\\' . basename($file, '.php'),
            glob(dirname(__DIR__, 2) . '/app/Blocks/*Block.php') ?: [],
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

        foreach (glob(dirname(__DIR__, 2) . '/app/Blocks/*Block.php') ?: [] as $file) {
            $slug = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', basename($file, 'Block.php')));

            if (! is_file(dirname(__DIR__, 2) . '/resources/views/blocks/' . $slug . '.blade.php')) {
                $missing[] = $slug;
            }
        }

        expect($missing)->toBe([]);
    });

    test('all registered Gutenberg pattern files exist and contain valid metadata headers', function () {
        $patternsDir = dirname(__DIR__, 2) . '/patterns';
        $patternFiles = glob($patternsDir . '/*.php');

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
            $path = $patternsDir . '/' . $requiredFile;
            expect(file_exists($path))->toBeTrue("Pattern file {$requiredFile} must exist");

            $content = file_get_contents($path);
            expect($content)->toContain('Title:')
                ->and($content)->toContain('Slug:')
                ->and($content)->toContain('Categories:');
        }
    });

    test('editorial and secondary landing patterns strictly contain zero unicode emojis', function () {
        $patternsDir = dirname(__DIR__, 2) . '/patterns';
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
            $content = file_get_contents($patternsDir . '/' . $file);
            expect(preg_match($emojiRegex, $content))->toBe(0, "Pattern {$file} must use vector SVGs instead of unicode emojis");
        }
    });

    test('theme editor styles are built and present in manifest.json', function () {
        $manifestPath = dirname(__DIR__, 2) . '/public/build/manifest.json';
        if (! file_exists($manifestPath)) {
            $this->markTestSkipped('Vite build output is not present; run npm run build.');
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        expect($manifest)->toHaveKey('resources/css/editor.css');

        $compiledEditorCss = dirname(__DIR__, 2) . '/public/build/' . $manifest['resources/css/editor.css']['file'];
        expect(file_exists($compiledEditorCss))->toBeTrue();

        $cssContent = file_get_contents($compiledEditorCss);
        expect($cssContent)->toContain('editor-styles-wrapper')
            ->and($cssContent)->toContain('is-style-pill-purple')
            ->and($cssContent)->toContain('rl-editorial-toc')
            ->and($cssContent)->toContain('rl-key-takeaways');
    });
});
