<?php

use App\Support\BlockDefaults;

/**
 * Title: Reviews - Pricing Funnel Intro
 * Slug: remote-leverage/reviews-hero-repeat
 * Categories: remote-leverage
 * Description: Secondary "$6-$10 Per Hour" pricing intro + talent profile marquee, reused inside the /reviews/ conversion funnel below the testimonials.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"1rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:1rem">
    <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.1","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
    <h2 class="wp-block-heading has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.1">
        Most Virtual Assistants<br>$6-$10 Per Hour
    </h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"}},"fontSize":"large"} -->
    <p class="has-large-font-size" style="line-height:1.6">
        Hire English Speaking Virtual Assistants From Latin America &amp; The Philippines <strong>for 70% Less Than U.S. Employees</strong>.
    </p>
    <!-- /wp:paragraph -->

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
