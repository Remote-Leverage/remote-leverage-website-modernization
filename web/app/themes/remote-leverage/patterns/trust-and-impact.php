<?php
/**
 * Title: Trust & Impact - 2,000+ Businesses
 * Slug: remote-leverage/trust-and-impact
 * Categories: remote-leverage
 * Description: Trust metrics, global flags, and economic impact counters paired with social proof headline.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-map","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-map-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":"4rem"}}} -->
    <div class="wp-block-columns are-vertically-aligned-center">
        <!-- wp:column {"width":"48%"} -->
        <div class="wp-block-column" style="flex-basis:48%">
            <?php echo \App\Support\BlockDefaults::renderTrustStats(); ?>
        </div>
        <!-- /wp:column -->

        <!-- wp:column {"width":"52%"} -->
        <div class="wp-block-column" style="flex-basis:52%">
            <!-- wp:paragraph -->
            <p style="color:#9F53E7;font-size:1.5rem;letter-spacing:0.1em;margin-bottom:1rem">
                &#9733;&#9733;&#9733;&#9733;&#9733;
            </p>
            <!-- /wp:paragraph -->

            <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.18","letterSpacing":"-0.02em"}},"fontSize":"huge"} -->
            <h2 class="wp-block-heading has-huge-font-size" style="letter-spacing:-0.02em;line-height:1.18">
                We've helped more than 2,000 businesses hire exceptional talent from Latin America, the Caribbean, and Europe.
            </h2>
            <!-- /wp:heading -->

            <!-- wp:buttons -->
            <div class="wp-block-buttons">
                <!-- wp:button {"className":"is-style-pill-purple"} -->
                <div class="wp-block-button is-style-pill-purple"><a class="wp-block-button__link wp-element-button" href="#testimonials">Watch Client Testimonials</a></div>
                <!-- /wp:button -->
            </div>
            <!-- /wp:buttons -->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->
</div>
<!-- /wp:group -->
