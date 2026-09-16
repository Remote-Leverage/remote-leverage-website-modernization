<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Infrastructure\WordPress\Security\SecuritySnapshot;
use App\Infrastructure\WordPress\Security\WordfenceConfigurator;

/**
 * Turns WordFence into "Security", a first-party area of the console.
 *
 * Three separate jobs, which are worth keeping distinct because they fail differently:
 *
 *  1. **The menu.** WordFence's top-level item is renamed, re-iconed, and stripped of the
 *     submenu entries that are wordfence.com marketing rather than site administration. This
 *     is pure `$menu`/`$submenu` surgery at `admin_menu` priority 999 — after every WordFence
 *     hook (its own run at 10-90, Login Security at 55).
 *
 *  2. **The landing page.** `admin.php?page=Wordfence` renders our own overview instead of
 *     WordFence's Vue dashboard, by swapping the callback registered on the page hook. The
 *     remaining screens — Firewall, Scan, Blocking, Tools, Login Security — are left working
 *     and reskinned by `SecuritySkin`. That split is deliberate: the dashboard is a read-only
 *     summary and cheap to own, whereas scan triage and rule toggling are stateful flows that
 *     would have to be re-tested against every WordFence release.
 *
 *  3. **The dashboard widget.** WordFence's "activity in the past week" widget is removed and
 *     replaced with one that answers the questions this site actually has — is the firewall
 *     on, did the last scan pass, what is being blocked, and has anyone hand-edited settings
 *     that `config/wordfence.php` owns.
 *
 * Nothing here hard-depends on WordFence. Every entry point checks for the class or the menu
 * entry first, so deactivating the plugin degrades to "no Security menu" rather than a fatal.
 */
class SecurityAdmin
{
    /** WordFence's top-level menu slug. Kept as-is on purpose — see rebrandMenu(). */
    public const PARENT_SLUG = 'Wordfence';

    /** The page hook `add_menu_page('...', 'Wordfence', ...)` registered the dashboard callback on. */
    public const PAGE_HOOK = 'toplevel_page_Wordfence';

    /** The callback WordFence registered there, as the string it used. */
    public const WF_DASHBOARD_CALLBACK = 'wordfence::menu_dashboard';

    /** WordFence's own dashboard widget. */
    public const WF_WIDGET_ID = 'wordfence_activity_report_widget';

    /** Body class the reskin is scoped to. */
    public const BODY_CLASS = 'rl-security-skin';

    /**
     * Submenu entries removed from the Security menu.
     *
     * Help and Wordfence Central are outbound marketing surfaces; the upgrade entries are
     * literal upsells that WordFence renders in orange bold. All Options is removed for a
     * different reason: `config/wordfence.php` is the source of truth and `rl:deploy` reverts
     * anything set by hand, so a screen inviting people to edit settings that will be silently
     * reverted is worse than no screen. The drift table on the overview replaces it.
     *
     * Removing a menu entry does not revoke access — the pages stay reachable by URL for
     * anyone who needs them.
     *
     * @var list<string>
     */
    public const HIDDEN_SUBMENUS = [
        'WordfenceSupport',
        'WordfenceCentral',
        'WordfenceOptions',
        'WordfenceUpgradeToPremium',
        'WordfenceUpgradeToCare',
        'WordfenceUpgradeToResponse',
        'WordfenceProtectMoreSites',
    ];

    /**
     * Submenu entries renamed, keyed by slug.
     *
     * Only the first differs in substance: "Dashboard" now points at our overview, and
     * "Overview" says so. The rest are title-cased consistently with the other console menus.
     *
     * @var array<string, string>
     */
    public const RENAMED_SUBMENUS = [
        'Wordfence' => 'Overview',
        'WordfenceWAF' => 'Firewall',
        'WordfenceBlocking' => 'Blocking',
        'WordfenceScan' => 'Scan',
        'WordfenceTools' => 'Tools',
        'WordfenceLiveTraffic' => 'Live Traffic',
        'WordfenceAuditLog' => 'Audit Log',
        'WFLS' => 'Login Security',
    ];

    public function register(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        // 999 puts this after every WordFence menu hook (10-90) and Login Security (55).
        add_action('admin_menu', [$this, 'rebrandMenu'], 999);
        add_action('admin_enqueue_scripts', [$this, 'enqueueMenuIcon'], 101);
        add_action('admin_enqueue_scripts', [$this, 'enqueueSecurityStyles'], 101);
        add_action('admin_bar_menu', [$this, 'rebrandAdminBar'], 1000);

        // 9999 puts this after MarketingDashboard (999) and WordFence's own setup (10).
        add_action('wp_dashboard_setup', [$this, 'setupDashboard'], 9999);

        add_action('admin_post_rl_security_action', [$this, 'handleAction']);

        // Fires at the top of #wpbody-content, before the page callback runs, which is the
        // only place a header can go on a screen somebody else renders.
        add_action('in_admin_header', [$this, 'renderSecurityHeader']);

        if (function_exists('add_filter')) {
            add_filter('admin_body_class', [$this, 'addBodyClass']);
        }
    }

    /* ======================================================================
       1. Menu
       ====================================================================== */

    /**
     * Rename WordFence to Security, re-icon it, and prune its submenu.
     *
     * The top-level slug stays `Wordfence`. Re-parenting the submenu under a slug of our own
     * would break every one of those pages: WordPress derives a page's hook name from its
     * parent's sanitised title, so moving the entries would have WordPress looking for
     * `security_page_WordfenceWAF` while WordFence registered `wordfence_page_WordfenceWAF`,
     * and each page would 404. The slug is an internal identifier and never shown, so
     * renaming the *titles* achieves everything visible at none of that cost.
     */
    public function rebrandMenu(): void
    {
        global $menu, $submenu;

        if (! is_array($menu)) {
            return;
        }

        foreach ($menu as $index => $item) {
            if (($item[2] ?? '') !== self::PARENT_SLUG) {
                continue;
            }

            // WordFence appends an unread-notification badge to the title. It is real signal,
            // so it is carried over rather than dropped with the name.
            $menu[$index][0] = 'Security'.$this->extractBadge((string) ($item[0] ?? ''));
            $menu[$index][3] = 'Security';
            $menu[$index][6] = $this->shieldDataUri('#a1a1aa');

            break;
        }

        $this->retargetDashboard();

        if (! isset($submenu[self::PARENT_SLUG]) || ! is_array($submenu[self::PARENT_SLUG])) {
            return;
        }

        foreach ($submenu[self::PARENT_SLUG] as $index => $item) {
            $slug = (string) ($item[2] ?? '');

            if (in_array($slug, self::HIDDEN_SUBMENUS, true)) {
                unset($submenu[self::PARENT_SLUG][$index]);

                continue;
            }

            if (isset(self::RENAMED_SUBMENUS[$slug])) {
                // No badge here: the top-level item already carries it, and the overview
                // states the issue count outright.
                $submenu[self::PARENT_SLUG][$index][0] = self::RENAMED_SUBMENUS[$slug];

                // Index 3 is the page title, which is what WordPress puts in <title>. Left
                // alone, the browser tab still reads "Wordfence Dashboard" on a screen the
                // menu calls Overview.
                $submenu[self::PARENT_SLUG][$index][3] = $slug === self::PARENT_SLUG
                    ? 'Security'
                    : 'Security: '.self::RENAMED_SUBMENUS[$slug];
            }
        }

        $submenu[self::PARENT_SLUG] = array_values($submenu[self::PARENT_SLUG]);
    }

    /**
     * Point the top-level page at our overview instead of WordFence's Vue dashboard.
     *
     * `add_menu_page()` registers the callback as an action on the page hook, so swapping it
     * is a `remove_action`/`add_action` pair rather than anything more invasive. WordFence
     * registers the same callback twice (once for the menu, once for the Dashboard submenu),
     * but both resolve to one entry in the hook array, so one removal is enough.
     */
    protected function retargetDashboard(): void
    {
        if (! class_exists('\wordfence') || ! function_exists('remove_action')) {
            return;
        }

        remove_action(self::PAGE_HOOK, self::WF_DASHBOARD_CALLBACK);
        add_action(self::PAGE_HOOK, [$this, 'renderOverview']);
    }

    /**
     * Pull WordFence's notification-count badge out of a menu title, if present.
     *
     * The badge is appended as trailing markup, so everything from the opening span to the
     * end of the string is it.
     */
    protected function extractBadge(string $title): string
    {
        if (preg_match('/\s*<span class="update-plugins.*$/s', $title, $matches) === 1) {
            return $matches[0];
        }

        return '';
    }

    /**
     * Rename the WordFence node in the front-end admin bar to match the menu.
     */
    public function rebrandAdminBar(mixed $bar): void
    {
        if (! is_object($bar) || ! method_exists($bar, 'get_node') || ! method_exists($bar, 'add_node')) {
            return;
        }

        $node = $bar->get_node('wordfence-menu');

        if ($node === null) {
            return;
        }

        $bar->add_node([
            'id' => 'wordfence-menu',
            'title' => preg_replace('/Wordfence/i', 'Security', (string) $node->title),
        ]);
    }

    /* ======================================================================
       2. Assets
       ====================================================================== */

    /**
     * Paint the Security menu item: icon, hover and current states, notification badge.
     *
     * Enqueued on every admin screen rather than only the Security ones, because the menu is
     * on every screen.
     *
     * The icon has to be painted on `::before` rather than on the menu item's own
     * `background-image`, because that is where WordFence paints and the later layer wins
     * regardless of the `icon_url` we set. `css/license/free-global.css` — a stylesheet keyed
     * to the licence tier, loaded on every admin page and *after* our inline styles — carries:
     *
     *     li#toplevel_page_Wordfence .wp-menu-image:before  { background-image: <wf logo> }
     *     #adminmenu li#toplevel_page_Wordfence.current a.menu-top { background-color: #1b719e }
     *
     * Every one of those selectors has a single id, so prefixing with `#adminmenu` gives us
     * two and wins outright rather than relying on source order, which we lose. The same
     * applies to the badge, which WordFence paints amber with `!important` from
     * `wf-adminbar.css`.
     *
     * A `background-image` cannot be recoloured by `color`, so the hover and current states
     * swap in a white copy of the same shield.
     */
    public function enqueueMenuIcon(): void
    {
        if (! function_exists('wp_add_inline_style')) {
            return;
        }

        $grey = $this->shieldDataUri('#a1a1aa');
        $white = $this->shieldDataUri('#ffffff');

        wp_add_inline_style('wp-admin', "
            /* The menu item keeps the shield as its icon_url so something sensible shows if
               this stylesheet never loads, but with it loaded the ::before below is the only
               layer that paints — otherwise the two would overlap at different sizes. */
            #adminmenu li#toplevel_page_Wordfence div.wp-menu-image.svg {
                background-image: none !important;
            }
            #adminmenu li#toplevel_page_Wordfence div.wp-menu-image:before,
            #adminmenu li#toplevel_page_Wordfence.wp-menu-open div.wp-menu-image:before {
                content: '' !important;
                display: block !important;
                width: 17px !important;
                height: 17px !important;
                background-image: url('{$grey}') !important;
                background-size: 17px 17px !important;
                background-position: center center !important;
                background-repeat: no-repeat !important;
                opacity: 1 !important;
            }
            #adminmenu li#toplevel_page_Wordfence:hover div.wp-menu-image:before,
            #adminmenu li#toplevel_page_Wordfence.opensub div.wp-menu-image:before,
            #adminmenu li#toplevel_page_Wordfence.current div.wp-menu-image:before,
            #adminmenu li#toplevel_page_Wordfence.wp-has-current-submenu div.wp-menu-image:before,
            #adminmenu li#toplevel_page_Wordfence > a.menu-top:focus div.wp-menu-image:before {
                background-image: url('{$white}') !important;
            }

            /* WordFence repaints its own menu item in its brand blue on hover and when
               current, which is the one item in the sidebar that would not match. */
            #adminmenu li#toplevel_page_Wordfence.menu-top:hover > a.menu-top,
            #adminmenu li#toplevel_page_Wordfence.opensub > a.menu-top,
            #adminmenu li#toplevel_page_Wordfence > a.menu-top:focus,
            #adminmenu a.toplevel_page_Wordfence:hover {
                background-color: #18181b !important;
                color: #fafafa !important;
            }
            #adminmenu li#toplevel_page_Wordfence.current > a.menu-top,
            #adminmenu li#toplevel_page_Wordfence.wp-has-current-submenu > a.menu-top,
            #adminmenu li#toplevel_page_Wordfence.wp-has-current-submenu > a.wp-has-current-submenu,
            .folded #adminmenu li#toplevel_page_Wordfence.current.menu-top {
                background-color: #27272a !important;
                color: #ffffff !important;
            }
            #adminmenu li#toplevel_page_Wordfence .wp-submenu a:hover,
            #adminmenu li#toplevel_page_Wordfence .wp-submenu a:focus,
            #adminmenu li#toplevel_page_Wordfence .wp-submenu li.current a {
                color: #ffffff !important;
            }

            /* WordFence paints its notification badge in its own amber, with !important. */
            #adminmenu li#toplevel_page_Wordfence .update-plugins.wf-menu-badge,
            #adminmenu li#toplevel_page_Wordfence .update-plugins {
                background-color: #3f3f46 !important;
                color: #fafafa !important;
                border-radius: 9999px !important;
                font-weight: 600 !important;
                box-shadow: none !important;
            }
            #adminmenu li#toplevel_page_Wordfence.current .update-plugins,
            #adminmenu li#toplevel_page_Wordfence:hover .update-plugins {
                background-color: #52525b !important;
            }
        ");
    }

    /**
     * Load the design system, the `.rl-sec-*` primitives, and — on WordFence's own screens —
     * the reskin.
     *
     * The components load on the dashboard too, because the widget is built from them.
     */
    public function enqueueSecurityStyles(): void
    {
        if (! function_exists('wp_add_inline_style') || ! function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        $id = $screen->id ?? '';

        $isSecurityScreen = $this->isSecurityScreen($id);
        $isDashboard = $id === 'dashboard';

        if (! $isSecurityScreen && ! $isDashboard) {
            return;
        }

        AdminDesignSystem::enqueue();
        wp_add_inline_style('wp-admin', SecuritySkin::components());

        if ($isSecurityScreen) {
            wp_add_inline_style('wp-admin', SecuritySkin::wordfenceSkin());
        }
    }

    /**
     * Scope the reskin with a body class rather than a pile of screen-id selectors.
     */
    public function addBodyClass(string $classes): string
    {
        if (! function_exists('get_current_screen')) {
            return $classes;
        }

        $screen = get_current_screen();

        if ($this->isSecurityScreen($screen->id ?? '')) {
            return trim($classes.' '.self::BODY_CLASS);
        }

        return $classes;
    }

    /**
     * Is this screen id one of WordFence's?
     *
     * WordFence's submenu pages hook as `wordfence_page_*` (the parent's sanitised title),
     * and the top level as `toplevel_page_Wordfence`. Login Security is `wordfence_page_WFLS`,
     * so it is covered by the prefix rather than needing its own case.
     */
    public function isSecurityScreen(string $screenId): bool
    {
        return $screenId === self::PAGE_HOOK || str_starts_with($screenId, 'wordfence_page_');
    }

    /**
     * A lucide-style shield, as a data URI for the menu's background-image.
     *
     * Inline rather than a file: the admin menu renders on every request, and a 400-byte data
     * URI is cheaper than a round trip for an icon this small.
     */
    public function shieldDataUri(string $fill = '#a1a1aa'): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->shieldSvg($fill));
    }

    public function shieldSvg(string $fill = '#a1a1aa', int $size = 20): string
    {
        $fill = esc_attr($fill);

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" '
            .'fill="none" stroke="'.$fill.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            .'<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>'
            .'<path d="m9 12 2 2 4-4"/>'
            .'</svg>';
    }

    /* ======================================================================
       3. Dashboard widget
       ====================================================================== */

    /**
     * Swap WordFence's activity widget for ours.
     *
     * The widget is also switched off at source in `config/wordfence.php`
     * (`email_summary_dashboard_widget_enabled => false`), which is the durable fix since it
     * survives into every environment on deploy. This removal covers the window before the
     * next deploy runs, and local databases restored from a dump.
     */
    public function setupDashboard(): void
    {
        if (function_exists('remove_meta_box')) {
            remove_meta_box(self::WF_WIDGET_ID, 'dashboard', 'normal');
            remove_meta_box(self::WF_WIDGET_ID, 'dashboard', 'side');
        }

        if (! function_exists('wp_add_dashboard_widget') || ! current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'rl_dashboard_security',
            'Security Posture & Attack Surface',
            [$this, 'renderWidget'],
            null,
            null,
            'normal',
            'low'
        );
    }

    /**
     * Render the dashboard widget.
     */
    public function renderWidget(): void
    {
        $data = app(SecuritySnapshot::class)->read();

        if (! $data['available']) {
            echo '<div class="rl-sec-empty"><span class="rl-sec-dot rl-sec-dot-idle"></span>'
                .'WordFence is not active on this environment, so there is no security posture to report.</div>';

            return;
        }

        $firewall = $data['firewall'];
        $scan = $data['scan'];
        $blocks = $data['blocks'];
        $config = $data['config'];
        ?>
        <div class="rl-sec-grid rl-sec-grid-4">
            <?php $this->renderFirewallTile($firewall); ?>
            <?php $this->renderScanTile($scan); ?>
            <?php $this->renderBlocksTile($blocks); ?>
            <?php $this->renderConfigTile($config); ?>
        </div>

        <div class="rl-sec-cols">
            <div class="rl-sec-col">
                <h3 class="rl-sec-section-title">Blocked requests</h3>
                <?php $this->renderBlockSplit($blocks['7d'], 'Last 7 days'); ?>
                <h3 class="rl-sec-section-title" style="margin-top:16px;">Most-blocked addresses</h3>
                <?php $this->renderTopIps($data['top_ips']); ?>
            </div>
            <div class="rl-sec-col">
                <h3 class="rl-sec-section-title">Failed logins</h3>
                <?php $this->renderFailedLogins($data['failed_logins']); ?>
                <h3 class="rl-sec-section-title" style="margin-top:16px;">Pending updates</h3>
                <?php $this->renderUpdates($data['updates']); ?>
            </div>
        </div>

        <div class="rl-sec-footer">
            <span>Read <?php echo esc_html($this->relativeTime((int) $data['generated_at'])); ?>.</span>
            <span>
                <a class="rl-btn rl-btn-outline rl-btn-sm" href="<?php echo esc_url($this->pageUrl('WordfenceScan')); ?>">Run a scan</a>
                <a class="rl-btn rl-btn-outline rl-btn-sm" href="<?php echo esc_url($this->pageUrl('WordfenceWAF')); ?>">Firewall</a>
                <a class="rl-btn rl-btn-primary rl-btn-sm" href="<?php echo esc_url($this->pageUrl(self::PARENT_SLUG)); ?>">Open Security</a>
            </span>
        </div>
        <?php
    }

    /* ======================================================================
       4. Overview screen
       ====================================================================== */

    /**
     * Render the Security overview at `admin.php?page=Wordfence`.
     *
     * WordFence hangs its Global Options screen off the same page behind `?subpage=`, and
     * that screen is linked from inside the Firewall and Scan pages. Since we have taken the
     * whole page hook, those requests are handed back to WordFence rather than silently
     * rendering the overview instead — which would look like a broken link.
     */
    public function renderOverview(): void
    {
        $subpage = isset($_GET['subpage']) ? sanitize_key((string) $_GET['subpage']) : '';

        if ($subpage !== '' && class_exists('\wordfence')) {
            \wordfence::menu_dashboard();

            return;
        }

        $data = app(SecuritySnapshot::class)->read();
        ?>
        <div class="wrap rl-admin-wrap">
            <div class="rl-admin-header">
                <h1 class="rl-admin-title">Security</h1>
                <p class="rl-admin-subtitle">
                    WordFence firewall, malware scanning and login protection &mdash; configured from
                    <code class="rl-mono">config/wordfence.php</code> and re-asserted on every deploy.
                </p>
            </div>

            <?php $this->renderTabs(self::PARENT_SLUG); ?>

            <?php if (! $data['available']) { ?>
                <div class="rl-sec-callout rl-sec-callout-warn">
                    <p><strong>WordFence is not loaded on this environment.</strong>
                    The firewall, scanner and login protection are all inactive, and nothing below has data to show.</p>
                </div>
            </div>
            <?php

            return;
            } ?>

            <?php $this->renderActionResult(); ?>
            <?php $this->renderAlerts($data); ?>

            <div class="rl-sec-grid rl-sec-grid-4">
                <?php $this->renderFirewallTile($data['firewall']); ?>
                <?php $this->renderScanTile($data['scan']); ?>
                <?php $this->renderBlocksTile($data['blocks']); ?>
                <?php $this->renderConfigTile($data['config']); ?>
            </div>

            <div class="rl-sec-cols">
                <div class="rl-sec-col">
                    <div class="rl-card">
                        <h2 class="rl-card-title">Attack activity</h2>
                        <p class="rl-card-sub">Requests WordFence blocked at this origin, by what stopped them.</p>
                        <?php $this->renderBlockTable($data['blocks']); ?>
                        <?php $this->renderActions([
                            ['Firewall rules', $this->pageUrl('WordfenceWAF'), 'primary'],
                            ['Blocking', $this->pageUrl('WordfenceBlocking'), 'outline'],
                        ]); ?>
                    </div>

                    <div class="rl-card">
                        <h2 class="rl-card-title">Managed configuration</h2>
                        <p class="rl-card-sub">
                            Settings owned by <code class="rl-mono">config/wordfence.php</code>. A drifted value was
                            changed in wp-admin and will be reverted on the next deploy.
                        </p>
                        <?php $this->renderConfigDrift($data['config']); ?>
                        <?php $this->renderActions(
                            [
                                ['Re-apply from git', $this->actionUrl('reapply_config'), $data['config']['drifted'] === [] ? 'outline' : 'primary'],
                                ['All WordFence options', $this->pageUrl('WordfenceOptions'), 'outline'],
                            ],
                            'Runs the same step as rl:deploy.'
                        ); ?>
                    </div>

                    <div class="rl-card">
                        <h2 class="rl-card-title">Most-blocked countries</h2>
                        <p class="rl-card-sub">Last 7 days, by total blocked requests.</p>
                        <?php $this->renderTopCountries($data['top_countries']); ?>
                        <?php $this->renderActions([
                            ['Manage country blocking', $this->pageUrl('WordfenceBlocking'), 'outline'],
                        ]); ?>
                    </div>
                </div>

                <div class="rl-sec-col">
                    <div class="rl-card">
                        <h2 class="rl-card-title">Scan detail</h2>
                        <?php $this->renderScanDetail($data['scan']); ?>
                        <?php $this->renderActions([
                            ['Open scan', $this->pageUrl('WordfenceScan'), $data['scan']['status'] === 'ok' && $data['scan']['issues_new'] === 0 ? 'outline' : 'primary'],
                            ['Scan options', $this->pageUrl('WordfenceScan').'&subpage=scan_options', 'outline'],
                        ]); ?>
                    </div>

                    <div class="rl-card">
                        <h2 class="rl-card-title">Failed logins</h2>
                        <p class="rl-card-sub">
                            Attempts against an existing account are credential stuffing; attempts against a
                            username that does not exist are enumeration.
                        </p>
                        <?php $this->renderFailedLogins($data['failed_logins']); ?>
                        <?php $this->renderActions([
                            ['Login Security', $this->pageUrl('WFLS'), 'outline'],
                            ['Brute force settings', $this->pageUrl('WordfenceWAF').'&subpage=waf_options', 'outline'],
                        ]); ?>
                    </div>

                    <div class="rl-card">
                        <h2 class="rl-card-title">Most-blocked addresses</h2>
                        <p class="rl-card-sub">Last 7 days. Behind a CDN these are edge addresses as often as attackers.</p>
                        <?php $this->renderTopIps($data['top_ips']); ?>
                        <?php $this->renderActions([
                            ['Blocked IPs', $this->pageUrl('WordfenceBlocking'), 'outline'],
                            ['Tools', $this->pageUrl('WordfenceTools'), 'outline'],
                        ]); ?>
                    </div>

                    <div class="rl-card">
                        <h2 class="rl-card-title">Pending updates</h2>
                        <p class="rl-card-sub">Outstanding core, plugin and theme updates, from WordFence's cached check.</p>
                        <?php $this->renderUpdates($data['updates']); ?>
                        <?php $this->renderActions([
                            ['WordPress updates', admin_url('update-core.php'), ($data['updates']['core'] || $data['updates']['plugins'] > 0 || $data['updates']['themes'] > 0) ? 'primary' : 'outline'],
                        ]); ?>
                    </div>
                </div>
            </div>

            <div class="rl-sec-footer">
                <span>Snapshot read <?php echo esc_html($this->relativeTime((int) $data['generated_at'])); ?>, cached for <?php echo esc_html((string) (SecuritySnapshot::CACHE_TTL / 60)); ?> minutes.</span>
                <span>
                    <a class="rl-btn rl-btn-outline rl-btn-sm" href="<?php echo esc_url($this->actionUrl('refresh')); ?>">Refresh now</a>
                    <a class="rl-btn rl-btn-primary rl-btn-sm" href="<?php echo esc_url($this->pageUrl('WordfenceScan')); ?>">Run a scan</a>
                </span>
            </div>
        </div>
        <?php
    }

    /**
     * Put the Security header and navigation on WordFence's own screens.
     *
     * Without this, Firewall, Scan, Tools and Login Security open with no indication of what
     * section you are in and no way back to the overview except the sidebar — they read as a
     * different product that the menu happens to link to. The overview renders the same
     * header inline, so the two match; this only fills in the screens we do not own.
     *
     * `in_admin_header` is the hook because it fires before the page callback, so the header
     * lands above WordFence's markup without touching it. Note it fires inside `#wpcontent`
     * but *before* `#wpbody` — which makes this element the first child of a container with
     * no top padding, so `SecuritySkin` spaces it with padding rather than a margin that
     * would collapse out and push the whole admin layout down.
     */
    public function renderSecurityHeader(): void
    {
        if (! function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();

        if (! $this->isSecurityScreen($screen->id ?? '')) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field((string) $_GET['page']) : '';
        $subpage = isset($_GET['subpage']) ? sanitize_key((string) $_GET['subpage']) : '';

        // The overview renders its own header inside .rl-admin-wrap. Its WordFence-owned
        // subpages (Global Options) still need one.
        if ($page === self::PARENT_SLUG && $subpage === '') {
            return;
        }

        $page = $this->resolveTabSlug($page, $subpage);
        ?>
        <div class="rl-security-header">
            <h1 class="rl-admin-title">Security</h1>
            <p class="rl-admin-subtitle">
                WordFence firewall, malware scanning and login protection &mdash; configured from
                <code class="rl-mono">config/wordfence.php</code> and re-asserted on every deploy.
            </p>
            <?php $this->renderTabs($page); ?>
        </div>
        <?php
    }

    /**
     * Which tab a screen belongs to, once WordFence's redirects are accounted for.
     *
     * The Audit Log and Live Traffic menu entries have their own slugs, but both land on
     * `page=WordfenceTools&subpage=…`. Taken at face value that highlights Tools and leaves
     * the tab you actually clicked never highlighting at all.
     */
    protected function resolveTabSlug(string $page, string $subpage): string
    {
        if ($page !== 'WordfenceTools') {
            return $page;
        }

        return match ($subpage) {
            'auditlog' => 'WordfenceAuditLog',
            'livetraffic' => 'WordfenceLiveTraffic',
            default => $page,
        };
    }

    /**
     * The tab strip across the Security screens.
     *
     * Only tabs whose page is actually registered are shown — Blocking, Live Traffic and
     * Audit Log are conditional on WordFence settings, and Live Traffic in particular is off
     * in this project's config because the site sits behind a CDN.
     */
    protected function renderTabs(string $current): void
    {
        global $submenu;

        $registered = [];

        foreach ((array) ($submenu[self::PARENT_SLUG] ?? []) as $item) {
            $registered[] = (string) ($item[2] ?? '');
        }

        echo '<nav class="rl-tabs">';

        foreach (self::RENAMED_SUBMENUS as $slug => $label) {
            if (! in_array($slug, $registered, true)) {
                continue;
            }

            $classes = 'rl-tab'.($slug === $current ? ' rl-tab-active' : '');

            printf(
                '<a class="%s" href="%s">%s</a>',
                esc_attr($classes),
                esc_url($this->pageUrl($slug)),
                esc_html($label)
            );
        }

        echo '</nav>';
    }

    /**
     * Surface the conditions worth acting on, above the fold, in severity order.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderAlerts(array $data): void
    {
        $alerts = [];

        if ($data['firewall']['mode'] === 'disabled') {
            $alerts[] = ['bad', '<strong>The firewall is disabled.</strong> Requests are reaching WordPress unfiltered.'];
        } elseif ($data['firewall']['learning']) {
            $alerts[] = ['warn', '<strong>The firewall is in learning mode.</strong> It is recording traffic patterns but not blocking.'];
        }

        if ($data['scan']['status'] === 'failed') {
            $alerts[] = ['bad', '<strong>The last scan failed.</strong> '.esc_html((string) $data['scan']['message'])];
        } elseif ($data['scan']['status'] === 'never') {
            $alerts[] = ['warn', '<strong>No scan has ever run here.</strong> Nothing is known about file integrity on this environment.'];
        }

        if ($data['scan']['issues_new'] > 0) {
            $alerts[] = ['warn', sprintf(
                '<strong>%d unresolved scan %s.</strong> Triage them on the Scan screen.',
                $data['scan']['issues_new'],
                $data['scan']['issues_new'] === 1 ? 'issue' : 'issues'
            )];
        }

        if ($data['config']['drifted'] !== []) {
            $alerts[] = ['warn', sprintf(
                '<strong>%d managed %s drifted from <code class="rl-mono">config/wordfence.php</code>.</strong> '
                .'The next deploy will revert %s.',
                count($data['config']['drifted']),
                count($data['config']['drifted']) === 1 ? 'setting has' : 'settings have',
                count($data['config']['drifted']) === 1 ? 'it' : 'them'
            )];
        }

        foreach ($alerts as [$level, $message]) {
            printf(
                '<div class="rl-sec-callout rl-sec-callout-%s"><p>%s</p></div>',
                esc_attr($level),
                wp_kses($message, ['strong' => [], 'code' => ['class' => []]])
            );
        }
    }

    /* ======================================================================
       5. Actions
       ====================================================================== */

    /**
     * Render a card's action footer.
     *
     * Targets that are not registered on this install are dropped rather than rendered as
     * dead links — Blocking, Live Traffic and Audit Log are all conditional on WordFence
     * settings, and Live Traffic is off in this project's config because the site sits
     * behind a CDN.
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $actions  label, url, variant
     */
    protected function renderActions(array $actions, string $note = ''): void
    {
        $rendered = [];

        foreach ($actions as [$label, $url, $variant]) {
            if (! $this->targetExists($url)) {
                continue;
            }

            $rendered[] = sprintf(
                '<a class="rl-btn rl-btn-%s rl-btn-sm" href="%s">%s</a>',
                esc_attr($variant),
                esc_url($url),
                esc_html($label)
            );
        }

        if ($rendered === []) {
            return;
        }

        printf(
            '<div class="rl-sec-actions">%s%s</div>',
            implode('', $rendered),
            $note !== '' ? '<p class="rl-sec-actions-note">'.esc_html($note).'</p>' : ''
        );
    }

    /**
     * Is the WordFence page this URL points at actually registered?
     *
     * Checked against `$submenu` before removal is applied, so pages we hide from the menu
     * (All Options) still count as present — they are reachable, just not listed.
     */
    protected function targetExists(string $url): bool
    {
        if (! str_contains($url, 'page=')) {
            return true;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $slug = (string) ($query['page'] ?? '');

        if ($slug === '' || ! str_starts_with($slug, 'Wordfence') && $slug !== 'WFLS') {
            return true;
        }

        // Hidden-but-reachable pages are registered with WordPress even though rebrandMenu()
        // took them out of $submenu.
        if (in_array($slug, self::HIDDEN_SUBMENUS, true)) {
            return isset($GLOBALS['_registered_pages']['wordfence_page_'.$slug]);
        }

        global $submenu;

        foreach ((array) ($submenu[self::PARENT_SLUG] ?? []) as $item) {
            if (($item[2] ?? '') === $slug) {
                return true;
            }
        }

        return false;
    }

    /**
     * A nonce-protected URL for one of our own actions.
     */
    protected function actionUrl(string $action): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=rl_security_action&rl_task='.rawurlencode($action)),
            'rl_security_'.$action
        );
    }

    /**
     * Run a Security action and bounce back to the overview.
     *
     * `reapply_config` is the one button here that changes anything. It runs the same
     * `WordfenceConfigurator::apply()` that `rl:deploy` runs, which is what makes the drift
     * table actionable rather than just informative: you can see a hand-edit and put it back
     * without waiting for a release or opening a shell.
     */
    public function handleAction(): void
    {
        $task = isset($_GET['rl_task']) ? sanitize_key((string) $_GET['rl_task']) : '';

        if (! in_array($task, ['reapply_config', 'refresh'], true)) {
            wp_safe_redirect($this->pageUrl(self::PARENT_SLUG));
            exit;
        }

        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to manage security settings.', '', ['response' => 403]);
        }

        check_admin_referer('rl_security_'.$task);

        $result = 'refreshed';

        if ($task === 'reapply_config') {
            $applied = app(WordfenceConfigurator::class)->apply();
            $result = $applied['skipped'] !== null
                ? 'skipped'
                : 'applied-'.count($applied['applied']);
        }

        // Whatever happened, the cached snapshot is now stale.
        app(SecuritySnapshot::class)->forget();

        wp_safe_redirect($this->pageUrl(self::PARENT_SLUG).'&rl_result='.rawurlencode($result));
        exit;
    }

    /**
     * Report the outcome of the last action, if we have just come back from one.
     */
    protected function renderActionResult(): void
    {
        $result = isset($_GET['rl_result']) ? sanitize_text_field((string) $_GET['rl_result']) : '';

        if ($result === '') {
            return;
        }

        if ($result === 'refreshed') {
            $message = 'Snapshot refreshed from WordFence.';
        } elseif ($result === 'skipped') {
            $message = 'Configuration was not applied — WordFence is unavailable, or enforcement is switched off.';
        } elseif (str_starts_with($result, 'applied-')) {
            $count = (int) substr($result, strlen('applied-'));
            $message = $count === 0
                ? 'Every managed setting already matched the repository. Nothing was written.'
                : sprintf('Re-applied %d %s from config/wordfence.php.', $count, $count === 1 ? 'setting' : 'settings');
        } else {
            return;
        }

        printf('<div class="rl-sec-callout"><p>%s</p></div>', esc_html($message));
    }

    /* ======================================================================
       6. Shared tiles and tables
       ====================================================================== */

    /**
     * @param  array<string, mixed>  $firewall
     */
    protected function renderFirewallTile(array $firewall): void
    {
        $mode = (string) $firewall['mode'];
        $dot = match ($mode) {
            'enabled' => 'ok',
            'learning-mode' => 'warn',
            default => 'bad',
        };
        $percentage = (float) $firewall['percentage'];
        $fill = $percentage >= 80 ? '' : ($percentage >= 50 ? ' rl-sec-meter-fill-warn' : ' rl-sec-meter-fill-bad');
        ?>
        <div class="rl-sec-tile">
            <div class="rl-sec-tile-head">
                <p class="rl-sec-tile-label">Firewall</p>
                <span class="rl-sec-dot rl-sec-dot-<?php echo esc_attr($dot); ?><?php echo $mode === 'enabled' ? ' rl-sec-dot-live' : ''; ?>"></span>
            </div>
            <p class="rl-sec-tile-value"><?php echo esc_html(number_format($percentage)); ?>%</p>
            <div class="rl-sec-meter"><span class="rl-sec-meter-fill<?php echo esc_attr($fill); ?>" style="width: <?php echo esc_attr((string) max(2, min(100, $percentage))); ?>%"></span></div>
            <p class="rl-sec-tile-meta">
                <?php echo esc_html((string) $firewall['label']); ?>,
                <?php echo esc_html((string) $firewall['protection']); ?> protection
            </p>
            <div class="rl-sec-actions">
                <a class="rl-sec-tile-link" href="<?php echo esc_url($this->pageUrl('WordfenceWAF')); ?>">Manage firewall</a>
            </div>
        </div>
        <?php
    }

    /**
     * @param  array<string, mixed>  $scan
     */
    protected function renderScanTile(array $scan): void
    {
        [$dot, $value] = match ((string) $scan['status']) {
            'ok' => $scan['issues_new'] > 0 ? ['warn', (string) $scan['issues_new'].' open'] : ['ok', 'Clean'],
            'failed' => ['bad', 'Failed'],
            'never' => ['idle', 'Never run'],
            default => ['idle', 'Unknown'],
        };

        if ($scan['running']) {
            $dot = 'warn';
            $value = 'Running';
        }
        ?>
        <div class="rl-sec-tile">
            <div class="rl-sec-tile-head">
                <p class="rl-sec-tile-label">Malware scan</p>
                <span class="rl-sec-dot rl-sec-dot-<?php echo esc_attr($dot); ?>"></span>
            </div>
            <p class="rl-sec-tile-value rl-sec-tile-value-sm"><?php echo esc_html($value); ?></p>
            <p class="rl-sec-tile-meta">
                <?php echo $scan['last_run'] ? esc_html('Last run '.$this->relativeTime((int) $scan['last_run'])) : 'No completed run on record'; ?>
            </p>
            <div class="rl-sec-actions">
                <a class="rl-sec-tile-link" href="<?php echo esc_url($this->pageUrl('WordfenceScan')); ?>"><?php echo $scan['issues_new'] > 0 ? 'Triage issues' : 'Run a scan'; ?></a>
            </div>
        </div>
        <?php
    }

    /**
     * @param  array<string, array<string, int>>  $blocks
     */
    protected function renderBlocksTile(array $blocks): void
    {
        $day = (int) $blocks['24h']['total'];
        $week = (int) $blocks['7d']['total'];
        ?>
        <div class="rl-sec-tile">
            <div class="rl-sec-tile-head">
                <p class="rl-sec-tile-label">Blocked, 24h</p>
                <span class="rl-sec-dot rl-sec-dot-<?php echo $day > 0 ? 'ok rl-sec-dot-live' : 'idle'; ?>"></span>
            </div>
            <p class="rl-sec-tile-value"><?php echo esc_html(number_format($day)); ?></p>
            <p class="rl-sec-tile-meta"><?php echo esc_html(number_format($week)); ?> over the last 7 days</p>
            <div class="rl-sec-actions">
                <a class="rl-sec-tile-link" href="<?php echo esc_url($this->pageUrl('WordfenceTools')); ?>">Traffic tools</a>
            </div>
        </div>
        <?php
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function renderConfigTile(array $config): void
    {
        $drifted = count((array) $config['drifted']);
        $dot = $drifted > 0 ? 'warn' : ($config['enforced'] ? 'ok' : 'idle');
        ?>
        <div class="rl-sec-tile">
            <div class="rl-sec-tile-head">
                <p class="rl-sec-tile-label">Managed config</p>
                <span class="rl-sec-dot rl-sec-dot-<?php echo esc_attr($dot); ?>"></span>
            </div>
            <p class="rl-sec-tile-value rl-sec-tile-value-sm">
                <?php echo $drifted > 0 ? esc_html($drifted.' drifted') : 'In sync'; ?>
            </p>
            <p class="rl-sec-tile-meta">
                <?php echo esc_html(number_format((int) $config['managed'])); ?> settings from git<?php
                    echo $config['enforced'] ? ', enforced on deploy' : ', enforcement off'; ?>
            </p>
            <div class="rl-sec-actions">
                <a class="rl-sec-tile-link" href="<?php echo esc_url($this->actionUrl('reapply_config')); ?>">Re-apply now</a>
            </div>
        </div>
        <?php
    }

    /**
     * A single window's blocks as a proportional bar plus a legend.
     *
     * @param  array<string, int>  $window
     */
    protected function renderBlockSplit(array $window, string $label): void
    {
        $total = max(1, (int) $window['total']);

        $segments = [
            'complex' => 'Firewall rules',
            'brute' => 'Brute force',
            'blocklist' => 'Blocklist',
        ];

        if ((int) $window['total'] === 0) {
            echo '<div class="rl-sec-empty"><span class="rl-sec-dot rl-sec-dot-idle"></span>Nothing blocked in this window.</div>';

            return;
        }

        echo '<div class="rl-sec-split">';
        foreach (array_keys($segments) as $key) {
            $width = round(((int) $window[$key] / $total) * 100, 2);
            printf('<span class="rl-sec-split-%s" style="width: %s%%"></span>', esc_attr($key), esc_attr((string) $width));
        }
        echo '</div>';

        echo '<ul class="rl-sec-legend">';
        printf('<li>%s</li>', esc_html($label));
        foreach ($segments as $key => $name) {
            printf(
                '<li><i class="rl-sec-split-%s"></i>%s <b>%s</b></li>',
                esc_attr($key),
                esc_html($name),
                esc_html(number_format((int) $window[$key]))
            );
        }
        echo '</ul>';
    }

    /**
     * @param  array<string, array<string, int>>  $blocks
     */
    protected function renderBlockTable(array $blocks): void
    {
        $rows = [
            'complex' => 'Firewall rules',
            'brute' => 'Brute force',
            'blocklist' => 'Blocklist and manual',
        ];
        ?>
        <div class="rl-table-container">
            <table class="rl-table">
                <thead>
                    <tr><th>Stopped by</th><th>24 hours</th><th>7 days</th><th>30 days</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $key => $label) { ?>
                        <tr>
                            <td><?php echo esc_html($label); ?></td>
                            <?php foreach (['24h', '7d', '30d'] as $window) { ?>
                                <td><?php echo esc_html(number_format((int) $blocks[$window][$key])); ?></td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                    <tr>
                        <td><strong>Total</strong></td>
                        <?php foreach (['24h', '7d', '30d'] as $window) { ?>
                            <td><strong><?php echo esc_html(number_format((int) $blocks[$window]['total'])); ?></strong></td>
                        <?php } ?>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * @param  list<array{ip: string, country: string, count: int}>  $ips
     */
    protected function renderTopIps(array $ips): void
    {
        if ($ips === []) {
            echo '<div class="rl-sec-empty"><span class="rl-sec-dot rl-sec-dot-idle"></span>No addresses have been blocked in the last 7 days.</div>';

            return;
        }

        echo '<ul class="rl-sec-list">';
        foreach ($ips as $row) {
            printf(
                '<li><span class="rl-sec-list-main rl-sec-mono">%s</span><span class="rl-sec-list-aside">%s</span><span class="rl-sec-list-count">%s</span></li>',
                esc_html($row['ip']),
                esc_html($row['country']),
                esc_html(number_format($row['count']))
            );
        }
        echo '</ul>';
    }

    /**
     * @param  list<array{code: string, name: string, count: int, ips: int}>  $countries
     */
    protected function renderTopCountries(array $countries): void
    {
        if ($countries === []) {
            echo '<div class="rl-sec-empty"><span class="rl-sec-dot rl-sec-dot-idle"></span>No blocked traffic has been attributed to a country yet.</div>';

            return;
        }

        echo '<ul class="rl-sec-list">';
        foreach ($countries as $row) {
            printf(
                '<li><span class="rl-sec-list-main">%s</span><span class="rl-sec-list-aside">%s addresses</span><span class="rl-sec-list-count">%s</span></li>',
                esc_html($row['name']),
                esc_html(number_format($row['ips'])),
                esc_html(number_format($row['count']))
            );
        }
        echo '</ul>';
    }

    /**
     * @param  list<array{username: string, attempts: int, exists: bool}>  $logins
     */
    protected function renderFailedLogins(array $logins): void
    {
        if ($logins === []) {
            echo '<div class="rl-sec-empty"><span class="rl-sec-dot rl-sec-dot-ok"></span>No failed logins in the last 7 days.</div>';

            return;
        }

        echo '<ul class="rl-sec-list">';
        foreach ($logins as $row) {
            printf(
                '<li><span class="rl-sec-dot rl-sec-dot-%s"></span><span class="rl-sec-list-main rl-sec-mono">%s</span>'
                .'<span class="rl-sec-list-aside">%s</span><span class="rl-sec-list-count">%s</span></li>',
                $row['exists'] ? 'bad' : 'idle',
                esc_html($row['username'] !== '' ? $row['username'] : '(blank)'),
                $row['exists'] ? 'real account' : 'no such user',
                esc_html(number_format($row['attempts']))
            );
        }
        echo '</ul>';
    }

    /**
     * @param  array{core: bool, plugins: int, themes: int}  $updates
     */
    protected function renderUpdates(array $updates): void
    {
        $pending = ($updates['core'] ? 1 : 0) + $updates['plugins'] + $updates['themes'];

        if ($pending === 0) {
            echo '<div class="rl-sec-empty"><span class="rl-sec-dot rl-sec-dot-ok"></span>Core, plugins and themes are all up to date.</div>';

            return;
        }
        ?>
        <dl style="margin:0;">
            <div class="rl-sec-kv"><dt>WordPress core</dt><dd><?php echo $updates['core'] ? 'Update available' : 'Current'; ?></dd></div>
            <div class="rl-sec-kv"><dt>Plugins</dt><dd><?php echo esc_html((string) $updates['plugins']); ?> pending</dd></div>
            <div class="rl-sec-kv"><dt>Themes</dt><dd><?php echo esc_html((string) $updates['themes']); ?> pending</dd></div>
        </dl>
        <?php
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function renderConfigDrift(array $config): void
    {
        $drifted = (array) $config['drifted'];
        ?>
        <dl style="margin:0 0 12px;">
            <div class="rl-sec-kv"><dt>Settings owned by git</dt><dd><?php echo esc_html(number_format((int) $config['managed'])); ?></dd></div>
            <div class="rl-sec-kv">
                <dt>Re-asserted on deploy</dt>
                <dd><?php echo $config['enforced'] ? 'Yes' : 'No &mdash; WORDFENCE_APPLY_ON_DEPLOY is off'; ?></dd>
            </div>
        </dl>

        <?php if ($drifted === []) { ?>
            <div class="rl-sec-empty"><span class="rl-sec-dot rl-sec-dot-ok"></span>Every managed setting matches the repository.</div>
        <?php } else { ?>
            <div class="rl-table-container">
                <table class="rl-table">
                    <thead><tr><th>Setting</th><th>Live</th><th>Repository</th></tr></thead>
                    <tbody>
                        <?php foreach ($drifted as $row) { ?>
                            <tr>
                                <td class="rl-mono"><?php echo esc_html((string) $row['key']); ?></td>
                                <td><?php echo esc_html($this->displayValue($row['current'])); ?></td>
                                <td><?php echo esc_html($this->displayValue($row['desired'])); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php }

        if (($config['unknown'] ?? []) !== []) { ?>
            <p class="rl-hint" style="margin-top:10px;">
                Not recognised by this WordFence version and skipped:
                <span class="rl-mono"><?php echo esc_html(implode(', ', (array) $config['unknown'])); ?></span>
            </p>
        <?php }
        }

    /**
     * @param  array<string, mixed>  $scan
     */
    protected function renderScanDetail(array $scan): void
    {
        ?>
        <dl style="margin:0;">
            <div class="rl-sec-kv"><dt>Scheduled scanning</dt><dd><?php echo $scan['enabled'] ? 'Enabled' : 'Disabled'; ?></dd></div>
            <div class="rl-sec-kv"><dt>Currently running</dt><dd><?php echo $scan['running'] ? 'Yes' : 'No'; ?></dd></div>
            <div class="rl-sec-kv">
                <dt>Last completed</dt>
                <dd><?php echo $scan['last_run'] ? esc_html($this->relativeTime((int) $scan['last_run'])) : 'Never'; ?></dd>
            </div>
            <div class="rl-sec-kv"><dt>Unresolved issues</dt><dd><?php echo esc_html((string) $scan['issues_new']); ?></dd></div>
            <div class="rl-sec-kv"><dt>Ignored issues</dt><dd><?php echo esc_html((string) $scan['issues_ignored']); ?></dd></div>
        </dl>
        <p class="rl-hint" style="margin-top:10px;"><?php echo esc_html((string) $scan['message']); ?></p>
        <?php
    }

    /* ======================================================================
       7. Small helpers
       ====================================================================== */

    /**
     * Render a WordFence config value the way a person reads it, not the way it is stored.
     *
     * WordFence persists booleans as '1' and '', which renders as "1" and nothing at all —
     * and "nothing at all" in a drift table is indistinguishable from a rendering bug.
     */
    public function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'on' : 'off';
        }

        if ($value === '' || $value === null) {
            return 'off';
        }

        if ($value === '1' || $value === 1) {
            return 'on';
        }

        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }

        return (string) $value;
    }

    /**
     * "4 minutes ago", or an absolute date once that stops being useful.
     */
    public function relativeTime(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return 'never';
        }

        $delta = time() - $timestamp;

        if ($delta < 0) {
            return 'just now';
        }

        if ($delta > 2592000) {
            return function_exists('wp_date')
                ? (string) wp_date('j M Y', $timestamp)
                : gmdate('j M Y', $timestamp);
        }

        return (function_exists('human_time_diff')
            ? human_time_diff($timestamp, time())
            : (string) round($delta / 60).' minutes').' ago';
    }

    /**
     * URL for one of the Security pages.
     */
    protected function pageUrl(string $slug): string
    {
        return admin_url('admin.php?page='.rawurlencode($slug));
    }
}
