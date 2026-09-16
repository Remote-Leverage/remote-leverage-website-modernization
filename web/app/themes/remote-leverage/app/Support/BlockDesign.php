<?php

declare(strict_types=1);

namespace App\Support;

use WP_Block_Type_Registry;

/**
 * Per-instance design controls for every `acf/*` block in this theme.
 *
 * ## Why this exists at all
 *
 * Until now a block instance could not be adjusted from the editor in any way. ACF Composer
 * already computes a `ComponentAttributeBag` for each block — `Block::getClasses()` folds in
 * `className`, the alignment supports and `anchor` — but not one of the 57 views in
 * `resources/views/blocks/` ever renders it. They all open with a hardcoded
 * `<section class="…">`. The practical consequence is worse than "unsupported": Gutenberg
 * still shows its built-in **Advanced → Additional CSS class(es)** field, so an editor can
 * type a class, save it, see it persisted in the markup attributes, and get nothing on the
 * front end. This closes that gap rather than adding a new one.
 *
 * ## Why the output is real CSS and not Tailwind utilities
 *
 * The obvious implementation — let an editor type `pt-24 bg-brand-navy` into a class field —
 * cannot work here and would fail silently, which is the worst way to fail. Tailwind v4 scans
 * the `@source` globs declared in `resources/css/app.css`: `app/`, the Blade views under
 * `resources/`, `patterns/` and `resources/patterns/`. A class that exists only in the database
 * is in none of them, so it is never compiled and the rule simply does not exist at runtime.
 * CLAUDE.md states this constraint directly.
 *
 * So every control below emits **literal CSS declarations** into a scoped `<style>` element.
 * That is independent of the build entirely: it works without a rebuild, works for values the
 * design system never anticipated, and cannot be invalidated by a future change to the
 * `@source` globs.
 *
 * ## Why the CSS is scoped, and how
 *
 * Custom CSS that can reach outside its own block is how a design system gets quietly forked —
 * the exact failure CLAUDE.md's "Reuse before you build" section exists to prevent. Every rule
 * emitted here is therefore prefixed with a generated `.rl-d-{hash}` class that is applied to
 * that block instance's own wrapper, and the bare `selector` keyword (Elementor's convention,
 * so the idiom is already familiar) expands to that class. A rule an editor writes as
 * `selector .card { … }` becomes `.rl-d-1a2b3c4d .card { … }` and cannot address anything
 * above itself in the tree.
 *
 * The hash is derived from the design payload, so two blocks configured identically share one
 * class and one rule rather than duplicating it.
 *
 * ## Why it hooks `render_block` instead of editing the views
 *
 * Applying this in Blade would mean touching all 57 views and remembering to touch the 58th.
 * `render_block` wraps every block's render callback, ACF blocks included, so one filter
 * covers every block that exists now and every block added later with no per-block wiring.
 *
 * @see BlockDesign::sanitiseCss() for what an editor is not allowed to emit.
 */
class BlockDesign
{
    /**
     * Prefix for every field this class adds, so a design control can never collide with a
     * block's own field names and `describe-page` can tell the two apart at a glance.
     */
    public const PREFIX = 'rl_design_';

    /**
     * The generated per-instance scope class prefix.
     */
    public const SCOPE_PREFIX = 'rl-d-';

    /**
     * Upper bound on a single block's custom CSS, in bytes.
     *
     * Not a security control — sanitiseCss() is. This is a guard against a paste accident
     * putting an entire stylesheet inline on every render of the page.
     */
    public const MAX_CSS_BYTES = 20000;

    /**
     * The block editor's mobile breakpoint for the visibility toggles.
     *
     * 1024px is Tailwind's `lg`, which is the breakpoint this theme's blocks actually switch
     * layout at — see the `lg:` variants throughout `resources/views/blocks/`. Using anything
     * else here would hide a block at a width where its own layout had not yet changed.
     */
    public const BREAKPOINT = 1024;

    /**
     * Named spacing steps, in pixels.
     *
     * Deliberately a short scale rather than a free number field. An editor choosing between
     * six named steps stays on the theme's rhythm; an editor typing `37px` does not.
     */
    public const SPACING = [
        '' => null,
        'none' => 0,
        'xs' => 16,
        'sm' => 32,
        'md' => 56,
        'lg' => 80,
        'xl' => 120,
    ];

    /**
     * Background choices, keyed to the tokens already declared in `resources/css/app.css`.
     *
     * These are resolved to their literal hex here because the emitted CSS is not processed by
     * Tailwind and so cannot reference a `--color-*` custom property that Tailwind generates.
     */
    public const BACKGROUNDS = [
        '' => ['label' => 'Unchanged (block default)', 'value' => null],
        'white' => ['label' => 'White', 'value' => '#FFFFFF'],
        'light' => ['label' => 'Light grey', 'value' => '#F7F7FA'],
        'navy' => ['label' => 'Brand navy', 'value' => '#342567'],
        'dark-violet' => ['label' => 'Dark violet', 'value' => '#25104A'],
        'midnight' => ['label' => 'Midnight', 'value' => '#18112C'],
        'purple' => ['label' => 'Brand purple', 'value' => '#8A2BE2'],
        'custom' => ['label' => 'Custom colour…', 'value' => null],
    ];

    /**
     * Register the editor fields and the front-end filter.
     */
    public static function init(): void
    {
        // Priority 20: ACF Composer registers its blocks on `acf/init` at the default 10, and
        // the location rules below are built by enumerating what it registered. At priority 10
        // the registry would still be empty and the field group would attach to nothing.
        add_action('acf/init', [self::class, 'registerFields'], 20);

        add_filter('render_block', [self::class, 'render'], 10, 2);
    }

    /**
     * Attach the design field group to every `acf/*` block this theme registers.
     */
    public static function registerFields(): void
    {
        if (! function_exists('acf_add_local_field_group')) {
            return;
        }

        $location = array_map(
            fn (string $name): array => [[
                'param' => 'block',
                'operator' => '==',
                'value' => $name,
            ]],
            self::themeBlocks()
        );

        if ($location === []) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_rl_block_design',
            'title' => 'Design',
            'location' => $location,
            // Last, so a block's own content fields stay at the top of the sidebar where an
            // editor expects them. Design is the thing you reach for second.
            'menu_order' => 100,
            'position' => 'normal',
            'style' => 'default',
            'active' => true,
            'description' => 'Per-instance spacing, background, visibility and CSS. '.
                'Leave everything blank to render the block exactly as designed.',
            'fields' => self::fields(),
        ]);
    }

    /**
     * The design fields themselves.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fields(): array
    {
        $p = self::PREFIX;

        $spacingChoices = [
            '' => 'Unchanged',
            'none' => 'None (0)',
            'xs' => 'Extra small (16px)',
            'sm' => 'Small (32px)',
            'md' => 'Medium (56px)',
            'lg' => 'Large (80px)',
            'xl' => 'Extra large (120px)',
        ];

        return [
            [
                'key' => 'field_rl_design_tab_layout',
                'label' => 'Layout',
                'name' => '',
                'type' => 'tab',
                'placement' => 'top',
            ],
            [
                'key' => 'field_rl_design_space_top',
                'label' => 'Space above',
                'name' => $p.'space_top',
                'type' => 'select',
                'choices' => $spacingChoices,
                'default_value' => '',
                'allow_null' => 0,
                'instructions' => 'Overrides the padding the block ships with. '.
                    'Use this to close a gap between two stacked sections.',
            ],
            [
                'key' => 'field_rl_design_space_bottom',
                'label' => 'Space below',
                'name' => $p.'space_bottom',
                'type' => 'select',
                'choices' => $spacingChoices,
                'default_value' => '',
                'allow_null' => 0,
            ],
            [
                'key' => 'field_rl_design_bg',
                'label' => 'Background',
                'name' => $p.'bg',
                'type' => 'select',
                'choices' => array_map(fn (array $b): string => $b['label'], self::BACKGROUNDS),
                'default_value' => '',
                'allow_null' => 0,
                'instructions' => 'Note that text colour does not follow the background. '.
                    'A block designed for a light surface will not become legible on a dark one '.
                    'just because the background changed — most blocks have their own light/dark '.
                    'setting, and that is the one to use.',
            ],
            [
                'key' => 'field_rl_design_bg_custom',
                'label' => 'Custom background colour',
                'name' => $p.'bg_custom',
                'type' => 'color_picker',
                'conditional_logic' => [[[
                    'field' => 'field_rl_design_bg',
                    'operator' => '==',
                    'value' => 'custom',
                ]]],
            ],
            [
                'key' => 'field_rl_design_tab_visibility',
                'label' => 'Visibility',
                'name' => '',
                'type' => 'tab',
                'placement' => 'top',
            ],
            [
                'key' => 'field_rl_design_hide_mobile',
                'label' => 'Hide on mobile',
                'name' => $p.'hide_mobile',
                'type' => 'true_false',
                'ui' => 1,
                'instructions' => 'Hidden below '.self::BREAKPOINT.'px. The block is still '.
                    'present in the HTML and still downloads its images — this hides it, it does '.
                    'not make the page lighter.',
            ],
            [
                'key' => 'field_rl_design_hide_desktop',
                'label' => 'Hide on desktop',
                'name' => $p.'hide_desktop',
                'type' => 'true_false',
                'ui' => 1,
                'instructions' => 'Hidden at '.self::BREAKPOINT.'px and above.',
            ],
            [
                'key' => 'field_rl_design_tab_advanced',
                'label' => 'Advanced',
                'name' => '',
                'type' => 'tab',
                'placement' => 'top',
            ],
            [
                'key' => 'field_rl_design_anchor',
                'label' => 'Anchor ID',
                'name' => $p.'anchor',
                'type' => 'text',
                'instructions' => 'Link to this section with #your-id. Letters, numbers and '.
                    'hyphens only.',
                'placeholder' => 'pricing',
            ],
            [
                'key' => 'field_rl_design_classes',
                'label' => 'Extra CSS classes',
                'name' => $p.'classes',
                'type' => 'text',
                'instructions' => 'Added to the block wrapper. These are hooks for the custom '.
                    'CSS below or for a stylesheet — typing a Tailwind utility here does nothing, '.
                    'because Tailwind only compiles classes it can find in the theme source.',
            ],
            [
                'key' => 'field_rl_design_css',
                'label' => 'Custom CSS',
                'name' => $p.'css',
                'type' => 'textarea',
                'rows' => 8,
                'instructions' => 'Use <code>selector</code> to mean this block. '.
                    'Example: <code>selector .rl-card { border-radius: 24px; }</code>. '.
                    'Rules are scoped to this block and cannot affect the rest of the page. '.
                    '@import, @charset and anything script-like are stripped.',
            ],
        ];
    }

    /**
     * Apply a block instance's design settings to its rendered HTML.
     *
     * @param  string  $content  The block's rendered HTML.
     * @param  array<string, mixed>  $block  The parsed block.
     */
    public static function render(string $content, array $block): string
    {
        $name = $block['blockName'] ?? '';

        if (! is_string($name) || ! str_starts_with($name, 'acf/') || trim($content) === '') {
            return $content;
        }

        $data = $block['attrs']['data'] ?? [];
        $design = is_array($data) ? self::extract($data) : [];

        // Gutenberg's own Advanced → "Additional CSS class(es)" field. It is shown on every
        // block whether or not anything consumes it, and until now nothing did: the views
        // discard ACF Composer's attribute bag, so a class typed there saved correctly and
        // then vanished at render. Honouring it here is the smaller half of this class's job
        // but the more surprising bug to leave in place.
        $className = $block['attrs']['className'] ?? '';

        if ($design === [] && ! is_string($className)) {
            return $content;
        }

        $classes = array_filter(array_merge(
            preg_split('/\s+/', is_string($className) ? $className : '') ?: [],
            preg_split('/\s+/', (string) ($design['classes'] ?? '')) ?: []
        ));

        $css = '';
        $scope = '';

        // Only mint a scope class when there is something to scope. A block carrying nothing
        // but a hand-typed className should not gain a meaningless generated class too.
        if ($design !== []) {
            $scope = self::SCOPE_PREFIX.substr(md5(serialize($design)), 0, 8);
            $css = self::compile($design, $scope);
            array_unshift($classes, $scope);
        }

        if ($classes === [] && $css === '' && ($design['anchor'] ?? '') === '') {
            return $content;
        }

        $html = self::applyAttributes(
            $content,
            implode(' ', array_unique($classes)),
            self::sanitiseAnchor((string) ($design['anchor'] ?? ''))
        );

        return $css === '' ? $html : $html.'<style>'.$css.'</style>';
    }

    /**
     * Pull the design values out of a block's ACF data, dropping empties.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function extract(array $data): array
    {
        $design = [];

        foreach ($data as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, self::PREFIX)) {
                continue;
            }

            // A false toggle and an unset select both mean "leave this block alone", so they
            // are dropped here. That keeps the hash — and therefore the scope class — stable
            // for a block whose design panel was opened and closed without a change.
            if ($value === '' || $value === null || $value === false || $value === 0 || $value === '0') {
                continue;
            }

            $design[substr($key, strlen(self::PREFIX))] = $value;
        }

        return $design;
    }

    /**
     * Build the scoped stylesheet for one block instance.
     *
     * @param  array<string, mixed>  $design
     */
    public static function compile(array $design, string $scope): string
    {
        $sel = '.'.$scope;
        $declarations = [];
        $rules = [];

        if (($top = self::spacing($design['space_top'] ?? null)) !== null) {
            $declarations[] = "padding-top:{$top}px";
        }

        if (($bottom = self::spacing($design['space_bottom'] ?? null)) !== null) {
            $declarations[] = "padding-bottom:{$bottom}px";
        }

        if (($bg = self::background($design)) !== null) {
            $declarations[] = "background-color:{$bg}";
        }

        if ($declarations !== []) {
            // !important is load-bearing rather than lazy: the block's own padding and
            // background arrive as Tailwind utility classes on the same element, and a single
            // generated class has no more specificity than they do. Without it the override
            // would win or lose on stylesheet order, which is not something an editor can see
            // or reason about.
            $rules[] = $sel.'{'.implode(';', array_map(
                fn (string $d): string => $d.' !important',
                $declarations
            )).'}';
        }

        if (! empty($design['hide_mobile'])) {
            $max = self::BREAKPOINT - 0.02;
            $rules[] = "@media (max-width:{$max}px){{$sel}{display:none !important}}";
        }

        if (! empty($design['hide_desktop'])) {
            $min = self::BREAKPOINT;
            $rules[] = "@media (min-width:{$min}px){{$sel}{display:none !important}}";
        }

        if (($custom = self::sanitiseCss((string) ($design['css'] ?? ''), $scope)) !== '') {
            $rules[] = $custom;
        }

        return implode('', $rules);
    }

    /**
     * Resolve a named spacing step to pixels, or null to leave the block's own padding alone.
     */
    private static function spacing(mixed $key): ?int
    {
        if (! is_string($key)) {
            return null;
        }

        return self::SPACING[$key] ?? null;
    }

    /**
     * Resolve the chosen background to a literal colour.
     *
     * @param  array<string, mixed>  $design
     */
    private static function background(array $design): ?string
    {
        $choice = $design['bg'] ?? '';

        if (! is_string($choice) || $choice === '') {
            return null;
        }

        if ($choice === 'custom') {
            $custom = (string) ($design['bg_custom'] ?? '');

            return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $custom) === 1
                ? $custom
                : null;
        }

        return self::BACKGROUNDS[$choice]['value'] ?? null;
    }

    /**
     * Reduce an anchor to something safe to put in an `id` attribute.
     */
    public static function sanitiseAnchor(string $anchor): ?string
    {
        $clean = strtolower(preg_replace('/[^A-Za-z0-9_-]/', '-', trim($anchor)) ?? '');
        $clean = trim((string) preg_replace('/-+/', '-', $clean), '-');

        return $clean === '' ? null : $clean;
    }

    /**
     * Scope an editor's CSS to this block and strip what it must not be able to emit.
     *
     * The threat here is not a malicious editor — anyone with `edit_pages` has far more direct
     * routes than a stylesheet. It is an editor pasting a snippet from the internet without
     * reading it. Each removal below is one such snippet:
     *
     * - `</style>` would close the element early and let everything after it be parsed as HTML,
     *   which turns a CSS field into an HTML field and therefore a script field.
     * - `@import` and `@charset` fetch a remote stylesheet on every page view: a third-party
     *   request, a render-blocking one, and a way to change the page after review.
     * - `expression()` is old IE script-in-CSS, and `javascript:`/`vbscript:` URLs still
     *   execute in some contexts.
     * - `behavior`/`-moz-binding` bind script to an element.
     *
     * Everything else is allowed through deliberately: the point of the field is to permit CSS
     * the design system did not anticipate, and an allowlist of properties would defeat it.
     */
    public static function sanitiseCss(string $css, string $scope): string
    {
        $css = trim($css);

        if ($css === '') {
            return '';
        }

        if (strlen($css) > self::MAX_CSS_BYTES) {
            $css = substr($css, 0, self::MAX_CSS_BYTES);
        }

        // Comments first: they are the easiest place to hide a `</style>` from the patterns
        // below, and nothing downstream needs them.
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        $css = (string) preg_replace('#</\s*style#i', '', $css);
        $css = (string) preg_replace('/@(import|charset)\b[^;{}]*;?/i', '', $css);
        $css = (string) preg_replace('/expression\s*\(/i', '(', $css);
        $css = (string) preg_replace('/(javascript|vbscript|data)\s*:/i', '', $css);
        $css = (string) preg_replace('/(behavior|-moz-binding)\s*:[^;}]*/i', '', $css);

        // `selector` is the Elementor idiom for "this block". Replacing it with the generated
        // class is what makes the rules scoped; the word-boundary check keeps it from matching
        // inside a longer identifier such as `.my-selector`.
        $css = (string) preg_replace('/(?<![\w.#-])selector(?![\w-])/', '.'.$scope, $css);

        // Bound the output as well as the input. Scoping expands the text — every `selector`
        // becomes a longer class — so a payload that fit the cap on the way in can exceed it on
        // the way out, and it is the emitted bytes that end up on the page. Truncating here can
        // cut a declaration in half; for a paste this size that is the desired outcome, and an
        // unterminated rule is discarded by the browser rather than misapplied.
        if (strlen($css) > self::MAX_CSS_BYTES) {
            $css = substr($css, 0, self::MAX_CSS_BYTES);
        }

        return trim($css);
    }

    /**
     * Merge a class list and an optional id into the outermost element of a block's HTML.
     *
     * Done with a regex on the first tag rather than a DOM parse because a block's rendered
     * output is an HTML fragment, and every DOM parser in PHP either wraps a fragment in
     * `<html><body>` or rejects it. The fragment is this theme's own Blade output, not
     * arbitrary input, so the shape is known: the first `<tag` is the wrapper.
     */
    public static function applyAttributes(string $html, string $classes, ?string $id): string
    {
        if ($classes === '' && $id === null) {
            return $html;
        }

        // Skip any leading whitespace or comment so the match lands on the real wrapper.
        if (preg_match('/^(\s*(?:<!--.*?-->\s*)*)(<[a-zA-Z][a-zA-Z0-9-]*)/s', $html, $m) !== 1) {
            // No element to attach to — wrap rather than silently dropping the settings, so a
            // block whose view returns a bare string still honours its design panel.
            $attrs = $classes !== '' ? ' class="'.esc_attr($classes).'"' : '';
            $attrs .= $id !== null ? ' id="'.esc_attr($id).'"' : '';

            return '<div'.$attrs.'>'.$html.'</div>';
        }

        $offset = strlen($m[1]) + strlen($m[2]);
        $rest = substr($html, $offset);

        // The attribute region of the opening tag, up to the first unquoted `>`.
        if (preg_match('/^((?:"[^"]*"|\'[^\']*\'|[^>])*)>/s', $rest, $tag) !== 1) {
            return $html;
        }

        $attributes = $tag[1];

        if ($classes !== '') {
            if (preg_match('/\sclass\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attributes, $existing) === 1) {
                $current = $existing[2] !== '' ? $existing[2] : ($existing[3] ?? '');
                $attributes = str_replace(
                    $existing[0],
                    ' class="'.esc_attr(trim($current.' '.$classes)).'"',
                    $attributes
                );
            } else {
                $attributes .= ' class="'.esc_attr($classes).'"';
            }
        }

        // An id the block already rendered wins — it is likely a scroll target something else
        // already links to, and replacing it would break that link.
        if ($id !== null && preg_match('/\sid\s*=\s*["\']/i', $attributes) !== 1) {
            $attributes .= ' id="'.esc_attr($id).'"';
        }

        return $m[1].$m[2].$attributes.'>'.substr($rest, strlen($tag[0]));
    }

    /**
     * Every block registered by this theme, as `acf/<slug>`.
     *
     * Read off the block registry rather than the `app/Blocks/` directory so a block that
     * fails to register does not silently get a design panel that goes nowhere.
     *
     * @return array<int, string>
     */
    public static function themeBlocks(): array
    {
        if (! class_exists(WP_Block_Type_Registry::class)) {
            return [];
        }

        $blocks = [];

        foreach (WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type) {
            if (! str_starts_with($name, 'acf/')) {
                continue;
            }

            if (($type->category ?? null) !== 'remote-leverage') {
                continue;
            }

            $blocks[] = $name;
        }

        sort($blocks);

        return $blocks;
    }
}
