<?php

/**
 * Title: Full Page - Isolated Form Fields Variant C
 * Slug: remote-leverage/hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-c
 * Categories: remote-leverage
 * Description: Production's isolated-form-fields variant C. Shares resources/patterns/consultation-landing.php; carries its own price-led headline and intro.
 *
 * Production also ships an "Instant Sales Call / Talk to Sales Representative" widget in this
 * page's markup, but it is not rendered at any breakpoint — the captured page shows the standard
 * ten sections and nothing else — so it is deliberately left out.
 */
$consult = [
    'hero_headline' => '<span class="block mb-6">$0 to Start.</span>Hire Experienced<br>Latin American Talent<br>from $6/hr',
    'hero_intro' => 'Meet, assess, and <strong>hire top 1% vetted professionals</strong> without the monthly markups.',
    'booking_title' => 'Tell us about your business',
    'tick_color' => '#10B981',
];

include get_theme_file_path('resources/patterns/consultation-landing.php');
