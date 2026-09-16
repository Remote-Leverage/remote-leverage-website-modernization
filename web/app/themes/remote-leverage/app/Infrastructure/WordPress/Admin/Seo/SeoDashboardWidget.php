<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin\Seo;

/**
 * Replaces Yoast's "Posts Overview" dashboard widget.
 *
 * What Yoast shipped was a React mount that rendered a score doughnut and, underneath it, an
 * RSS feed of yoast.com blog posts — a marketing surface on our dashboard. This renders three
 * things that are actually actionable, server-side, with no JavaScript:
 *
 *   1. The score distribution, as a stacked bar, each band linking into a filtered post list.
 *   2. The content gaps — published pages with no meta description or no SEO title.
 *   3. Indexing health — the settings that silently stop content ranking.
 *
 * Registered at priority 1000, one higher than MarketingDashboard's 999, because Yoast adds
 * its widget from `wp_dashboard_setup` too and remove_meta_box() only works once the box is
 * there. See SeoAdminSkin for the matching asset dequeue.
 */
class SeoDashboardWidget
{
    /** Yoast's widget id, as registered by Yoast_Dashboard_Widget. */
    private const YOAST_WIDGET_ID = 'wpseo-dashboard-overview';

    /** Yoast's other widget — the Wincher rank-tracker upsell. */
    private const WINCHER_WIDGET_ID = 'wpseo-wincher-dashboard-overview';

    private const WIDGET_ID = 'rl_seo_overview';

    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'setupDashboard'], 1000);

        // Keep the figures honest after an edit without waiting out the cache window.
        add_action('save_post', [SeoStats::class, 'flush']);
    }

    public function setupDashboard(): void
    {
        remove_meta_box(self::YOAST_WIDGET_ID, 'dashboard', 'normal');
        remove_meta_box(self::WINCHER_WIDGET_ID, 'dashboard', 'normal');

        wp_add_dashboard_widget(
            self::WIDGET_ID,
            'Search Visibility',
            [$this, 'render'],
            null,
            null,
            'normal',
            'default'
        );
    }

    public function render(): void
    {
        $stats = SeoStats::all();

        echo '<div class="rl-seo-w">';

        $this->renderScores($stats['scores'], $stats['total']);
        $this->renderGaps($stats['gaps']);
        $this->renderIndexing($stats['indexing']);

        echo '</div>';
    }

    /**
     * The score distribution: a stacked bar over four linked tiles.
     *
     * @param  array<string, int>  $scores
     */
    private function renderScores(array $scores, int $total): void
    {
        $percentages = SeoStats::percentages($scores);
        ?>
        <div class="rl-seo-w-section">
            <div class="rl-seo-w-head">
                <span class="rl-seo-w-title">SEO score</span>
                <span class="rl-seo-w-meta"><?php echo esc_html((string) $total); ?> published posts &amp; pages</span>
            </div>

            <?php if ($total === 0) { ?>
                <p class="rl-seo-empty">Nothing published yet.</p>
            <?php } else { ?>
                <div class="rl-seo-bar" role="img" aria-label="Distribution of SEO scores across published content">
                    <?php foreach ($percentages as $band => $percentage) {
                        if ($percentage <= 0) {
                            continue;
                        } ?>
                        <span class="is-<?php echo esc_attr($band); ?>" style="width: <?php echo esc_attr((string) $percentage); ?>%;"></span>
                    <?php } ?>
                </div>

                <div class="rl-seo-legend">
                    <?php foreach (SeoStats::BANDS as $band => $meta) { ?>
                        <a
                            href="<?php echo esc_url(admin_url('edit.php?post_type=post&seo_filter='.$band)); ?>"
                            title="Open posts filtered by &ldquo;<?php echo esc_attr($meta['label']); ?>&rdquo;"
                        >
                            <span class="rl-seo-legend-top">
                                <span class="rl-seo-dot is-<?php echo esc_attr($band); ?>"></span>
                                <span class="rl-seo-legend-label"><?php echo esc_html($meta['label']); ?></span>
                            </span>
                            <span class="rl-seo-legend-num"><?php echo esc_html((string) ($scores[$band] ?? 0)); ?></span>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * Published content missing the two fields that show up in search results.
     *
     * @param  array{missing_metadesc: int, missing_title: int, total: int}  $gaps
     */
    private function renderGaps(array $gaps): void
    {
        $total = $gaps['total'];

        $rows = [
            [
                'label' => 'No meta description',
                'sub' => 'Google writes its own snippet instead',
                'count' => $gaps['missing_metadesc'],
                'icon' => '<path d="M4 7h16"/><path d="M4 12h10"/><path d="M4 17h7"/>',
            ],
            [
                'label' => 'No SEO title set',
                'sub' => 'Falls back to the site-wide title template',
                'count' => $gaps['missing_title'],
                'icon' => '<path d="M4 7V5h16v2"/><path d="M9 19h6"/><path d="M12 5v14"/>',
            ],
        ];
        ?>
        <div class="rl-seo-w-section">
            <div class="rl-seo-w-head">
                <span class="rl-seo-w-title">Content gaps</span>
                <a class="rl-seo-w-meta" href="<?php echo esc_url(admin_url('admin.php?page=wpseo_page_bulk_edit')); ?>">Bulk edit</a>
            </div>

            <div class="rl-seo-rows">
                <?php foreach ($rows as $row) {
                    $severity = SeoStats::severity($row['count'], $total); ?>
                    <a class="rl-seo-row" href="<?php echo esc_url(admin_url('admin.php?page=wpseo_page_bulk_edit')); ?>">
                        <span class="rl-seo-row-main">
                            <svg class="rl-seo-row-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $row['icon']; ?></svg>
                            <span>
                                <span class="rl-seo-row-label"><?php echo esc_html($row['label']); ?></span>
                                <span class="rl-seo-row-sub"><?php echo esc_html($row['sub']); ?></span>
                            </span>
                        </span>
                        <span class="rl-seo-pill is-<?php echo esc_attr($severity); ?>">
                            <?php echo esc_html((string) $row['count']); ?>
                        </span>
                    </a>
                <?php } ?>
            </div>
        </div>
        <?php
    }

    /**
     * The settings that quietly stop content ranking.
     *
     * @param  array{noindex: int, discouraged: bool, sitemap: bool, indexables: int, indexable_gap: int}  $indexing
     */
    private function renderIndexing(array $indexing): void
    {
        ?>
        <div class="rl-seo-w-section">
            <div class="rl-seo-w-head">
                <span class="rl-seo-w-title">Indexing</span>
                <a class="rl-seo-w-meta" href="<?php echo esc_url(admin_url('admin.php?page=wpseo_page_settings')); ?>">Settings</a>
            </div>

            <div class="rl-seo-rows">
                <?php if ($indexing['discouraged']) { ?>
                    <?php /* Nothing else on this widget matters while this is true. */ ?>
                    <a class="rl-seo-row" href="<?php echo esc_url(admin_url('options-reading.php')); ?>">
                        <span class="rl-seo-row-main">
                            <svg class="rl-seo-row-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 2 20 20"/><path d="M6.7 6.7A10.6 10.6 0 0 0 1 12s4 7 11 7a10.9 10.9 0 0 0 5.3-1.4"/><path d="M9.9 4.2A11.8 11.8 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.7 3.9"/></svg>
                            <span>
                                <span class="rl-seo-row-label">Search engines are discouraged</span>
                                <span class="rl-seo-row-sub">The whole site is hidden from search &mdash; fix in Reading settings</span>
                            </span>
                        </span>
                        <span class="rl-seo-pill is-bad">Critical</span>
                    </a>
                <?php } ?>

                <div class="rl-seo-row">
                    <span class="rl-seo-row-main">
                        <svg class="rl-seo-row-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        <span>
                            <span class="rl-seo-row-label">Noindexed content</span>
                            <span class="rl-seo-row-sub">Published, but excluded from search</span>
                        </span>
                    </span>
                    <span class="rl-seo-pill is-<?php echo $indexing['noindex'] > 0 ? 'warn' : 'ok'; ?>">
                        <?php echo esc_html((string) $indexing['noindex']); ?>
                    </span>
                </div>

                <div class="rl-seo-row">
                    <span class="rl-seo-row-main">
                        <svg class="rl-seo-row-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18"/><path d="M12 3v18"/><circle cx="12" cy="12" r="9"/></svg>
                        <span>
                            <span class="rl-seo-row-label">XML sitemap</span>
                            <span class="rl-seo-row-sub">How search engines discover new content</span>
                        </span>
                    </span>
                    <span class="rl-seo-pill is-<?php echo $indexing['sitemap'] ? 'ok' : 'bad'; ?>">
                        <?php echo $indexing['sitemap'] ? 'On' : 'Off'; ?>
                    </span>
                </div>

                <?php if ($indexing['indexable_gap'] > 0) { ?>
                    <div class="rl-seo-row">
                        <span class="rl-seo-row-main">
                            <svg class="rl-seo-row-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v5"/><path d="M12 16h.01"/><circle cx="12" cy="12" r="9"/></svg>
                            <span>
                                <span class="rl-seo-row-label">Unindexed by Yoast</span>
                                <span class="rl-seo-row-sub">SEO data is stale for these &mdash; re-run the index in Tools</span>
                            </span>
                        </span>
                        <span class="rl-seo-pill is-warn"><?php echo esc_html((string) $indexing['indexable_gap']); ?></span>
                    </div>
                <?php } ?>
            </div>
        </div>
        <?php
    }
}
