<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\WordPress\Admin\Seo\SeoAdmin;
use App\Infrastructure\WordPress\Admin\Seo\SeoAdminSkin;
use App\Infrastructure\WordPress\Admin\Seo\SeoDashboardWidget;
use App\Infrastructure\WordPress\Admin\Seo\SeoEditor;
use App\Infrastructure\WordPress\Admin\Seo\SeoMenu;
use App\Infrastructure\WordPress\Admin\Seo\SeoSkinStyles;
use App\Infrastructure\WordPress\Admin\Seo\SeoStats;
use App\Infrastructure\WordPress\Admin\WordPressAdminTheme;
use Illuminate\Support\Facades\Cache;

/** The plugin directory, relative to the theme. Absent on a checkout without plugins installed. */
function yoastCssDir(): string
{
    return dirname(__DIR__, 4).'/plugins/wordpress-seo/css/dist';
}

describe('SeoMenu', function () {
    it('strips the vendor name from every label shape Yoast ships', function () {
        expect(SeoMenu::stripVendor('Yoast SEO: Dashboard'))->toBe('Dashboard')
            ->and(SeoMenu::stripVendor('Settings - Yoast SEO'))->toBe('Settings')
            ->and(SeoMenu::stripVendor('Yoast SEO'))->toBe('SEO')
            ->and(SeoMenu::stripVendor('Yoast'))->toBe('SEO');
    });

    it('never returns an empty label', function () {
        expect(SeoMenu::stripVendor('Yoast SEO'))->not->toBe('')
            ->and(SeoMenu::stripVendor(''))->toBe('SEO');
    });

    it('renames the top-level menu to SEO and swaps the icon', function () {
        $GLOBALS['menu'] = [
            ['Posts', 'edit_posts', 'edit.php', '', '', '', 'dashicons-admin-post'],
            ['Yoast SEO', 'wpseo_manage_options', 'wpseo_dashboard', '', '', '', 'data:image/svg+xml;base64,AAAA'],
        ];
        $GLOBALS['submenu'] = [];

        (new SeoMenu)->rebrand();

        expect($GLOBALS['menu'][1][0])->toBe('SEO')
            ->and($GLOBALS['menu'][1][6])->toBe('dashicons-search')
            // Untouched neighbours stay untouched.
            ->and($GLOBALS['menu'][0][0])->toBe('Posts')
            ->and($GLOBALS['menu'][0][6])->toBe('dashicons-admin-post');
    });

    it('keeps the notification counter attached to the renamed label', function () {
        $counter = '<span class="update-plugins count-3"><span class="plugin-count">3</span></span>';

        $GLOBALS['menu'] = [['Yoast SEO '.$counter, 'cap', 'wpseo_dashboard', '', '', '', 'icon']];
        $GLOBALS['submenu'] = [];

        (new SeoMenu)->rebrand();

        expect($GLOBALS['menu'][0][0])->toStartWith('SEO')
            ->toContain('update-plugins count-3');
    });

    it('unregisters every premium and marketing page through Yoast own filter', function () {
        // Index 4 is the slug — WPSEO_Base_Menu::get_submenu_page().
        $pages = [
            ['wpseo_dashboard', '', 'Settings', 'cap', 'wpseo_page_settings', 'cb'],
            ['wpseo_dashboard', '', 'Academy', 'cap', 'wpseo_page_academy', 'cb'],
            ['wpseo_dashboard', '', 'Workouts', 'cap', 'wpseo_workouts', 'cb'],
            ['wpseo_dashboard', '', 'Redirects', 'cap', 'wpseo_redirects', 'cb'],
            ['wpseo_dashboard', '', 'Support', 'cap', 'wpseo_page_support', 'cb'],
            ['wpseo_dashboard', '', 'AI Brand Insights', 'cap', 'wpseo_brand_insights', 'cb'],
            ['wpseo_dashboard', '', 'Upgrade', 'cap', 'wpseo_upgrade_sidebar', 'cb'],
            ['wpseo_dashboard', '', 'Tools', 'cap', 'wpseo_tools', 'cb'],
        ];

        $kept = array_column((new SeoMenu)->removeCommercialPages($pages), 4);

        // Removing these from the filter means they are never registered, so they are gone
        // from the menu and unreachable by URL.
        expect($kept)->toBe(['wpseo_page_settings', 'wpseo_tools']);
    });

    it('keeps the filtered page list a list, since Yoast indexes into it positionally', function () {
        $pages = [
            ['wpseo_dashboard', '', 'Academy', 'cap', 'wpseo_page_academy', 'cb'],
            ['wpseo_dashboard', '', 'Tools', 'cap', 'wpseo_tools', 'cb'],
        ];

        $filtered = (new SeoMenu)->removeCommercialPages($pages);

        expect(array_keys($filtered))->toBe([0]);
    });

    it('leaves malformed filter entries alone rather than dropping them', function () {
        $pages = [['wpseo_dashboard', '', 'Tools', 'cap', 'wpseo_tools', 'cb'], 'not-an-array'];

        expect((new SeoMenu)->removeCommercialPages($pages))->toHaveCount(2);
    });

    it('sweeps up commercial pages registered without the filter', function () {
        $GLOBALS['menu'] = [['Yoast SEO', 'cap', 'wpseo_dashboard', '', '', '', 'icon']];
        $GLOBALS['submenu'] = [
            'wpseo_dashboard' => [
                ['Dashboard', 'cap', 'wpseo_dashboard'],
                ['Settings', 'cap', 'wpseo_page_settings'],
                ['Premium', 'cap', 'wpseo_licenses'],
                ['Tools - Yoast SEO', 'cap', 'wpseo_tools'],
            ],
        ];

        (new SeoMenu)->rebrand();

        $slugs = array_column($GLOBALS['submenu']['wpseo_dashboard'], 2);
        $labels = array_column($GLOBALS['submenu']['wpseo_dashboard'], 0);

        expect($slugs)->not->toContain('wpseo_licenses')
            ->and($slugs)->toContain('wpseo_page_settings')
            ->and($labels)->toContain('Overview')
            ->and($labels)->toContain('Tools')
            // Must stay a list after the unset.
            ->and(array_keys($GLOBALS['submenu']['wpseo_dashboard']))->toBe([0, 1, 2]);
    });

    it('switches the toolbar menu off at Yoast own gate', function () {
        $menu = new SeoMenu;

        // WPSEO_Admin_Bar_Menu::register_hooks() bails on this before adding the node or
        // enqueueing its front-end and admin stylesheets.
        expect($menu->disableAdminBarMenu(['enable_admin_bar_menu' => true])['enable_admin_bar_menu'])
            ->toBeFalse()
            // Other keys in the option are left exactly as they were.
            ->and($menu->disableAdminBarMenu(['enable_xml_sitemap' => true]))
            ->toBe(['enable_xml_sitemap' => true, 'enable_admin_bar_menu' => false]);
    });

    it('passes a non-array option straight through', function () {
        // The option can legitimately be false before Yoast has ever written it.
        expect((new SeoMenu)->disableAdminBarMenu(false))->toBeFalse();
    });

    it('removes the toolbar node if something re-enabled it behind the option', function () {
        $bar = new \WP_Admin_Bar;
        $bar->add_node(['id' => 'wpseo-menu', 'title' => 'Yoast SEO']);
        $bar->add_node(['id' => 'site-name', 'title' => 'Remote Leverage']);

        (new SeoMenu)->removeAdminBarMenu($bar);

        expect($bar->get_node('wpseo-menu'))->toBeNull()
            // Nothing else on the toolbar is touched.
            ->and($bar->get_node('site-name'))->not->toBeNull();
    });
});

describe('SeoStats', function () {
    it('maps raw scores onto Yoast score bands at the boundaries', function () {
        expect(SeoStats::bandFor(0))->toBe('na')
            ->and(SeoStats::bandFor(1))->toBe('bad')
            ->and(SeoStats::bandFor(40))->toBe('bad')
            ->and(SeoStats::bandFor(41))->toBe('ok')
            ->and(SeoStats::bandFor(70))->toBe('ok')
            ->and(SeoStats::bandFor(71))->toBe('good')
            ->and(SeoStats::bandFor(100))->toBe('good');
    });

    it('produces percentages that total 100', function () {
        // 3 / 3 / 3 each round to 33.3, which would leave the stacked bar 0.1 short.
        // Rounded to the same precision the values carry — summing IEEE floats of 33.3 and
        // 33.4 lands on 99.99999999999999, which is a property of binary floats, not drift.
        $percentages = SeoStats::percentages(['good' => 3, 'ok' => 3, 'bad' => 3, 'na' => 0]);

        expect(round(array_sum($percentages), 1))->toBe(100.0);
    });

    it('absorbs rounding drift into the largest band', function () {
        $percentages = SeoStats::percentages(['good' => 10, 'ok' => 3, 'bad' => 3, 'na' => 3]);

        expect(round(array_sum($percentages), 1))->toBe(100.0)
            ->and($percentages['good'])->toBeGreaterThan($percentages['ok']);
    });

    it('never lets the stacked bar overflow its track', function () {
        // Every shape below is one the widget can actually be handed.
        foreach ([[1, 1, 1, 0], [3, 3, 3, 1], [7, 0, 0, 0], [1, 2, 3, 4], [10, 3, 3, 3]] as $shape) {
            $percentages = SeoStats::percentages(array_combine(['good', 'ok', 'bad', 'na'], $shape));

            expect(array_sum($percentages))->toBeLessThanOrEqual(100.0001);
        }
    });

    it('returns zeroes rather than dividing by zero on an empty site', function () {
        $percentages = SeoStats::percentages(['good' => 0, 'ok' => 0, 'bad' => 0, 'na' => 0]);

        expect($percentages)->toBe(['good' => 0.0, 'ok' => 0.0, 'bad' => 0.0, 'na' => 0.0]);
    });

    it('grades gap severity as a share of the content measured', function () {
        expect(SeoStats::severity(0, 100))->toBe('ok')
            // Anything non-zero is at least a warning.
            ->and(SeoStats::severity(1, 100))->toBe('warn')
            ->and(SeoStats::severity(24, 100))->toBe('warn')
            ->and(SeoStats::severity(25, 100))->toBe('bad')
            // A zero total must not divide by zero.
            ->and(SeoStats::severity(5, 0))->toBe('ok');
    });

    it('exposes all four of Yoast score bands', function () {
        expect(array_keys(SeoStats::BANDS))->toBe(['good', 'ok', 'bad', 'na']);
    });
});

describe('SeoSkinStyles', function () {
    it('repaints every primary utility Yoast actually ships', function () {
        $dir = yoastCssDir();

        if (! is_dir($dir)) {
            $this->markTestSkipped('Yoast plugin not installed in this checkout.');
        }

        $tailwind = glob($dir.'/tailwind-*.css');
        $tailwind = array_values(array_filter($tailwind, static fn ($p) => ! str_contains($p, '-rtl')));

        if ($tailwind === []) {
            $this->markTestSkipped('Yoast tailwind stylesheet not found.');
        }

        $css = (string) file_get_contents($tailwind[0]);

        preg_match_all('/yst-[a-z-]*primary-\d+/', $css, $matches);
        $shipped = array_unique($matches[0]);

        $skin = SeoSkinStyles::appCss();

        $unhandled = array_values(array_filter(
            $shipped,
            static fn (string $utility): bool => ! str_contains($skin, $utility)
        ));

        // A Yoast upgrade that introduces a new primary-* utility lands here rather than as
        // an unexplained patch of magenta on a screen nobody happened to open.
        expect($unhandled)->toBe([]);
    });

    it('scopes overrides under body so they outrank Yoast !important utilities', function () {
        $skin = SeoSkinStyles::appCss();

        // Yoast emits every utility with !important at specificity (0,1,0). An override that
        // is not additionally qualified loses the tie no matter what order it loads in.
        expect($skin)->toContain('body .yst-bg-primary-500')
            ->and($skin)->not->toMatch('/^\s*\.yst-bg-primary-500/m');
    });

    it('declares the shared tokens the widget markup relies on', function () {
        $tokens = SeoSkinStyles::tokens();

        foreach (['--rl-fg', '--rl-border', '--rl-primary', '--rl-ok', '--rl-warn', '--rl-bad', '--rl-radius-lg'] as $token) {
            expect($tokens)->toContain($token);
        }
    });

    it('styles every pill and dot modifier the widget renders', function () {
        $css = SeoSkinStyles::widgetCss();

        foreach (['is-good', 'is-ok', 'is-bad', 'is-na'] as $modifier) {
            expect($css)->toContain('.rl-seo-dot.'.$modifier);
        }

        foreach (['is-ok', 'is-warn', 'is-bad'] as $modifier) {
            expect($css)->toContain('.rl-seo-pill.'.$modifier);
        }
    });

    it('keeps score bullets colour-coded — that is data, not branding', function () {
        $css = SeoSkinStyles::editorCss();

        expect($css)->toContain('.wpseo-score-icon.good')
            ->and($css)->toContain('.wpseo-score-icon.bad')
            ->and($css)->toContain('.wpseo-score-icon.ok');
    });
});

describe('SeoAdminSkin', function () {
    beforeEach(function () {
        $GLOBALS['rl_dequeued_styles'] = [];
        $GLOBALS['rl_dequeued_scripts'] = [];
        $GLOBALS['rl_registered_styles'] = [];
        $GLOBALS['rl_inline_styles'] = [];
        $GLOBALS['rl_known_styles'] = ['yoast-seo-tailwind', 'yoast-seo-monorepo'];
    });

    it('drops the promotional sheets and the dead dashboard widget assets', function () {
        $GLOBALS['rl_current_screen'] = new \WP_Screen('dashboard', 'dashboard');

        (new SeoAdminSkin)->enqueue();

        expect($GLOBALS['rl_dequeued_styles'])->toContain('yoast-seo-black-friday-banner')
            ->toContain('yoast-seo-introductions')
            ->toContain('yoast-seo-wp-dashboard')
            ->and($GLOBALS['rl_dequeued_scripts'])->toContain('yoast-seo-dashboard-widget');
    });

    it('never dequeues the structural sheets the React screens need for layout', function () {
        $GLOBALS['rl_current_screen'] = new \WP_Screen('seo_page_wpseo_page_settings', 'toplevel_page');

        (new SeoAdminSkin)->enqueue();

        // Without these the settings app has no flex, grid or spacing at all.
        expect($GLOBALS['rl_dequeued_styles'])->not->toContain('yoast-seo-tailwind')
            ->not->toContain('yoast-seo-new-settings')
            ->not->toContain('yoast-seo-monorepo');
    });

    it('declares the Yoast sheets as dependencies so our rules print after them', function () {
        $GLOBALS['rl_current_screen'] = new \WP_Screen('seo_page_wpseo_page_settings', 'toplevel_page');

        (new SeoAdminSkin)->enqueue();

        expect($GLOBALS['rl_registered_styles']['rl-seo-skin'])->toBe(['yoast-seo-tailwind', 'yoast-seo-monorepo']);
    });

    it('filters out dependencies that are not registered on this screen', function () {
        // Naming an unregistered dependency makes WordPress skip the stylesheet silently.
        $GLOBALS['rl_known_styles'] = [];
        $GLOBALS['rl_current_screen'] = new \WP_Screen('seo_page_wpseo_page_settings', 'toplevel_page');

        (new SeoAdminSkin)->enqueue();

        expect($GLOBALS['rl_registered_styles']['rl-seo-skin'])->toBe([]);
    });

    it('loads the editor slice on post list tables, where the score columns render', function () {
        $GLOBALS['rl_current_screen'] = new \WP_Screen('edit-post', 'edit');

        (new SeoAdminSkin)->enqueue();

        expect($GLOBALS['rl_inline_styles']['rl-seo-skin'][0])->toContain('.column-wpseo-score');
    });

    it('adds nothing on screens Yoast does not touch', function () {
        $GLOBALS['rl_current_screen'] = new \WP_Screen('options-general', 'options-general');

        (new SeoAdminSkin)->enqueue();

        expect($GLOBALS['rl_inline_styles'])->toBe([]);
    });
});

describe('SeoDashboardWidget', function () {
    beforeEach(function () {
        $GLOBALS['rl_removed_meta_boxes'] = [];
        $GLOBALS['rl_added_dashboard_widgets'] = [];
    });

    it('removes Yoast widgets and mounts ours in their place', function () {
        (new SeoDashboardWidget)->setupDashboard();

        expect($GLOBALS['rl_removed_meta_boxes'])->toContain('wpseo-dashboard-overview')
            ->toContain('wpseo-wincher-dashboard-overview')
            ->and($GLOBALS['rl_added_dashboard_widgets'])->toContain('rl_seo_overview');
    });

    it('renders the three requested sections without emojis', function () {
        ob_start();
        (new SeoDashboardWidget)->render();
        $output = ob_get_clean();

        expect($output)->toContain('SEO score')
            ->toContain('Content gaps')
            ->toContain('Indexing')
            ->toContain('No meta description')
            ->toContain('XML sitemap');

        $emoji = '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u';
        expect(preg_match($emoji, $output))->toBe(0);
    });

    it('links each score band into a filtered post list', function () {
        Cache::put('rl_seo_widget_stats', [
            'scores' => ['good' => 12, 'ok' => 5, 'bad' => 2, 'na' => 1],
            'total' => 20,
            'gaps' => ['missing_metadesc' => 6, 'missing_title' => 2, 'total' => 20],
            'indexing' => ['noindex' => 1, 'discouraged' => false, 'sitemap' => true, 'indexables' => 20, 'indexable_gap' => 0],
        ], 60);

        ob_start();
        (new SeoDashboardWidget)->render();
        $output = ob_get_clean();

        // seo_filter is Yoast's own list-table query arg (admin/class-meta-columns.php).
        foreach (['good', 'ok', 'bad', 'na'] as $band) {
            expect($output)->toContain('seo_filter='.$band);
        }

        expect($output)->toContain('>12<')
            ->and($output)->toContain('20 published posts');

        Cache::forget('rl_seo_widget_stats');
    });

    it('shows the empty state rather than a zeroed bar on a site with nothing published', function () {
        Cache::put('rl_seo_widget_stats', [
            'scores' => ['good' => 0, 'ok' => 0, 'bad' => 0, 'na' => 0],
            'total' => 0,
            'gaps' => ['missing_metadesc' => 0, 'missing_title' => 0, 'total' => 0],
            'indexing' => ['noindex' => 0, 'discouraged' => false, 'sitemap' => true, 'indexables' => 0, 'indexable_gap' => 0],
        ], 60);

        ob_start();
        (new SeoDashboardWidget)->render();
        $output = ob_get_clean();

        expect($output)->toContain('Nothing published yet')
            ->and($output)->not->toContain('seo_filter=');

        Cache::forget('rl_seo_widget_stats');
    });

    it('escalates the discouraged-search-engines state above everything else', function () {
        Cache::put('rl_seo_widget_stats', [
            'scores' => ['good' => 20, 'ok' => 0, 'bad' => 0, 'na' => 0],
            'total' => 20,
            'gaps' => ['missing_metadesc' => 0, 'missing_title' => 0, 'total' => 20],
            'indexing' => ['noindex' => 0, 'discouraged' => true, 'sitemap' => true, 'indexables' => 20, 'indexable_gap' => 0],
        ], 60);

        ob_start();
        (new SeoDashboardWidget)->render();
        $output = ob_get_clean();

        // Every score is green here; the site is still invisible. That has to be said plainly.
        expect($output)->toContain('Search engines are discouraged')
            ->and($output)->toContain('rl-seo-pill is-bad');

        Cache::forget('rl_seo_widget_stats');
    });
});

describe('SeoAdmin', function () {
    it('registers nothing when Yoast is not installed', function () {
        // WPSEO_VERSION is undefined in the test process, so this is the real code path.
        expect(SeoAdmin::pluginActive())->toBeFalse();

        $GLOBALS['rl_added_dashboard_widgets'] = [];

        (new SeoAdmin)->register();

        // A missing plugin must leave no trace — not a widget full of zeroes.
        expect($GLOBALS['rl_added_dashboard_widgets'])->toBe([]);
    });
});

describe('SeoEditor', function () {
    it('renames the metabox heading without re-registering the box', function () {
        $GLOBALS['wp_meta_boxes'] = [
            'post' => [
                'normal' => [
                    'high' => [
                        'wpseo_meta' => [
                            'id' => 'wpseo_meta',
                            'title' => 'Yoast SEO',
                            'callback' => 'cb',
                            // Dropping this would strand the box in the block editor's
                            // compatibility panel, which is why the title is edited in place.
                            'args' => ['__block_editor_compatible_meta_box' => true],
                        ],
                        'other_box' => ['id' => 'other_box', 'title' => 'Yoast SEO lookalike'],
                    ],
                ],
            ],
        ];

        (new SeoEditor)->renameMetabox();

        $box = $GLOBALS['wp_meta_boxes']['post']['normal']['high']['wpseo_meta'];

        expect($box['title'])->toBe('SEO')
            ->and($box['args'])->toBe(['__block_editor_compatible_meta_box' => true])
            ->and($box['callback'])->toBe('cb')
            // Only wpseo_meta is ours to touch.
            ->and($GLOBALS['wp_meta_boxes']['post']['normal']['high']['other_box']['title'])
            ->toBe('Yoast SEO lookalike');
    });

    it('survives a malformed metabox registry', function () {
        $GLOBALS['wp_meta_boxes'] = ['post' => ['normal' => ['high' => false]], 'page' => null];

        (new SeoEditor)->renameMetabox();
    })->throwsNoExceptions();

    it('replaces Yoast glyphs with the theme Lucide icons, keeping the accessible label', function () {
        $columns = [
            'title' => 'Title',
            'wpseo-score' => '<span class="yoast-column-seo-score yoast-column-header-has-tooltip" data-tooltip-text="SEO score"><span class="screen-reader-text">SEO score</span></span>',
            'wpseo-score-readability' => '<span class="yoast-column-readability yoast-column-header-has-tooltip"></span>',
            'wpseo-links' => '<span class="yoast-linked-to yoast-column-header-has-tooltip"></span>',
            'wpseo-linked' => '<span class="yoast-linked-from yoast-column-header-has-tooltip"></span>',
        ];

        $relabelled = (new SeoEditor)->relabelColumns($columns);

        foreach (['wpseo-score', 'wpseo-score-readability', 'wpseo-links', 'wpseo-linked'] as $key) {
            expect($relabelled[$key])
                // The theme's Lucide construction, same as MarketingDashboard's icons.
                ->toContain('stroke="currentColor"')
                ->toContain('viewBox="0 0 24 24"')
                ->toContain('stroke-linecap="round"')
                ->toContain('class="rl-seo-col-icon"')
                // Decorative glyph, named by the sibling span.
                ->toContain('aria-hidden="true"')
                ->toContain('screen-reader-text')
                ->toContain('data-rl-tip')
                // Yoast's own tooltip hook is gone, so its upward tooltip cannot fire too.
                ->not->toContain('yoast-column-header-has-tooltip');
        }

        // A native title would stack a second tooltip in a different place.
        expect($relabelled['wpseo-score'])->not->toContain('title=')
            ->and($relabelled['title'])->toBe('Title');
    });

    it('keeps each column accessible name distinct', function () {
        $columns = array_fill_keys(
            ['wpseo-score', 'wpseo-score-readability', 'wpseo-links', 'wpseo-linked'],
            '<span></span>'
        );

        $relabelled = (new SeoEditor)->relabelColumns($columns);

        preg_match_all('/screen-reader-text">([^<]+)</', implode('', $relabelled), $m);

        expect($m[1])->toHaveCount(4)
            ->and(array_unique($m[1]))->toHaveCount(4);
    });

    it('leaves columns alone when Yoast has not added them', function () {
        expect((new SeoEditor)->relabelColumns(['title' => 'Title']))->toBe(['title' => 'Title']);
    });
});

describe('SeoSkinStyles editor slice', function () {
    it('targets the metabox classes Yoast actually renders, not the yst- library', function () {
        $css = SeoSkinStyles::editorCss();

        // The metabox is Yoast's older component set — WPSEO_Metabox::render_tabs() and
        // WPSEO_Metabox_Section_React::display_link(). Rules scoped to `.yst-root` never
        // reach it, which is exactly how this was wrong the first time.
        foreach ([
            '.wpseo-metabox-menu ul.yoast-aria-tabs',
            '.wpseo-meta-section-link',
            '.wpseo-metabox-menu ul li.active a',
            '.yoast-collapsible__trigger',
            '.yoast-field-group__title',
        ] as $selector) {
            expect($css)->toContain($selector);
        }
    });

    it('hides the Yoast wordmark and upsells inside the editor', function () {
        $css = SeoSkinStyles::editorCss();

        expect($css)->toContain('.wpseo-buy-premium')
            ->toContain('.wpseo-metabox-buy-premium')
            ->toContain('.yoast-data-model--upsell');
    });
});

describe('SEO list-table columns', function () {
    it('gives each SEO column a width, since fixed table layout squeezes otherwise', function () {
        $css = SeoSkinStyles::editorCss();

        // WP core sets .widefat { table-layout: fixed }: undeclared columns split the remainder.
        expect($css)->toContain('.wp-list-table th.column-wpseo-score,')
            ->toContain('width: 74px !important');
    });

    it('drops the tooltip below the icon, not above it', function () {
        $css = SeoSkinStyles::editorCss();

        // Yoast pointed it upward, into the table border and the sort-arrow row.
        expect($css)->toContain('content: attr(data-rl-tip)')
            ->toContain('top: calc(100% + 9px)')
            // The caret sits under the icon, so its *bottom* edge is the coloured one.
            ->toContain('border-bottom-color: var(--rl-primary)')
            ->and($css)->not->toContain('bottom: calc(100% +');
    });

    it('lets the tooltip escape the cell it is anchored in', function () {
        $editor = SeoSkinStyles::editorCss();
        $global = (new WordPressAdminTheme)->getGlobalAdminCss();

        // An absolutely-positioned tooltip is clipped by any ancestor that hides overflow.
        expect($editor)->toContain('overflow: visible !important')
            ->and($global)->toContain('overflow: visible !important')
            ->and($global)->toContain('border-top-left-radius: 10px !important');
    });
});

describe('SEO skin coverage across surfaces', function () {
    it('ships the magenta kill and the component layer to the editor, not just settings', function () {
        $editor = SeoSkinStyles::editorCss();

        // The metabox mixes yst- components (toggles, buttons, cards) into Yoast's older
        // markup. Shipping only the wpseo-/yoast- rules left a magenta toggle and a magenta
        // AI button sitting under our restyled tabs.
        expect($editor)->toContain('body .yst-bg-primary-500')
            ->toContain('body .yst-toggle--checked')
            ->toContain('body .yst-button--primary');
    });

    it('does not gate the component layer behind .yst-root', function () {
        foreach ([SeoSkinStyles::appCss(), SeoSkinStyles::editorCss()] as $css) {
            // .yst-root wraps the settings app but is not a reliable ancestor in the metabox,
            // so the component rules must key off the component classes themselves.
            expect($css)->not->toContain('body .yst-root .yst-button')
                ->and($css)->not->toContain('body .yst-root .yst-toggle');
        }
    });

    it('neutralises the per-tab folder styling, not just the tab links', function () {
        $css = SeoSkinStyles::editorCss();

        // Yoast styles the <li> itself with a grey fill, a shadow and overlapping -1px margins.
        expect($css)->toContain('.wpseo-metabox-menu ul li {')
            ->toContain('background-color: transparent !important')
            ->toContain('box-shadow: none !important');
    });

    it('hides the Yoast wordmark in both of its spellings', function () {
        $css = SeoSkinStyles::editorCss();

        expect($css)->toContain('.yoast-logo')->toContain('.yst-logo-icon');
    });
});

describe('SeoMenu cross-parent sweep', function () {
    it('removes commercial pages parked under other menus', function () {
        $GLOBALS['menu'] = [];
        $GLOBALS['submenu'] = [
            // "Yoast Redirects" is an add_management_page() stub under Tools whose only job is
            // to redirect to the Premium screen. Sweeping only wpseo_dashboard left it visible.
            'tools.php' => [
                ['Import', 'cap', 'import.php'],
                ['Yoast Redirects', 'cap', 'wpseo_redirects_tools'],
            ],
            'wpseo_dashboard' => [
                ['Dashboard', 'cap', 'wpseo_dashboard'],
                ['Settings', 'cap', 'wpseo_page_settings'],
            ],
        ];

        (new SeoMenu)->rebrand();

        expect(array_column($GLOBALS['submenu']['tools.php'], 2))->toBe(['import.php'])
            ->and(array_column($GLOBALS['submenu']['wpseo_dashboard'], 0))->toBe(['Overview', 'Settings']);
    });

    it('renames labels only inside the SEO menu', function () {
        $GLOBALS['menu'] = [];
        $GLOBALS['submenu'] = [
            // A same-named page under someone else's menu is not ours to relabel.
            'options-general.php' => [['Yoast SEO lookalike', 'cap', 'other-plugin']],
            'wpseo_dashboard' => [['Tools - Yoast SEO', 'cap', 'wpseo_tools']],
        ];

        (new SeoMenu)->rebrand();

        expect($GLOBALS['submenu']['options-general.php'][0][0])->toBe('Yoast SEO lookalike')
            ->and($GLOBALS['submenu']['wpseo_dashboard'][0][0])->toBe('Tools');
    });
});

describe('Settings upsell rail', function () {
    it('hides the fixed promo rail and reclaims the space it reserved', function () {
        $css = SeoSkinStyles::appCss();

        // The rail has no semantic hook, so it is addressed by the utilities that define it.
        expect($css)->toContain('div[class*="yst-fixed"][class*="yst-end-8"]')
            // Hiding it alone would leave 17.5rem of empty inline-end padding behind.
            ->toContain('padding-inline-end: 0 !important');
    });
});

describe('SeoEditor metabox chrome', function () {
    it('hides the wordmark and the AI upsell row inside the metabox', function () {
        $css = SeoSkinStyles::editorCss();

        // The wordmark carries only utility classes, so it is keyed off its width utility.
        expect($css)->toContain('#wpseo-metabox-root svg[class*="yst-w-14"]')
            // :has() takes the button's row so its caption does not survive as an orphan line.
            ->toContain('#wpseo-metabox-root div:has(> .yst-button--ai-secondary)');
    });

    it('flattens the tab score glyphs while keeping their colour', function () {
        $css = SeoSkinStyles::editorCss();

        // Yoast draws these as smiley-face SVGs; the colour is information, the face is not.
        expect($css)->toContain('.wpseo-score-icon-container > svg.yoast-svg-icon')
            ->toContain(':has(.yoast-svg-icon-seo-score-good)')
            ->toContain(':has(.yoast-svg-icon-seo-score-ok)')
            ->toContain(':has(.yoast-svg-icon-seo-score-bad)');
    });

    it('swaps the schema glyph for our icon set rather than recolouring Yoast own', function () {
        $css = SeoSkinStyles::editorCss();

        // A mask, not a background-image, so the glyph takes a design token colour.
        expect($css)->toContain('.wpseo-schema-icon')
            ->toContain('mask-image')
            ->toContain('background-color: var(--rl-fg-muted) !important');
    });
});
