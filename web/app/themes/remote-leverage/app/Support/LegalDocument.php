<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Turns a rendered legal document (Privacy Policy, Terms of Use) into content
 * with stable heading anchors plus the table of contents that links to them.
 *
 * The source content stays plain Gutenberg headings/paragraphs/lists so editors
 * keep editing legal copy as ordinary rich text — the anchors and the TOC are
 * derived at render time rather than stored, so adding a section to the document
 * adds it to the sidebar navigation for free.
 */
class LegalDocument
{
    /**
     * @param  string  $html  Rendered post content.
     * @param  int[]  $levels  Heading levels to treat as document sections.
     */
    public function __construct(
        protected string $html,
        protected array $levels = [2],
    ) {}

    /**
     * Content with an `id` on every section heading.
     */
    public function content(): string
    {
        return $this->parse()['html'];
    }

    /**
     * Section list for the sidebar navigation.
     *
     * @return array<int, array{id: string, text: string, level: int}>
     */
    public function sections(): array
    {
        return $this->parse()['sections'];
    }

    /**
     * @return array{html: string, sections: array<int, array{id: string, text: string, level: int}>}
     */
    protected function parse(): array
    {
        static $cache = [];

        $key = md5($this->html . serialize($this->levels));

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $sections = [];
        $used = [];
        $pattern = '/<h([' . implode('', $this->levels) . '])([^>]*)>(.*?)<\/h\1>/is';

        $html = preg_replace_callback($pattern, function (array $match) use (&$sections, &$used): string {
            [$full, $level, $attributes, $inner] = $match;

            $text = trim(html_entity_decode(wp_strip_all_tags($inner), ENT_QUOTES, 'UTF-8'));

            if ($text === '') {
                return $full;
            }

            // Respect an id the editor set by hand; otherwise derive a stable one.
            if (preg_match('/\sid=(["\'])(.*?)\1/i', $attributes, $existing)) {
                $id = $existing[2];
            } else {
                $id = $this->uniqueSlug($text, $used);
                $attributes .= ' id="' . esc_attr($id) . '"';
            }

            $used[$id] = true;

            $sections[] = [
                'id' => $id,
                'text' => $text,
                'level' => (int) $level,
            ];

            return '<h' . $level . $attributes . '>' . $inner . '</h' . $level . '>';
        }, $this->html);

        return $cache[$key] = [
            'html' => $html ?? $this->html,
            'sections' => $sections,
        ];
    }

    /**
     * @param  array<string, true>  $used
     */
    protected function uniqueSlug(string $text, array $used): string
    {
        $base = sanitize_title($text) ?: 'section';
        $slug = $base;
        $suffix = 2;

        while (isset($used[$slug])) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
