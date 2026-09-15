<?php

/**
 * Title: Steal Back Your Time
 * Slug: remote-leverage/steal-back-your-time
 * Categories: remote-leverage
 * Description: Campaign landing page — "Steal Back Your Time", on the near-black #0D0D0D ground.
 *
 * Config only — markup is resources/patterns/steal-campaign.php.
 */
$steal = [
    // Same near-black ground as /stealing-jobs/. Production body is #0D0D0D.
    'theme' => 'dark',

    'cta_text' => 'Book my free 15-min call',
    'steps_cta_text' => 'Book a free consultation',

    'hero_headline' => 'Steal Back Your Time.<br>Hand The Busywork<br>To A Virtual Assistant.',
    'hero_sub' => 'Hire a top 1% Latin American VA to take the busywork off your plate, so your time goes back to running the business.',
    'hero_note' => 'You keep running the business. We&rsquo;ll steal back the hours it&rsquo;s taking from you.',
    // Production swaps five logos into the strip on this page only.
    'logos' => [
        ['image-9.png', 'Bench'],
        ['image-7.png', 'SETAERO'],
        ['hireva4-chickfila-1.png', 'Chick-fil-A'],
        ['hireva4-adp-1.png', 'ADP'],
        ['hireva4-mainstreet-1.png', 'Mainstreet'],
        ['hireva4-farmers-1.png', 'Farmers Insurance'],
        ['hireva4-college-hunks-1.png', 'College Hunks Hauling Junk'],
        ['image-8.png', 'Sivia Law'],
        ['image-6.png', 'Q-Bit Wellness'],
        ['image-10.png', 'Garuz Legal Group'],
        ['image-4.png', 'BRRRR'],
        ['image-5.png', 'AdCenter360'],
        ['1-1.png', 'RE/MAX'],
        ['image-3.png', 'Carbon Solutions Group'],
    ],

    'ownership_headline' => '<strong>Your new VA isn&rsquo;t here to<br>&ldquo;help out.&rdquo;</strong><br>They&rsquo;re here to give you<br>your time back.',

    'roles_headline' => 'What&rsquo;s stealing your time right now?',
    // The only page of the three with a genuine fifth role in this slot.
    'roles_fifth' => [
        'title' => 'And More',
        'span' => '',
        'image' => 'Earth-Illustration-2-1-1.png',
        'media_class' => 'pb-0 flex justify-end',
        'chips' => ['Data entry and research', 'Bookkeeping and invoicing', 'Project and task tracking', 'HR and recruiting support'],
    ],

    // Production spells "resume" without the accent on this page only.
    'steps_note' => 'No upfront gamble. No endless resume hunting. No long-term agency markup.',
    'pricing_note' => 'No upfront gamble. No endless resume hunting. No long-term agency markup.',

    'closing_headline' => 'Steal back your time.<br>Your VA takes it from here.',
    'closing_image' => 'form-v2-1.jpg',
];

include get_theme_file_path('resources/patterns/steal-campaign.php');
