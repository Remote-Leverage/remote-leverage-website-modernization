<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin\Seo;

/**
 * Entry point for everything that turns Yoast SEO into our "SEO" section.
 *
 * Five pieces, registered together because they are meaningless apart: the menu rebrand, the
 * stylesheet skin, the replacement dashboard widget, the post-screen fixes (metabox title and
 * list-table column headings) and the rebuilt overview screen.
 *
 * All of it is gated on Yoast actually being active. Without the guard the widget would still
 * mount and report four zero-filled score bands read from postmeta that nothing writes — a
 * dashboard panel confidently stating the site has no SEO data, when really it has no SEO
 * plugin. A missing plugin should leave no trace, not a wrong number.
 */
class SeoAdmin
{
    public function register(): void
    {
        if (! self::pluginActive()) {
            return;
        }

        (new SeoMenu)->register();
        (new SeoAdminSkin)->register();
        (new SeoDashboardWidget)->register();
        (new SeoEditor)->register();
        (new SeoOverviewPage)->register();
    }

    /**
     * Whether Yoast SEO is loaded.
     *
     * `WPSEO_VERSION` is defined in wp-seo.php before any of our hooks can run, which makes it
     * a cheaper and more reliable signal here than is_plugin_active() — that needs
     * wp-admin/includes/plugin.php, which is not loaded on every request this runs in.
     */
    public static function pluginActive(): bool
    {
        return defined('WPSEO_VERSION');
    }
}
