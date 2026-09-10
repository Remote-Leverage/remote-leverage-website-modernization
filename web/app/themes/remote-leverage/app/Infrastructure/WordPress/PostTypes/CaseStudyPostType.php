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
