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
            add_action('admin_bar_menu', [$this, 'customizeAdminBarLogo'], 11);
            add_action('admin_head', [$this, 'injectAdminFavicon']);
            add_action('login_head', [$this, 'injectAdminFavicon']);
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
     */
    public function getIsoDataUri(string $fillHex = '#FFFFFF'): string
    {
        $encodedFill = str_replace('#', '%23', $fillHex);

        return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 125 125' fill='none'%3E%3Cpath fill-rule='evenodd' clip-rule='evenodd' d='"
            .self::ISO_PATH
            ."' fill='{$encodedFill}'/%3E%3C/svg%3E";
    }

    /**
     * Replace WordPress "W" logo in admin bar with the Remote Leverage brand.
     */
    public function customizeAdminBarLogo(\WP_Admin_Bar $wp_admin_bar): void
    {
        $wp_admin_bar->remove_node('wp-logo');

        $leadsUrl = function_exists('admin_url') ? admin_url('admin.php?page=rl-leads') : '#';
        $siteUrl = function_exists('home_url') ? home_url('/') : '/';

        $wp_admin_bar->add_node([
            'id'    => 'wp-logo',
            'title' => '<span class="ab-icon rl-brand-iso" aria-hidden="true"></span><span class="rl-brand-text">Remote Leverage</span>',
            'href'  => $leadsUrl,
            'meta'  => [
                'title' => 'Remote Leverage Admin',
            ],
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'rl-sub-leads',
            'parent' => 'wp-logo',
            'title'  => 'Leads & Submissions',
            'href'   => $leadsUrl,
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'rl-sub-site',
            'parent' => 'wp-logo',
            'title'  => 'Live Website',
            'href'   => $siteUrl,
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
            #wpbody,
            #wpcontent {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
                background-color: #f4f4f5 !important;
                color: #09090b !important;
                -webkit-font-smoothing: antialiased !important;
                -moz-osx-font-smoothing: grayscale !important;
            }

            /* --- 2. Top Admin Bar (#wpadminbar) --- */
            #wpadminbar {
                background: #09090b !important;
                border-bottom: 1px solid #27272a !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
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
            #wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon,
            #wpadminbar .rl-brand-iso {
                width: 20px !important;
                height: 20px !important;
                background-image: url('{$whiteIsoUri}') !important;
                background-size: contain !important;
                background-position: center !important;
                background-repeat: no-repeat !important;
                display: inline-block !important;
                vertical-align: middle !important;
                margin-top: -2px !important;
                float: left !important;
            }
            #wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon:before {
                display: none !important;
                content: '' !important;
            }
            #wpadminbar .rl-brand-text {
                font-weight: 600 !important;
                color: #fafafa !important;
                margin-left: 6px !important;
                letter-spacing: -0.01em !important;
            }

            /* --- 3. Left Navigation Menu (#adminmenu) --- */
            #adminmenu,
            #adminmenuback,
            #adminmenuwrap {
                background-color: #09090b !important;
                border-right: 1px solid #27272a !important;
            }
            #adminmenu a {
                color: #a1a1aa !important;
                font-size: 13px !important;
                font-weight: 500 !important;
                transition: all 0.15s ease !important;
            }
            #adminmenu li.menu-top {
                margin: 2px 8px !important;
                border-radius: 6px !important;
                transition: all 0.15s ease !important;
            }
            #adminmenu li.menu-top > a {
                border-radius: 6px !important;
                padding: 7px 10px !important;
            }
            #adminmenu a:hover,
            #adminmenu li.menu-top:hover,
            #adminmenu li.opensub > a.menu-top {
                background-color: #18181b !important;
                color: #fafafa !important;
            }
            #adminmenu li.current a.menu-top,
            #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu,
            #adminmenu li.wp-has-current-submenu .wp-submenu .wp-submenu-head {
                background-color: #27272a !important;
                color: #ffffff !important;
                font-weight: 600 !important;
                border-radius: 6px !important;
            }
            #adminmenu li.current > a.menu-top:after,
            #adminmenu li.wp-has-current-submenu > a.menu-top:after {
                border-right-color: #f4f4f5 !important;
            }
            #adminmenu .wp-submenu {
                background-color: #121215 !important;
                border: 1px solid #27272a !important;
                border-radius: 8px !important;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2) !important;
                padding: 4px !important;
            }
            #adminmenu .wp-submenu a {
                color: #a1a1aa !important;
                border-radius: 6px !important;
                padding: 6px 12px !important;
            }
            #adminmenu .wp-submenu a:hover,
            #adminmenu .wp-submenu li.current a {
                color: #ffffff !important;
                background-color: #18181b !important;
            }
            #collapse-button {
                color: #71717a !important;
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

            /* --- 5. Shadcn Button Design System --- */
            .wp-core-ui .button,
            .wp-core-ui .button-secondary {
                background: #ffffff !important;
                color: #09090b !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 6px !important;
                font-size: 13px !important;
                font-weight: 500 !important;
                padding: 5px 14px !important;
                height: 34px !important;
                line-height: 22px !important;
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
                height: 34px !important;
                line-height: 22px !important;
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

            /* --- 6. Form Controls & Inputs --- */
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
                padding: 6px 12px !important;
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

            /* --- 7. Modern WP List Tables (.wp-list-table) --- */
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

            /* --- 8. Modern Callout Notices (.notice) --- */
            .notice,
            div.updated,
            div.error {
                background: #ffffff !important;
                border: 1px solid #e4e4e7 !important;
                border-left: 4px solid #f59e0b !important;
                border-radius: 8px !important;
                padding: 12px 16px !important;
                margin: 16px 0 !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
                color: #09090b !important;
                font-size: 13px !important;
            }
            .notice-success,
            div.updated {
                border-left-color: #10b981 !important;
            }
            .notice-error,
            div.error {
                border-left-color: #ef4444 !important;
            }
            .notice-info {
                border-left-color: #3b82f6 !important;
            }

            /* --- 9. Post Boxes & Panels (.postbox) --- */
            .postbox {
                background: #ffffff !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 10px !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
                overflow: hidden !important;
            }
            .postbox-header,
            .postbox .hndle {
                border-bottom: 1px solid #f4f4f5 !important;
                font-weight: 600 !important;
                font-size: 14px !important;
                color: #09090b !important;
                padding: 12px 16px !important;
                background: #fafafa !important;
            }

            /* --- 10. Admin Footer (#wpfooter) --- */
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
     * Generate CSS for the WordPress login page replacing WP logo with Remote Leverage ISO.
     */
    public function getLoginCss(): string
    {
        $blackIsoUri = $this->getIsoDataUri('#09090b');

        return "
            body.login {
                background-color: #f4f4f5 !important;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            }
            #login h1 a {
                background-image: url('{$blackIsoUri}') !important;
                background-size: 64px 64px !important;
                width: 64px !important;
                height: 64px !important;
                margin: 0 auto 20px auto !important;
            }
            #login form {
                background: #ffffff !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 12px !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
                padding: 26px !important;
            }
            #login .button-primary {
                background: #18181b !important;
                border-color: #18181b !important;
                color: #fafafa !important;
                border-radius: 6px !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08) !important;
                transition: all 0.15s ease !important;
            }
            #login .button-primary:hover {
                background: #27272a !important;
                border-color: #27272a !important;
            }
        ";
    }
}
