<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Hire Real Estate Virtual Assistants (FLP)
 * Slug: remote-leverage/hire-real-estate-virtual-assistants-flp
 * Categories: remote-leverage
 * Description: Production's /hire-real-estate-virtual-assistants-flp/. The same ten sections as its five siblings, with real-estate copy in the hero and the two section headers, and four real-estate role cards.
 */

/*
 * Production reuses the standard department-card photography here — the four role cards carry
 * the same four images as every other page in the family and change only their copy, so this is
 * a content override on acf/department-cards rather than new art.
 */
$roleCards = [
    [
        'img' => BlockDefaults::homeImg('magnific_half-body-shot-of-a-young_SOmwQLyUb8-1.webp'),
        'title' => 'Lead Generation &amp;<br>Inside Sales',
        'desc' => 'Generate and qualify leads, conduct outreach, schedule appointments, and follow up with prospects, including expired listings and FSBOs.',
    ],
    [
        'img' => BlockDefaults::homeImg('magnific_wPmw8Jk7EI-1.webp'),
        'title' => 'CRM &amp; Pipeline<br>Management',
        'desc' => 'Keep your database organized, update lead records, manage pipelines, track follow-ups, and prepare performance reports.',
    ],
    [
        'img' => BlockDefaults::homeImg('magnific_ubzu0aUQLD-1.webp'),
        'title' => 'Listing &amp; Transaction<br>Coordination',
        'desc' => 'Support listings and transactions by organizing documents, tracking deadlines, coordinating inspections and appraisals, and communicating with clients and vendors.',
    ],
    [
        'img' => BlockDefaults::homeImg('magnific_YVjYLdkWeC-1.webp'),
        'title' => 'Marketing &amp; Client<br>Support',
        'desc' => 'Assist with property marketing, social media, email campaigns, open houses, client communication, and referral requests.',
    ],
];

$consult = [
    'hero_headline' => 'Latin American<br>Real Estate VAs<br>$6-$10 Per Hour',
    'hero_intro' => 'Hire experienced, English-speaking Real Estate Virtual Assistants who can manage leads, listings, transactions, CRM updates, and administrative work so your agents can stay focused on clients and closing more deals.',
    // The only page in the family that promises 48 hours rather than 72.
    'hero_checklist' => [
        'No contracts or recurring fees',
        'Get matched within 48 hours',
        'Fluent English + U.S. time zones',
        '12-month replacement guarantee',
    ],
    'booking_title' => 'Book a Free Consultation',
    'tick_color' => '#F50084',

    'talent_headline' => 'Top Real Estate Talent,<br>Hired Directly for You',
    'talent_intro' => 'Hire skilled Real Estate VAs directly into your business, with no subscriptions, salary markups, or recurring agency fees. We source and vet professionals who can support your agents, manage your pipeline, coordinate transactions, and keep your daily operations moving.',

    'roles_headline' => 'More Than Administrative Support',
    'roles_intro' => 'Hire experienced professionals who can support your real estate business across lead generation, sales, transactions, marketing, and operations.',
    'roles_cards' => $roleCards,
];

include get_theme_file_path('resources/patterns/consultation-landing.php');
