<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\PartnerHub\Models\PartnershipProspect;
use App\Domains\PartnerHub\Support\PartnershipProspectMetrics;
use App\Domains\PartnerHub\Support\PartnershipProspectOptions;

/**
 * Partners Hub → Partnership Overview: how many companies are asking to partner, where they
 * came from, and how far the conversations have got.
 *
 * The partnerships counterpart of ReferralAdminDashboard::renderAnalytics(), and laid out the
 * same way — KPI tiles, then the pipeline, then the breakdowns, then the latest arrivals — so
 * anyone who reads one reads the other. The figures come from PartnershipProspectMetrics, cached
 * for three minutes and refreshed by any write to a prospect.
 *
 * Every number is printed. The bars beside the status and answer counts only show each count's
 * share of the whole; they are one hue, carry no information the number does not, and are
 * hidden from screen readers for that reason.
 */
class PartnershipOverviewAdmin
{
    public const SLUG = 'rl-partnership-overview';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    public static function url(array $args = []): string
    {
        return PartnershipAdminChrome::url(self::SLUG, $args);
    }

    public function enqueueStyles(string $hook): void
    {
        if (str_contains($hook, self::SLUG)) {
            PartnershipAdminChrome::enqueue();
        }
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            parent_slug: PartnershipAdminChrome::PARENT,
            page_title: 'Partnership Overview',
            menu_title: 'Partnership Overview',
            capability: 'manage_options',
            menu_slug: self::SLUG,
            callback: [$this, 'render'],
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to view this page.');
        }

        $m = PartnershipProspectMetrics::cached();

        echo '<div class="wrap rl-admin-wrap rl-partnerships">';
        echo '<div class="rl-admin-header rl-admin-header-split"><div>';
        echo '<h1 class="rl-admin-title">Partnership overview</h1>';
        echo '<p class="rl-admin-subtitle">Companies asking to partner with us, where they found us, and how far each conversation has got.</p>';
        echo '</div><div class="rl-actions">';
        printf(
            '<a class="rl-btn rl-btn-outline" href="%s">All prospects</a>',
            esc_url(PartnershipProspectsAdmin::url()),
        );
        printf(
            '<a class="rl-btn rl-btn-primary" href="%s">%s Add prospect</a>',
            esc_url(PartnershipProspectsAdmin::url(['new' => 1])),
            PartnershipAdminChrome::icon('plus', ''),
        );
        echo '</div></div>';

        PartnershipAdminChrome::nav(self::SLUG);

        $this->renderStats($m);

        echo '<div class="rl-split">';
        $this->renderFunnel($m);
        $this->renderBreakdown('Organization type', 'organization_type', $m['breakdowns']['organization_type']);
        echo '</div>';

        echo '<div class="rl-split">';
        $this->renderBreakdown('Monthly revenue', 'monthly_revenue', $m['breakdowns']['monthly_revenue']);
        $this->renderBreakdown('Businesses reached', 'businesses_reached', $m['breakdowns']['businesses_reached']);
        echo '</div>';

        echo '<div class="rl-split">';
        $this->renderTop(
            'Top sources',
            'By utm_source. Manual entries are counted on their own; "'.PartnershipProspectMetrics::UNTAGGED.'" is a form submission with no campaign tag.',
            'Source',
            $m['topSources'],
        );
        $this->renderTop('Top landing pages', 'The page the form was submitted from, without its query string.', 'Page', $m['topLandingPages']);
        echo '</div>';

        $this->renderRecent($m['recent']);

        printf(
            '<p class="rl-muted" style="margin-top:12px">Figures as of %s. They refresh within three minutes, and straight away after any change to a prospect.</p>',
            esc_html(PartnershipAdminChrome::when((int) $m['computedAt'])),
        );

        echo '</div>';
    }

    /**
     * @param  array<string, mixed>  $m
     */
    protected function renderStats(array $m): void
    {
        $tiles = [
            ['Total prospects', 'users', (string) $m['total'], $m['manual'] > 0 ? $m['manual'].' entered by hand' : 'All from the form'],
            ['New this week', 'clock', (string) $m['last7'], $m['last30'].' in the last 30 days'],
            ['Awaiting contact', 'inbox', (string) $m['awaitingContact'], 'Still at status New'],
            ['Calls booked', 'calendar', (string) $m['booked'], PartnershipAdminChrome::percent((float) $m['bookedRate']).' of prospects'],
            ['Converted', 'check', (string) $m['converted'], PartnershipAdminChrome::percent((float) $m['conversionRate']).' conversion'],
        ];

        echo '<div class="rl-stats-grid">';

        foreach ($tiles as [$label, $icon, $value, $sub]) {
            printf(
                '<div class="rl-stat"><div class="rl-stat-head"><span class="rl-stat-label">%s</span>%s</div>'
                .'<div class="rl-stat-value">%s</div><div class="rl-stat-sub">%s</div></div>',
                esc_html($label),
                PartnershipAdminChrome::icon($icon),
                esc_html($value),
                esc_html($sub),
            );
        }

        echo '</div>';
    }

    /**
     * @param  array<string, mixed>  $m
     */
    protected function renderFunnel(array $m): void
    {
        echo '<div class="rl-card"><div class="rl-card-head"><p class="rl-card-title">Pipeline</p>';
        printf('<a class="rl-muted" href="%s">Open the list</a>', esc_url(PartnershipProspectsAdmin::url()));
        echo '</div>';

        echo '<div class="rl-table-container"><table class="rl-table"><thead><tr>'
            .'<th scope="col">Status</th><th scope="col" class="rl-num">Prospects</th><th scope="col" class="rl-num">Share</th><th scope="col"><span class="screen-reader-text">Share of all prospects</span></th>'
            .'</tr></thead><tbody>';

        foreach ($m['funnel'] as $status => $row) {
            printf(
                '<tr><td><a href="%s">%s</a></td><td class="rl-num">%d</td><td class="rl-num">%s</td><td class="rl-bar-cell">%s</td></tr>',
                esc_url(PartnershipProspectsAdmin::url(['status' => $status])),
                PartnershipAdminChrome::statusBadge((string) $status),
                (int) $row['count'],
                esc_html(PartnershipAdminChrome::percent((float) $row['share'])),
                $this->meter((float) $row['share']),
            );
        }

        echo '</tbody></table></div></div>';
    }

    /**
     * One of the three answers, every option in the form's order.
     *
     * @param  array<int, array{slug: string, label: string, count: int, converted: int, share: float}>  $rows
     */
    protected function renderBreakdown(string $title, string $field, array $rows): void
    {
        printf('<div class="rl-card"><div class="rl-card-head"><p class="rl-card-title">%s</p></div>', esc_html($title));

        echo '<div class="rl-table-container"><table class="rl-table"><thead><tr>'
            .'<th scope="col">Answer</th><th scope="col" class="rl-num">Prospects</th><th scope="col" class="rl-num">Converted</th><th scope="col"><span class="screen-reader-text">Share of all prospects</span></th>'
            .'</tr></thead><tbody>';

        foreach ($rows as $row) {
            $known = in_array($row['slug'], PartnershipProspectOptions::slugs($field), true);

            printf(
                '<tr><td>%s</td><td class="rl-num">%d</td><td class="rl-num">%d</td><td class="rl-bar-cell">%s</td></tr>',
                $known
                    ? sprintf('<a href="%s">%s</a>', esc_url(PartnershipProspectsAdmin::url([$field => $row['slug']])), esc_html($row['label']))
                    : esc_html($row['label']),
                (int) $row['count'],
                (int) $row['converted'],
                $this->meter((float) $row['share']),
            );
        }

        echo '</tbody></table></div></div>';
    }

    /**
     * @param  array<int, array{label: string, count: int, converted: int}>  $rows
     */
    protected function renderTop(string $title, string $hint, string $column, array $rows): void
    {
        printf(
            '<div class="rl-card"><p class="rl-card-title">%s</p><p class="rl-card-sub">%s</p>',
            esc_html($title),
            esc_html($hint),
        );

        if ($rows === []) {
            echo '<div class="rl-table-container"><p class="rl-empty">Nothing recorded yet.</p></div></div>';

            return;
        }

        printf(
            '<div class="rl-table-container"><table class="rl-table"><thead><tr><th scope="col">%s</th>'
            .'<th scope="col" class="rl-num">Prospects</th><th scope="col" class="rl-num">Converted</th></tr></thead><tbody>',
            esc_html($column),
        );

        foreach ($rows as $row) {
            printf(
                '<tr><td class="rl-mono" style="font-size:12px;color:#09090b">%s</td><td class="rl-num">%d</td><td class="rl-num">%d</td></tr>',
                esc_html($row['label']),
                (int) $row['count'],
                (int) $row['converted'],
            );
        }

        echo '</tbody></table></div></div>';
    }

    /**
     * @param  array<int, array<string, mixed>>  $recent
     */
    protected function renderRecent(array $recent): void
    {
        echo '<div class="rl-card" style="margin-top:16px"><div class="rl-card-head"><p class="rl-card-title">Recent prospects</p>';
        printf('<a class="rl-muted" href="%s">View all</a>', esc_url(PartnershipProspectsAdmin::url()));
        echo '</div>';

        if ($recent === []) {
            echo '<div class="rl-table-container"><p class="rl-empty">No prospects yet. They appear here as soon as someone submits /become-a-partner/, or when you add one by hand.</p></div></div>';

            return;
        }

        echo '<div class="rl-table-container"><table class="rl-table"><thead><tr>'
            .'<th scope="col">Prospect</th><th scope="col">Organization</th><th scope="col">Source</th><th scope="col">Call</th><th scope="col">Status</th><th scope="col">Submitted</th>'
            .'</tr></thead><tbody>';

        foreach ($recent as $row) {
            $link = PartnershipProspectsAdmin::detailUrl((int) $row['id']);

            printf(
                '<tr><td><div class="rl-contact"><div class="rl-avatar" aria-hidden="true">%s</div><div>'
                .'%s<br><span class="rl-muted">%s</span></div></div></td>'
                .'<td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html(PartnershipAdminChrome::initials((string) $row['name'])),
                '<a class="rl-row-link" href="'.esc_url($link).'">'.esc_html((string) $row['name']).'</a>',
                esc_html((string) $row['company']),
                esc_html((string) $row['organization']),
                esc_html(PartnershipProspect::SOURCES[(string) $row['source']] ?? (string) $row['source']),
                $row['booked'] ? '<span class="rl-badge rl-badge-ok">Booked</span>' : '<span class="rl-muted">&mdash;</span>',
                PartnershipAdminChrome::statusBadge((string) $row['status']),
                esc_html(PartnershipAdminChrome::when($row['created_at'] !== null ? (int) $row['created_at'] : null)),
            );
        }

        echo '</tbody></table></div></div>';
    }

    protected function meter(float $share): string
    {
        $width = max(0.0, min(100.0, $share));

        return '<div class="rl-meter" aria-hidden="true"><span class="rl-meter-fill" style="width:'.esc_attr((string) $width).'%"></span></div>';
    }
}
