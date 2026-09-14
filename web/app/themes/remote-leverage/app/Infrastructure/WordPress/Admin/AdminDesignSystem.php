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
        CSS;
    }
}
