<?php

/**
 * Title: Stealing Jobs
 * Slug: remote-leverage/stealing-jobs
 * Categories: remote-leverage
 * Description: Campaign landing page — "We're here to steal your job", on the near-black #0D0D0D ground.
 *
 * Config only. The markup is resources/patterns/steal-campaign.php, shared with
 * stealing-jobs-lp and steal-back-your-time; its header carries the measured palette
 * and the block-reuse ladder.
 */
$steal = [
    // Production paints body #0D0D0D here — NOT the brand #250D4A midnight purple.
    'theme' => 'dark',

    'cta_text' => 'Book my free 15-min call',
    'steps_cta_text' => 'Book a free consultation',

    'hero_headline' => 'We&#39;re here <br>to steal your job', // straight apostrophe, as production; the entity survives wptexturize, a raw ' does not
    'hero_sub' => 'Well… the parts you shouldn&rsquo;t be doing',
    'hero_note' => 'Don&rsquo;t worry. <br>You can keep the CEO title.',

    'ownership_headline' => '<strong>Your new VA isn&rsquo;t here to<br>&ldquo;help out.&rdquo;</strong><br>They&rsquo;re here to take<br>ownership.',

    'roles_headline' => 'What can we steal from your to-do list?',
    /* Production repeats "Customer Support" in the fifth slot rather than showing a fifth
       role — /steal-back-your-time/ has "And More" here. Reproduced as production has it;
       flagged in the migration report as a likely production content bug. */
    'roles_fifth' => [
        'title' => 'Customer Support',
        'span' => '',
        'image' => 'Earth-Illustration-2-1-1.png',
        'media_class' => 'pb-0 flex justify-end',
        'chips' => ['Email and chat support', 'Customer follow-ups', 'Ticket management', 'Order assistance'],
    ],

    'steps_note' => 'No upfront gamble. No endless résumé hunting. No long-term agency markup.',
    'pricing_note' => 'No upfront gamble. No endless résumé hunting. No long-term agency markup.',

    'closing_headline' => 'Keep the CEO title. We&rsquo;ll take the rest.',
    'closing_image' => 'form-v2-1.jpg',
];

include get_theme_file_path('resources/patterns/steal-campaign.php');
