<?php

/**
 * Title: Full Page - Hire VA Isolated Form
 * Slug: remote-leverage/hire-va-isolated-form
 * Categories: remote-leverage
 * Description: Older "Virtual Assistant Roles" landing page without the first-month offer, rebuilt section-for-section against production page 50489 (2026-09-15).
 */

/*
 * Production leads with an <h2>, not an <h1> — there is no <h1> anywhere on the page. Reproduced
 * as-is rather than "fixed".
 *
 * Production also carries a "Talk to Sales Representative" instant-call panel, which sits inside
 * the hidden .rl-jlc-modal-* subtree and is omitted per the "confirm a section is actually visible
 * before reproducing it" rule. The hero consequently has no CTA button on this page — its "Meet a
 * specialist now" trigger belongs to that hidden modal.
 *
 * The "Book a Free 15-Minute Consultation" card, however, IS part of the hero: it was previously
 * read as an off-canvas popup because at 1440px production gives the hero's left column the full
 * 1280px and the card overflows the viewport, clipped to a sliver. Measured at 1920px it is a
 * 472px white card in the hero's right column. It is reproduced here.
 */
$page = [
    'hero_title_lead' => 'Latin American',
    'hero_title_gradient' => 'Virtual Assistants<br />$6-$10 Per Hour',
    'hero_cta_text' => '',

    // Production runs this page's headline at 80/88 and pairs the copy column with the white
    // "Book a Free 15-Minute Consultation" card on the right (measured at 1920px, 2026-09-15).
    'hero_headline_size' => '80',
    'hero_card_title' => 'Book a Free 15-Minute Consultation',
    'hero_card_button' => 'Find me an Assistant',

    'hero_checks_left' => ['Hire Direct', '30% Discount on Future Hires', 'Hire Within 72 Hours'],
    'hero_checks_right' => ['No Contracts', 'Interview Before You Hire', 'No Recurring Fees'],

    // Same rows as /1monthonus/ minus the first-month card. Bar colours read off production.
    'why_cards' => [
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
