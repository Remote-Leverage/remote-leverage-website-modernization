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
];
