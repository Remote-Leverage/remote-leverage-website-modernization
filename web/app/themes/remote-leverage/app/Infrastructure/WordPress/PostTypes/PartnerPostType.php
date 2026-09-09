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
            'name' => 'Partners',
            'singular_name' => 'Partner',
            'menu_name' => 'Partners Hub',
            'name_admin_bar' => 'Partner Hub',
            'archives' => 'Partner Archives',
            'attributes' => 'Partner Attributes',
            'all_items' => 'All Partners',
            'add_new_item' => 'Add New Partner Hub',
            'add_new' => 'Add New Partner',
            'new_item' => 'New Partner',
            'edit_item' => 'Edit Partner',
            'update_item' => 'Update Partner',
            'view_item' => 'View Partner Hub',
            'view_items' => 'View Partners',
            'search_items' => 'Search Partner',
            'not_found' => 'No partners found',
            'not_found_in_trash' => 'No partners found in Trash',
        ];

        register_post_type('rl_partner', [
            'label' => 'Partner',
            'description' => 'Co-branded partner hubs for Remote Leverage partners.',
            'labels' => $labels,
            'supports' => ['title', 'editor', 'thumbnail', 'revisions', 'custom-fields'],
            'public' => true,
            'has_archive' => 'partners',
            'rewrite' => ['slug' => 'partners', 'with_front' => false],
            'show_in_rest' => true,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-groups',
        ]);
    }
}
