<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Infrastructure\WordPress\PostDuplicator;
use WP_Admin_Bar;
use WP_Post;

/**
 * "Duplicate" for pages, posts, partner hubs and case studies — a row action in the
 * list table and an admin-bar item beside "Edit Page" on the front end.
 *
 * Both link to one admin-post handler, which copies the post as a draft and opens the
 * copy in the editor. The copy logic itself lives in PostDuplicator, shared with the
 * MCP clone-page ability.
 */
class DuplicatePostAdmin
{
    public const ACTION = 'rl_duplicate_post';

    public const POST_TYPES = ['page', 'post', 'rl_partner', 'case_study'];

    public function __construct(private PostDuplicator $duplicator) {}

    public function register(): void
    {
        add_filter('page_row_actions', [$this, 'addRowAction'], 10, 2);
        add_filter('post_row_actions', [$this, 'addRowAction'], 10, 2);
        add_action('admin_bar_menu', [$this, 'addAdminBarNode'], 81);
        add_action('admin_post_'.self::ACTION, [$this, 'handleRequest']);
    }

    /**
     * @param  array<string, string>  $actions
     * @return array<string, string>
     */
    public function addRowAction(array $actions, WP_Post $post): array
    {
        if ($this->canDuplicate($post)) {
            $actions['rl_duplicate'] = '<a href="'.esc_url($this->url($post)).'">Duplicate</a>';
        }

        return $actions;
    }

    /**
     * Priority 81 lands the node straight after core's "Edit" (80).
     */
    public function addAdminBarNode(WP_Admin_Bar $bar): void
    {
        if (is_admin() || ! is_singular()) {
            return;
        }

        $post = get_queried_object();

        if (! $post instanceof WP_Post || ! $this->canDuplicate($post)) {
            return;
        }

        $label = get_post_type_object($post->post_type)?->labels->singular_name ?? 'Post';

        $bar->add_node([
            'id' => 'rl-duplicate',
            'title' => 'Duplicate '.$label,
            'href' => $this->url($post),
        ]);
    }

    public function handleRequest(): void
    {
        wp_safe_redirect($this->duplicateFromRequest());
        exit;
    }

    /**
     * Validate the request, make the copy and return the URL to send the user to.
     */
    public function duplicateFromRequest(): string
    {
        $postId = absint($_GET['post'] ?? 0);

        if (! wp_verify_nonce((string) ($_GET['_wpnonce'] ?? ''), self::ACTION.'_'.$postId)) {
            wp_die('This duplicate link has expired. Go back and try again.', '', ['response' => 403]);
        }

        $post = get_post($postId);

        if (! $post instanceof WP_Post || ! $this->canDuplicate($post)) {
            wp_die('You are not allowed to duplicate this item.', '', ['response' => 403]);
        }

        $copyId = $this->duplicator->duplicate($postId);

        if (is_wp_error($copyId)) {
            wp_die(esc_html($copyId->get_error_message()));
        }

        return (string) get_edit_post_link($copyId, 'raw');
    }

    private function canDuplicate(WP_Post $post): bool
    {
        if (! in_array($post->post_type, self::POST_TYPES, true)) {
            return false;
        }

        $type = get_post_type_object($post->post_type);

        return $type !== null
            && current_user_can('edit_post', $post->ID)
            && current_user_can($type->cap->create_posts);
    }

    private function url(WP_Post $post): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action='.self::ACTION.'&post='.$post->ID),
            self::ACTION.'_'.$post->ID,
        );
    }
}
