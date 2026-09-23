<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress;

use WP_Error;
use WP_Post;

/**
 * Copies a post — any type — with its content, meta and terms.
 *
 * Shared by the wp-admin "Duplicate" row action and the MCP clone-page ability, so
 * both agree on what follows a post to its copy. Meta is copied wholesale because a
 * copy missing its page template or ACF field data renders differently from the
 * original; the exclusions below are the keys that identify one specific post.
 */
class PostDuplicator
{
    /**
     * postmeta that must not follow a post to its copy.
     *
     * - `_edit_*` / `_wp_old_*`: WordPress bookkeeping for the source post.
     * - `_rl_partner_code`: PartnerLink::findByCode() resolves a code to one partner, so a
     *   second hub carrying it would split that partner's lead attribution.
     * - `_yoast_wpseo_canonical`: an explicit canonical would point the copy at its source
     *   and keep it out of the index once published.
     */
    public const META_NOT_COPIED = [
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
        '_wp_old_date',
        '_rl_partner_code',
        '_yoast_wpseo_canonical',
    ];

    /**
     * Duplicate a post as a new draft titled "<title> (Copy)".
     *
     * @param  array<string, mixed>  $overrides  wp_insert_post() fields to set on the copy.
     */
    public function duplicate(int $sourceId, array $overrides = []): int|WP_Error
    {
        $source = get_post($sourceId);

        if (! $source instanceof WP_Post) {
            return new WP_Error('not_found', "Post {$sourceId} not found.");
        }

        $postId = wp_insert_post(wp_slash($overrides + [
            'post_type' => $source->post_type,
            'post_title' => $source->post_title.' (Copy)',
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
            'post_content' => $source->post_content,
            'post_excerpt' => $source->post_excerpt,
            'post_parent' => $source->post_parent,
            'menu_order' => $source->menu_order,
            'comment_status' => $source->comment_status,
            'ping_status' => $source->ping_status,
            'post_password' => $source->post_password,
        ]), true);

        if (is_wp_error($postId)) {
            return $postId;
        }

        $this->copyMeta($sourceId, $postId);
        $this->copyTerms($source, $postId);

        return $postId;
    }

    public function copyMeta(int $sourceId, int $postId): void
    {
        foreach (get_post_meta($sourceId) as $key => $values) {
            if (in_array($key, self::META_NOT_COPIED, true)) {
                continue;
            }

            foreach ($values as $value) {
                // get_post_meta() without a key returns the raw serialized strings, and
                // add_post_meta() unslashes its input — so unserialize, then slash, or a
                // backslash in ACF or block JSON is silently stripped on the copy.
                add_post_meta($postId, $key, wp_slash(maybe_unserialize($value)));
            }
        }
    }

    private function copyTerms(WP_Post $source, int $postId): void
    {
        foreach (get_object_taxonomies($source->post_type) as $taxonomy) {
            $terms = wp_get_object_terms($source->ID, $taxonomy, ['fields' => 'ids']);

            if (! is_wp_error($terms) && $terms !== []) {
                wp_set_object_terms($postId, $terms, $taxonomy);
            }
        }
    }
}
