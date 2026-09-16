<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin\Seo;

use App\Infrastructure\WordPress\Admin\AdminDesignSystem;

/**
 * Our own SEO landing screen, in place of Yoast's "General" page.
 *
 * Yoast's version is a React app whose three tabs are a feature tour, a site-representation
 * form duplicated from Settings, and an upsell carousel. None of that is what someone opening
 * the SEO menu needs, so this replaces it with the same figures the dashboard widget reports
 * plus the specific content worth opening next.
 *
 * The seam is WordPress's page-hook action. `add_menu_page()` registers the render callback as
 * `add_action($hook_suffix, $callback)` and `admin.php` fires it with `do_action($page_hook)`,
 * so clearing that one hook and re-adding our own swaps the screen without touching the menu
 * registration, the capability check or the URL. Yoast's own React mount point is never
 * printed, so its bundle finds nothing to attach to and stays inert.
 */
class SeoOverviewPage
{
    /** The page hook WordPress derives from Yoast's top-level slug. */
    private const PAGE_HOOK = 'toplevel_page_'.SeoMenu::PARENT_SLUG;

    public function register(): void
    {
        // After Yoast has registered its render callback (admin_menu priority 5) but before
        // admin.php fires the hook.
        add_action('admin_menu', [$this, 'takeOverRender'], 101);
        add_action('admin_enqueue_scripts', [$this, 'enqueue'], 101);
    }

    public function takeOverRender(): void
    {
        remove_all_actions(self::PAGE_HOOK);

        add_action(self::PAGE_HOOK, [$this, 'render']);
    }

    /**
     * This screen is ours, so it wants our tokens rather than the Yoast skin.
     */
    public function enqueue(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (! $screen instanceof \WP_Screen || $screen->id !== self::PAGE_HOOK) {
            return;
        }

        AdminDesignSystem::enqueue();
        wp_add_inline_style('wp-admin', SeoSkinStyles::tokens()."\n".SeoSkinStyles::widgetCss());
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'remote-leverage'));
        }

        $stats = SeoStats::all();
        $attention = SeoStats::needsAttention();
        ?>
        <div class="rl-admin-wrap">
            <div class="rl-admin-header">
                <h1 class="rl-admin-title">SEO</h1>
                <p class="rl-admin-subtitle">
                    How the <?php echo esc_html((string) $stats['total']); ?> published posts and pages on this site look to search engines.
                </p>
            </div>

            <?php $this->renderCriticalNotice($stats['indexing']); ?>

            <div class="rl-card">
                <h2 class="rl-card-title">Search visibility</h2>
                <p class="rl-card-sub">Yoast's analysis score across everything published.</p>
                <?php $this->renderScoreBands($stats['scores'], $stats['total']); ?>
            </div>

            <div class="rl-card">
                <h2 class="rl-card-title">Indexing</h2>
                <p class="rl-card-sub">The settings that decide whether content can rank at all.</p>
                <?php $this->renderIndexing($stats['indexing']); ?>
            </div>

            <div class="rl-card">
                <h2 class="rl-card-title">Needs attention</h2>
                <p class="rl-card-sub">
                    Published content missing a meta description, or scoring below 40. Missing
                    descriptions come first &mdash; that is the fix with the most visible effect.
                </p>
                <?php $this->renderAttentionTable($attention); ?>
            </div>

            <div class="rl-card">
                <h2 class="rl-card-title">Configuration</h2>
                <p class="rl-card-sub">Site-wide SEO settings and maintenance tools.</p>
                <p>
                    <a class="rl-btn rl-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=wpseo_page_settings')); ?>">Settings</a>
                    <a class="rl-btn rl-btn-outline" href="<?php echo esc_url(admin_url('admin.php?page=wpseo_tools')); ?>">Tools</a>
                    <a class="rl-btn rl-btn-outline" href="<?php echo esc_url(admin_url('admin.php?page=wpseo_page_bulk_edit')); ?>">Bulk edit</a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * @param  array{noindex: int, discouraged: bool, sitemap: bool, indexables: int, indexable_gap: int}  $indexing
     */
    private function renderCriticalNotice(array $indexing): void
    {
        if (! $indexing['discouraged']) {
            return;
        }
        ?>
        <div class="rl-card" style="border-color: #fecaca; background: #fef2f2;">
            <h2 class="rl-card-title" style="color: #b91c1c;">This site is hidden from search engines</h2>
            <p class="rl-card-sub" style="margin-bottom: 12px;">
                Reading settings has &ldquo;Discourage search engines&rdquo; switched on, so nothing
                below matters until it is off. This is the state a database restored from staging
                arrives in.
            </p>
            <a class="rl-btn rl-btn-primary" href="<?php echo esc_url(admin_url('options-reading.php')); ?>">Open Reading settings</a>
        </div>
        <?php
    }

    /**
     * @param  array<string, int>  $scores
     */
    private function renderScoreBands(array $scores, int $total): void
    {
        if ($total === 0) {
            echo '<p class="rl-seo-empty">Nothing published yet.</p>';

            return;
        }

        $percentages = SeoStats::percentages($scores);
        ?>
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
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=post&seo_filter='.$band)); ?>">
                    <span class="rl-seo-legend-top">
                        <span class="rl-seo-dot is-<?php echo esc_attr($band); ?>"></span>
                        <span class="rl-seo-legend-label"><?php echo esc_html($meta['label']); ?></span>
                    </span>
                    <span class="rl-seo-legend-num"><?php echo esc_html((string) ($scores[$band] ?? 0)); ?></span>
                    <span class="rl-seo-row-sub"><?php echo esc_html((string) ($percentages[$band] ?? 0)); ?>%</span>
                </a>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * @param  array{noindex: int, discouraged: bool, sitemap: bool, indexables: int, indexable_gap: int}  $indexing
     */
    private function renderIndexing(array $indexing): void
    {
        $rows = [
            [
                'label' => 'XML sitemap',
                'sub' => 'How search engines discover new content',
                'value' => $indexing['sitemap'] ? 'On' : 'Off',
                'state' => $indexing['sitemap'] ? 'ok' : 'bad',
            ],
            [
                'label' => 'Noindexed content',
                'sub' => 'Published, but deliberately excluded from search',
                'value' => (string) $indexing['noindex'],
                'state' => $indexing['noindex'] > 0 ? 'warn' : 'ok',
            ],
            [
                'label' => 'Unindexed by Yoast',
                'sub' => 'SEO data is stale for these until the index is rebuilt in Tools',
                'value' => (string) $indexing['indexable_gap'],
                'state' => $indexing['indexable_gap'] > 0 ? 'warn' : 'ok',
            ],
        ];
        ?>
        <div class="rl-seo-rows">
            <?php foreach ($rows as $row) { ?>
                <div class="rl-seo-row">
                    <span class="rl-seo-row-main">
                        <span>
                            <span class="rl-seo-row-label"><?php echo esc_html($row['label']); ?></span>
                            <span class="rl-seo-row-sub"><?php echo esc_html($row['sub']); ?></span>
                        </span>
                    </span>
                    <span class="rl-seo-pill is-<?php echo esc_attr($row['state']); ?>"><?php echo esc_html($row['value']); ?></span>
                </div>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * @param  list<array{id: int, title: string, type: string, score: int, has_metadesc: bool}>  $rows
     */
    private function renderAttentionTable(array $rows): void
    {
        if ($rows === []) {
            echo '<p class="rl-seo-empty">Nothing outstanding &mdash; every published page has a meta description and scores above 40.</p>';

            return;
        }
        ?>
        <div class="rl-table-container">
            <table class="rl-table">
                <thead>
                    <tr>
                        <th>Content</th>
                        <th>Type</th>
                        <th>Score</th>
                        <th>Meta description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) {
                        $band = SeoStats::bandFor($row['score']); ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($row['id']) ?? ''); ?>">
                                    <?php echo esc_html($row['title'] !== '' ? $row['title'] : '(no title)'); ?>
                                </a>
                            </td>
                            <td><span class="rl-mono"><?php echo esc_html($row['type']); ?></span></td>
                            <td>
                                <span class="rl-seo-dot is-<?php echo esc_attr($band); ?>" style="display:inline-block; margin-right:6px;"></span>
                                <?php echo esc_html($row['score'] > 0 ? (string) $row['score'] : 'Not analysed'); ?>
                            </td>
                            <td>
                                <?php if ($row['has_metadesc']) { ?>
                                    <span class="rl-badge rl-badge-ok">Set</span>
                                <?php } else { ?>
                                    <span class="rl-badge rl-badge-bad">Missing</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
