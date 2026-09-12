<?php

/**
 * Theme filters.
 */

namespace App;

use App\Support\BlockDefaults;

/**
 * Add "… Continued" to the excerpt.
 *
 * @return string
 */
add_filter('excerpt_more', function () {
    return sprintf(' &hellip; <a href="%s">%s</a>', get_permalink(), __('Continued', 'sage'));
});

// Initialize default demo content hooks for ACF blocks and patterns
BlockDefaults::init();

/**
 * Blog listing page sizes, matching production: 60 articles on the posts page and
 * 30 on a category archive. WordPress's own posts_per_page setting stays at its
 * default for everything else.
 */
add_action('pre_get_posts', function (\WP_Query $query) {
    if (is_admin() || ! $query->is_main_query()) {
        return;
    }

    if ($query->is_home()) {
        $query->set('posts_per_page', 60);
    } elseif ($query->is_category()) {
        $query->set('posts_per_page', 30);
    }
});
