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
