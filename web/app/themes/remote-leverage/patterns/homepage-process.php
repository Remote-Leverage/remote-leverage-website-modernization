<?php

use App\Support\BlockDefaults;

/**
 * Title: Homepage Process - How Hiring Works With Remote Leverage
 * Slug: remote-leverage/homepage-process
 * Categories: remote-leverage
 * Description: Three-step hiring timeline with the heading beside its intro, over a pale ground.
 *
 * The step bodies are BlockDefaults::hireVa4ProcessSteps() verbatim — the comp reuses them
 * word for word. Only the titles differ, so only the titles are overridden here.
 */
$steps = BlockDefaults::hireVa4ProcessSteps();
$steps[0]['title'] = 'Tell Us Your<br>Ideal Hire';
$steps[1]['title'] = 'We Screen<br>Your Shortlist';
$steps[2]['title'] = 'Interview and Hire<br>Your Favorite';
?>
<!-- wp:group {"align":"full","className":"px-4 sm:px-6 lg:px-8","style":{"spacing":{"padding":{"top":"5rem","bottom":"5.5rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull px-4 sm:px-6 lg:px-8 has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:5.5rem">
    <!-- wp:columns {"verticalAlignment":"top","style":{"spacing":{"blockGap":{"left":"4rem"}}}} -->
    <div class="wp-block-columns are-vertically-aligned-top">
        <!-- wp:column {"verticalAlignment":"top","width":"52%"} -->
        <div class="wp-block-column is-vertically-aligned-top" style="flex-basis:52%">
            <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.02em"}},"fontSize":"huge"} -->
            <h2 class="wp-block-heading has-huge-font-size" style="letter-spacing:-0.02em;line-height:1.08">How Hiring Works<br>With Remote Leverage</h2>
            <!-- /wp:heading -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column {"verticalAlignment":"top","width":"48%"} -->
        <div class="wp-block-column is-vertically-aligned-top" style="flex-basis:48%">
            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"}}} -->
            <p style="line-height:1.6">Hire top-tier talent in just 48 hours. We screen thousands of applicants daily, so you only meet the top 1%. Move from open role to working team member in days, not weeks.</p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->

    <?= BlockDefaults::renderBlockWithRepeater(
        'process-steps',
        'steps',
        'field_process_steps_block_steps',
        $steps,
        BlockDefaults::withFieldKeys('process_steps_block', ['variant' => 'cards']),
    ) ?>

    <!-- wp:html -->
    <div class="mt-16 flex justify-center">
        <?= view('blocks.partials.cta-pill', ['text' => 'BOOK A CONSULTATION', 'url' => '#booking-footer'])->render() ?>
    </div>
    <!-- /wp:html -->
</div>
<!-- /wp:group -->
