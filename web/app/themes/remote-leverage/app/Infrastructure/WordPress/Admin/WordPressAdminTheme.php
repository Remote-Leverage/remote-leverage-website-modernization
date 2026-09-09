<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

class WordPressAdminTheme
{
    /**
     * Remote Leverage circular ISO vector icon SVG path data.
     */
    public const ISO_PATH = 'M104.67 0C115.884 0 125 9.12268 125 20.3297C125 26.1294 122.553 31.3643 118.647 35.0715C122.71 43.3569 125 52.6669 125 62.5C125 96.9665 96.9604 125 62.5 125C45.031 125 29.2189 117.797 17.8697 106.207C17.2586 105.626 16.6508 105.025 16.0767 104.389C13.5454 101.702 11.3191 98.7279 9.4547 95.5143C3.46545 85.929 1.06977e-06 74.6137 0 62.5C0 28.0335 28.0396 0 62.5 0C72.3326 8.70308e-07 81.6427 2.28901 89.9278 6.35196C93.6303 2.44534 98.8715 9.46266e-06 104.67 0ZM79.3306 108.999L78.7921 109.406C79.1529 109.138 79.5098 108.865 79.8627 108.587C79.6864 108.725 79.5089 108.863 79.3306 108.999ZM81.4238 107.301C81.2534 107.448 81.0821 107.593 80.9096 107.737L81.4238 107.301C81.5941 107.155 81.7632 107.008 81.9314 106.859L81.4238 107.301ZM62.5 13.5656C47.1314 13.5656 33.3964 20.6947 24.4205 31.818C31.9846 26.7858 41.0559 23.8519 50.8035 23.8518C77.148 23.8518 98.5775 45.2814 98.5775 71.6258C98.5775 85.6011 92.5421 98.1925 82.9446 106.936C99.7451 99.182 111.434 82.183 111.434 62.5V62.4943C111.434 54.6202 109.552 47.1864 106.229 40.592C105.716 40.6319 105.195 40.653 104.67 40.653C93.457 40.653 84.3407 31.5309 84.3405 20.324C84.3405 19.7991 84.3617 19.2781 84.4016 18.7644C77.8071 15.4477 70.3736 13.5656 62.5 13.5656ZM50.81 37.4239C37.057 37.4239 25.176 45.5835 19.7481 57.3192C25.3302 52.8003 32.4332 50.0869 40.1597 50.0869C58.0798 50.0869 72.6576 64.6641 72.6577 82.5842C72.6577 89.9819 70.1606 96.8077 65.9797 102.276C77.252 96.6719 85.0175 85.0421 85.0177 71.6323C85.0177 52.7706 69.6717 37.4239 50.81 37.4239ZM40.1597 63.6467C29.7225 63.6469 21.228 72.1411 21.228 82.5784C21.2281 86.763 22.5914 90.6269 24.8959 93.7622C25.6933 94.7181 26.5267 95.6391 27.3955 96.5318C27.4386 96.5723 27.4795 96.6121 27.5168 96.6481C27.5597 96.6895 27.5982 96.7262 27.6367 96.7623C30.8688 99.6246 35.0768 101.397 39.6908 101.51H40.1597C50.597 101.51 59.0913 93.0157 59.0914 82.5784C59.0914 72.1411 50.597 63.6467 40.1597 63.6467ZM27.8026 97.8674C27.8377 97.8958 27.8736 97.9232 27.9089 97.9514C27.8702 97.9204 27.831 97.89 27.7925 97.8587L27.8026 97.8674ZM59.7872 83.8106C59.7918 83.7367 59.7978 83.6628 59.8015 83.5887V83.5808C59.7977 83.6576 59.7919 83.7341 59.7872 83.8106ZM20.5092 83.3905C20.5127 83.4745 20.5177 83.5582 20.5222 83.6419C20.5105 83.424 20.5015 83.2052 20.497 82.9855L20.5092 83.3905ZM59.8209 82.1483L59.8202 82.0715C59.8196 82.0473 59.8173 82.0231 59.8166 81.9989C59.8181 82.0487 59.8198 82.0985 59.8209 82.1483ZM21.1131 77.669C21.0322 77.983 20.959 78.3 20.8934 78.6197C20.9262 78.4599 20.9609 78.3014 20.9975 78.143C21.0342 77.9845 21.0726 77.826 21.1131 77.669ZM85.7149 73.2451C85.7184 73.1687 85.7234 73.0924 85.7264 73.016V73.0059C85.7233 73.0857 85.7185 73.1654 85.7149 73.2451ZM85.753 71.6323L85.7501 71.1814C85.749 71.0977 85.7461 71.0142 85.7443 70.9307C85.7492 71.1641 85.753 71.3979 85.753 71.6323ZM28.4007 66.8234C28.2897 66.9065 28.1796 66.9908 28.0704 67.0762C28.1819 66.989 28.2945 66.9032 28.4079 66.8184L28.4007 66.8234ZM82.4154 36.8214C82.5547 36.948 82.6934 37.0752 82.8312 37.2034L82.4154 36.8214C82.2762 36.6949 82.1361 36.5686 81.9953 36.4437L82.4154 36.8214ZM27.4838 30.7868C26.9106 31.1156 26.3448 31.456 25.787 31.8079L26.63 31.2888C26.9127 31.1186 27.1973 30.9512 27.4838 30.7868ZM65.8749 27.063C66.0506 27.1226 66.2258 27.1831 66.4005 27.2447C66.0446 27.1192 65.6868 26.9976 65.327 26.8806L65.8749 27.063ZM104.67 13.5721C100.94 13.5721 97.907 16.6051 97.9069 20.3355C97.9069 24.066 100.94 27.0996 104.67 27.0996C108.401 27.0996 111.434 24.066 111.434 20.3355C111.434 16.6055 108.395 13.5721 104.67 13.5721ZM124.235 21.4068C124.226 21.5807 124.214 21.754 124.2 21.9267C124.228 21.5769 124.248 21.2245 124.258 20.8697L124.235 21.4068ZM112.106 21.2962C112.114 21.2315 112.124 21.1669 112.131 21.1016L112.132 21.0894C112.125 21.1588 112.115 21.2275 112.106 21.2962Z';

    /**
     * Register WordPress hooks for global admin styling and branding.
     */
    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('admin_enqueue_scripts', [$this, 'enqueueGlobalAdminStyles'], 99);
            add_action('admin_bar_menu', [$this, 'customizeAdminBarLogo'], 999);
            add_action('admin_head', [$this, 'injectAdminFavicon']);
            add_action('admin_footer', [$this, 'renderNotificationsCenterMarkup'], 999);
            add_action('login_head', [$this, 'injectAdminFavicon']);
            add_action('login_head', [$this, 'renderLoginHeaderStyles'], 99);
            add_action('login_enqueue_scripts', [$this, 'enqueueLoginStyles'], 99);
        }

        if (function_exists('add_filter')) {
            add_filter('admin_footer_text', [$this, 'customizeFooterText']);
            add_filter('update_footer', [$this, 'customizeFooterVersion'], 99);
            add_filter('login_headerurl', [$this, 'customizeLoginUrl']);
            add_filter('login_headertext', [$this, 'customizeLoginTitle']);
            add_filter('get_site_icon_url', [$this, 'filterSiteIconUrl'], 10, 3);
        }
    }

    /**
     * Return SVG vector markup for the Remote Leverage ISO icon.
     */
    public function getIsoSvg(string $fill = '#FFFFFF', int $size = 20): string
    {
        $escapedFill = function_exists('esc_attr') ? esc_attr($fill) : htmlspecialchars($fill, ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<svg width="%d" height="%d" viewBox="0 0 125 125" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="%s" fill="%s"/></svg>',
            $size,
            $size,
            self::ISO_PATH,
            $escapedFill
        );
    }

    /**
     * Return data URI for the Remote Leverage ISO icon for use in CSS backgrounds.
     * Uses base64 encoding to prevent quote collisions or SVG parsing quirks in CSS url().
     */
    public function getIsoDataUri(string $fillHex = '#FFFFFF'): string
    {
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 125 125" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="%s" fill="%s"/></svg>',
            self::ISO_PATH,
            htmlspecialchars($fillHex, ENT_QUOTES, 'UTF-8')
        );

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Modernize admin bar: unified brand on the left and removal of noisy items.
     */
    public function customizeAdminBarLogo(\WP_Admin_Bar $wp_admin_bar): void
    {
        $wp_admin_bar->remove_node('wp-logo');
        $wp_admin_bar->remove_node('comments');
        $wp_admin_bar->remove_node('rl-brand-header');
        $wp_admin_bar->remove_node('view-site');
        $wp_admin_bar->remove_node('dashboard');
        $wp_admin_bar->remove_node('themes');
        $wp_admin_bar->remove_node('widgets');
        $wp_admin_bar->remove_node('menus');

        $leadsUrl = function_exists('admin_url') ? admin_url('admin.php?page=rl-leads') : '#';
        $siteUrl = function_exists('home_url') ? home_url('/') : '/';

        $wp_admin_bar->add_node([
            'id'    => 'site-name',
            'title' => '<span class="rl-brand-iso" aria-hidden="true"></span><span class="rl-brand-text">Remote Leverage</span>',
            'href'  => $leadsUrl,
            'meta'  => [
                'title' => 'Remote Leverage Admin',
            ],
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'rl-sub-leads',
            'parent' => 'site-name',
            'title'  => 'Leads & Submissions',
            'href'   => $leadsUrl,
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'rl-sub-site',
            'parent' => 'site-name',
            'title'  => 'View Live Website',
            'href'   => $siteUrl,
        ]);

        // Notifications Center Trigger in top-secondary (right side of admin bar)
        $wp_admin_bar->add_node([
            'id'     => 'rl-notifications',
            'parent' => 'top-secondary',
            'title'  => '<span class="rl-notif-bar-trigger" id="rl-notif-trigger" role="button" tabindex="0" title="Notifications Center">'
                .'<svg class="rl-notif-bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>'
                .'<span class="rl-notif-badge" id="rl-notif-badge" style="display:none;">0</span>'
                .'</span>',
            'href'   => '#',
            'meta'   => [
                'title' => 'Notifications Center',
                'class' => 'rl-notif-admin-bar-item',
            ],
        ]);
    }

    /**
     * Inject Remote Leverage SVG favicon into <head> of wp-admin and wp-login.
     */
    public function injectAdminFavicon(): void
    {
        $faviconUrl = function_exists('home_url') ? home_url('/favicon.svg') : '/favicon.svg';
        $escapedFavicon = function_exists('esc_url') ? esc_url($faviconUrl) : htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8');
        echo '<link rel="icon" type="image/svg+xml" href="'.$escapedFavicon.'">'."\n";
    }

    /**
     * Filter site icon URL to fallback to Remote Leverage favicon.
     */
    public function filterSiteIconUrl(string $url, int $size, int $blogId): string
    {
        if (empty($url)) {
            return function_exists('home_url') ? home_url('/favicon.svg') : '/favicon.svg';
        }

        return $url;
    }

    /**
     * Customize admin footer attribution text.
     */
    public function customizeFooterText(): string
    {
        return '<span class="rl-footer-brand" style="font-weight: 500; color: #71717a;">Remote Leverage Admin Console</span> &bull; <span style="color: #a1a1aa;">All systems operational</span>';
    }

    /**
     * Customize update footer version text.
     */
    public function customizeFooterVersion(): string
    {
        return '<span style="color: #a1a1aa; font-size: 11px;">v2.0 &bull; High Reliability</span>';
    }

    /**
     * Render the slide-out Notifications Center drawer markup and client-side notice interception script.
     */
    public function renderNotificationsCenterMarkup(): void
    {
        ?>
        <!-- Remote Leverage Notifications Center Drawer -->
        <div id="rl-notif-backdrop" class="rl-notif-backdrop" aria-hidden="true"></div>
        <aside id="rl-notif-drawer" class="rl-notif-drawer" aria-label="Notifications Center" aria-hidden="true">
            <div class="rl-notif-drawer-header">
                <div class="rl-notif-header-title-wrap">
                    <svg class="rl-notif-header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    <h2 class="rl-notif-header-title">Notifications</h2>
                    <span id="rl-notif-total-badge" class="rl-notif-pill">0</span>
                </div>
                <div class="rl-notif-header-actions">
                    <button type="button" id="rl-notif-clear-all" class="rl-notif-btn-clear" title="Dismiss all notifications">Dismiss all</button>
                    <button type="button" id="rl-notif-close-btn" class="rl-notif-btn-close" aria-label="Close Notifications">&times;</button>
                </div>
            </div>

            <div class="rl-notif-drawer-body" id="rl-notif-list">
                <!-- Dynamically aggregated from WordPress notices -->
            </div>

            <div class="rl-notif-empty-state" id="rl-notif-empty" style="display: none;">
                <div class="rl-notif-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <p class="rl-notif-empty-title">All caught up</p>
                <p class="rl-notif-empty-desc">No active system alerts, environment warnings, or pending notices.</p>
            </div>

            <div class="rl-notif-drawer-footer">
                <span class="rl-notif-footer-text">Remote Leverage System Hub</span>
                <button type="button" id="rl-notif-restore-btn" class="rl-notif-link-subtle" title="Show notices back inline">Restore inline</button>
            </div>
        </aside>

        <script>
        (function() {
            function initNotificationsCenter() {
                var drawer = document.getElementById('rl-notif-drawer');
                var backdrop = document.getElementById('rl-notif-backdrop');
                var trigger = document.getElementById('rl-notif-trigger');
                var closeBtn = document.getElementById('rl-notif-close-btn');
                var clearAllBtn = document.getElementById('rl-notif-clear-all');
                var restoreBtn = document.getElementById('rl-notif-restore-btn');
                var listContainer = document.getElementById('rl-notif-list');
                var emptyState = document.getElementById('rl-notif-empty');
                var badge = document.getElementById('rl-notif-badge');
                var totalBadge = document.getElementById('rl-notif-total-badge');

                if (!drawer || !listContainer) return;

                var dismissedStorageKey = 'rl_dismissed_notices_v1';
                var dismissedHashes = [];
                try {
                    dismissedHashes = JSON.parse(sessionStorage.getItem(dismissedStorageKey) || '[]');
                } catch(e) { dismissedHashes = []; }

                function hashString(str) {
                    var hash = 0;
                    for (var i = 0; i < str.length; i++) {
                        hash = ((hash << 5) - hash) + str.charCodeAt(i);
                        hash |= 0;
                    }
                    return 'h_' + hash;
                }

                function openDrawer() {
                    document.body.classList.add('rl-notif-open');
                    drawer.setAttribute('aria-hidden', 'false');
                    if (backdrop) backdrop.setAttribute('aria-hidden', 'false');
                }

                function closeDrawer() {
                    document.body.classList.remove('rl-notif-open');
                    drawer.setAttribute('aria-hidden', 'true');
                    if (backdrop) backdrop.setAttribute('aria-hidden', 'true');
                }

                if (trigger) {
                    trigger.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (document.body.classList.contains('rl-notif-open')) {
                            closeDrawer();
                        } else {
                            openDrawer();
                        }
                    });
                }
                if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
                if (backdrop) backdrop.addEventListener('click', closeDrawer);
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && document.body.classList.contains('rl-notif-open')) {
                        closeDrawer();
                    }
                });

                // Capture raw WordPress admin notices from content flow
                var noticeSelector = '#wpbody-content .notice, #wpbody-content div.updated, #wpbody-content div.error, #wpbody-content .update-nag, .wrap > .notice, .wrap > div.updated, .wrap > div.error';
                var rawNotices = document.querySelectorAll(noticeSelector);
                var capturedCount = 0;

                rawNotices.forEach(function(el) {
                    if (el.closest('#rl-notif-drawer') || el.classList.contains('rl-intercepted')) return;
                    
                    var rawText = (el.innerText || el.textContent || '').trim();
                    if (!rawText) return;

                    var hash = hashString(rawText);
                    el.classList.add('rl-intercepted');

                    // Check if user previously dismissed in this session
                    if (dismissedHashes.indexOf(hash) !== -1) {
                        el.style.display = 'none';
                        return;
                    }

                    // Hide raw disruptive notice from page content
                    el.style.display = 'none';

                    // Classify severity
                    var severity = 'info';
                    var tagLabel = 'System Notice';
                    var tagClass = 'rl-tag-info';

                    if (el.classList.contains('notice-error') || el.classList.contains('error')) {
                        severity = 'error';
                        tagLabel = 'Critical Error';
                        tagClass = 'rl-tag-error';
                    } else if (el.classList.contains('notice-warning') || el.classList.contains('update-nag')) {
                        severity = 'warning';
                        tagLabel = 'Warning';
                        tagClass = 'rl-tag-warning';
                    } else if (el.classList.contains('notice-success') || el.classList.contains('updated')) {
                        severity = 'success';
                        tagLabel = 'Success';
                        tagClass = 'rl-tag-success';
                    }

                    // Categorize source domain/origin
                    var cleanHtml = el.innerHTML;
                    if (rawText.indexOf('Bedrock:') === 0 || rawText.indexOf('Bedrock') !== -1) {
                        tagLabel = 'Bedrock Environment';
                    } else if (rawText.indexOf('WordPress') !== -1 || el.classList.contains('update-nag')) {
                        tagLabel = 'WordPress Core';
                    } else if (rawText.indexOf('WooCommerce') !== -1) {
                        tagLabel = 'WooCommerce';
                    } else if (rawText.indexOf('ACF') !== -1) {
                        tagLabel = 'ACF Pro';
                    }

                    var card = document.createElement('div');
                    card.className = 'rl-notif-card rl-notif-' + severity;
                    card.setAttribute('data-hash', hash);

                    var cardHeader = document.createElement('div');
                    cardHeader.className = 'rl-notif-card-header';
                    cardHeader.innerHTML = '<span class="rl-notif-tag ' + tagClass + '">' + tagLabel + '</span>' +
                        '<button type="button" class="rl-notif-dismiss-item-btn" title="Dismiss notification">&times;</button>';

                    var cardBody = document.createElement('div');
                    cardBody.className = 'rl-notif-card-body';
                    cardBody.innerHTML = cleanHtml;

                    // Strip any native redundant dismiss button from inside body
                    var nativeDismiss = cardBody.querySelector('.notice-dismiss');
                    if (nativeDismiss) nativeDismiss.remove();

                    card.appendChild(cardHeader);
                    card.appendChild(cardBody);

                    // Dismiss handler for single card
                    var dismissBtn = cardHeader.querySelector('.rl-notif-dismiss-item-btn');
                    dismissBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        card.classList.add('is-dismissing');
                        setTimeout(function() {
                            card.remove();
                            dismissedHashes.push(hash);
                            try {
                                sessionStorage.setItem(dismissedStorageKey, JSON.stringify(dismissedHashes));
                            } catch(e) {}
                            
                            // Trigger native WP dismiss click if original had one
                            var origDismiss = el.querySelector('.notice-dismiss');
                            if (origDismiss) origDismiss.click();

                            updateBadgeCount();
                        }, 200);
                    });

                    listContainer.appendChild(card);
                    capturedCount++;
                });

                function updateBadgeCount() {
                    var remaining = listContainer.querySelectorAll('.rl-notif-card').length;
                    if (badge) {
                        badge.textContent = remaining;
                        badge.style.display = remaining > 0 ? 'inline-flex' : 'none';
                    }
                    if (totalBadge) {
                        totalBadge.textContent = remaining + (remaining === 1 ? ' Alert' : ' Alerts');
                    }
                    if (emptyState) {
                        emptyState.style.display = remaining === 0 ? 'flex' : 'none';
                    }
                }

                if (clearAllBtn) {
                    clearAllBtn.addEventListener('click', function() {
                        var cards = listContainer.querySelectorAll('.rl-notif-card');
                        cards.forEach(function(c) {
                            var h = c.getAttribute('data-hash');
                            if (h && dismissedHashes.indexOf(h) === -1) {
                                dismissedHashes.push(h);
                            }
                            c.remove();
                        });
                        try {
                            sessionStorage.setItem(dismissedStorageKey, JSON.stringify(dismissedHashes));
                        } catch(e) {}
                        updateBadgeCount();
                    });
                }

                if (restoreBtn) {
                    restoreBtn.addEventListener('click', function() {
                        rawNotices.forEach(function(el) {
                            el.style.display = '';
                            el.classList.add('rl-keep-notice');
                        });
                        closeDrawer();
                    });
                }

                updateBadgeCount();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initNotificationsCenter);
            } else {
                initNotificationsCenter();
            }
        })();
        </script>
        <?php
    }

    /**
     * Customize login logo link URL.
     */
    public function customizeLoginUrl(): string
    {
        return function_exists('home_url') ? home_url('/') : '/';
    }

    /**
     * Customize login logo link title.
     */
    public function customizeLoginTitle(): string
    {
        return 'Remote Leverage';
    }

    /**
     * Enqueue global shadcn/ui zinc styles across the ENTIRE WordPress admin console.
     */
    public function enqueueGlobalAdminStyles(): void
    {
        if (function_exists('wp_add_inline_style')) {
            wp_add_inline_style('wp-admin', $this->getGlobalAdminCss());
        }
    }

    /**
     * Enqueue login styles replacing WordPress logo with Remote Leverage ISO.
     */
    public function enqueueLoginStyles(): void
    {
        if (function_exists('wp_add_inline_style')) {
            wp_add_inline_style('login', $this->getLoginCss());
        }
    }

    /**
     * Generate comprehensive global admin CSS for the entire WordPress console.
     */
    public function getGlobalAdminCss(): string
    {
        $whiteIsoUri = $this->getIsoDataUri('#FFFFFF');

        return "
            /* ==========================================================================
               REMOTE LEVERAGE GLOBAL ADMIN CONSOLE THEME (SHADCN/UI ZINC AESTHETIC)
               ========================================================================== */

            /* --- 1. Global Reset & Typography Hierarchy --- */
            body.wp-admin,
            #wpwrap,
            #wpbody {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
                background-color: #f4f4f5 !important;
                color: #09090b !important;
                -webkit-font-smoothing: antialiased !important;
                -moz-osx-font-smoothing: grayscale !important;
            }
            #wpcontent {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
                background-color: #f4f4f5 !important;
                color: #09090b !important;
                margin-left: 180px !important;
                padding-left: 24px !important;
                padding-right: 24px !important;
                box-sizing: border-box !important;
            }
            #wpfooter {
                margin-left: 180px !important;
                box-sizing: border-box !important;
            }
            #wpbody-content {
                float: none !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                padding-right: 0 !important;
                padding-bottom: 40px !important;
            }
            .wrap {
                margin: 16px 0 24px 0 !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            #screen-meta-links {
                margin-right: 0 !important;
                box-sizing: border-box !important;
            }
            #posts-filter {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            /* --- 2. Top Admin Bar (#wpadminbar) --- */
            #wpadminbar {
                background: #09090b !important;
                border-bottom: 1px solid #27272a !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            }
            #wpadminbar #wp-admin-bar-wp-logo,
            #wpadminbar #wp-admin-bar-comments,
            #wpadminbar #wp-admin-bar-rl-brand-header {
                display: none !important;
            }
            #wpadminbar .ab-item,
            #wpadminbar a.ab-item {
                color: #a1a1aa !important;
                font-size: 13px !important;
                font-weight: 500 !important;
                transition: color 0.15s ease, background-color 0.15s ease !important;
            }
            #wpadminbar .ab-item:hover,
            #wpadminbar a.ab-item:hover,
            #wpadminbar li.hover > .ab-item,
            #wpadminbar .ab-top-menu > li:hover > .ab-item {
                color: #fafafa !important;
                background-color: #18181b !important;
            }
            #wpadminbar .menupop .ab-sub-wrapper,
            #wpadminbar .shortlink-input {
                background: #09090b !important;
                border: 1px solid #27272a !important;
                border-radius: 8px !important;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -4px rgba(0, 0, 0, 0.2) !important;
                padding: 4px !important;
            }
            #wpadminbar .ab-submenu .ab-item {
                color: #a1a1aa !important;
                border-radius: 6px !important;
                padding: 4px 12px !important;
            }
            #wpadminbar .ab-submenu .ab-item:hover {
                color: #ffffff !important;
                background-color: #27272a !important;
            }

            /* --- Remote Leverage Admin Bar Brand / ISO --- */
            #wpadminbar #wp-admin-bar-site-name > .ab-item:before,
            #wpadminbar #wp-admin-bar-site-name .ab-icon,
            #wpadminbar #wp-admin-bar-site-name .ab-icon:before {
                display: none !important;
                content: '' !important;
            }
            #wpadminbar #wp-admin-bar-site-name > .ab-item {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                padding: 0 12px 0 10px !important;
            }
            #wpadminbar .rl-brand-iso {
                width: 18px !important;
                height: 18px !important;
                background-image: url(\"{$whiteIsoUri}\") !important;
                background-size: contain !important;
                background-position: center !important;
                background-repeat: no-repeat !important;
                display: inline-block !important;
                vertical-align: middle !important;
                flex-shrink: 0 !important;
            }
            #wpadminbar .rl-brand-text {
                font-weight: 600 !important;
                color: #fafafa !important;
                letter-spacing: -0.01em !important;
                font-size: 13px !important;
            }

            /* --- 3. Left Navigation Menu (#adminmenu) --- */
            #adminmenuback,
            #adminmenuwrap {
                width: 180px !important;
                background-color: #09090b !important;
                box-sizing: border-box !important;
            }
            #adminmenuback {
                border-right: 1px solid #27272a !important;
            }
            #adminmenuwrap {
                border-right: 1px solid #27272a !important;
            }
            #adminmenu {
                width: 180px !important;
                max-width: 180px !important;
                margin: 8px 0 0 0 !important;
                padding: 0 8px !important;
                box-sizing: border-box !important;
                border: none !important;
                border-right: none !important;
                background: transparent !important;
            }
            #adminmenu,
            #adminmenu *,
            #adminmenu *:before,
            #adminmenu *:after {
                box-sizing: border-box !important;
            }

            /* Responsive and Folded sidebar sizing in 100% lockstep */
            .folded #adminmenuback,
            .folded #adminmenuwrap,
            .folded #adminmenu {
                width: 48px !important;
                max-width: 48px !important;
            }
            .folded #adminmenu {
                padding: 0 4px !important;
            }
            .folded #wpcontent,
            .folded #wpfooter {
                margin-left: 48px !important;
            }
            @media screen and (max-width: 960px) {
                .auto-fold #adminmenuback,
                .auto-fold #adminmenuwrap,
                .auto-fold #adminmenu {
                    width: 48px !important;
                    max-width: 48px !important;
                }
                .auto-fold #adminmenu {
                    padding: 0 4px !important;
                }
                .auto-fold #wpcontent,
                .auto-fold #wpfooter {
                    margin-left: 48px !important;
                }
            }

            /* Neutralize all li container backgrounds so default WP color schemes never leak blue */
            #adminmenu li,
            #adminmenu li.menu-top,
            #adminmenu li.menu-top:hover,
            #adminmenu li.opensub,
            #adminmenu li.opensub > a.menu-top,
            #adminmenu li.wp-has-current-submenu,
            #adminmenu li.wp-has-current-submenu:hover,
            #adminmenu li.wp-has-current-submenu.opensub,
            #adminmenu li.wp-has-current-submenu.hover,
            #adminmenu li.current,
            #adminmenu li.current:hover,
            #adminmenu li.wp-has-submenu.wp-not-current-submenu.opensub:hover {
                background: transparent !important;
                background-color: transparent !important;
                border: none !important;
            }

            /* Eliminate all WP focus/hover box-shadow bars (removes white crescent pill) */
            #adminmenu a,
            #adminmenu a:hover,
            #adminmenu a:focus,
            #adminmenu a:active,
            #adminmenu li.menu-top > a,
            #adminmenu li.menu-top > a:hover,
            #adminmenu li.menu-top > a:focus,
            #adminmenu .wp-submenu a,
            #adminmenu .wp-submenu a:hover,
            #adminmenu .wp-submenu a:focus,
            .folded #adminmenu .wp-submenu-head:hover,
            .folded #adminmenu .wp-submenu-head:focus {
                box-shadow: none !important;
                outline: none !important;
                text-decoration: none !important;
            }

            #adminmenu li.menu-top {
                width: 100% !important;
                max-width: 100% !important;
                margin: 1px 0 !important;
                border-radius: 6px !important;
                min-height: 0 !important;
                box-sizing: border-box !important;
            }
            #adminmenu li.menu-top > a {
                width: 100% !important;
                max-width: 100% !important;
                border-radius: 6px !important;
                padding: 0 8px !important;
                height: 32px !important;
                min-height: 32px !important;
                line-height: 32px !important;
                font-size: 13px !important;
                font-weight: 500 !important;
                color: #a1a1aa !important;
                background-color: transparent !important;
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                border: none !important;
                box-sizing: border-box !important;
                transition: background-color 0.15s ease, color 0.15s ease !important;
            }
            #adminmenu div.wp-menu-image {
                width: 18px !important;
                height: 18px !important;
                line-height: 18px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                float: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            #adminmenu div.wp-menu-image:before {
                font-size: 15px !important;
                width: 15px !important;
                height: 15px !important;
                line-height: 15px !important;
                padding: 0 !important;
                color: #71717a !important;
                transition: color 0.15s ease !important;
            }
            #adminmenu div.wp-menu-name {
                padding: 0 !important;
                font-size: 13px !important;
                line-height: 32px !important;
                font-weight: 500 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                flex: 1 1 auto !important;
                min-width: 0 !important;
            }
            #adminmenu li.menu-top:hover > a,
            #adminmenu li.menu-top > a:hover,
            #adminmenu li.opensub > a.menu-top {
                background-color: #18181b !important;
                color: #fafafa !important;
            }
            #adminmenu li.menu-top:hover div.wp-menu-image:before,
            #adminmenu li.opensub div.wp-menu-image:before {
                color: #ffffff !important;
            }
            #adminmenu li.current > a.menu-top,
            #adminmenu li.wp-has-current-submenu > a.menu-top,
            #adminmenu li.wp-has-current-submenu.hover > a.menu-top,
            #adminmenu li.wp-has-current-submenu.opensub > a.menu-top,
            #adminmenu li.wp-has-current-submenu > a.wp-has-current-submenu {
                background-color: #27272a !important;
                color: #ffffff !important;
                font-weight: 600 !important;
                border-radius: 6px !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            #adminmenu li.current div.wp-menu-image:before,
            #adminmenu li.wp-has-current-submenu div.wp-menu-image:before {
                color: #ffffff !important;
            }

            /* Completely eliminate all old WordPress triangle arrows, notches and pseudo-elements */
            .wp-menu-arrow,
            .wp-menu-arrow div,
            ul#adminmenu a.wp-has-current-submenu:after,
            ul#adminmenu > li.current > a.current:after,
            #adminmenu a.menu-top:after,
            #adminmenu li.menu-top:after,
            #adminmenu li.current > a.menu-top:after,
            #adminmenu li.wp-has-current-submenu > a.menu-top:after,
            #adminmenu li.opensub > a.menu-top:after,
            #adminmenu li.hover > a.menu-top:after,
            #adminmenu li > a:after,
            #adminmenu .wp-submenu:before,
            #adminmenu .wp-submenu:after,
            #adminmenu .wp-submenu li:before,
            #adminmenu .wp-submenu li:after,
            #adminmenu .wp-submenu a:before,
            #adminmenu .wp-submenu a:after {
                display: none !important;
                content: none !important;
                border: none !important;
                width: 0 !important;
                height: 0 !important;
            }

            /* --- Inline Submenu for Active Current Menu (Expanded Sidebar) --- */
            #adminmenu .wp-submenu,
            #adminmenu .wp-has-current-submenu .wp-submenu,
            #adminmenu .wp-has-current-submenu.opensub .wp-submenu,
            #adminmenu a.wp-has-current-submenu:focus + .wp-submenu {
                background: transparent !important;
                background-color: transparent !important;
                box-sizing: border-box !important;
            }
            body:not(.folded) #adminmenu li.wp-has-current-submenu .wp-submenu,
            body:not(.folded) #adminmenu li.wp-has-current-submenu.hover .wp-submenu,
            body:not(.folded) #adminmenu li.wp-has-current-submenu.opensub .wp-submenu {
                position: static !important;
                display: block !important;
                background: transparent !important;
                background-color: transparent !important;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                padding: 2px 0 4px 0 !important;
                margin: 0 !important;
                width: 100% !important;
                min-width: 0 !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            body:not(.folded) #adminmenu li.wp-has-current-submenu .wp-submenu li {
                background: transparent !important;
                background-color: transparent !important;
                margin: 1px 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            body:not(.folded) #adminmenu li.wp-has-current-submenu .wp-submenu a,
            #adminmenu .wp-has-current-submenu .wp-submenu a,
            #adminmenu a.wp-has-current-submenu:focus + .wp-submenu a,
            #adminmenu .wp-has-current-submenu.opensub .wp-submenu a {
                padding: 0 8px 0 26px !important;
                height: 28px !important;
                line-height: 28px !important;
                font-size: 12px !important;
                font-weight: 500 !important;
                color: #a1a1aa !important;
                border-radius: 6px !important;
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
                background-color: transparent !important;
                transition: color 0.15s ease, background-color 0.15s ease !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
            body:not(.folded) #adminmenu li.wp-has-current-submenu .wp-submenu a:hover,
            body:not(.folded) #adminmenu li.wp-has-current-submenu .wp-submenu a:focus,
            #adminmenu .wp-has-current-submenu .wp-submenu a:hover,
            #adminmenu .wp-has-current-submenu .wp-submenu a:focus,
            #adminmenu a.wp-has-current-submenu:focus + .wp-submenu a:hover,
            #adminmenu a.wp-has-current-submenu:focus + .wp-submenu a:focus {
                color: #ffffff !important;
                background-color: #18181b !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            body:not(.folded) #adminmenu li.wp-has-current-submenu .wp-submenu li.current a,
            #adminmenu .wp-has-current-submenu .wp-submenu li.current a,
            #adminmenu a.wp-has-current-submenu:focus + .wp-submenu li.current a,
            #adminmenu .wp-has-current-submenu.opensub .wp-submenu li.current a {
                color: #ffffff !important;
                font-weight: 600 !important;
                background-color: #27272a !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            /* --- Floating Flyout Submenu for Inactive Menus on Hover --- */
            body:not(.folded) #adminmenu li.wp-not-current-submenu.opensub .wp-submenu,
            body:not(.folded) #adminmenu li.wp-not-current-submenu:hover .wp-submenu,
            .folded #adminmenu li.opensub .wp-submenu,
            .folded #adminmenu li:hover .wp-submenu {
                position: absolute !important;
                top: 0 !important;
                left: 100% !important;
                background-color: #18181b !important;
                border: 1px solid #27272a !important;
                border-radius: 8px !important;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.3) !important;
                padding: 4px !important;
                margin-left: 6px !important;
                min-width: 170px !important;
                width: auto !important;
                z-index: 99999 !important;
            }
            body:not(.folded) #adminmenu .wp-submenu .wp-submenu-head {
                display: none !important;
            }
            .folded #adminmenu .wp-submenu .wp-submenu-head {
                color: #fafafa !important;
                font-weight: 600 !important;
                font-size: 12px !important;
                padding: 6px 10px !important;
                border-bottom: 1px solid #27272a !important;
                margin-bottom: 4px !important;
                background: transparent !important;
            }
            #adminmenu .wp-not-current-submenu .wp-submenu a,
            .folded #adminmenu .wp-submenu a {
                color: #a1a1aa !important;
                border-radius: 6px !important;
                padding: 6px 10px !important;
                font-size: 13px !important;
                font-weight: 400 !important;
                display: block !important;
                transition: color 0.15s ease, background-color 0.15s ease !important;
            }
            #adminmenu .wp-not-current-submenu .wp-submenu a:hover,
            .folded #adminmenu .wp-submenu a:hover {
                color: #ffffff !important;
                background-color: #27272a !important;
            }
            #collapse-menu {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            #collapse-button {
                color: #71717a !important;
                padding: 0 8px !important;
                height: 32px !important;
                line-height: 32px !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            #collapse-button:hover {
                color: #ffffff !important;
                background: #18181b !important;
            }
            .update-plugins,
            .awaiting-mod {
                background: #27272a !important;
                color: #fafafa !important;
                border: 1px solid #3f3f46 !important;
                font-size: 11px !important;
                font-weight: 600 !important;
                border-radius: 9999px !important;
                padding: 1px 6px !important;
            }

            /* --- 4. Main Body Content Area (#wpbody-content) --- */
            #wpbody-content {
                padding: 24px !important;
            }
            .wrap h1.wp-heading-inline,
            .wrap > h1:first-child {
                font-size: 24px !important;
                font-weight: 700 !important;
                letter-spacing: -0.025em !important;
                color: #09090b !important;
                margin-bottom: 16px !important;
            }

            /* --- Screen Options & Help Toggles --- */
            #screen-meta-links {
                margin: 0 24px 0 0 !important;
                top: 0 !important;
            }
            #screen-meta-links .screen-meta-toggle {
                position: relative !important;
            }
            #screen-meta-links .show-settings {
                background: #ffffff !important;
                border: 1px solid #e4e4e7 !important;
                border-top: none !important;
                border-radius: 0 0 6px 6px !important;
                color: #52525b !important;
                font-size: 12px !important;
                font-weight: 500 !important;
                height: 28px !important;
                line-height: 28px !important;
                padding: 0 10px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 4px !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
                transition: all 0.15s ease !important;
            }
            #screen-meta-links .show-settings:hover,
            #screen-meta-links .show-settings:focus {
                background: #f4f4f5 !important;
                color: #09090b !important;
                border-color: #d4d4d8 !important;
            }
            #screen-meta-links .show-settings:after {
                content: '\f140' !important;
                font: normal 14px/1 dashicons !important;
                position: static !important;
                bottom: auto !important;
                right: auto !important;
                vertical-align: middle !important;
                margin: 0 !important;
                padding: 0 !important;
                display: inline-block !important;
                color: #71717a !important;
            }
            #screen-meta-links .show-settings:hover:after {
                color: #09090b !important;
            }

            /* --- 5. Shadcn Button Design System & Page Actions --- */
            .wrap h1.wp-heading-inline {
                font-size: 24px !important;
                font-weight: 700 !important;
                letter-spacing: -0.025em !important;
                color: #09090b !important;
                margin-right: 12px !important;
                line-height: 32px !important;
            }
            .wrap .page-title-action,
            .wrap .page-title-action:active,
            .wrap .page-title-action:visited,
            .page-title-action {
                background: #ffffff !important;
                color: #09090b !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 6px !important;
                font-size: 13px !important;
                font-weight: 500 !important;
                padding: 4px 12px !important;
                height: 30px !important;
                line-height: 20px !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
                transition: all 0.15s ease !important;
                text-decoration: none !important;
                display: inline-flex !important;
                align-items: center !important;
                vertical-align: middle !important;
                top: 0 !important;
                margin-left: 8px !important;
            }
            .wrap .page-title-action:hover,
            .wrap .page-title-action:focus,
            .page-title-action:hover,
            .page-title-action:focus {
                background: #f4f4f5 !important;
                border-color: #d4d4d8 !important;
                color: #09090b !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08) !important;
                outline: none !important;
            }
            .wp-core-ui .button,
            .wp-core-ui .button-secondary {
                background: #ffffff !important;
                color: #09090b !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 6px !important;
                font-size: 13px !important;
                font-weight: 500 !important;
                padding: 5px 14px !important;
                height: 32px !important;
                line-height: 20px !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
                transition: all 0.15s ease !important;
                text-shadow: none !important;
                cursor: pointer !important;
            }
            .wp-core-ui .button:hover,
            .wp-core-ui .button-secondary:hover {
                background: #f4f4f5 !important;
                border-color: #d4d4d8 !important;
                color: #09090b !important;
            }
            .wp-core-ui .button-primary {
                background: #18181b !important;
                border-color: #18181b !important;
                color: #fafafa !important;
                text-shadow: none !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08) !important;
                border-radius: 6px !important;
                font-size: 13px !important;
                font-weight: 500 !important;
                padding: 5px 14px !important;
                height: 32px !important;
                line-height: 20px !important;
                transition: all 0.15s ease !important;
            }
            .wp-core-ui .button-primary:hover,
            .wp-core-ui .button-primary:focus {
                background: #27272a !important;
                border-color: #27272a !important;
                color: #ffffff !important;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.12) !important;
            }
            .wp-core-ui .button-link-delete {
                color: #ef4444 !important;
            }
            .wp-core-ui .button-link-delete:hover {
                color: #dc2626 !important;
                text-decoration: underline !important;
            }

            /* --- 6. Form Controls, Checkboxes & Inputs --- */
            input[type='text'],
            input[type='search'],
            input[type='password'],
            input[type='email'],
            input[type='url'],
            input[type='tel'],
            input[type='number'],
            input[type='date'],
            textarea,
            select {
                border: 1px solid #e4e4e7 !important;
                border-radius: 6px !important;
                background-color: #ffffff !important;
                color: #09090b !important;
                padding: 5px 10px !important;
                font-size: 13px !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;
                transition: border-color 0.15s ease, box-shadow 0.15s ease !important;
            }
            input[type='text']:focus,
            input[type='search']:focus,
            input[type='password']:focus,
            input[type='email']:focus,
            input[type='url']:focus,
            input[type='tel']:focus,
            input[type='number']:focus,
            input[type='date']:focus,
            textarea:focus,
            select:focus {
                border-color: #18181b !important;
                box-shadow: 0 0 0 1px #18181b !important;
                outline: none !important;
            }
            input[type='checkbox'],
            input[type='radio'] {
                border: 1px solid #d4d4d8 !important;
                border-radius: 4px !important;
                background: #ffffff !important;
                color: #18181b !important;
                width: 16px !important;
                height: 16px !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
                transition: all 0.15s ease !important;
            }
            input[type='radio'] {
                border-radius: 9999px !important;
            }
            input[type='checkbox']:checked,
            input[type='radio']:checked {
                background-color: #18181b !important;
                border-color: #18181b !important;
            }
            input[type='checkbox']:checked:before {
                filter: invert(1) !important;
            }
            input[type='checkbox']:focus,
            input[type='radio']:focus {
                border-color: #18181b !important;
                box-shadow: 0 0 0 2px rgba(24, 24, 27, 0.2) !important;
                outline: none !important;
            }

            /* --- 7. Subsubsub, Table Navigation & Search --- */
            .subsubsub {
                color: #a1a1aa !important;
                font-size: 13px !important;
                margin: 10px 0 14px 0 !important;
            }
            .subsubsub li {
                color: #d4d4d8 !important;
            }
            .subsubsub a {
                color: #71717a !important;
                text-decoration: none !important;
                font-size: 13px !important;
                transition: color 0.15s ease !important;
            }
            .subsubsub a:hover {
                color: #09090b !important;
            }
            .subsubsub a.current {
                color: #09090b !important;
                font-weight: 600 !important;
            }
            .subsubsub .count {
                color: #a1a1aa !important;
                font-weight: 400 !important;
            }

            .tablenav {
                height: auto !important;
                margin: 10px 0 !important;
                padding: 0 !important;
                clear: both !important;
            }
            .tablenav .actions select {
                height: 32px !important;
                line-height: 32px !important;
                border-radius: 6px !important;
                border: 1px solid #e4e4e7 !important;
                background: #ffffff !important;
                color: #09090b !important;
                font-size: 13px !important;
                padding: 0 24px 0 10px !important;
            }
            .tablenav .actions .button,
            .tablenav .button {
                height: 32px !important;
                line-height: 20px !important;
                border-radius: 6px !important;
                border: 1px solid #e4e4e7 !important;
                background: #ffffff !important;
                color: #09090b !important;
                font-size: 13px !important;
                font-weight: 500 !important;
                padding: 5px 12px !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
            }
            .tablenav .actions .button:hover,
            .tablenav .button:hover {
                background: #f4f4f5 !important;
                border-color: #d4d4d8 !important;
                color: #09090b !important;
            }
            .tablenav .displaying-num {
                color: #71717a !important;
                font-size: 12px !important;
                font-weight: 500 !important;
                line-height: 32px !important;
            }
            .tablenav-pages .pagination-links a,
            .tablenav-pages .pagination-links .tablenav-pages-navspan {
                border: 1px solid #e4e4e7 !important;
                background: #ffffff !important;
                color: #09090b !important;
                border-radius: 6px !important;
                padding: 3px 8px !important;
                font-size: 13px !important;
                font-weight: 500 !important;
                text-decoration: none !important;
            }
            .tablenav-pages .pagination-links a:hover {
                background: #f4f4f5 !important;
                border-color: #d4d4d8 !important;
            }
            .tablenav-pages .pagination-links .tablenav-pages-navspan {
                color: #a1a1aa !important;
                background: #fafafa !important;
            }

            .search-box {
                margin-bottom: 8px !important;
            }
            .search-box input[type='search'],
            #post-search-input {
                height: 32px !important;
                line-height: 32px !important;
                border-radius: 6px !important;
                border: 1px solid #e4e4e7 !important;
                background: #ffffff !important;
                font-size: 13px !important;
                padding: 0 10px !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;
            }
            .search-box input[type='submit'],
            #search-submit {
                height: 32px !important;
                line-height: 20px !important;
                border-radius: 6px !important;
                border: 1px solid #e4e4e7 !important;
                background: #ffffff !important;
                color: #09090b !important;
                font-size: 13px !important;
                font-weight: 500 !important;
                padding: 5px 14px !important;
                margin-left: 4px !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
            }
            .search-box input[type='submit']:hover,
            #search-submit:hover {
                background: #f4f4f5 !important;
                border-color: #d4d4d8 !important;
                color: #09090b !important;
            }

            /* --- 8. Modern WP List Tables (.wp-list-table) --- */
            .wp-list-table,
            table.widefat {
                background: #ffffff !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 10px !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03) !important;
                overflow: hidden !important;
                border-collapse: separate !important;
                border-spacing: 0 !important;
            }
            .wp-list-table th,
            table.widefat th {
                background: #fafafa !important;
                color: #71717a !important;
                font-weight: 600 !important;
                font-size: 12px !important;
                border-bottom: 1px solid #e4e4e7 !important;
                padding: 10px 14px !important;
                text-transform: none !important;
                letter-spacing: -0.01em !important;
            }
            .wp-list-table th a,
            .wp-list-table th a:visited {
                color: #71717a !important;
                text-decoration: none !important;
                font-weight: 600 !important;
            }
            .wp-list-table th a:hover,
            .wp-list-table th.sorted a {
                color: #09090b !important;
            }
            .sorting-indicator:before,
            .sorting-indicators:before {
                color: #71717a !important;
            }
            th.sorted .sorting-indicator:before {
                color: #09090b !important;
            }
            .wp-list-table a.row-title {
                color: #09090b !important;
                font-weight: 600 !important;
                font-size: 13px !important;
                text-decoration: none !important;
            }
            .wp-list-table a.row-title:hover {
                color: #18181b !important;
                text-decoration: underline !important;
            }
            .wp-list-table .post-state {
                color: #71717a !important;
                font-weight: 400 !important;
            }
            .wp-list-table td,
            table.widefat td {
                padding: 12px 14px !important;
                border-bottom: 1px solid #f4f4f5 !important;
                color: #09090b !important;
                font-size: 13px !important;
            }
            .wp-list-table tr:hover td,
            table.widefat tr:hover td {
                background: #fafafa !important;
            }
            .wp-list-table tr:last-child td,
            table.widefat tr:last-child td {
                border-bottom: none !important;
            }

            /* --- 8. Modern Callout Notices & Notifications Center --- */
            /* Hide raw disruptive notices from the content flow when JS is active */
            .js #wpbody-content > .notice:not(.rl-keep-notice),
            .js #wpbody-content > div.updated:not(.rl-keep-notice),
            .js #wpbody-content > div.error:not(.rl-keep-notice),
            .js #wpbody-content > .update-nag:not(.rl-keep-notice),
            .js #wpbody-content .wrap > .notice:not(.rl-keep-notice),
            .js #wpbody-content .wrap > div.updated:not(.rl-keep-notice),
            .js #wpbody-content .wrap > div.error:not(.rl-keep-notice) {
                display: none !important;
            }

            .notice,
            div.updated,
            div.error {
                background: #ffffff !important;
                border: 1px solid #e4e4e7 !important;
                border-left: 3px solid #18181b !important;
                border-radius: 8px !important;
                padding: 12px 16px !important;
                margin: 16px 0 !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
                color: #09090b !important;
                font-size: 13px !important;
            }
            .notice-success,
            div.updated {
                border-left-color: #18181b !important;
            }
            .notice-error,
            div.error {
                border-left-color: #dc2626 !important;
            }
            .notice-info {
                border-left-color: #71717a !important;
            }

            /* Admin Bar Notifications Bell Trigger */
            #wpadminbar #wp-admin-bar-rl-notifications {
                display: block !important;
            }
            #wpadminbar #wp-admin-bar-rl-notifications > .ab-item {
                padding: 0 !important;
                background: transparent !important;
            }
            .rl-notif-bar-trigger {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                position: relative !important;
                cursor: pointer !important;
                height: 32px !important;
                width: 38px !important;
                color: #a1a1aa !important;
                transition: color 0.15s ease, background 0.15s ease !important;
            }
            .rl-notif-bar-trigger:hover,
            body.rl-notif-open .rl-notif-bar-trigger {
                color: #ffffff !important;
                background: rgba(255, 255, 255, 0.08) !important;
            }
            .rl-notif-bell-icon {
                width: 16px !important;
                height: 16px !important;
                stroke: currentColor !important;
            }
            .rl-notif-badge {
                position: absolute !important;
                top: 5px !important;
                right: 5px !important;
                min-width: 14px !important;
                height: 14px !important;
                border-radius: 9999px !important;
                background: #09090b !important;
                color: #ffffff !important;
                border: 1px solid #3f3f46 !important;
                font-size: 9px !important;
                font-weight: 700 !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 0 3px !important;
                line-height: 1 !important;
                box-shadow: 0 0 0 1.5px #18181b !important;
            }

            /* Notifications Slide-Over Drawer & Backdrop */
            .rl-notif-backdrop {
                position: fixed !important;
                inset: 0 !important;
                background: rgba(9, 9, 11, 0.35) !important;
                backdrop-filter: blur(2px) !important;
                z-index: 100049 !important;
                opacity: 0 !important;
                pointer-events: none !important;
                transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
            }
            body.rl-notif-open .rl-notif-backdrop {
                opacity: 1 !important;
                pointer-events: auto !important;
            }
            .rl-notif-drawer {
                position: fixed !important;
                top: 0 !important;
                right: 0 !important;
                width: 420px !important;
                max-width: calc(100vw - 20px) !important;
                height: 100vh !important;
                z-index: 100050 !important;
                background: #ffffff !important;
                box-shadow: -4px 0 28px rgba(0, 0, 0, 0.12) !important;
                border-left: 1px solid #e4e4e7 !important;
                transform: translateX(100%) !important;
                transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
                display: flex !important;
                flex-direction: column !important;
                box-sizing: border-box !important;
            }
            body.rl-notif-open .rl-notif-drawer {
                transform: translateX(0) !important;
            }
            .rl-notif-drawer-header {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                padding: 16px 20px !important;
                border-bottom: 1px solid #f4f4f5 !important;
                background: #ffffff !important;
            }
            .rl-notif-header-title-wrap {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
            }
            .rl-notif-header-icon {
                width: 16px !important;
                height: 16px !important;
                stroke: #09090b !important;
            }
            .rl-notif-header-title {
                font-size: 15px !important;
                font-weight: 700 !important;
                color: #09090b !important;
                margin: 0 !important;
                letter-spacing: -0.01em !important;
            }
            .rl-notif-pill {
                background: #f4f4f5 !important;
                color: #09090b !important;
                font-size: 11px !important;
                font-weight: 600 !important;
                padding: 2px 8px !important;
                border-radius: 9999px !important;
                border: 1px solid #e4e4e7 !important;
            }
            .rl-notif-header-actions {
                display: flex !important;
                align-items: center !important;
                gap: 6px !important;
            }
            .rl-notif-btn-clear {
                background: transparent !important;
                border: none !important;
                color: #71717a !important;
                font-size: 12px !important;
                font-weight: 500 !important;
                cursor: pointer !important;
                padding: 4px 8px !important;
                border-radius: 4px !important;
                transition: all 0.15s ease !important;
            }
            .rl-notif-btn-clear:hover {
                color: #09090b !important;
                background: #f4f4f5 !important;
            }
            .rl-notif-btn-close {
                background: transparent !important;
                border: none !important;
                color: #71717a !important;
                font-size: 20px !important;
                cursor: pointer !important;
                width: 28px !important;
                height: 28px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 6px !important;
                transition: all 0.15s ease !important;
                line-height: 1 !important;
            }
            .rl-notif-btn-close:hover {
                color: #09090b !important;
                background: #f4f4f5 !important;
            }
            .rl-notif-drawer-body {
                flex: 1 !important;
                overflow-y: auto !important;
                padding: 16px 20px !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 12px !important;
            }
            .rl-notif-card {
                background: #fafafa !important;
                border: 1px solid #e4e4e7 !important;
                border-left: 3px solid #18181b !important;
                border-radius: 8px !important;
                padding: 12px 14px !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 6px !important;
                transition: opacity 0.2s ease, transform 0.2s ease !important;
            }
            .rl-notif-card.is-dismissing {
                opacity: 0 !important;
                transform: translateX(20px) !important;
            }
            .rl-notif-card.rl-notif-warning {
                border-left-color: #f59e0b !important;
            }
            .rl-notif-card.rl-notif-error {
                border-left-color: #ef4444 !important;
            }
            .rl-notif-card.rl-notif-info {
                border-left-color: #71717a !important;
            }
            .rl-notif-card.rl-notif-success {
                border-left-color: #10b981 !important;
            }
            .rl-notif-card-header {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
            }
            .rl-notif-tag {
                font-size: 10px !important;
                font-weight: 600 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.04em !important;
                padding: 2px 6px !important;
                border-radius: 4px !important;
                background: #f4f4f5 !important;
                color: #52525b !important;
                border: 1px solid #e4e4e7 !important;
            }
            .rl-tag-warning {
                background: #fef3c7 !important;
                color: #92400e !important;
                border-color: #fde68a !important;
            }
            .rl-tag-error {
                background: #fee2e2 !important;
                color: #991b1b !important;
                border-color: #fecaca !important;
            }
            .rl-tag-success {
                background: #d1fae5 !important;
                color: #065f46 !important;
                border-color: #a7f3d0 !important;
            }
            .rl-tag-info {
                background: #f4f4f5 !important;
                color: #3f3f46 !important;
                border-color: #e4e4e7 !important;
            }
            .rl-notif-dismiss-item-btn {
                background: transparent !important;
                border: none !important;
                color: #a1a1aa !important;
                font-size: 16px !important;
                cursor: pointer !important;
                width: 20px !important;
                height: 20px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 4px !important;
                line-height: 1 !important;
                transition: all 0.15s ease !important;
            }
            .rl-notif-dismiss-item-btn:hover {
                color: #09090b !important;
                background: #e4e4e7 !important;
            }
            .rl-notif-card-body {
                font-size: 12px !important;
                color: #09090b !important;
                line-height: 1.45 !important;
            }
            .rl-notif-card-body p {
                margin: 0 !important;
            }
            .rl-notif-card-body code {
                background: #f4f4f5 !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 4px !important;
                padding: 1px 5px !important;
                font-size: 11px !important;
                color: #09090b !important;
            }
            .rl-notif-empty-state {
                flex: 1 !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                text-align: center !important;
                padding: 40px 24px !important;
            }
            .rl-notif-empty-icon {
                width: 44px !important;
                height: 44px !important;
                border-radius: 50% !important;
                background: #f4f4f5 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                margin-bottom: 12px !important;
            }
            .rl-notif-empty-icon svg {
                width: 20px !important;
                height: 20px !important;
                stroke: #10b981 !important;
            }
            .rl-notif-empty-title {
                font-size: 14px !important;
                font-weight: 600 !important;
                color: #09090b !important;
                margin: 0 0 4px 0 !important;
            }
            .rl-notif-empty-desc {
                font-size: 12px !important;
                color: #71717a !important;
                margin: 0 !important;
                line-height: 1.4 !important;
                max-width: 260px !important;
            }
            .rl-notif-drawer-footer {
                padding: 12px 20px !important;
                border-top: 1px solid #f4f4f5 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                font-size: 11px !important;
                color: #a1a1aa !important;
                background: #ffffff !important;
            }
            .rl-notif-link-subtle {
                background: transparent !important;
                border: none !important;
                color: #71717a !important;
                font-size: 11px !important;
                cursor: pointer !important;
                text-decoration: underline !important;
                padding: 0 !important;
            }
            .rl-notif-link-subtle:hover {
                color: #09090b !important;
            }

            /* --- 9. Post Boxes & Modern Dashboard Widgets (.postbox) --- */
            #dashboard-widgets .postbox-container .empty-container {
                display: none !important;
            }
            @media only screen and (min-width: 800px) {
                #dashboard-widgets #postbox-container-1,
                #dashboard-widgets #postbox-container-2 {
                    width: 50% !important;
                    box-sizing: border-box !important;
                }
                #dashboard-widgets #postbox-container-3,
                #dashboard-widgets #postbox-container-4 {
                    display: none !important;
                }
            }
            @media only screen and (max-width: 799px) {
                #dashboard-widgets #postbox-container-1,
                #dashboard-widgets #postbox-container-2,
                #dashboard-widgets #postbox-container-3,
                #dashboard-widgets #postbox-container-4 {
                    width: 100% !important;
                    box-sizing: border-box !important;
                }
            }
            .postbox {
                background: #ffffff !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 12px !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
                margin-bottom: 20px !important;
                overflow: hidden !important;
            }
            .postbox-header {
                border-bottom: 1px solid #f4f4f5 !important;
                padding: 14px 18px !important;
                background: #ffffff !important;
            }
            .postbox-header h2,
            .postbox .hndle {
                font-weight: 600 !important;
                font-size: 15px !important;
                color: #09090b !important;
                letter-spacing: -0.01em !important;
                border: none !important;
            }
            .postbox .inside {
                padding: 18px !important;
                margin: 0 !important;
            }

            /* Dashboard Marketing KPIs */
            .rl-dash-kpi-grid {
                display: grid !important;
                grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)) !important;
                gap: 12px !important;
                margin-bottom: 16px !important;
            }
            .rl-dash-kpi-card {
                background: #fafafa !important;
                border: 1px solid #f4f4f5 !important;
                border-radius: 8px !important;
                padding: 14px 16px !important;
            }
            .rl-dash-kpi-header {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                margin-bottom: 6px !important;
            }
            .rl-dash-kpi-label {
                font-size: 11px !important;
                font-weight: 600 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.05em !important;
                color: #71717a !important;
            }
            .rl-dash-kpi-icon {
                width: 14px !important;
                height: 14px !important;
                stroke: #a1a1aa !important;
            }
            .rl-dash-kpi-number {
                font-size: 24px !important;
                font-weight: 700 !important;
                color: #09090b !important;
                line-height: 1.1 !important;
                letter-spacing: -0.02em !important;
            }
            .rl-dash-kpi-meta {
                font-size: 12px !important;
                color: #71717a !important;
                margin-top: 4px !important;
            }
            .rl-dash-badge-dark {
                background: #09090b !important;
                color: #ffffff !important;
                font-size: 10px !important;
                font-weight: 600 !important;
                padding: 2px 7px !important;
                border-radius: 9999px !important;
                white-space: nowrap !important;
                line-height: 1.2 !important;
                display: inline-block !important;
            }
            .rl-dash-sources-bar {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                flex-wrap: wrap !important;
                background: #fafafa !important;
                border: 1px solid #f4f4f5 !important;
                border-radius: 8px !important;
                padding: 10px 14px !important;
                margin-bottom: 14px !important;
            }
            .rl-dash-sources-title {
                font-size: 12px !important;
                font-weight: 600 !important;
                color: #09090b !important;
            }
            .rl-dash-sources-list {
                display: flex !important;
                gap: 6px !important;
                flex-wrap: wrap !important;
            }
            .rl-dash-source-tag {
                background: #ffffff !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 6px !important;
                padding: 2px 8px !important;
                font-size: 11px !important;
                color: #52525b !important;
            }
            .rl-dash-source-count {
                background: #f4f4f5 !important;
                border-radius: 9999px !important;
                padding: 1px 5px !important;
                font-weight: 600 !important;
                margin-left: 4px !important;
                font-size: 10px !important;
                color: #09090b !important;
            }

            /* Dashboard Recent Leads Table */
            .rl-dash-table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 13px !important;
            }
            .rl-dash-table th {
                text-align: left !important;
                padding: 8px 10px !important;
                font-size: 11px !important;
                font-weight: 600 !important;
                text-transform: uppercase !important;
                color: #71717a !important;
                border-bottom: 1px solid #e4e4e7 !important;
            }
            .rl-dash-table td {
                padding: 10px !important;
                border-bottom: 1px solid #f4f4f5 !important;
                vertical-align: middle !important;
            }
            .rl-dash-contact-cell {
                display: flex !important;
                align-items: center !important;
                gap: 10px !important;
            }
            .rl-dash-avatar {
                width: 28px !important;
                height: 28px !important;
                border-radius: 50% !important;
                background: #18181b !important;
                color: #fafafa !important;
                font-size: 10px !important;
                font-weight: 600 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                flex-shrink: 0 !important;
            }
            .rl-dash-lead-name {
                font-weight: 600 !important;
                color: #09090b !important;
                line-height: 1.2 !important;
            }
            .rl-dash-lead-sub {
                font-size: 12px !important;
                color: #71717a !important;
                line-height: 1.2 !important;
            }
            .rl-dash-timestamp {
                font-size: 12px !important;
                color: #71717a !important;
                white-space: nowrap !important;
            }
            .rl-dash-btn-ghost {
                display: inline-flex !important;
                align-items: center !important;
                font-size: 12px !important;
                font-weight: 500 !important;
                color: #09090b !important;
                text-decoration: none !important;
                padding: 4px 8px !important;
                border-radius: 6px !important;
                background: #f4f4f5 !important;
                transition: background 0.15s !important;
            }
            .rl-dash-btn-ghost:hover {
                background: #e4e4e7 !important;
                text-decoration: none !important;
            }

            /* Dashboard Badges */
            .rl-badge-dark {
                background: #09090b !important;
                color: #ffffff !important;
                padding: 2px 8px !important;
                border-radius: 9999px !important;
                font-size: 11px !important;
                font-weight: 600 !important;
                display: inline-block !important;
            }
            .rl-badge-subtle {
                background: #f4f4f5 !important;
                color: #71717a !important;
                padding: 2px 8px !important;
                border-radius: 9999px !important;
                font-size: 11px !important;
                font-weight: 500 !important;
                display: inline-block !important;
            }
            .rl-badge-emerald {
                background: #ecfdf5 !important;
                color: #059669 !important;
                border: 1px solid #a7f3d0 !important;
                padding: 2px 8px !important;
                border-radius: 9999px !important;
                font-size: 11px !important;
                font-weight: 600 !important;
                display: inline-block !important;
            }
            .rl-badge-zinc {
                background: #f4f4f5 !important;
                color: #52525b !important;
                border: 1px solid #e4e4e7 !important;
                padding: 2px 8px !important;
                border-radius: 9999px !important;
                font-size: 11px !important;
                font-weight: 500 !important;
                display: inline-block !important;
            }
            .rl-badge-amber {
                background: #fffbeb !important;
                color: #d97706 !important;
                border: 1px solid #fde68a !important;
                padding: 2px 8px !important;
                border-radius: 9999px !important;
                font-size: 11px !important;
                font-weight: 600 !important;
                display: inline-block !important;
            }

            /* Dashboard Platform Health */
            .rl-dash-health-wrap {
                display: flex !important;
                flex-direction: column !important;
                gap: 8px !important;
            }
            .rl-dash-health-item {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                padding: 9px 12px !important;
                background: #fafafa !important;
                border: 1px solid #f4f4f5 !important;
                border-radius: 8px !important;
                gap: 12px !important;
            }
            .rl-dash-health-left {
                display: flex !important;
                align-items: center !important;
                gap: 10px !important;
            }
            .rl-dash-indicator-green {
                width: 8px !important;
                height: 8px !important;
                border-radius: 50% !important;
                background: #10b981 !important;
                box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2) !important;
                flex-shrink: 0 !important;
            }
            .rl-dash-health-name {
                font-size: 13px !important;
                font-weight: 600 !important;
                color: #09090b !important;
                line-height: 1.2 !important;
            }
            .rl-dash-health-desc {
                font-size: 11px !important;
                color: #71717a !important;
                line-height: 1.2 !important;
            }

            /* Dashboard Marketing Shortcuts */
            .rl-dash-shortcuts-grid {
                display: grid !important;
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)) !important;
                gap: 10px !important;
            }
            .rl-dash-shortcut-card {
                display: flex !important;
                flex-direction: column !important;
                gap: 6px !important;
                padding: 12px 14px !important;
                background: #fafafa !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 8px !important;
                text-decoration: none !important;
                transition: all 0.15s ease !important;
            }
            .rl-dash-shortcut-card:hover {
                background: #ffffff !important;
                border-color: #09090b !important;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05) !important;
                transform: translateY(-1px) !important;
            }
            .rl-dash-shortcut-icon {
                width: 28px !important;
                height: 28px !important;
                border-radius: 6px !important;
                background: #f4f4f5 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                color: #09090b !important;
            }
            .rl-dash-shortcut-icon svg {
                width: 14px !important;
                height: 14px !important;
                stroke: #09090b !important;
            }
            .rl-dash-shortcut-title {
                font-size: 13px !important;
                font-weight: 600 !important;
                color: #09090b !important;
                text-decoration: none !important;
                line-height: 1.2 !important;
            }
            .rl-dash-shortcut-desc {
                font-size: 11px !important;
                color: #71717a !important;
                text-decoration: none !important;
                line-height: 1.2 !important;
            }
            .rl-dash-card-footer {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                margin-top: 14px !important;
                padding-top: 12px !important;
                border-top: 1px solid #f4f4f5 !important;
                font-size: 12px !important;
            }
            .rl-dash-link {
                font-weight: 600 !important;
                color: #09090b !important;
                text-decoration: none !important;
            }
            .rl-dash-link:hover {
                text-decoration: underline !important;
            }
            .rl-dash-footer-meta {
                color: #71717a !important;
            }
            .rl-dash-empty {
                text-align: center !important;
                padding: 24px !important;
                color: #71717a !important;
            }

            /* Funnel Progression Styles */
            .rl-dash-funnel-box {
                background: #fafafa !important;
                border: 1px solid #f4f4f5 !important;
                border-radius: 8px !important;
                padding: 14px 16px !important;
                margin-bottom: 14px !important;
            }
            .rl-dash-funnel-header {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                margin-bottom: 12px !important;
            }
            .rl-dash-box-subtitle {
                font-size: 13px !important;
                font-weight: 600 !important;
                color: #09090b !important;
            }
            .rl-dash-box-meta {
                font-size: 11px !important;
                color: #71717a !important;
            }
            .rl-dash-funnel-stages {
                display: flex !important;
                flex-direction: column !important;
                gap: 10px !important;
            }
            .rl-dash-funnel-stage {
                display: flex !important;
                flex-direction: column !important;
                gap: 5px !important;
            }
            .rl-dash-stage-info {
                display: flex !important;
                justify-content: space-between !important;
                font-size: 11px !important;
            }
            .rl-dash-stage-name {
                font-weight: 500 !important;
                color: #3f3f46 !important;
            }
            .rl-dash-stage-val {
                font-weight: 600 !important;
                color: #09090b !important;
            }
            .rl-dash-bar-track {
                background: #f4f4f5 !important;
                border: 1px solid #e4e4e7 !important;
                height: 7px !important;
                border-radius: 9999px !important;
                overflow: hidden !important;
            }
            .rl-dash-bar-fill {
                height: 100% !important;
                border-radius: 9999px !important;
                transition: width 0.3s ease !important;
            }
            .rl-dash-bar-fill.rl-stage-1 {
                background: #09090b !important;
            }
            .rl-dash-bar-fill.rl-stage-2 {
                background: #27272a !important;
            }
            .rl-dash-bar-fill.rl-stage-3 {
                background: #10b981 !important;
            }

            /* 7-Day Ingestion Volume Responsive Bar Chart */
            .rl-dash-chart-card {
                background: #fafafa !important;
                border: 1px solid #f4f4f5 !important;
                border-radius: 8px !important;
                padding: 16px 18px 14px 18px !important;
            }
            .rl-dash-chart-header {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                margin-bottom: 16px !important;
            }
            .rl-dash-chart-body {
                width: 100% !important;
                padding-top: 4px !important;
            }
            .rl-dash-barchart-grid {
                display: grid !important;
                grid-template-columns: repeat(7, 1fr) !important;
                gap: 8px !important;
                align-items: end !important;
                height: 110px !important;
                border-bottom: 1px solid #e4e4e7 !important;
                padding-bottom: 2px !important;
            }
            .rl-dash-bar-col {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                height: 100% !important;
                justify-content: flex-end !important;
                position: relative !important;
            }
            .rl-dash-bar-val-wrap {
                height: 20px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                margin-bottom: 6px !important;
            }
            .rl-dash-bar-val {
                font-size: 11px !important;
                font-weight: 600 !important;
                color: #09090b !important;
                line-height: 1 !important;
            }
            .rl-dash-bar-val.rl-val-peak {
                background: #09090b !important;
                color: #ffffff !important;
                padding: 2px 6px !important;
                border-radius: 4px !important;
                font-size: 10px !important;
                letter-spacing: 0.02em !important;
            }
            .rl-dash-bar-val-empty {
                font-size: 11px !important;
                color: #d4d4d8 !important;
                line-height: 1 !important;
            }
            .rl-dash-bar-slot {
                width: 100% !important;
                height: 56px !important;
                display: flex !important;
                align-items: flex-end !important;
                justify-content: center !important;
            }
            .rl-dash-bar-fill-v {
                width: 22px !important;
                max-width: 26px !important;
                background: #27272a !important;
                border-radius: 4px 4px 0 0 !important;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
                cursor: pointer !important;
            }
            .rl-dash-bar-fill-v.rl-bar-peak {
                background: #09090b !important;
                box-shadow: 0 2px 6px rgba(9, 9, 11, 0.2) !important;
            }
            .rl-dash-bar-fill-v:hover {
                filter: brightness(1.3) !important;
                transform: translateY(-2px) !important;
            }
            .rl-dash-bar-fill-zero {
                width: 18px !important;
                height: 3px !important;
                background: #e4e4e7 !important;
                border-radius: 2px 2px 0 0 !important;
            }
            .rl-dash-bar-label-wrap {
                padding-top: 8px !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
            }
            .rl-dash-bar-day {
                font-size: 11px !important;
                font-weight: 500 !important;
                color: #71717a !important;
                line-height: 1 !important;
            }
            .rl-dash-bar-day.rl-day-peak {
                font-weight: 700 !important;
                color: #09090b !important;
            }

            /* Channels Stacked Distribution */
            .rl-dash-stacked-bar {
                display: flex !important;
                height: 8px !important;
                border-radius: 9999px !important;
                overflow: hidden !important;
                background: #e4e4e7 !important;
                margin-bottom: 14px !important;
                gap: 2px !important;
            }
            .rl-dash-stacked-seg {
                height: 100% !important;
                border-radius: 9999px !important;
                transition: opacity 0.15s ease !important;
            }
            .rl-dash-stacked-seg:hover {
                opacity: 0.8 !important;
            }
            .rl-dash-channels-grid {
                display: flex !important;
                flex-direction: column !important;
                gap: 6px !important;
            }
            .rl-dash-channel-item {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                padding: 6px 10px !important;
                background: #fafafa !important;
                border: 1px solid #f4f4f5 !important;
                border-radius: 6px !important;
                font-size: 12px !important;
            }
            .rl-dash-channel-left {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
            }
            .rl-dash-color-dot {
                width: 8px !important;
                height: 8px !important;
                border-radius: 50% !important;
                flex-shrink: 0 !important;
            }
            .rl-dash-channel-name {
                font-weight: 500 !important;
                color: #09090b !important;
            }
            .rl-dash-channel-right {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
            }
            .rl-dash-channel-count {
                font-weight: 600 !important;
                color: #09090b !important;
            }
            .rl-dash-channel-pct {
                color: #71717a !important;
                font-size: 11px !important;
            }
            .rl-dash-channel-link {
                color: #71717a !important;
                text-decoration: none !important;
                font-weight: 600 !important;
                transition: color 0.15s ease !important;
            }
            .rl-dash-channel-link:hover {
                color: #09090b !important;
            }

            /* Domain Architecture Overview */
            .rl-dash-domains-list {
                display: flex !important;
                flex-direction: column !important;
                gap: 10px !important;
            }
            .rl-dash-domain-card {
                background: #fafafa !important;
                border: 1px solid #f4f4f5 !important;
                border-radius: 8px !important;
                padding: 12px 14px !important;
            }
            .rl-dash-domain-top {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                margin-bottom: 6px !important;
            }
            .rl-dash-domain-title-wrap {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
            }
            .rl-dash-domain-title {
                font-size: 13px !important;
                font-weight: 600 !important;
                color: #09090b !important;
            }
            .rl-dash-domain-desc {
                font-size: 12px !important;
                color: #71717a !important;
                line-height: 1.4 !important;
                margin: 0 0 8px 0 !important;
            }
            .rl-dash-domain-badges {
                display: flex !important;
                gap: 6px !important;
                flex-wrap: wrap !important;
            }
            .rl-dash-chip {
                background: #ffffff !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 4px !important;
                padding: 2px 6px !important;
                font-size: 11px !important;
                font-weight: 500 !important;
                color: #27272a !important;
            }

            /* --- 10. Global Link & Text Neutralization (Zero Hideous WP Blue) --- */
            #wpbody-content a,
            .wrap a,
            #dashboard-widgets a,
            .postbox a,
            .notice a,
            .wp-list-table a,
            #activity-widget a,
            #dashboard_right_now a,
            #dashboard_quick_press a,
            #dashboard_site_health a,
            .community-events a,
            .welcome-panel a,
            #footer-thankyou a,
            #footer-upgrade a {
                color: #09090b !important;
                text-decoration: underline !important;
                text-decoration-color: #e4e4e7 !important;
                text-underline-offset: 2px !important;
                transition: color 0.15s ease, text-decoration-color 0.15s ease !important;
            }
            #wpbody-content a:hover,
            .wrap a:hover,
            #dashboard-widgets a:hover,
            .postbox a:hover,
            .notice a:hover,
            .wp-list-table a:hover,
            #activity-widget a:hover,
            #dashboard_right_now a:hover,
            #dashboard_quick_press a:hover,
            #dashboard_site_health a:hover,
            .community-events a:hover,
            .welcome-panel a:hover,
            #footer-thankyou a:hover,
            #footer-upgrade a:hover {
                color: #18181b !important;
                text-decoration-color: #09090b !important;
            }
            #wpbody-content a:focus,
            .wrap a:focus {
                outline: none !important;
                box-shadow: 0 0 0 2px rgba(9, 9, 11, 0.15) !important;
            }

            .subsubsub,
            .subsubsub a {
                color: #71717a !important;
                text-decoration: none !important;
                font-size: 12px !important;
            }
            .subsubsub a:hover,
            .subsubsub a.current {
                color: #09090b !important;
                font-weight: 600 !important;
            }
            .row-actions,
            .row-actions span,
            .row-actions a {
                color: #71717a !important;
                font-size: 12px !important;
                text-decoration: none !important;
            }
            .row-actions a:hover {
                color: #09090b !important;
                text-decoration: underline !important;
            }
            .row-actions span.trash a,
            .row-actions span.delete a {
                color: #ef4444 !important;
            }

            #dashboard_right_now li a {
                color: #09090b !important;
                font-weight: 500 !important;
            }
            #dashboard_right_now .dashicons,
            #dashboard_right_now .dashicons:before,
            #dashboard_right_now li a:before,
            #dashboard_right_now li span:before,
            #dashboard_activity .dashicons:before {
                color: #71717a !important;
            }
            #dashboard_site_health .site-health-progress-count {
                color: #09090b !important;
            }

            .notice code,
            #wpbody-content code {
                background: #f4f4f5 !important;
                color: #09090b !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 4px !important;
                padding: 2px 6px !important;
                font-size: 12px !important;
            }

            /* --- 11. Admin Footer (#wpfooter) --- */
            #wpfooter {
                color: #71717a !important;
                font-size: 12px !important;
                border-top: 1px solid #e4e4e7 !important;
                padding: 16px 24px !important;
                background: #fafafa !important;
            }
            #wpfooter a {
                color: #09090b !important;
                text-decoration: none !important;
                font-weight: 500 !important;
            }
            #wpfooter a:hover {
                text-decoration: underline !important;
            }
        ";
    }

    /**
     * Directly output login screen styles into <head> for guaranteed rendering.
     */
    public function renderLoginHeaderStyles(): void
    {
        echo '<style id="rl-login-custom-styles">' . $this->getLoginCss() . '</style>' . "\n";
    }

    /**
     * Generate CSS for the WordPress login page replacing WP logo with Remote Leverage ISO.
     */
    public function getLoginCss(): string
    {
        $blackIsoUri = $this->getIsoDataUri('#09090b');

        return "
            /* ==========================================================================
               REMOTE LEVERAGE LOGIN SCREEN (SHADCN/UI ZINC AESTHETIC)
               ========================================================================== */

            body.login {
                background-color: #f4f4f5 !important;
                background: radial-gradient(circle at 50% 0%, #ffffff 0%, #f4f4f5 100%) !important;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
                color: #09090b !important;
                -webkit-font-smoothing: antialiased !important;
                -moz-osx-font-smoothing: grayscale !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
                min-height: 100vh !important;
                padding: 40px 16px !important;
                box-sizing: border-box !important;
            }

            #login {
                width: 100% !important;
                max-width: 390px !important;
                padding: 0 !important;
                margin: 0 auto !important;
            }

            /* --- Logo Replacement (Remote Leverage ISO) --- */
            #login h1 {
                margin-bottom: 24px !important;
                text-align: center !important;
            }
            #login h1 a,
            .login h1 a {
                background-image: url(\"{$blackIsoUri}\") !important;
                background-size: 56px 56px !important;
                background-position: center !important;
                background-repeat: no-repeat !important;
                width: 56px !important;
                height: 56px !important;
                margin: 0 auto 12px auto !important;
                padding: 0 !important;
                display: block !important;
                transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
                text-indent: -9999px !important;
                overflow: hidden !important;
                outline: none !important;
                box-shadow: none !important;
            }
            #login h1 a:hover,
            .login h1 a:hover {
                transform: scale(1.05) !important;
            }
            #login h1:after {
                content: 'Remote Leverage' !important;
                display: block !important;
                font-size: 22px !important;
                font-weight: 700 !important;
                letter-spacing: -0.03em !important;
                color: #09090b !important;
                line-height: 1.25 !important;
                margin-top: 6px !important;
            }

            /* --- Messages, Notices & Errors --- */
            #login .message,
            #login .notice,
            #login #login_error,
            .login .message,
            .login .notice,
            .login #login_error {
                background: #ffffff !important;
                border: 1px solid #e4e4e7 !important;
                border-left: 3px solid #18181b !important;
                border-radius: 8px !important;
                padding: 12px 14px !important;
                margin-bottom: 20px !important;
                font-size: 13px !important;
                line-height: 1.5 !important;
                color: #09090b !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
            }
            #login .message p,
            #login .notice p,
            #login #login_error p,
            .login .message p,
            .login .notice p,
            .login #login_error p {
                margin: 0 !important;
                padding: 0 !important;
                font-size: 13px !important;
                color: #09090b !important;
            }
            #login #login_error,
            .login #login_error {
                border-left-color: #dc2626 !important;
                background: #fef2f2 !important;
                border-color: #fecaca !important;
            }
            #login #login_error p,
            .login #login_error p {
                color: #991b1b !important;
            }
            #login .message a,
            #login #login_error a {
                color: #09090b !important;
                font-weight: 500 !important;
                text-decoration: underline !important;
            }

            /* --- Form Card --- */
            #login form {
                background: #ffffff !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 12px !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 10px 25px -5px rgba(0, 0, 0, 0.04) !important;
                padding: 28px !important;
                margin: 0 0 20px 0 !important;
            }
            #login form p {
                margin-bottom: 18px !important;
            }
            #login form label {
                font-size: 13px !important;
                font-weight: 500 !important;
                color: #09090b !important;
                display: block !important;
                margin-bottom: 6px !important;
            }

            /* --- Form Inputs --- */
            #login form input[type='text'],
            #login form input[type='password'] {
                border: 1px solid #e4e4e7 !important;
                border-radius: 8px !important;
                background-color: #ffffff !important;
                color: #09090b !important;
                padding: 8px 12px !important;
                font-size: 14px !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;
                height: 42px !important;
                line-height: 24px !important;
                transition: border-color 0.15s ease, box-shadow 0.15s ease !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
            #login form input[type='text']:focus,
            #login form input[type='password']:focus {
                border-color: #09090b !important;
                box-shadow: 0 0 0 1px #09090b, 0 1px 2px rgba(0, 0, 0, 0.05) !important;
                outline: none !important;
            }

            /* --- Password Wrap & Visibility Button --- */
            #login form .user-pass-wrap {
                margin-bottom: 18px !important;
            }
            #login form .wp-pwd {
                position: relative !important;
                display: flex !important;
                align-items: center !important;
            }
            #login form .wp-pwd input[type='password'] {
                padding-right: 42px !important;
            }
            #login form .wp-pwd .wp-hide-pw {
                position: absolute !important;
                right: 6px !important;
                top: 50% !important;
                transform: translateY(-50%) !important;
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                color: #71717a !important;
                padding: 4px !important;
                height: 32px !important;
                width: 32px !important;
                min-height: 0 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                cursor: pointer !important;
                border-radius: 6px !important;
                transition: color 0.15s ease, background-color 0.15s ease !important;
            }
            #login form .wp-pwd .wp-hide-pw:hover {
                color: #09090b !important;
                background-color: #f4f4f5 !important;
            }
            #login form .wp-pwd .wp-hide-pw .dashicons {
                font-size: 18px !important;
                width: 18px !important;
                height: 18px !important;
                line-height: 18px !important;
            }

            /* --- Checkbox (Remember Me) --- */
            #login form .forgetmenot {
                float: none !important;
                margin: 4px 0 18px 0 !important;
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
            }
            #login form .forgetmenot label {
                display: inline-block !important;
                font-size: 13px !important;
                font-weight: 400 !important;
                color: #52525b !important;
                margin: 0 !important;
                line-height: 18px !important;
                cursor: pointer !important;
                user-select: none !important;
            }
            #login form input[type='checkbox'] {
                appearance: none !important;
                -webkit-appearance: none !important;
                width: 16px !important;
                height: 16px !important;
                border: 1px solid #d4d4d8 !important;
                border-radius: 4px !important;
                background-color: #ffffff !important;
                cursor: pointer !important;
                margin: 0 !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                transition: all 0.15s ease !important;
            }
            #login form input[type='checkbox']:checked {
                background-color: #09090b !important;
                border-color: #09090b !important;
                background-image: url(\"data:image/svg+xml,%3Csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3E%3C/svg%3E\") !important;
                background-size: 12px !important;
                background-position: center !important;
                background-repeat: no-repeat !important;
            }
            #login form input[type='checkbox']:focus {
                box-shadow: 0 0 0 2px rgba(9, 9, 11, 0.15) !important;
                border-color: #09090b !important;
                outline: none !important;
            }
            #login form .wp-tooltip {
                display: inline-flex !important;
                align-items: center !important;
            }
            #login form .wp-tooltip__toggle {
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                color: #a1a1aa !important;
                padding: 0 !important;
                margin-left: 2px !important;
                cursor: pointer !important;
                display: inline-flex !important;
                align-items: center !important;
            }
            #login form .wp-tooltip__toggle:hover {
                color: #71717a !important;
            }

            /* --- Submit Button (Solid Dark Zinc) --- */
            #login form p.submit {
                margin: 20px 0 0 0 !important;
                padding: 0 !important;
                float: none !important;
            }
            #login form .button-primary,
            #login form #wp-submit {
                background: #09090b !important;
                border: 1px solid #09090b !important;
                color: #ffffff !important;
                border-radius: 8px !important;
                font-size: 14px !important;
                font-weight: 500 !important;
                padding: 0 16px !important;
                height: 42px !important;
                line-height: 40px !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06) !important;
                transition: all 0.15s ease !important;
                width: 100% !important;
                float: none !important;
                cursor: pointer !important;
                text-shadow: none !important;
                display: block !important;
                text-align: center !important;
            }
            #login form .button-primary:hover,
            #login form #wp-submit:hover {
                background: #27272a !important;
                border-color: #27272a !important;
                color: #ffffff !important;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
            }
            #login form .button-primary:focus,
            #login form #wp-submit:focus {
                box-shadow: 0 0 0 2px rgba(9, 9, 11, 0.15) !important;
                outline: none !important;
            }
            #login form .button-primary:active,
            #login form #wp-submit:active {
                transform: translateY(1px) !important;
            }

            /* --- Bottom Navigation Links (Zero Blue Text) --- */
            #login #nav,
            #login #backtoblog {
                text-align: center !important;
                padding: 0 !important;
                margin: 14px 0 0 0 !important;
            }
            #login #nav a,
            #login #backtoblog a {
                color: #71717a !important;
                font-size: 13px !important;
                font-weight: 500 !important;
                text-decoration: none !important;
                transition: color 0.15s ease !important;
            }
            #login #nav a:hover,
            #login #backtoblog a:hover {
                color: #09090b !important;
                text-decoration: underline !important;
            }

            /* --- Language Switcher --- */
            .language-switcher {
                margin-top: 20px !important;
                text-align: center !important;
            }
            .language-switcher select {
                border: 1px solid #e4e4e7 !important;
                border-radius: 6px !important;
                background-color: #ffffff !important;
                color: #71717a !important;
                font-size: 12px !important;
                padding: 4px 8px !important;
            }
        ";
    }
}
