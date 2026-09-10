<?php

use App\Support\BlockDefaults;

/**
 * Title: Reviews - Our Hiring Process (3 Steps)
 * Slug: remote-leverage/reviews-process-steps
 * Categories: remote-leverage
 * Description: 3-step hiring process cards with the exact copy used on the pricing/reviews funnel.
 */
$steps = [
    [
        'num' => '01',
        'title' => 'Tell Us the<br>Job Position',
        'desc' => "Tell us what role you want to fill, job requirements, who your ideal fit would be, experience required, and anything else that's important in who you hire.",
    ],
    [
        'num' => '02',
        'title' => 'We Screen<br>Virtual Assistants',
        'desc' => 'We will interview qualified Virtual Assistants and assess their skill level, then pass on 4-6 Virtual Assistants for you to interview that match your requirements.',
    ],
    [
        'num' => '03',
        'title' => 'You Meet<br>&amp; Choose Best Fit',
        'desc' => 'After you interview the Virtual Assistants we bring, you get to choose who you feel is the best fit to work in your business.',
    ],
];
$data = [];
BlockDefaults::encodeRepeater('steps', 'field_process_steps_block_steps', $steps, $data);
$stepsBlock = BlockDefaults::patternBlock('process-steps', $data);
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <!-- wp:columns {"style":{"spacing":{"margin":{"bottom":"3.5rem"}}}} -->
    <div class="wp-block-columns" style="margin-bottom:3.5rem">
        <!-- wp:column {"width":"55%"} -->
        <div class="wp-block-column" style="flex-basis:55%">
            <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
            <h2 class="wp-block-heading has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.08">
                Our Hiring Process
            </h2>
            <!-- /wp:heading -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column {"width":"45%"} -->
        <div class="wp-block-column" style="flex-basis:45%">
            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"}}} -->
            <p style="line-height:1.6">
                Hire top-tier virtual assistants in just 72 hours. We handle the screening, so you only meet the top 1% of candidates — ensuring you find the perfect fit for your business quickly and efficiently.
            </p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->

    <?= $stepsBlock ?>
</div>
<!-- /wp:group -->
