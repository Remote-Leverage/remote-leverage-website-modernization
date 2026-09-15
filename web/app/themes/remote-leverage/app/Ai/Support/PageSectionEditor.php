<?php

declare(strict_types=1);

namespace App\Ai\Support;

use WP_Block_Patterns_Registry;

/**
 * Reads and rewrites a page's block tree section by section.
 *
 * Pages in this project come in three shapes, and all three have to work:
 *
 *  1. A single `wp:pattern` reference (the convention in CLAUDE.md — 12 of the
 *     site's pages). These expand to the full, fully-named ACF section tree the
 *     mother patterns declare, so they are the best case; they just have to be
 *     resolved first, which is what makes a 70-byte page describable at all.
 *  2. Expanded ACF block markup, where copy lives in a flat attrs.data map —
 *     including repeaters, which expand to "table_1_rows" => 5 plus
 *     "table_1_rows_0_label" => "…".
 *  3. Expanded core block markup (paragraph/heading), where copy lives in
 *     innerHTML instead. Most legacy pages are mostly this.
 *
 * Sections are addressed by their pattern-declared metadata.name where there is
 * one and by a positional "#0.2" path where there is not, because only 2 of the
 * 163 top-level blocks in the site's expanded pages carry a name. describe-page
 * always hands back the address to use, so a caller never has to derive one.
 *
 * parse_blocks()/serialize_blocks() round-trips this theme's patterns
 * byte-for-byte, so sections that were not overridden come back out unchanged.
 */
final class PageSectionEditor
{
    /**
     * Core blocks whose copy lives in innerHTML rather than attrs.data, mapped
     * to the tag that holds it. Limited to blocks that render exactly one
     * text-bearing element, so the replacement target is never ambiguous.
     */
    private const TEXT_BLOCKS = [
        'core/paragraph' => 'p',
        'core/heading' => 'h[1-6]',
    ];

    /**
     * Expand every `wp:pattern` reference into the blocks it stands for.
     *
     * A pattern-referencing page carries no content of its own, so without
     * this it describes as empty and clones as a page that renders the source
     * pattern verbatim — overrides silently lost.
     */
    public function resolve(string $content): string
    {
        $blocks = parse_blocks($content);
        $this->expandPatterns($blocks, 0);

        return serialize_blocks($blocks);
    }

    /**
     * Describe every addressable section of a page, top to bottom.
     *
     * @return array<int, array<string, mixed>>
     */
    public function outline(string $content): array
    {
        $sections = [];
        $this->walk(parse_blocks($this->resolve($content)), '', 0, $sections);

        return $sections;
    }

    /**
     * Apply field overrides to named sections.
     *
     * An override naming a section or field that does not exist is reported
     * back rather than applied. ACF resolves a value through its companion
     * "_field" key (e.g. "headline" is only meaningful alongside "_headline"
     * => "field_…"), so inventing a key here would write a value ACF cannot
     * map — the page would look wired up and render nothing.
     *
     * @param  array<int, array{section: string, fields: array<string, mixed>}>  $overrides
     * @return array{content: string, applied: array<int, string>, skipped: array<int, string>}
     */
    public function apply(string $content, array $overrides): array
    {
        $blocks = parse_blocks($this->resolve($content));

        $wanted = [];

        foreach ($overrides as $override) {
            $section = (string) ($override['section'] ?? '');
            $fields = $override['fields'] ?? [];

            if ($section === '' || ! is_array($fields)) {
                continue;
            }

            $wanted[$section] = array_merge($wanted[$section] ?? [], $fields);
        }

        $applied = [];
        $seen = [];
        $this->rewrite($blocks, '', 0, $wanted, $applied, $seen);

        $skipped = [];

        foreach ($wanted as $section => $fields) {
            if (! in_array($section, $seen, true)) {
                $skipped[] = "section \"{$section}\" does not exist on this page";

                continue;
            }

            foreach (array_keys($fields) as $field) {
                if (! in_array("{$section}.{$field}", $applied, true)) {
                    $skipped[] = "field \"{$field}\" does not exist on section \"{$section}\"";
                }
            }
        }

        return [
            'content' => serialize_blocks($blocks),
            'applied' => $applied,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function expandPatterns(array &$blocks, int $depth): void
    {
        // Patterns may reference patterns; the bound stops a cycle from
        // expanding forever rather than reflecting a real nesting limit.
        if ($depth > 5) {
            return;
        }

        $out = [];

        foreach ($blocks as $block) {
            $slug = $block['blockName'] === 'core/pattern' ? ($block['attrs']['slug'] ?? null) : null;

            if ($slug !== null) {
                $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered($slug);

                if ($pattern !== null) {
                    $inner = parse_blocks($pattern['content']);
                    $this->expandPatterns($inner, $depth + 1);

                    foreach ($inner as $resolved) {
                        $out[] = $resolved;
                    }

                    continue;
                }
            }

            if (! empty($block['innerBlocks'])) {
                $this->expandPatterns($block['innerBlocks'], $depth + 1);
            }

            $out[] = $block;
        }

        $blocks = $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function walk(array $blocks, string $path, int $depth, array &$sections): void
    {
        $index = 0;

        foreach ($blocks as $block) {
            if ($block['blockName'] === null) {
                continue;
            }

            $here = $path === '' ? (string) $index : "{$path}.{$index}";
            $index++;

            $fields = $this->describeFields($block);

            if ($fields !== []) {
                $sections[] = [
                    'section' => $block['attrs']['metadata']['name'] ?? "#{$here}",
                    'block' => $block['blockName'],
                    'depth' => $depth,
                    'fields' => $fields,
                ];
            }

            if (! empty($block['innerBlocks'])) {
                $this->walk($block['innerBlocks'], $here, $depth + 1, $sections);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<int, array<string, mixed>>
     */
    private function describeFields(array $block): array
    {
        $fields = [];

        foreach ($block['attrs']['data'] ?? [] as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }

            $fields[] = [
                'field' => $key,
                'type' => $this->classify((string) $key, $value, $block['attrs']['data']),
                'value' => $value,
            ];
        }

        $text = $this->readText($block);

        if ($text !== null) {
            $fields[] = ['field' => 'text', 'type' => 'text', 'value' => $text];
        }

        return $fields;
    }

    /**
     * Classify each editable field so a caller can tell copy it may rewrite
     * from structure it should leave alone. The classification is derived from
     * the data itself — a sibling "key_0_…" proves a repeater count, and an
     * attachment lookup proves an image ID — rather than guessed from naming.
     *
     * @param  array<string, mixed>  $data
     */
    private function classify(string $key, mixed $value, array $data): string
    {
        if (! is_numeric($value)) {
            return 'text';
        }

        foreach (array_keys($data) as $sibling) {
            if (preg_match('/^'.preg_quote($key, '/').'_\d+_/', (string) $sibling) === 1) {
                return 'repeater_count';
            }
        }

        if (get_post_type((int) $value) === 'attachment') {
            return 'image_id';
        }

        return 'number';
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function readText(array $block): ?string
    {
        $tag = self::TEXT_BLOCKS[$block['blockName']] ?? null;

        if ($tag === null) {
            return null;
        }

        if (preg_match('#<'.$tag.'\b[^>]*>(.*)</'.$tag.'>#s', (string) ($block['innerHTML'] ?? ''), $m) !== 1) {
            return null;
        }

        return $m[1];
    }

    /**
     * Replace the inner text of a core text block in both innerHTML and the
     * innerContent chunk that mirrors it — serialize_blocks() reads
     * innerContent, so writing only innerHTML changes nothing on save.
     *
     * @param  array<string, mixed>  $block
     */
    private function writeText(array &$block, string $value): bool
    {
        $tag = self::TEXT_BLOCKS[$block['blockName']] ?? null;

        if ($tag === null) {
            return false;
        }

        $pattern = '#(<'.$tag.'\b[^>]*>).*(</'.$tag.'>)#s';
        $replace = '${1}'.str_replace('$', '\$', $value).'${2}';

        $html = preg_replace($pattern, $replace, (string) ($block['innerHTML'] ?? ''), 1);

        if ($html === null || $html === $block['innerHTML']) {
            return false;
        }

        $block['innerHTML'] = $html;

        foreach ($block['innerContent'] as $i => $chunk) {
            if (is_string($chunk) && preg_match($pattern, $chunk) === 1) {
                $block['innerContent'][$i] = preg_replace($pattern, $replace, $chunk, 1);

                return true;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, array<string, mixed>>  $wanted
     * @param  array<int, string>  $applied
     * @param  array<int, string>  $seen
     */
    private function rewrite(array &$blocks, string $path, int $depth, array $wanted, array &$applied, array &$seen): void
    {
        $index = 0;

        foreach ($blocks as &$block) {
            if ($block['blockName'] === null) {
                continue;
            }

            $here = $path === '' ? (string) $index : "{$path}.{$index}";
            $index++;

            // A section is addressable by its declared name or its position,
            // and describe-page hands back whichever applies.
            foreach ([$block['attrs']['metadata']['name'] ?? null, "#{$here}"] as $address) {
                if ($address === null || ! isset($wanted[$address])) {
                    continue;
                }

                $seen[] = $address;

                foreach ($wanted[$address] as $field => $value) {
                    if ($field === 'text' && $this->writeText($block, (string) $value)) {
                        $applied[] = "{$address}.text";

                        continue;
                    }

                    if (! array_key_exists($field, $block['attrs']['data'] ?? [])) {
                        continue;
                    }

                    $block['attrs']['data'][$field] = $value;
                    $applied[] = "{$address}.{$field}";
                }
            }

            if (! empty($block['innerBlocks'])) {
                $this->rewrite($block['innerBlocks'], $here, $depth + 1, $wanted, $applied, $seen);
            }
        }

        // $block is still bound to the last element by reference; leaving it
        // bound would make the next foreach over this array overwrite it.
        unset($block);
    }
}
