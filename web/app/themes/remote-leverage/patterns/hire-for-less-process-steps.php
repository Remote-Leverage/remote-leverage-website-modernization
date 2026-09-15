<?php

use App\Support\BlockDefaults;

/**
 * Title: Process Steps - Our Hiring Process (Hire For Less)
 * Slug: remote-leverage/hire-for-less-process-steps
 * Categories: remote-leverage
 * Description: Production's "Our Hiring Process" as /hire-for-less/ titles its three steps.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <!-- wp:group {"style":{"spacing":{"margin":{"bottom":"3rem"}}},"layout":{"type":"constrained","contentSize":"768px","justifyContent":"left"}} -->
    <div class="wp-block-group" style="margin-bottom:3rem">
        <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
        <h2 class="wp-block-heading has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.08">Our Hiring Process</h2>
        <!-- /wp:heading -->

        <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"},"spacing":{"margin":{"top":"1.25rem"}}},"textColor":"text-muted"} -->
        <p class="has-text-muted-color has-text-color" style="line-height:1.6;margin-top:1.25rem">Hire top-tier talent in just 48 hours. We screen thousands of applicants daily, so you only meet the top 1%. Move from open role to working team member in days, not weeks.</p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:group -->

    <?= BlockDefaults::renderHireVa4ProcessSteps([], BlockDefaults::hireForLessProcessSteps()) ?>
</div>
<!-- /wp:group -->
