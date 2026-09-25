<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\PartnerHub\Models\PartnershipProspect;

/**
 * What the partnership screens share: where they live, the tabs that join them, one-shot
 * messages across a redirect, and the small formatting and icon helpers each one would
 * otherwise copy.
 *
 * The screens are Partners Hub → Partnership Overview, → Prospects (with each prospect's detail
 * inside it) and → Partnership Settings. They are separate submenu pages, each its own class,
 * where ReferralAdminDashboard is one class with five; its renderNav() is what the tabs here
 * reproduce, so moving between them feels like one area of the admin rather than three pages
 * that happen to sit in the same menu.
 *
 * Everything is static because there is no state to hold: the screens are registered once, and
 * the flash lives in a transient, not on an instance.
 */
final class PartnershipAdminChrome
{
    /** The Partners Hub menu — the `rl_partner` post type's list — that every screen sits under. */
    public const PARENT = 'edit.php?post_type=rl_partner';

    /** Tab order, and the label each screen goes by inside the area. */
    private const TABS = [
        PartnershipOverviewAdmin::SLUG => 'Overview',
        PartnershipProspectsAdmin::SLUG => 'Prospects',
        PartnershipSettingsAdmin::SLUG => 'Settings',
    ];

    /**
     * A screen's URL under the Partners Hub menu.
     *
     * @param  array<string, string|int>  $args
     */
    public static function url(string $slug, array $args = []): string
    {
        return (string) admin_url(self::PARENT.'&'.http_build_query(['page' => $slug, ...$args]));
    }

    /**
     * The design system and the few rules only these screens need.
     */
    public static function enqueue(): void
    {
        AdminDesignSystem::enqueue();

        wp_add_inline_style('wp-admin', self::css());
    }

    public static function css(): string
    {
        return <<<'CSS'
        .rl-admin-wrap.rl-partnerships { max-width: 1360px; }
        .rl-admin-wrap.rl-partnerships.rl-partnerships-narrow { max-width: 880px; }
        .rl-partnerships .rl-tab .rl-tab-count { color: #71717a; font-size: 12px; margin-left: 4px; }
        .rl-partnerships .rl-muted { color: #71717a; font-size: 12px; }
        .rl-partnerships .rl-message { max-width: 320px; white-space: pre-line; color: #3f3f46; line-height: 1.5; }
        .rl-partnerships .rl-prose { white-space: pre-line; line-height: 1.6; color: #27272a; margin: 0; }
        .rl-partnerships .rl-row-form { display: flex; gap: 6px; align-items: center; margin: 0; }
        .rl-partnerships .rl-pager { margin-top: 16px; display: flex; gap: 6px; flex-wrap: wrap; }
        .rl-partnerships .rl-back { margin-bottom: 16px; }
        .rl-partnerships .rl-card-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .rl-partnerships .rl-card-head .rl-card-title { margin: 0; }
        .rl-partnerships .rl-split { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
        .rl-partnerships .rl-split > .rl-card { margin-bottom: 0; }
        .rl-partnerships .rl-split + .rl-split, .rl-partnerships .rl-split + .rl-card { margin-top: 16px; }
        .rl-partnerships .rl-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .rl-partnerships .rl-bar-cell { width: 30%; }
        .rl-partnerships .rl-title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .rl-partnerships .rl-card .rl-table-container { box-shadow: none; }
        .rl-partnerships a.rl-row-link { font-weight: 600; }
        CSS;
    }

    /**
     * The tabs across the top of every partnership screen.
     *
     * Followed by WordPress's own `wp-header-end` marker, which is where it moves admin notices
     * to. Without it they are hoisted to just after the first heading — inside the header's
     * left column on these screens, between the title and its subtitle.
     */
    public static function nav(string $active): void
    {
        echo '<nav class="rl-tabs" aria-label="Partnerships">';

        foreach (self::TABS as $slug => $label) {
            printf(
                '<a class="rl-tab%s" href="%s"%s>%s</a>',
                $slug === $active ? ' rl-tab-active' : '',
                esc_url(self::url($slug)),
                $slug === $active ? ' aria-current="page"' : '',
                esc_html($label),
            );
        }

        echo '</nav><hr class="wp-header-end">';
    }

    /**
     * Stash a value for exactly one following request, per admin user.
     *
     * The same arrangement as ReferralAdminDashboard::flash(): a rejected form's errors and the
     * values that were typed have to survive the POST-redirect-GET every action here ends with,
     * and neither belongs in a query string, which is written to the access log.
     */
    public static function flash(string $key, mixed $value): void
    {
        set_transient(self::flashKey($key), $value, 5 * MINUTE_IN_SECONDS);
    }

    /**
     * Read a flashed value and consume it, so a refresh does not show it a second time.
     */
    public static function takeFlash(string $key): mixed
    {
        $name = self::flashKey($key);
        $value = get_transient($name);
        delete_transient($name);

        return $value === false ? null : $value;
    }

    /**
     * In the site's own zone, which is what anyone reading "submitted at 9:14am" assumes.
     */
    public static function when(\DateTimeInterface|int|null $at, string $format = 'M j, Y g:ia'): string
    {
        if ($at === null) {
            return '';
        }

        $timestamp = $at instanceof \DateTimeInterface ? $at->getTimestamp() : $at;

        return function_exists('wp_date')
            ? (string) wp_date($format, $timestamp)
            : gmdate($format, $timestamp);
    }

    public static function initials(string $name, string $email = ''): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr((string) end($parts), 0, 1));
        }

        $basis = $parts[0] ?? trim($email);

        return $basis !== '' ? mb_strtoupper(mb_substr($basis, 0, 2)) : 'RL';
    }

    /**
     * A status as a badge. `new` is amber because it is the one that wants something doing —
     * nobody has replied yet — and the two closed outcomes read as they are.
     */
    public static function statusBadge(string $status): string
    {
        $tone = match ($status) {
            'new' => ' rl-badge-busy',
            'converted' => ' rl-badge-ok',
            'declined' => ' rl-badge-bad',
            default => '',
        };

        return '<span class="rl-badge'.$tone.'">'.esc_html(ucfirst($status)).'</span>';
    }

    /**
     * The status select every status form uses, on the list and on the detail screen.
     */
    public static function statusSelect(string $current, string $label): string
    {
        $html = sprintf('<select name="status" class="rl-select" aria-label="%s">', esc_attr($label));

        foreach (PartnershipProspect::STATUSES as $status) {
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr($status),
                $status === $current ? ' selected' : '',
                esc_html(ucfirst($status)),
            );
        }

        return $html.'</select>';
    }

    public static function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.').'%';
    }

    /**
     * Lucide-style line icons, as ReferralAdminDashboard draws them. Decorative: every one sits
     * beside text that says the same thing, so each is hidden from assistive technology.
     */
    public static function icon(string $name, string $class = 'rl-stat-icon'): string
    {
        $paths = [
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'inbox' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
            'calendar' => '<rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/>',
            'check' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
            'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'plus' => '<line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/>',
            'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
            'arrow-left' => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        ];

        $size = $class === '' ? ' width="14" height="14"' : '';

        return '<svg class="'.esc_attr($class).'"'.$size.' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
            .'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.($paths[$name] ?? '').'</svg>';
    }

    private static function flashKey(string $key): string
    {
        return 'rl_partnerships_flash_'.$key.'_'.get_current_user_id();
    }
}
