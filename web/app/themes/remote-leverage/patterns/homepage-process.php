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
    <?php /* Heading beside its intro on desktop, stacked on mobile. A core wp:columns block was
         staying side by side at 376px here — the theme does not load core's column stacking
         CSS — which set the heading one word per line. */ ?>
    <!-- wp:html -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[52%_1fr] lg:gap-16 lg:items-start">
        <h2 class="font-display text-4xl sm:text-5xl lg:text-[48px] font-bold leading-[1.08] tracking-[-0.02em] text-brand-hero">How Hiring Works<br class="hidden sm:inline">With Remote Leverage</h2>
        <p class="text-base leading-[1.6] text-black lg:pt-2">Hire top-tier talent in just 48 hours. We screen thousands of applicants daily, so you only meet the top 1%. Move from open role to working team member in days, not weeks.</p>
    </div>
    <!-- /wp:html -->

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
