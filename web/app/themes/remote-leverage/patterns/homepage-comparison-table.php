<?php

use App\Support\BlockDefaults;

/**
 * Title: Homepage Comparison Table - Remote Leverage vs Other Agencies
 * Slug: remote-leverage/homepage-comparison-table
 * Categories: remote-leverage
 * Description: Six criteria compared against other agencies, with a separate mobile treatment.
 *
 * Each row carries two values per side: the desktop wording and the shorter phrase the mobile
 * comp uses, which has to read without the row label beside it. Where the two comps disagree on
 * substance — "4–8 weeks" against "4–8 weeks to hire", "None" against "Not offered" — the short
 * form is the mobile comp's own wording, not an abbreviation invented here.
 */
$rows = [
    [
        'feature' => 'Cost to Hire',
        'rl' => '1-time placement fee',
        'rl_short' => '1-time placement fee',
        'diy' => '3-4x monthly markup',
        'diy_short' => '3–4x monthly markup',
    ],
    [
        'feature' => 'Cost to Start',
        'rl' => 'Free to interview, only pay after hire',
        'rl_short' => 'Free to interview',
        'diy' => 'High retainers to start recruiting process',
        'diy_short' => 'High retainer up front',
    ],
    [
        'feature' => 'Time to Hire',
        'rl' => '48 hours',
        'rl_short' => 'Hired in 48 hours',
        'diy' => '4–8 weeks',
        'diy_short' => '4–8 weeks to hire',
    ],
    [
        'feature' => 'English Fluency',
        'rl' => 'Near-native English',
        'rl_short' => 'Near-native English',
        'diy' => 'Little English fluency screening',
        'diy_short' => 'Rarely screened',
    ],
    [
        'feature' => 'Replacements',
        'rl' => '12-month guarantee, fast replacements',
        'rl_short' => '12-month replacement guarantee',
        'diy' => 'Slow, complex replacements – if possible',
        'diy_short' => 'Slow, if possible',
    ],
    [
        'feature' => 'Screen Monitoring',
        'rl' => 'Optional. Ensure your VA is productive.',
        'rl_short' => 'Optional screen monitoring',
        'diy' => 'None',
        'diy_short' => 'Not offered',
    ],
];
?>
<!-- wp:group {"align":"full","className":"px-4 sm:px-6 lg:px-8","style":{"spacing":{"padding":{"top":"5rem","bottom":"5rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull px-4 sm:px-6 lg:px-8 has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:5rem">
    <!-- wp:heading {"textAlign":"center","level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.02em"},"spacing":{"margin":{"bottom":"3.5rem"}}},"fontSize":"huge"} -->
    <h2 class="wp-block-heading has-text-align-center has-huge-font-size" style="letter-spacing:-0.02em;line-height:1.08;margin-bottom:3.5rem">Remote Leverage vs Other Agencies</h2>
    <!-- /wp:heading -->

    <?= BlockDefaults::renderDataTable(BlockDefaults::withFieldKeys('data_table_block', [
        'variant' => 'leverage-first',
        'col_0_header' => '',
        'col_1_header' => 'Other Agencies',
        'col_2_header' => 'RemoteLeverage',
    ]), $rows) ?>
</div>
<!-- /wp:group -->
