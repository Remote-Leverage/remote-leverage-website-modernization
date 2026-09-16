<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin\Seo;

use Illuminate\Support\Facades\Vite;

/**
 * The Yoast surfaces on post screens: the metabox title and the list-table columns.
 *
 * Both are markup Yoast hardcodes, so both are rewritten after the fact rather than filtered
 * at source — there is no `wpseo_metabox_title` and the column headings are baked into
 * WPSEO_Meta_Columns::column_heading().
 */
class SeoEditor
{
    /** Yoast's metabox id. */
    private const METABOX_ID = 'wpseo_meta';

    /**
     * Yoast's column glyphs, replaced with the theme's own icon set.
     *
     * Yoast renders these four headers as a bare `<span>` carrying a background-image glyph in
     * its house style, with the real label hidden in `.screen-reader-text` and a
     * `data-tooltip-text` attribute its stylesheet paints *above* the cell — where the table's
     * own `overflow: hidden` clipped it (that clipping is fixed in WordPressAdminTheme).
     *
     * The replacements are Lucide-style inline SVG, the same 24x24 / `currentColor` /
     * round-capped 2px construction MarketingDashboard and SecurityAdmin already use, so an SEO
     * column reads as part of the same admin as everything around it. Paths are the icon body
     * only — the wrapping <svg> and its shared attributes come from icon() below.
     *
     * The accessible label is kept: it moves into `.screen-reader-text` exactly as Yoast had
     * it, so removing the glyph costs nothing for screen readers, and is repeated in
     * `data-rl-tip` for the sighted tooltip.
     *
     * @var array<string, array{label: string, tip: string, icon: string}>
     */
    private const COLUMNS = [
        'wpseo-score' => [
            'label' => 'SEO score',
            'tip' => 'SEO score',
            // lucide: gauge
            'icon' => '<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>',
        ],
        'wpseo-score-readability' => [
            'label' => 'Readability score',
            'tip' => 'Readability',
            // lucide: book-open
            'icon' => '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>',
        ],
        'wpseo-links' => [
            'label' => 'Outgoing internal links',
            'tip' => 'Outgoing links',
            // lucide: external-link
            'icon' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        ],
        'wpseo-linked' => [
            'label' => 'Received internal links',
            'tip' => 'Incoming links',
            // lucide: log-in
            'icon' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/>',
        ],
    ];

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'renameMetabox'], 100);
        add_action('admin_init', [$this, 'hookColumnHeadings'], 100);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorScript']);
    }

    /**
     * Load the shim that renames Yoast's Gutenberg sidebar panel.
     *
     * The panel title is a hardcoded string inside Yoast's React bundle, so no PHP filter can
     * reach it: `gettext` never sees a JS string, and `load_script_translations` only fires when
     * a translation file exists, which it does not on an English install. Rewriting the rendered
     * label is the only seam left, so this is a deliberate exception to keeping the rebrand in
     * PHP rather than an oversight.
     */
    public function enqueueEditorScript(): void
    {
        wp_enqueue_script(
            'rl-seo-admin',
            Vite::asset('resources/js/seo-admin.js'),
            [],
            null,
            true
        );
    }

    /**
     * Rewrite the metabox heading from "Yoast SEO" to "SEO".
     *
     * `add_meta_box()` stores the title in `$wp_meta_boxes` with no filter on the way past, so
     * the registered structure is edited in place. Re-registering the box instead would drop
     * the `__block_editor_compatible_meta_box` flag Yoast passes and strand it in the block
     * editor's compatibility panel.
     */
    public function renameMetabox(): void
    {
        global $wp_meta_boxes;

        if (! is_array($wp_meta_boxes)) {
            return;
        }

        foreach ($wp_meta_boxes as $screen => $contexts) {
            if (! is_array($contexts)) {
                continue;
            }

            foreach ($contexts as $context => $priorities) {
                if (! is_array($priorities)) {
                    continue;
                }

                foreach ($priorities as $priority => $boxes) {
                    if (! is_array($boxes) || ! isset($boxes[self::METABOX_ID]['title'])) {
                        continue;
                    }

                    $wp_meta_boxes[$screen][$context][$priority][self::METABOX_ID]['title']
                        = SeoMenu::stripVendor((string) $boxes[self::METABOX_ID]['title']);
                }
            }
        }
    }

    /**
     * Attach the heading rewrite to every post type Yoast adds columns to.
     *
     * Priority 20 against Yoast's 10 on the same filter. `manage_{$post_type}_posts_columns`
     * is the last of the three column filters WP_Posts_List_Table applies, so hooking the
     * generic `manage_posts_columns` instead would run *before* Yoast and be overwritten.
     */
    public function hookColumnHeadings(): void
    {
        foreach (get_post_types(['show_ui' => true], 'names') as $postType) {
            add_filter("manage_{$postType}_posts_columns", [$this, 'relabelColumns'], 20);
        }
    }

    /**
     * Swap Yoast's glyph spans for ours.
     *
     * No `title` attribute: the CSS tooltip in SeoSkinStyles renders from `data-rl-tip`, and a
     * native title on the same element would stack a second, differently-placed tooltip on top
     * of it.
     *
     * @param  array<string, string>  $columns
     * @return array<string, string>
     */
    public function relabelColumns(array $columns): array
    {
        foreach (self::COLUMNS as $key => $meta) {
            if (! isset($columns[$key])) {
                continue;
            }

            $columns[$key] = sprintf(
                '<span class="rl-seo-col" data-rl-tip="%s">%s<span class="screen-reader-text">%s</span></span>',
                esc_attr($meta['tip']),
                self::icon($meta['icon']),
                esc_html($meta['label'])
            );
        }

        return $columns;
    }

    /**
     * Wrap an icon body in the theme's standard Lucide frame.
     *
     * `aria-hidden` because the accessible name is carried by the sibling screen-reader span.
     */
    private static function icon(string $body): string
    {
        return '<svg class="rl-seo-col-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none"'
            .' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            .$body
            .'</svg>';
    }
}
