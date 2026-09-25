<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

/**
 * The shadcn-flavoured tokens the custom admin screens are built from.
 *
 * Extracted so a screen does not have to carry its own copy of the palette.
 * The values are the ones LeadsAdminDashboard already ships inline — same
 * greys, same radii, same button shapes — so screens using this look like the
 * ones that came before rather than like default WordPress.
 *
 * Everything is scoped under .rl-admin-wrap. wp-admin's own styles are loaded
 * on the same page and are aggressive, so the scope is what keeps these from
 * being a global restyle of the dashboard.
 */
class AdminDesignSystem
{
    /**
     * Enqueue the tokens onto the current admin screen.
     */
    public static function enqueue(): void
    {
        wp_add_inline_style('wp-admin', self::css());
    }

    public static function css(): string
    {
        return <<<'CSS'
        .rl-admin-wrap {
            max-width: 1100px;
            margin: 28px auto 64px !important;
            padding: 0 20px !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #09090b;
            -webkit-font-smoothing: antialiased;
        }
        .rl-admin-wrap * { box-sizing: border-box; }
        .rl-admin-wrap h1, .rl-admin-wrap h2, .rl-admin-wrap h3 { color: #09090b; }

        .rl-admin-header { margin-bottom: 20px; }
        .rl-admin-title {
            font-size: 24px; font-weight: 700; letter-spacing: -0.025em;
            margin: 0; line-height: 1.25; padding: 0;
        }
        .rl-admin-subtitle { margin: 4px 0 0; color: #71717a; font-size: 13px; line-height: 1.5; }

        .rl-tabs {
            display: inline-flex; height: 38px; align-items: center; border-radius: 8px;
            background: #f4f4f5; padding: 3px; gap: 2px; margin: 0 0 18px;
            border: 1px solid #e4e4e7;
        }
        .rl-tab {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 6px 14px; font-size: 13px; font-weight: 500; color: #71717a;
            text-decoration: none; border-radius: 6px; transition: all .15s ease; white-space: nowrap;
        }
        .rl-tab:hover { color: #09090b; }
        .rl-tab-active {
            background: #fff; color: #09090b !important; font-weight: 600;
            box-shadow: 0 1px 2px rgba(0,0,0,.06);
        }

        .rl-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            font-size: 13px; font-weight: 500; padding: 7px 14px; border-radius: 6px;
            text-decoration: none; cursor: pointer; transition: all .15s ease; line-height: 1.4;
            white-space: nowrap; border: 1px solid transparent;
        }
        .rl-btn-primary {
            background: #18181b; color: #fafafa !important; border-color: #18181b;
            box-shadow: 0 1px 2px rgba(0,0,0,.05);
        }
        .rl-btn-primary:hover { background: #27272a; border-color: #27272a; }
        .rl-btn-outline {
            background: #fff; color: #09090b !important; border-color: #e4e4e7;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .rl-btn-outline:hover { background: #f4f4f5; border-color: #d4d4d8; }
        .rl-btn-destructive {
            background: #fff; color: #ef4444 !important; border-color: #fecaca;
            box-shadow: 0 1px 2px rgba(0,0,0,.03);
        }
        .rl-btn-destructive:hover { background: #fef2f2; border-color: #f87171; color: #dc2626 !important; }
        .rl-btn-sm { padding: 5px 10px; font-size: 12px; }
        .rl-btn[disabled] { opacity: .5; cursor: not-allowed; }

        .rl-card {
            background: #fff; border: 1px solid #e4e4e7; border-radius: 12px;
            padding: 18px 20px; margin-bottom: 16px;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .rl-card-title { font-size: 15px; font-weight: 600; margin: 0 0 4px; letter-spacing: -0.01em; }
        .rl-card-sub { color: #71717a; font-size: 13px; margin: 0 0 14px; line-height: 1.5; }

        .rl-field { margin-bottom: 16px; }
        .rl-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .rl-hint { display: block; color: #71717a; font-size: 12px; margin-top: 5px; line-height: 1.5; }
        .rl-input, .rl-select {
            border: 1px solid #e4e4e7 !important; border-radius: 6px !important;
            padding: 7px 11px !important; font-size: 13px !important; color: #09090b !important;
            background: #fff !important; box-shadow: none !important; min-height: 36px;
        }
        .rl-input { width: 100%; max-width: 420px; }
        .rl-input:focus, .rl-select:focus {
            border-color: #a1a1aa !important; outline: 2px solid rgba(24,24,27,.08) !important;
        }
        .rl-input::placeholder { color: #a1a1aa; }

        .rl-choices { list-style: none; margin: 0; padding: 0; }
        .rl-choice {
            border: 1px solid #e4e4e7; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px;
            transition: border-color .15s ease;
        }
        .rl-choice:hover { border-color: #d4d4d8; }
        .rl-choice label { font-size: 13px; font-weight: 600; }
        .rl-choice .rl-hint { margin-top: 3px; }
        .rl-choice-aside { font-weight: 400; color: #71717a; font-size: 12px; margin-left: 10px; }

        .rl-badge {
            display: inline-block; background: #f4f4f5; color: #3f3f46; border-radius: 9999px;
            padding: 2px 9px; font-size: 11px; font-weight: 600; line-height: 1.6;
        }
        .rl-badge-ok { background: #f0fdf4; color: #15803d; }
        .rl-badge-bad { background: #fef2f2; color: #b91c1c; }
        .rl-badge-busy { background: #fefce8; color: #a16207; }

        .rl-table-container { background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden; }
        .rl-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .rl-table th {
            text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em;
            color: #71717a; font-weight: 600; padding: 10px 16px; border-bottom: 1px solid #e4e4e7;
            background: #fafafa;
        }
        .rl-table td { padding: 12px 16px; border-bottom: 1px solid #f4f4f5; vertical-align: middle; }
        .rl-table tr:last-child td { border-bottom: 0; }
        .rl-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; color: #71717a; }

        .rl-inline { display: inline; }
        .rl-empty { color: #71717a; font-size: 13px; padding: 18px 20px; }

        /*
         * The pieces a record-keeping screen needs beyond a settings form: a header with its
         * actions on the right, KPI tiles, a filter toolbar, a person cell, a grid form, a
         * key-value table and a two-column detail layout.
         *
         * Lifted from ReferralAdminDashboard's inline sheet, which drew them first, with the
         * same greys and sizes, so a screen built from these reads as the same product. Renamed
         * where that sheet's names collide with the ones above: its `.rl-card` is a KPI tile,
         * which is `.rl-stat` here, because `.rl-card` in this system is a padded section.
         */
        .rl-admin-header-split {
            display: flex; align-items: flex-start; justify-content: space-between;
            flex-wrap: wrap; gap: 16px;
        }
        .rl-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

        .rl-stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px; margin-bottom: 16px;
        }
        .rl-stat {
            background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 16px 18px;
            box-shadow: 0 1px 2px rgba(0,0,0,.03);
        }
        .rl-stat-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .rl-stat-label {
            font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em;
            color: #71717a;
        }
        .rl-stat-icon { width: 16px; height: 16px; color: #a1a1aa; flex-shrink: 0; }
        .rl-stat-value { font-size: 28px; font-weight: 700; letter-spacing: -0.025em; line-height: 1.1; }
        .rl-stat-sub { font-size: 12px; color: #71717a; margin-top: 6px; }

        .rl-filter-bar {
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;
            gap: 12px; margin-bottom: 16px; background: #fff; border: 1px solid #e4e4e7;
            border-radius: 10px; padding: 10px 14px; box-shadow: 0 1px 2px rgba(0,0,0,.02);
        }
        .rl-filter-form { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin: 0; }
        .rl-search { position: relative; display: inline-flex; align-items: center; }
        .rl-search-icon {
            position: absolute; left: 11px; width: 15px; height: 15px; color: #a1a1aa;
            pointer-events: none;
        }
        .rl-search .rl-input { padding-left: 34px !important; width: 260px; }
        .rl-count-badge {
            font-size: 12px; color: #71717a; background: #f4f4f5; padding: 4px 10px;
            border-radius: 9999px; border: 1px solid #e4e4e7; white-space: nowrap;
        }

        .rl-contact { display: flex; align-items: center; gap: 12px; }
        .rl-avatar {
            width: 36px; height: 36px; border-radius: 9999px; background: #f4f4f5;
            border: 1px solid #e4e4e7; color: #09090b; font-weight: 600; font-size: 12px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .rl-avatar-lg { width: 48px; height: 48px; font-size: 15px; }

        .rl-form-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px; margin-bottom: 14px;
        }
        .rl-form-grid .rl-field { margin-bottom: 0; }
        .rl-form-grid .rl-input, .rl-form-grid .rl-select { width: 100%; max-width: none; }
        .rl-field-wide { grid-column: 1 / -1; }
        .rl-textarea {
            width: 100%; min-height: 88px; resize: vertical; border: 1px solid #e4e4e7 !important;
            border-radius: 6px !important; padding: 8px 11px !important; font-size: 13px !important;
            line-height: 1.5; color: #09090b !important; background: #fff !important;
            box-shadow: none !important;
        }
        .rl-textarea:focus { border-color: #a1a1aa !important; outline: 2px solid rgba(24,24,27,.08) !important; }
        .rl-required { color: #dc2626; }
        .rl-field-error { display: block; color: #b91c1c; font-size: 12px; margin-top: 5px; }
        .rl-form-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

        .rl-kv { width: 100%; border-collapse: collapse; font-size: 13px; }
        .rl-kv th {
            text-align: left; font-weight: 500; color: #71717a; width: 38%;
            padding: 8px 12px 8px 0; vertical-align: top;
        }
        .rl-kv td { padding: 8px 0; vertical-align: top; overflow-wrap: anywhere; }
        .rl-kv tr + tr th, .rl-kv tr + tr td { border-top: 1px solid #f4f4f5; }

        .rl-detail-grid {
            display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
            gap: 16px; align-items: start;
        }
        @media (max-width: 960px) {
            .rl-detail-grid { grid-template-columns: minmax(0, 1fr); }
        }

        /* A share of a whole, drawn beside the number it illustrates and never instead of it. */
        .rl-meter { height: 6px; background: #f4f4f5; border-radius: 9999px; overflow: hidden; min-width: 72px; }
        .rl-meter-fill { display: block; height: 100%; background: #18181b; border-radius: 9999px; }

        /*
         * Anchors styled as controls.
         *
         * WordPressAdminTheme's "Global Link & Text Neutralization" block paints every link in
         * the content area with `#wpbody-content a { color: #09090b !important; text-decoration:
         * underline !important }`. That selector is (1,0,1); every rule above is (0,1,0), so
         * !important does not save them — an <a class="rl-btn rl-btn-primary"> renders as black
         * text on a black button with an underline through it. The ID in these selectors is
         * what clears that bar, and is the only reason they are qualified this way.
         */
        #wpbody-content .rl-admin-wrap a.rl-btn,
        #wpbody-content .rl-admin-wrap a.rl-tab {
            text-decoration: none !important;
        }
        #wpbody-content .rl-admin-wrap a.rl-btn-primary { color: #fafafa !important; }
        #wpbody-content .rl-admin-wrap a.rl-btn-outline { color: #09090b !important; }
        #wpbody-content .rl-admin-wrap a.rl-btn-destructive { color: #ef4444 !important; }
        #wpbody-content .rl-admin-wrap a.rl-btn-destructive:hover { color: #dc2626 !important; }
        #wpbody-content .rl-admin-wrap a.rl-tab { color: #71717a !important; }
        #wpbody-content .rl-admin-wrap a.rl-tab-active { color: #09090b !important; }
        CSS;
    }
}
