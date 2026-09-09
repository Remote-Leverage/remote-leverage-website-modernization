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
            <!-- wp:html -->
            <div style="display:flex;gap:4px;color:#9F53E7;margin-bottom:1rem;" aria-label="5 out of 5 stars">
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                <?php endfor; ?>
            </div>
            <!-- /wp:html -->

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
