<?php

/**
 * Title: Full Page - Hire VA
 * Slug: remote-leverage/hire-va
 * Categories: remote-leverage
 * Description: Production page 38574 (/hire-va/). Third configuration of the shared "Virtual Assistant Roles" landing template — no first-month offer and no hero booking card, a green "Book a Consultation" CTA instead.
 */

/*
 * This page is a *configuration*, not a build.
 *
 * /hire-va/ is the same Elementor template as /hire-va-isolated-form/ and /1monthonus/, which
 * already live in v2 on resources/patterns/va-roles-landing.php. Verified 2026-09-15 against
 * production rather than assumed:
 *
 *   - Section manifest identical, in order and count: hero (780px, #6200A4 -> #6E1686 135deg),
 *     logo strip (120px), "Virtual Assistant Roles" (#FAFAFA), "We've hired thousands..." (#FFF),
 *     "Why Hire Virtual Assistants Through Remote Leverage?" (#FAFAFA), "Our Hiring Process",
 *     "12-Month Replacement Guarantee", "Client Reviews" (#FAFAFA), "Frequently Asked Questions",
 *     "15 Minute Virtual Assistant Hiring Consultation" + booking, footer.
 *   - A full visible-text-node diff of production /hire-va/ against production
 *     /hire-va-isolated-form/ returns exactly two differences: the hero tick list, and this page
 *     carrying a "Book a Consultation" button where the other carries the white booking card.
 *     Role copy, benefit rows, process steps, guarantee, reviews and all 11 FAQ answers are
 *     byte-identical.
 *
 * No new key was needed on the shared template, so the sibling pages it drives are untouched.
 *
 * Production leads with an <h2>, not an <h1> — there is no <h1> anywhere on the page, as on the
 * two siblings. Reproduced as-is rather than "fixed".
 *
 * The "Sales Team Currently Unavailable / Offline" copy present in the hero's DOM belongs to the
 * hidden .rl-jlc-modal-* instant-call subtree (display:none at every breakpoint) and is omitted
 * per the "confirm a section is actually visible before reproducing it" rule.
 */
$page = [
    'hero_title_lead' => 'Latin American',
    'hero_title_gradient' => 'Virtual Assistants<br />$6-$10 Per Hour',

    // Measured on production 2026-09-15: 290x62, #68B93D, 24px/700 white, radius 5px,
    // padding 19px 40px, href "#booking-footer" — the template's existing hero CTA exactly.
    'hero_cta_text' => 'Book a Consultation',

    // Headline is three lines and production runs it at 80/88 (getComputedStyle on the hero h2).
    'hero_headline_size' => '80',

    // No hero booking card and no hero background image on this page: the band is the bare
    // 135deg #6200A4 -> #6E1686 gradient, backgroundImage reads as that gradient alone.

    'hero_checks_left' => ['No Contracts', 'Interview Before You Hire', 'No Recurring Fees'],
    'hero_checks_right' => ['30% Discount on Future Hires', 'Hire Direct &#45; No Middleman', 'Hire Within 72 Hours'],

    // Same four rows as /hire-va-isolated-form/ — no "First Month Salary Paid By Us" row.
    // Bar colours read off production's border-left on each row.
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
