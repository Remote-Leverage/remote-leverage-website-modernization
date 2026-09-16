<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\PostTypes;

class CaseStudyPostType
{
    /**
     * Register the case_study Custom Post Type.
     */
    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('pre_get_posts', [$this, 'showEveryCaseStudyOnArchive']);
    }

    /**
     * Put every published case study on /case-study/, with no pager.
     *
     * The archive template renders the loop straight through, so without this it inherits
     * the site's default `posts_per_page` of 10 and silently truncates — 10 of 23 studies
     * rendered, the other 13 live but unreachable from the index, and no pager to hint that
     * anything was cut. Production lists all of its own on one page with no pagination, so
     * an unbounded query is the parity behaviour as well as the correct one.
     *
     * Guarded on the main front-end query: leaving admin list tables and any secondary
     * WP_Query (related studies, REST) on their own paging.
     */
    public function showEveryCaseStudyOnArchive(\WP_Query $query): void
    {
        if (is_admin() || ! $query->is_main_query()) {
            return;
        }

        if ($query->is_post_type_archive('case_study')) {
            $query->set('posts_per_page', -1);
        }
    }

    public function registerPostType(): void
    {
        $labels = [
            'name' => 'Case Studies',
            'singular_name' => 'Case Study',
            'menu_name' => 'Case Studies',
            'name_admin_bar' => 'Case Study',
            'archives' => 'Case Study Archives',
            'attributes' => 'Case Study Attributes',
            'all_items' => 'All Case Studies',
            'add_new_item' => 'Add New Case Study',
            'add_new' => 'Add New',
            'new_item' => 'New Case Study',
            'edit_item' => 'Edit Case Study',
            'update_item' => 'Update Case Study',
            'view_item' => 'View Case Study',
            'view_items' => 'View Case Studies',
            'search_items' => 'Search Case Studies',
            'not_found' => 'No case studies found',
            'not_found_in_trash' => 'No case studies found in Trash',
        ];

        register_post_type('case_study', [
            'label' => 'Case Study',
            'description' => 'Client success stories and hiring outcome case studies.',
            'labels' => $labels,
            'supports' => ['title', 'editor', 'thumbnail', 'revisions', 'custom-fields'],
            'public' => true,
            'has_archive' => 'case-study',
            'rewrite' => ['slug' => 'case-study', 'with_front' => false],
            'show_in_rest' => true,
            'menu_position' => 26,
            'menu_icon' => 'dashicons-chart-line',
        ]);
    }
}
