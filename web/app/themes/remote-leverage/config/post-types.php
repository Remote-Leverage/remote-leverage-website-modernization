<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Custom Post Types Configuration
    |--------------------------------------------------------------------------
    |
    | Defines configuration parameters for application custom post types.
    |
    */

    'partner' => [
        'post_type' => 'rl_partner',
        'singular' => 'Partner',
        'plural' => 'Partners',
        'slug' => 'partners',
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
        'menu_icon' => 'dashicons-groups',
    ],

    'case_study' => [
        'post_type' => 'case_study',
        'singular' => 'Case Study',
        'plural' => 'Case Studies',
        'slug' => 'case-study',
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
        'menu_icon' => 'dashicons-chart-line',
    ],
];
