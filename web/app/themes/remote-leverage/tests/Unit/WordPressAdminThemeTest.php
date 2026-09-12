<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\WordPress\Admin\WordPressAdminTheme;

describe('WordPressAdminTheme Branding & Modernization', function () {
    it('generates Remote Leverage ISO SVG and Data URI with correct geometry', function () {
        $theme = new WordPressAdminTheme;

        $svg = $theme->getIsoSvg('#09090B', 24);
        expect($svg)->toContain('<svg width="24" height="24"')
            ->toContain('viewBox="0 0 125 125"')
            ->toContain('fill="#09090B"')
            ->toContain(WordPressAdminTheme::ISO_PATH);

        $dataUri = $theme->getIsoDataUri('#09090b');
        expect($dataUri)->toStartWith('data:image/svg+xml;base64,')
            ->and(base64_decode(substr($dataUri, strlen('data:image/svg+xml;base64,'))))->toContain(WordPressAdminTheme::ISO_PATH);
    });

    it('generates comprehensive shadcn/ui zinc global admin CSS without triangle arrows', function () {
        $theme = new WordPressAdminTheme;
        $css = $theme->getGlobalAdminCss();

        expect($css)->toContain('body.wp-admin')
            ->toContain('#09090b')
            ->toContain('.wp-menu-arrow')
            ->toContain('display: none !important')
            ->toContain('.opensub .wp-submenu')
            ->toContain('#wpadminbar')
            ->toContain('box-sizing: border-box !important')
            ->toContain('margin-left: 180px !important');

        // Verify that the old WordPress triangle notch is eliminated
        expect($css)->not->toContain('border-right-color: #f4f4f5 !important');
    });

    it('generates sleek shadcn/ui login CSS with Remote Leverage ISO and zero browser outlines', function () {
        $theme = new WordPressAdminTheme;
        $css = $theme->getLoginCss();

        expect($css)->toContain('body.login')
            ->toContain('radial-gradient')
            ->toContain('#login h1 a')
            ->toContain('#login form')
            ->toContain('.wp-pwd')
            ->toContain('.wp-hide-pw')
            ->toContain('#wp-submit');
    });

    it('customizes footer attribution and login headers cleanly', function () {
        $theme = new WordPressAdminTheme;

        expect($theme->customizeFooterText())->toContain('Remote Leverage Admin Console')
            ->and($theme->customizeFooterVersion())->toContain('v2.0')
            ->and($theme->customizeLoginTitle())->toBe('Remote Leverage');
    });

    it('adds notifications center node to admin bar top-secondary', function () {
        $theme = new WordPressAdminTheme;
        $adminBar = new \WP_Admin_Bar;

        $theme->customizeAdminBarLogo($adminBar);

        $node = $adminBar->get_node('rl-notifications');
        expect($node)->not->toBeNull()
            ->and($node['parent'])->toBe('top-secondary')
            ->and($node['title'])->toContain('rl-notif-bar-trigger')
            ->and($node['title'])->toContain('rl-notif-bell-icon')
            ->and($node['title'])->toContain('rl-notif-badge')
            ->and($node['meta']['title'])->toBe('Notifications Center');
    });

    it('renders notifications drawer markup with bell, actions, and empty state', function () {
        $theme = new WordPressAdminTheme;

        ob_start();
        $theme->renderNotificationsCenterMarkup();
        $output = ob_get_clean();

        expect($output)->toContain('id="rl-notif-drawer"')
            ->toContain('id="rl-notif-backdrop"')
            ->toContain('rl-notif-header-title')
            ->toContain('id="rl-notif-clear-all"')
            ->toContain('id="rl-notif-close-btn"')
            ->toContain('id="rl-notif-list"')
            ->toContain('id="rl-notif-empty"')
            ->toContain('All caught up')
            ->toContain('initNotificationsCenter');
    });

    it('generates notifications center CSS in global admin styles', function () {
        $theme = new WordPressAdminTheme;
        $css = $theme->getGlobalAdminCss();

        expect($css)->toContain('#wpadminbar #wp-admin-bar-rl-notifications')
            ->toContain('.rl-notif-drawer')
            ->toContain('.rl-notif-backdrop')
            ->toContain('.rl-tag-warning')
            ->toContain('.rl-tag-error')
            ->toContain('.rl-tag-info')
            ->toContain('#wpbody-content > .notice:not(.rl-keep-notice)');
    });

    it('generates unified admin bar CSS containing brand ISO, dropdown styles, and sticky header fixes', function () {
        $theme = new WordPressAdminTheme;
        $css = $theme->getAdminBarCss();

        expect($css)->toContain('#wpadminbar')
            ->toContain('#09090b !important')
            ->toContain('.rl-brand-iso')
            ->toContain('.rl-brand-text')
            ->toContain('.rl-notif-bar-trigger')
            ->toContain('.rl-notif-drawer')
            ->toContain('#wp-admin-bar-customize')
            ->toContain('#wp-admin-bar-search')
            ->toContain('body.admin-bar header.sticky')
            ->toContain('@media screen and (max-width: 782px)');
    });

    it('removes customize and search clutter and configures admin console navigation', function () {
        $theme = new WordPressAdminTheme;
        $adminBar = new \WP_Admin_Bar;

        // Seed nodes that exist by default on front-end
        $adminBar->add_node(['id' => 'customize', 'title' => 'Customize']);
        $adminBar->add_node(['id' => 'search', 'title' => 'Search']);

        $theme->customizeAdminBarLogo($adminBar);

        // Clutter nodes removed
        expect($adminBar->get_node('customize'))->toBeNull()
            ->and($adminBar->get_node('search'))->toBeNull();

        // Navigation nodes added
        $siteName = $adminBar->get_node('site-name');
        expect($siteName)->not->toBeNull()
            ->and($siteName['title'])->toContain('rl-brand-iso')
            ->and($siteName['title'])->toContain('Remote Leverage');

        $dashboard = $adminBar->get_node('rl-sub-dashboard');
        expect($dashboard)->not->toBeNull()
            ->and($dashboard['parent'])->toBe('site-name')
            ->and($dashboard['title'])->toBe('Admin Console');

        $leads = $adminBar->get_node('rl-sub-leads');
        expect($leads)->not->toBeNull()
            ->and($leads['parent'])->toBe('site-name')
            ->and($leads['title'])->toBe('Leads & Submissions');

        $site = $adminBar->get_node('rl-sub-site');
        expect($site)->not->toBeNull()
            ->and($site['parent'])->toBe('site-name')
            ->and($site['title'])->toBe('View Live Website');
    });

    it('repaints the Redis Object Cache chart in the dashboard accent green', function () {
        $theme = new WordPressAdminTheme;
        $js = $theme->getRedisCacheChartJs();

        // Same green as .rl-dash-indicator-green and .rl-dash-bar-fill.rl-stage-3.
        expect($js)->toContain("var accent = '#10b981';")
            ->and($theme->getGlobalAdminCss())->toContain('#10b981');

        // The plugin's per-chart options are deep copies of the defaults, so both
        // the shared palette (read by the tooltips) and each chart must be patched.
        expect($js)->toContain('window.rediscache.chart_defaults.colors[0] = accent;')
            ->toContain('charts[type].colors[0] = accent;')
            ->toContain('window.rediscache.charts || {}');

        // Bails out instead of throwing when the plugin is inactive.
        expect($js)->toContain('if (! window.rediscache || ! window.rediscache.chart_defaults) {');
    });

    it('strictly contains zero unicode emojis', function () {
        $theme = new WordPressAdminTheme;

        ob_start();
        $theme->renderNotificationsCenterMarkup();
        $markup = ob_get_clean();

        $allContent = $theme->getGlobalAdminCss().$theme->getAdminBarCss().$theme->getLoginCss().$theme->customizeFooterText().$markup;

        // Regex detecting any Unicode emojis
        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';

        expect(preg_match($emojiPattern, $allContent))->toBe(0);
    });
});
