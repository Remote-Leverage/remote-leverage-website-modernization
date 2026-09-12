<?php

declare(strict_types=1);

namespace App\Support;

use WP_Block_Patterns_Registry;

/**
 * Renders a registered block pattern from a Blade template.
 *
 * Lets page furniture that already exists as a pattern (the booking footer, say)
 * be reused by templates without duplicating its markup in Blade — the pattern
 * stays the single definition, editor-insertable and template-renderable both.
 */
class Pattern
{
    public static function render(string $slug): string
    {
        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered($slug);

        if ($pattern === null) {
            return '';
        }

        return do_blocks($pattern['content']);
    }
}
