<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

/**
 * Admin list-table enhancements for the `rl_partner` co-branded hub CPT.
 *
 * Field editing itself is handled natively by ACF's tabbed field group
 * (see `App\Fields\PartnerHubFields`) — this class only adds the Referral
 * Code / Submission Form columns to the Posts list, which ACF has no
 * built-in equivalent for.
 */
class PartnerHubAdmin
{
    protected const POST_TYPE = 'rl_partner';

    public function register(): void
    {
        add_filter('manage_'.self::POST_TYPE.'_posts_columns', [$this, 'addAdminColumns']);
        add_action('manage_'.self::POST_TYPE.'_posts_custom_column', [$this, 'renderAdminColumns'], 10, 2);
    }

    public function addAdminColumns(array $columns): array
    {
        $new = [];
        foreach ($columns as $key => $title) {
            $new[$key] = $title;
            if ($key === 'title') {
                $new['partner_code'] = 'Referral Code';
                $new['referral_form'] = 'Submission Form';
            }
        }

        return $new;
    }

    public function renderAdminColumns(string $column, int $postId): void
    {
        if ($column === 'partner_code') {
            $code = get_post_meta($postId, '_rl_partner_code', true);
            echo '<code>'.esc_html($code ?: '—').'</code>';

            return;
        }

        if ($column === 'referral_form') {
            $form = get_post_meta($postId, '_rl_referral_form_url', true);
            if ($form) {
                echo '<a href="'.esc_url($form).'" target="_blank">View Form &#8599;</a>';
            } else {
                echo '<span style="color:#999;">None set</span>';
            }
        }
    }
}
