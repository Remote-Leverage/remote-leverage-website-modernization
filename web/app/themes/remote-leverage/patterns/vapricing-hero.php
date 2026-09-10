<?php

use App\Support\BlockDefaults;

/**
 * Title: VA Pricing Hero - $6-$10 Per Hour
 * Slug: remote-leverage/vapricing-hero
 * Categories: remote-leverage
 * Description: Pricing page hero with headline, trust checklist, CTA, and talent profile marquee.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"3.5rem","bottom":"1rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:3.5rem;padding-bottom:1rem">
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

    <!-- wp:buttons {"style":{"spacing":{"margin":{"top":"1.5rem","bottom":"3rem"}}}} -->
    <div class="wp-block-buttons" style="margin-top:1.5rem;margin-bottom:3rem">
        <!-- wp:button {"className":"is-style-pill-purple"} -->
        <div class="wp-block-button is-style-pill-purple"><a class="wp-block-button__link wp-element-button" href="#booking-footer">BOOK A CONSULTATION</a></div>
        <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->

    <?= BlockDefaults::renderVaPricingTalentMarquee() ?>
</div>
<!-- /wp:group -->
