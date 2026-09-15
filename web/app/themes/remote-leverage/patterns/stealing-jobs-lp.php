<?php

/**
 * Title: Stealing Jobs LP
 * Slug: remote-leverage/stealing-jobs-lp
 * Categories: remote-leverage
 * Description: Light-ground variant of the "steal your job" campaign page.
 *
 * Config only — markup is resources/patterns/steal-campaign.php.
 *
 * This is NOT a duplicate of /stealing-jobs/. The two are word-for-word identical apart
 * from their CTA labels, but production inverts the entire surface palette here: the hero
 * band is #3D1A5D instead of #0D0D0D, the page ground is #F4F6FC instead of #0D0D0D, the
 * roles band is #13132F, and the closing band is #250D4A with Map-1.png. Measured with
 * getComputedStyle on 2026-09-15.
 */
$steal = [
    'theme' => 'light',

    'cta_text' => 'Book Your FREE Consultation',
    'steps_cta_text' => 'Book Your FREE Consultation',

    'hero_headline' => 'We&#39;re here <br>to steal your job', // straight apostrophe, as production; the entity survives wptexturize, a raw ' does not
    'hero_sub' => 'Well… the parts you shouldn&rsquo;t be doing',
    'hero_note' => 'Don&rsquo;t worry. <br>You can keep the CEO title.',

    'ownership_headline' => '<strong>Your new VA isn&rsquo;t here to<br>&ldquo;help out.&rdquo;</strong><br>They&rsquo;re here to take<br>ownership.',

    'roles_headline' => 'What can we steal from your to-do list?',
    // Same repeated "Customer Support" card production shows on /stealing-jobs/.
    // DELIBERATE DIVERGENCE (approved 2026-09-15): production repeats "Customer Support" here,
    // title and chips identical to the card above it, so the roles grid shows the same role
    // twice and never names the catch-all. /steal-back-your-time/ — same template, same section —
    // carries the real fifth card, so this is production's content for the slot, not invented.
    // Flagged for fixing on production; until then v2 and production differ on this card.
    'roles_fifth' => [
        'title' => 'And More',
        'span' => '',
        'image' => 'Earth-Illustration-2-1-1.png',
        'media_class' => 'pb-0 flex justify-end',
        'chips' => ['Data entry and research', 'Bookkeeping and invoicing', 'Project and task tracking', 'HR and recruiting support'],
    ],

    'steps_note' => 'No upfront gamble. No endless résumé hunting. No long-term agency markup.',
    // The one copy delta from /stealing-jobs/ beyond the CTA labels.
    'pricing_note' => 'Hire directly. Pay your VA directly. Keep the leverage.',

    'closing_headline' => 'Keep the CEO title. We&rsquo;ll take the rest.',
    'closing_image' => 'Map-1.png',
];

include get_theme_file_path('resources/patterns/steal-campaign.php');
