<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\PostTypes;

class PartnerPostType
{
    /**
     * Register the rl_partner Custom Post Type and custom rewrite rules.
     * Ported from RL_Partner_CPT in rl-partners-hub.
     */
    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('init', [$this, 'addRewriteRules']);
        add_filter('query_vars', [$this, 'registerQueryVars']);
    }

    /**
     * Whitelist the internal query var used by the tabbed partner hub route.
     */
    public function registerQueryVars(array $vars): array
    {
        $vars[] = 'rl_tab';

        return $vars;
    }

    /**
     * Rewrite rule for tabbed partner hubs: /partners/{slug}/{tab}/
     */
    public function addRewriteRules(): void
    {
        add_rewrite_rule('^partners/([^/]+)/([^/]+)/?$', 'index.php?rl_partner=$matches[1]&rl_tab=$matches[2]', 'top');
    }

    public function registerPostType(): void
    {
        $labels = [
            'name' => _x('Partners', 'Post Type General Name', 'remote-leverage'),
            'singular_name' => _x('Partner', 'Post Type Singular Name', 'remote-leverage'),
            'menu_name' => __('Partners Hub', 'remote-leverage'),
            'name_admin_bar' => __('Partner Hub', 'remote-leverage'),
            'archives' => __('Partner Archives', 'remote-leverage'),
            'attributes' => __('Partner Attributes', 'remote-leverage'),
            'all_items' => __('All Partners', 'remote-leverage'),
            'add_new_item' => __('Add New Partner Hub', 'remote-leverage'),
            'add_new' => __('Add New Partner', 'remote-leverage'),
            'new_item' => __('New Partner', 'remote-leverage'),
            'edit_item' => __('Edit Partner', 'remote-leverage'),
            'update_item' => __('Update Partner', 'remote-leverage'),
            'view_item' => __('View Partner Hub', 'remote-leverage'),
            'view_items' => __('View Partners', 'remote-leverage'),
            'search_items' => __('Search Partner', 'remote-leverage'),
            'not_found' => __('No partners found', 'remote-leverage'),
            'not_found_in_trash' => __('No partners found in Trash', 'remote-leverage'),
        ];

        register_post_type('rl_partner', [
            'label' => __('Partner', 'remote-leverage'),
            'description' => __('Co-branded partner hubs for Remote Leverage partners.', 'remote-leverage'),
            'labels' => $labels,
            'supports' => ['title', 'editor', 'thumbnail', 'revisions', 'custom-fields'],
            'public' => true,
            'has_archive' => false,
            'rewrite' => ['slug' => 'partners', 'with_front' => false],
            'show_in_rest' => true,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-groups',
        ]);
    }
}
