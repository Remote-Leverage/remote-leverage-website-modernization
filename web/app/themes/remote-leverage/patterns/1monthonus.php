<?php

/**
 * Title: Full Page - 1 Month On Us
 * Slug: remote-leverage/1monthonus
 * Categories: remote-leverage
 * Description: Older "Virtual Assistant Roles" landing page with the first-month-free offer, rebuilt section-for-section against production page 27643 (2026-09-15).
 */

/*
 * Production leads with an <h2>, not an <h1> — there is no <h1> anywhere on the page. That is
 * reproduced as-is rather than "fixed", because changing it is a content change.
 */
$page = [
    'hero_title_lead' => 'Latin American',
    'hero_title_gradient' => 'Virtual Assistants<br />$6-$10 Per Hour.<br />First Month FREE!',
    'hero_cta_text' => 'Book a Consultation',

    // Unlike /hire-va-isolated-form/, this page has no booking card in the hero. Production paints
    // the band with Violet-Gradient-Header.jpg (background-size:cover, background-position:100% 50%),
    // which carries the photographed assistant on the right-hand side, and keeps the headline at
    // 64/70.4 because its fourth line would otherwise overflow the 768px copy column.
    'hero_headline_size' => '64',
    'hero_bg_image' => 'Violet-Gradient-Header.jpg',

    'hero_checks_left' => ['1st Month Salary Paid By Us', 'Interview Before You Hire', 'No Contracts'],
    'hero_checks_right' => ['30% Discount on Future Hires', 'Hire Direct &#45; No Middleman', 'Hire Within 72 Hours'],

    // Bar colours read off production's border-left on each row.
    'why_cards' => [
        [
            'color' => '#3CC3FD',
            'title' => 'First Month Salary Paid By Us',
            'desc' => 'We will give you credit to cover the first month’s salary of whoever you decide to hire, you just pay our one time placement fee.',
        ],
        [
            'color' => '#3CC3FD',
            'title' => 'Fluent English',
            'desc' => 'We understand how important it is to speak fluent English with little to no accent. We go through hundreds of applicants a day and only bring you the top 1%.',
        ],
        [
            'color' => '#FF9C00',
            'title' => 'No Recurring Fees &#45; Hire Direct',
            'desc' => 'Save thousands of Dollars a year by hiring the Virtual Assistant directly. We charge a one time flat hiring fee if you decide to hire one of the Virtual Assistants we bring you, and help you onboard them directly to avoid ongoing fees.',
        ],
        [
            'color' => '#8932FF',
            'title' => '30% Discount on Future Hires',
            'desc' => 'Get 30% off placement fees for every additional VA you hire within 12 months of your first placement.',
        ],
        [
            'color' => '#F81EFF',
            'title' => 'No Contracts',
            'desc' => 'You’re not locked into any sort of long term commitment with us or any Virtual Assistant you hire through us. If you’re not happy with the applicants we bring you, we don’t get paid.',
        ],
    ],
];

include get_theme_file_path('resources/patterns/va-roles-landing.php');
