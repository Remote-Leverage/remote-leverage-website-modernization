<?php

use App\Support\BlockDefaults;

/**
 * Title: VA Pricing Hero - $6-$10 Per Hour
 * Slug: remote-leverage/vapricing-hero
 * Categories: remote-leverage
 * Description: Split pricing page hero — headline, trust checklist, and CTA on the left, talent profile grid on the right.
 */
?>
<!-- wp:group {"align":"full","className":"relative overflow-hidden rl-hero-group","style":{"spacing":{"padding":{"top":"3.5rem","bottom":"3rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull relative overflow-hidden rl-hero-group has-bg-light-background-color has-background" style="padding-top:3.5rem;padding-bottom:3rem">
    <!-- wp:columns {"verticalAlignment":"center","className":"rl-vapricing-hero-columns relative z-10","style":{"spacing":{"blockGap":"2.5rem"}}} -->
    <div class="wp-block-columns are-vertically-aligned-center rl-vapricing-hero-columns relative z-10">
        <!-- wp:column {"verticalAlignment":"center","width":"38%"} -->
        <div class="wp-block-column is-vertically-aligned-center" style="flex-basis:38%">
            <!-- wp:heading {"level":1,"style":{"typography":{"lineHeight":"1.1","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
            <h1 class="wp-block-heading has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.1">
                Most Virtual Assistants<br>$6-$10 Per Hour
            </h1>
            <!-- /wp:heading -->

            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"}},"fontSize":"large"} -->
            <p class="has-large-font-size" style="line-height:1.6">
                Hire English Speaking Virtual Assistants From Latin America &amp; The Philippines <strong>for 70% Less Than U.S. Employees.</strong>
            </p>
            <!-- /wp:paragraph -->

            <!-- wp:list -->
            <ul class="wp-block-list">
            <!-- wp:list-item -->
            <li>No Contracts</li>
            <!-- /wp:list-item -->
            <!-- wp:list-item -->
            <li>Interview Before You Hire</li>
            <!-- /wp:list-item -->
            <!-- wp:list-item -->
            <li>No Recurring Fees</li>
            <!-- /wp:list-item -->
            <!-- wp:list-item -->
            <li>30% Discount on Future Hires</li>
            <!-- /wp:list-item -->
            <!-- wp:list-item -->
            <li>Hire Direct &#8211; No Middleman</li>
            <!-- /wp:list-item -->
            <!-- wp:list-item -->
            <li>Hire Within 72 Hours</li>
            <!-- /wp:list-item -->
            </ul>
            <!-- /wp:list -->

            <!-- wp:buttons {"style":{"spacing":{"margin":{"top":"1.5rem"}}}} -->
            <div class="wp-block-buttons" style="margin-top:1.5rem">
                <!-- wp:button {"className":"is-style-pill-purple"} -->
                <div class="wp-block-button is-style-pill-purple"><a class="wp-block-button__link wp-element-button" href="#booking-footer">BOOK A CONSULTATION</a></div>
                <!-- /wp:button -->
            </div>
            <!-- /wp:buttons -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column {"verticalAlignment":"center","width":"62%"} -->
        <div class="wp-block-column is-vertically-aligned-center" style="flex-basis:62%">
            <?= BlockDefaults::renderVaPricingTalentGrid() ?>
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->
</div>
<!-- /wp:group -->
