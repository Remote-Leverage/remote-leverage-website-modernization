<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin\Seo;

/**
 * Presents Yoast as "SEO" — our menu entry, not the vendor's.
 *
 * Yoast registers its menu on `admin_menu` at priority 5 and hardcodes both the label and a
 * base64 SVG icon, so neither is filterable; the only seam is to rewrite the `$menu` and
 * `$submenu` globals afterwards. Hence priority 100.
 *
 * The icon is a dashicon rather than a custom SVG on purpose. WordPressAdminTheme colours the
 * admin menu through `#adminmenu div.wp-menu-image:before` — the dashicon *font glyph* — with
 * distinct resting, hover and current-item colours. A data-URI SVG renders as a background
 * image on the same element and inherits none of that, so it would sit there in one fixed
 * colour while every neighbouring icon responded to hover. `dashicons-search` picks up the
 * existing treatment for free and matches the weight of `dashicons-groups` next to it.
 */
class SeoMenu
{
    /** Yoast's top-level menu slug. */
    public const PARENT_SLUG = 'wpseo_dashboard';

    /** What we call it. */
    public const LABEL = 'SEO';

    public const ICON = 'dashicons-search';

    /**
     * Pages removed outright: Yoast's storefront, its course catalogue and its Premium teasers.
     *
     * Every one of these is either a paid feature the free plugin only advertises (Workouts and
     * Redirects render a locked screen with an upgrade button) or a Yoast-branded marketing
     * surface. Slugs verified against the integrations that register them — `PAGE` constants in
     * src/integrations/{academy,support}-integration.php,
     * src/integrations/admin/{workouts,redirects-page,brand-insights-page}.php and
     * src/plans/user-interface/{plans-page,upgrade-sidebar-menu}-integration.php.
     *
     * @var list<string>
     */
    private const REMOVED_SUBMENUS = [
        'wpseo_page_academy',
        'wpseo_page_support',
        'wpseo_licenses',
        'wpseo_page_plans',
        'wpseo_upgrade_sidebar',
        'wpseo_workouts',
        'wpseo_redirects',
        // The redirects *tools* page registers under its own slug and is a separate menu entry
        // from wpseo_redirects — found by reading the rendered submenu, not the source.
        'wpseo_redirects_tools',
        'wpseo_brand_insights',
        'wpseo_brand_insights_premium',
    ];

    /**
     * Submenu labels that still say "Yoast", keyed by slug.
     *
     * @var array<string, string>
     */
    private const RELABELLED_SUBMENUS = [
        self::PARENT_SLUG => 'Overview',
    ];

    public function register(): void
    {
        // PHP_INT_MAX so this runs after every integration that contributes a page — the
        // upgrade nag and the brand-insights upsell both add themselves at PHP_INT_MAX - 1.
        add_filter('wpseo_submenu_pages', [$this, 'removeCommercialPages'], PHP_INT_MAX);
        add_filter('wpseo_network_submenu_pages', [$this, 'removeCommercialPages'], PHP_INT_MAX);

        // Yoast's own switch for the HelpScout support widget — the magenta circle it pins to
        // the bottom-right of every SEO screen. A filter, so the beacon is never rendered or
        // its script enqueued, rather than hidden after the fact.
        add_filter('wpseo_helpscout_show_beacon', '__return_false');

        add_action('admin_menu', [$this, 'rebrand'], 100);
        add_action('admin_bar_menu', [$this, 'rebrandAdminBar'], 100);
        add_filter('admin_title', [$this, 'filterAdminTitle'], 10, 2);
    }

    /**
     * Drop the premium and marketing pages before Yoast ever registers them.
     *
     * Filtering `wpseo_submenu_pages` means these screens are never added to the admin at all,
     * so they are gone from the menu *and* unreachable by URL. Hiding them in CSS would have
     * left both the route and the upsell behind it live.
     *
     * Index 4 of each entry is the page slug — see WPSEO_Base_Menu::get_submenu_page().
     *
     * @param  array<int, array<int, mixed>>  $pages
     * @return array<int, array<int, mixed>>
     */
    public function removeCommercialPages(array $pages): array
    {
        $kept = array_filter(
            $pages,
            static fn ($page): bool => ! is_array($page)
                || ! in_array($page[4] ?? '', self::REMOVED_SUBMENUS, true)
        );

        // Yoast indexes into this array positionally when it picks a fallback landing page for
        // users without the full capability, so it has to stay a list.
        return array_values($kept);
    }

    /**
     * Rewrite the registered menu in place.
     */
    public function rebrand(): void
    {
        global $menu, $submenu;

        if (is_array($menu)) {
            foreach ($menu as $index => $item) {
                if (($item[2] ?? null) !== self::PARENT_SLUG) {
                    continue;
                }

                // Yoast appends a notification-count bubble to the label. That badge is real
                // information — "your site is discouraging search engines", say — so it is
                // preserved and only the vendor word in front of it is replaced.
                $menu[$index][0] = self::LABEL.$this->extractCounter((string) $item[0]);
                $menu[$index][6] = self::ICON;
            }
        }

        if (! is_array($submenu)) {
            return;
        }

        // Sweep every parent, not just ours. removeCommercialPages() has already stripped the
        // pages that go through `wpseo_submenu_pages`, but some are registered directly and
        // under a different parent entirely: "Yoast Redirects" is an add_management_page() stub
        // hanging off tools.php whose only job is to redirect to the Premium screen, so looking
        // only under wpseo_dashboard left it on the menu.
        foreach ($submenu as $parent => $items) {
            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $index => $item) {
                $slug = $item[2] ?? '';

                if (in_array($slug, self::REMOVED_SUBMENUS, true)) {
                    unset($submenu[$parent][$index]);

                    continue;
                }

                // Labels are only ours to rewrite inside the SEO menu.
                if ($parent !== self::PARENT_SLUG) {
                    continue;
                }

                $submenu[$parent][$index][0] = self::RELABELLED_SUBMENUS[$slug]
                    ?? self::stripVendor((string) $item[0]);
            }

            // Re-index so the array stays a list after the unsets above.
            $submenu[$parent] = array_values($submenu[$parent]);
        }
    }

    /**
     * Admin-bar nodes that are storefront, not function.
     *
     * Ids read off the rendered toolbar rather than the source — `wpseo_brand_insights` uses
     * underscores where every sibling uses hyphens, which is not something you would guess.
     *
     * @var list<string>
     */
    private const REMOVED_ADMIN_BAR_NODES = [
        'wpseo-academy',
        'wpseo-get-premium',
        'wpseo_brand_insights',
    ];

    /**
     * Clean up the toolbar's SEO menu: our icon, our name, none of the storefront.
     *
     * The menu removals here are separate from the admin-menu ones — WP_Admin_Bar keeps its own
     * node tree, so stripping `wpseo_submenu_pages` does nothing to the toolbar. That is why
     * Academy, Upgrade and AI Brand Insights survived the first pass.
     */
    public function rebrandAdminBar(\WP_Admin_Bar $bar): void
    {
        foreach (self::REMOVED_ADMIN_BAR_NODES as $id) {
            $bar->remove_node($id);
        }

        $node = $bar->get_node('wpseo-menu');

        if ($node === null) {
            return;
        }

        $bar->add_node([
            'id' => 'wpseo-menu',
            'title' => self::rebrandBarTitle(self::nodeTitle($node)),
        ]);
    }

    /**
     * Swap the Yoast mark in the toolbar for a dashicon, keeping everything after it.
     *
     * The title is a concatenation — logo, then the current page's score badge, the notification
     * counter and the notification popup — so it cannot simply be replaced wholesale without
     * losing three live pieces of UI. The logo itself is a `<div id="yoast-ab-icon">` carrying a
     * base64 SVG background, and `.ab-icon` is what WordPress expects a toolbar glyph to be.
     */
    public static function rebrandBarTitle(string $title): string
    {
        $withoutLogo = preg_replace(
            '/<div id="yoast-ab-icon".*?<\/div>/s',
            '<span class="ab-icon dashicons dashicons-search" aria-hidden="true"></span>',
            $title
        ) ?? $title;

        return self::stripVendor($withoutLogo);
    }

    /**
     * Read a node's title regardless of whether it arrives as an object or an array.
     *
     * WP_Admin_Bar::get_node() returns a stdClass in core; the theme's test stub returns an
     * array, and enough existing tests depend on that shape that changing it is not worth the
     * churn. Accepting both keeps this method exercisable without a live WordPress.
     *
     * @param  object|array<string, mixed>  $node
     */
    private static function nodeTitle(object|array $node): string
    {
        if (is_array($node)) {
            return (string) ($node['title'] ?? '');
        }

        return (string) ($node->title ?? '');
    }

    /**
     * Yoast builds `<title>` as "Yoast SEO: Dashboard" / "Settings - Yoast SEO".
     *
     * @param  string  $adminTitle  The full document title.
     * @param  string  $title  The screen's own title.
     */
    public function filterAdminTitle(string $adminTitle, string $title): string
    {
        return self::stripVendor($adminTitle);
    }

    /**
     * Drop the vendor name from a label without mangling the rest of it.
     *
     * Handles the four shapes Yoast ships: "Yoast SEO: Dashboard", "Settings - Yoast SEO",
     * a bare "Yoast SEO", and "Yoast" on its own.
     */
    public static function stripVendor(string $label): string
    {
        $cleaned = preg_replace(
            [
                '/\bYoast SEO:\s*/u',   // leading "Yoast SEO: "
                '/\s*[-–]\s*Yoast SEO\b/u', // trailing " - Yoast SEO"
                '/\bYoast SEO\b/u',     // anything left
                '/\bYoast\b/u',
            ],
            ['', '', self::LABEL, self::LABEL],
            $label
        ) ?? $label;

        $cleaned = trim($cleaned);

        return $cleaned === '' ? self::LABEL : $cleaned;
    }

    /**
     * Pull Yoast's notification-count markup off a menu label so it can be re-attached.
     */
    private function extractCounter(string $label): string
    {
        if (preg_match('/<span class="update-plugins.*<\/span>\s*$/us', $label, $matches) === 1) {
            return ' '.$matches[0];
        }

        return '';
    }
}
