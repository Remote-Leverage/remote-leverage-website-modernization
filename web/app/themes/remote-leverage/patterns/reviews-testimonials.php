<?php

use App\Support\BlockDefaults;

/**
 * Title: Reviews - Client Reviews (Full 77-Video Grid)
 * Slug: remote-leverage/reviews-testimonials
 * Categories: remote-leverage
 * Description: The complete /reviews/ page hero — "Client Reviews" heading plus the full 77-item video testimonial grid.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"3.5rem","bottom":"5rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:3.5rem;padding-bottom:5rem">
    <!-- wp:columns {"style":{"spacing":{"margin":{"bottom":"3rem"}}}} -->
    <div class="wp-block-columns" style="margin-bottom:3rem">
        <!-- wp:column {"width":"60%"} -->
        <div class="wp-block-column" style="flex-basis:60%">
            <!-- wp:heading {"level":1,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
            <h1 class="wp-block-heading has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.08">
                Client Reviews
            </h1>
            <!-- /wp:heading -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column {"width":"40%"} -->
        <div class="wp-block-column" style="flex-basis:40%">
            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"}}} -->
            <p style="line-height:1.6">
                Don't just take our word for it, hear from business owners who've hired through Remote Leverage. See why quality makes all the difference!
            </p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->

    <?= BlockDefaults::renderReviewsTestimonials() ?>
</div>
<!-- /wp:group -->
