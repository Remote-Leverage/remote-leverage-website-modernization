<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin\Seo;

/**
 * Loads the Yoast skin and strips the parts of Yoast we do not ship.
 *
 * Ordering matters more than it looks. Yoast emits every utility with `!important`, so an
 * override needs higher specificity *and* a later position in the cascade. SeoSkinStyles
 * handles the specificity; this class handles the position, by registering a src-less style
 * handle that declares the Yoast sheets as dependencies. WordPress then prints our inline
 * CSS after them. Hanging the CSS off 'wp-admin' instead — the way AdminDesignSystem does,
 * which is fine for screens Yoast never touches — would print it *before* Yoast and leave
 * the result depending entirely on specificity ties.
 *
 * Only unambiguously promotional sheets are dequeued. The structural ones (tailwind,
 * new-settings, monorepo) stay: the React screens have no layout at all without them.
 */
class SeoAdminSkin
{
    /** Handle for our virtual stylesheet. */
    private const HANDLE = 'rl-seo-skin';

    /**
     * Yoast sheets we drop outright.
     *
     * Every one of these is promotional or belongs to a surface we remove — nothing here
     * carries layout for a screen that survives. The seasonal banner and the feature-
     * announcement modals are pure marketing; the dashboard pair is dead weight once
     * SeoDashboardWidget has removed the widget they were styling.
     *
     * @var list<string>
     */
    private const DEQUEUED_STYLES = [
        'yoast-seo-black-friday-banner',
        'yoast-seo-introductions',
        'yoast-seo-wp-dashboard',
    ];

    /**
     * Yoast scripts we drop outright.
     *
     * `dashboard-widget` renders into the container SeoDashboardWidget removes, so it would
     * mount a React app against a node that is not on the page.
     *
     * @var list<string>
     */
    private const DEQUEUED_SCRIPTS = [
        'yoast-seo-dashboard-widget',
        'yoast-seo-introductions',
    ];

    /**
     * Yoast sheets our overrides have to outrank, declared as dependencies so they print first.
     *
     * Missing handles are filtered out before registration — Yoast only enqueues a subset per
     * screen, and naming an unregistered dependency makes WordPress silently skip our sheet
     * entirely, which would look like the skin "randomly not applying" on some screens.
     *
     * @var list<string>
     */
    private const SKIN_DEPENDENCIES = [
        'yoast-seo-tailwind',
        'yoast-seo-monorepo',
        'yoast-seo-new-settings',
        'yoast-seo-admin-global',
        'yoast-seo-metabox-css',
        'yoast-seo-edit-page',
        'yoast-seo-inside-editor',
        'yoast-seo-alert',
        'yoast-seo-notifications',
        'yoast-seo-toggle-switch',
        'yoast-seo-tooltips',
        'yoast-seo-academy',
        'yoast-seo-support',
        'yoast-seo-workouts',
        'yoast-seo-general-page',
    ];

    public function register(): void
    {
        // Late enough that Yoast has enqueued and we can see what is actually on the screen.
        add_action('admin_enqueue_scripts', [$this, 'enqueue'], 100);
    }

    public function enqueue(): void
    {
        $this->dequeueYoastExtras();

        $css = $this->cssForCurrentScreen();

        if ($css === '') {
            return;
        }

        $deps = array_values(array_filter(
            self::SKIN_DEPENDENCIES,
            static fn (string $handle): bool => wp_style_is($handle, 'registered')
        ));

        wp_register_style(self::HANDLE, false, $deps);
        wp_enqueue_style(self::HANDLE);
        wp_add_inline_style(self::HANDLE, SeoSkinStyles::tokens()."\n".$css);
    }

    /**
     * The skin each screen needs, or '' for screens Yoast does not touch.
     *
     * The editor slice is deliberately also loaded on the post/page list tables: the SEO and
     * readability columns render the same score bullets there as the editor does.
     */
    private function cssForCurrentScreen(): string
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (! $screen instanceof \WP_Screen) {
            return '';
        }

        if ($screen->id === 'dashboard') {
            return SeoSkinStyles::widgetCss();
        }

        if (str_contains($screen->id, 'wpseo')) {
            return SeoSkinStyles::appCss();
        }

        if (in_array($screen->base, ['post', 'edit', 'term', 'edit-tags'], true)) {
            return SeoSkinStyles::editorCss();
        }

        return '';
    }

    private function dequeueYoastExtras(): void
    {
        foreach (self::DEQUEUED_STYLES as $handle) {
            wp_dequeue_style($handle);
        }

        foreach (self::DEQUEUED_SCRIPTS as $handle) {
            wp_dequeue_script($handle);
        }
    }
}
