<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\ContentAudit\Actions\ApplyElementorConversionAction;

/**
 * Editorial Review Queue for AI-converted Gutenberg posts (WR-101, ADR-0005 Amendment).
 *
 * Enforces "every AI-converted post — mapped and unmapped alike — is queued
 * for human sign-off before it is allowed to go live."
 */
class ContentAuditAdmin
{
    protected const POST_TYPES = ['post', 'page'];

    public function register(): void
    {
        foreach (self::POST_TYPES as $postType) {
            add_filter("manage_{$postType}_posts_columns", [$this, 'addConversionStatusColumn']);
            add_action("manage_{$postType}_posts_custom_column", [$this, 'renderConversionStatusColumn'], 10, 2);
        }

        add_action('restrict_manage_posts', [$this, 'renderConversionStatusFilter']);
        add_action('pre_get_posts', [$this, 'applyConversionStatusFilter']);
        add_filter('post_row_actions', [$this, 'addApproveRowAction'], 10, 2);
        add_filter('page_row_actions', [$this, 'addApproveRowAction'], 10, 2);
        add_action('admin_action_rl_approve_conversion', [$this, 'handleApproveAction']);
    }

    public function addConversionStatusColumn(array $columns): array
    {
        $columns['rl_conversion_status'] = 'Conversion Status';

        return $columns;
    }

    public function renderConversionStatusColumn(string $column, int $postId): void
    {
        if ($column !== 'rl_conversion_status') {
            return;
        }

        $status = get_post_meta($postId, '_rl_conversion_status', true);

        if (empty($status)) {
            echo '<span style="color:#71717a;">—</span>';

            return;
        }

        $unmapped = get_post_meta($postId, '_rl_conversion_unmapped_widgets', true);
        $unmappedCount = is_array($unmapped) ? count($unmapped) : 0;

        $badges = [
            'needs_review' => ['label' => 'Needs Review', 'bg' => '#fef3c7', 'color' => '#92400e'],
            'approved' => ['label' => 'Approved', 'bg' => '#dcfce7', 'color' => '#166534'],
        ];
        $badge = $badges[$status] ?? ['label' => ucwords(str_replace('_', ' ', $status)), 'bg' => '#e5e7eb', 'color' => '#374151'];

        printf(
            '<span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;background:%s;color:%s;">%s</span>',
            esc_attr($badge['bg']),
            esc_attr($badge['color']),
            esc_html($badge['label'])
        );

        if ($unmappedCount > 0 && $status !== 'approved') {
            printf(
                ' <span style="display:inline-block;margin-left:4px;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;background:#fee2e2;color:#991b1b;">%d Unmapped</span>',
                $unmappedCount
            );
        }
    }

    public function renderConversionStatusFilter(): void
    {
        global $typenow;

        if (! in_array($typenow, self::POST_TYPES, true)) {
            return;
        }

        $current = sanitize_text_field($_GET['rl_conversion_status'] ?? '');
        ?>
        <select name="rl_conversion_status">
            <option value="">All Conversion Statuses</option>
            <option value="needs_review" <?php selected($current, 'needs_review'); ?>>Needs Editorial Review</option>
            <option value="unmapped" <?php selected($current, 'unmapped'); ?>>Unmapped Widgets Detected</option>
            <option value="approved" <?php selected($current, 'approved'); ?>>Approved</option>
        </select>
        <?php
    }

    public function applyConversionStatusFilter(\WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        $filter = sanitize_text_field($_GET['rl_conversion_status'] ?? '');
        if (empty($filter) || ! in_array($query->get('post_type'), self::POST_TYPES, true)) {
            return;
        }

        if ($filter === 'unmapped') {
            $query->set('meta_query', [
                ['key' => '_rl_conversion_unmapped_widgets', 'compare' => 'EXISTS'],
                ['key' => '_rl_conversion_status', 'value' => 'approved', 'compare' => '!='],
            ]);

            return;
        }

        $query->set('meta_key', '_rl_conversion_status');
        $query->set('meta_value', $filter);
    }

    public function addApproveRowAction(array $actions, \WP_Post $post): array
    {
        $status = get_post_meta($post->ID, '_rl_conversion_status', true);

        if ($status !== 'needs_review' || ! current_user_can('manage_options')) {
            return $actions;
        }

        $url = wp_nonce_url(
            admin_url('admin.php?action=rl_approve_conversion&post_id='.$post->ID),
            'rl_approve_conversion_'.$post->ID
        );

        $actions['rl_approve_conversion'] = '<a href="'.esc_url($url).'" style="color:#166534;font-weight:600;">Approve &amp; Mark Clean</a>';

        return $actions;
    }

    public function handleApproveAction(): void
    {
        $postId = absint($_GET['post_id'] ?? 0);

        check_admin_referer('rl_approve_conversion_'.$postId);

        if (! current_user_can('manage_options') || ! $postId) {
            wp_die('You do not have permission to approve this post.');
        }

        app(ApplyElementorConversionAction::class)->approve($postId);

        wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php'));
        exit;
    }
}
