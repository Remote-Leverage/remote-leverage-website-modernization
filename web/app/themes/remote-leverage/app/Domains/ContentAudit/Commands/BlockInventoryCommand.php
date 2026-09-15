<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Commands;

use Illuminate\Console\Command;

/**
 * Regenerates docs/block-inventory.md — the "what already exists" index.
 *
 * Whoever builds a page (human or agent) needs one place that answers "which block
 * already renders this?" before writing markup. Reading 42 block classes is not that;
 * a single generated file is. Regenerated from source so it cannot drift.
 */
class BlockInventoryCommand extends Command
{
    protected $signature = 'blocks:inventory {--check : Exit non-zero if the committed inventory is stale}';

    protected $description = 'Generate docs/block-inventory.md from the theme block classes, views and pattern usages';

    public function handle(): int
    {
        $themePath = dirname(__DIR__, 4);
        $blocks = $this->collectBlocks($themePath);

        if ($blocks === []) {
            $this->error('No blocks found — expected classes in app/Blocks.');

            return self::FAILURE;
        }

        $markdown = $this->render($blocks, $this->collectPatterns($themePath));
        $target = $themePath.'/docs/block-inventory.md';

        if ($this->option('check')) {
            $current = is_file($target) ? file_get_contents($target) : '';

            if ($this->normalise($current) !== $this->normalise($markdown)) {
                $this->error('docs/block-inventory.md is out of date. Run: wp acorn blocks:inventory');

                return self::FAILURE;
            }

            $this->info('Block inventory is up to date.');

            return self::SUCCESS;
        }

        file_put_contents($target, $markdown);
        $this->info(sprintf('Wrote %s (%d blocks).', 'docs/block-inventory.md', count($blocks)));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{slug: string, name: string, summary: string, fields: array<int, string>, view: bool}>
     */
    protected function collectBlocks(string $themePath): array
    {
        $blocks = [];

        foreach (glob($themePath.'/app/Blocks/*Block.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            $slug = $this->slugFor(basename($file, 'Block.php'));
            $viewPath = $themePath.'/resources/views/blocks/'.$slug.'.blade.php';

            $blocks[] = [
                'slug' => $slug,
                'name' => $this->matchOne('/public \$name\s*=\s*[\'"](.+?)[\'"]/', $source) ?? $slug,
                'summary' => $this->summaryFor($viewPath, $source),
                'fields' => $this->fieldsFor($source),
                'view' => is_file($viewPath),
            ];
        }

        usort($blocks, fn (array $a, array $b): int => strcmp($a['slug'], $b['slug']));

        return $blocks;
    }

    /**
     * Where each block is already used, so the reader can open a real example.
     *
     * Patterns reach blocks three ways and all three must be counted, or the report
     * says "unused" for a block that half the site renders: a direct `acf/<slug>`
     * comment, `patternBlock('<slug>')`, and — most often — a `BlockDefaults::render*`
     * helper whose slug only appears inside BlockDefaults itself.
     *
     * @return array<string, array<int, string>>
     */
    protected function collectPatterns(string $themePath): array
    {
        $helperSlugs = $this->helperSlugMap($themePath);
        $usages = [];
        $files = array_merge(
            glob($themePath.'/patterns/*.php') ?: [],
            glob($themePath.'/resources/patterns/*.php') ?: [],
            glob($themePath.'/resources/views/*.blade.php') ?: [],
        );

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            preg_match_all('#acf/([a-z0-9-]+)#', $source, $direct);
            preg_match_all("#patternBlock\(\s*'([a-z0-9-]+)'#", $source, $viaPatternBlock);
            preg_match_all('#BlockDefaults::(render[A-Za-z0-9]+)\s*\(#', $source, $viaHelper);

            $slugs = array_merge($direct[1], $viaPatternBlock[1]);

            foreach ($viaHelper[1] as $helper) {
                $slugs = array_merge($slugs, $helperSlugs[$helper] ?? []);
            }

            foreach (array_unique($slugs) as $slug) {
                $usages[$slug][] = basename($file);
            }
        }

        foreach ($usages as $slug => $files) {
            $usages[$slug] = array_values(array_unique($files));
        }

        return $usages;
    }

    /**
     * render* helper name => block slugs it can emit (following helper-to-helper calls).
     *
     * @return array<string, array<int, string>>
     */
    protected function helperSlugMap(string $themePath): array
    {
        $source = (string) file_get_contents($themePath.'/app/Support/BlockDefaults.php');
        preg_match_all('/function (render[A-Za-z0-9]+)\(.*?\n    \}/s', $source, $m, PREG_SET_ORDER);

        $direct = [];
        $delegates = [];

        foreach ($m as $fn) {
            preg_match_all("/patternBlock\(\s*'([a-z0-9-]+)'/", $fn[0], $slugs);
            preg_match_all('/self::(render[A-Za-z0-9]+)\(/', $fn[0], $calls);
            $direct[$fn[1]] = array_unique($slugs[1]);
            $delegates[$fn[1]] = array_unique(array_diff($calls[1], [$fn[1]]));
        }

        $resolve = function (string $helper, array $seen = []) use (&$resolve, $direct, $delegates): array {
            $out = $direct[$helper] ?? [];

            foreach ($delegates[$helper] ?? [] as $next) {
                if (! in_array($next, $seen, true)) {
                    $out = array_merge($out, $resolve($next, [...$seen, $helper]));
                }
            }

            return array_values(array_unique($out));
        };

        $map = [];

        foreach (array_keys($direct) as $helper) {
            $map[$helper] = $resolve($helper);
        }

        return $map;
    }

    /**
     * The first sentence of the view's leading Blade comment is written to describe
     * the production section the block reproduces, which is exactly what a reader
     * scanning for "what renders this?" needs. Falls back to the block class.
     */
    protected function summaryFor(string $viewPath, string $classSource): string
    {
        if (is_file($viewPath)) {
            $head = (string) file_get_contents($viewPath);

            if (preg_match('/\{\{--\s*(.+?)--\}\}/s', $head, $m) === 1) {
                $text = trim(preg_replace('/\s+/', ' ', $m[1]) ?? '');

                if ($text !== '') {
                    return $text;
                }
            }
        }

        $keywords = $this->matchOne('/public \$keywords\s*=\s*\[(.+?)\]/s', $classSource);

        return $keywords !== null
            ? 'Keywords: '.trim(preg_replace('/[\'"\s]+/', ' ', $keywords) ?? '')
            : '—';
    }

    /**
     * @return array<int, string>
     */
    protected function fieldsFor(string $source): array
    {
        preg_match_all("/->add[A-Z][A-Za-z]*\(\s*'([a-z0-9_]+)'/", $source, $m);

        return array_values(array_unique($m[1]));
    }

    protected function matchOne(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $m) === 1 ? $m[1] : null;
    }

    protected function slugFor(string $studly): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $studly));
    }

    /** Ignore the timestamp line so --check only fails on real content drift. */
    protected function normalise(string $markdown): string
    {
        return trim((string) preg_replace('/^_Generated .*$/m', '', $markdown));
    }

    /**
     * @param  array<int, array{slug: string, name: string, summary: string, fields: array<int, string>, view: bool}>  $blocks
     * @param  array<string, array<int, string>>  $usages
     */
    protected function render(array $blocks, array $usages): string
    {
        $lines = [
            '# Block inventory',
            '',
            '**Read this before writing markup in a pattern.** Every row is something that already',
            'exists. If a section you are building resembles one of these, use it — extend the block',
            'with an option if production has a variant, rather than hand-rolling a second copy.',
            '',
            'Render a block from a pattern with the `BlockDefaults::render*` helpers, or inline as',
            '`<!-- wp:acf/<slug> ... /-->`. Repeater data must be ACF-encoded — pass it through the',
            'helper rather than as a raw array, or the block silently falls back to its presets.',
            '',
            '_Generated by `wp acorn blocks:inventory` — do not edit by hand._',
            '',
            '| Block | What it renders | Fields | Used by |',
            '| --- | --- | --- | --- |',
        ];

        foreach ($blocks as $block) {
            $used = $usages[$block['slug']] ?? [];
            sort($used);

            $lines[] = sprintf(
                '| `acf/%s`%s | %s | %s | %s |',
                $block['slug'],
                $block['view'] ? '' : ' ⚠️ no view',
                $this->cell($block['summary']),
                $block['fields'] === [] ? '—' : '`'.implode('`, `', $block['fields']).'`',
                $used === [] ? '_unused_' : implode(', ', array_map(static fn (string $f): string => '`'.$f.'`', array_slice($used, 0, 4)))
                    .(count($used) > 4 ? sprintf(' _+%d_', count($used) - 4) : ''),
            );
        }

        $lines[] = '';
        $lines[] = '## Finding the right block';
        $lines[] = '';
        $lines[] = '```bash';
        $lines[] = '# What renders a given production section? The view comments name them.';
        $lines[] = 'grep -rn "Production" resources/views/blocks/*.blade.php';
        $lines[] = '';
        $lines[] = '# How does an existing full page compose blocks? Read one before authoring.';
        $lines[] = 'grep -oE "acf/[a-z0-9-]+" patterns/comparison-full.php | sort -u';
        $lines[] = '```';
        $lines[] = '';

        return implode("\n", $lines)."\n";
    }

    protected function cell(string $text): string
    {
        $text = str_replace(['|', "\n"], ['\\|', ' '], $text);

        return mb_strlen($text) > 180 ? mb_substr($text, 0, 177).'…' : $text;
    }
}
