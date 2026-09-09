<?php
/**
 * Title: Booking Footer - Ready to Scale
 * Slug: remote-leverage/booking-footer
 * Categories: remote-leverage
 * Description: High-converting final call-to-action with embedded reactive booking wizard.
 */
$imgBase = get_template_directory_uri().'/public/images/home';
?>
<!-- wp:group {"align":"full","className":"relative overflow-hidden rl-booking-footer-group","style":{"spacing":{"padding":{"top":"6rem","bottom":"6rem"}}},"backgroundColor":"brand-dark-violet","textColor":"white","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull relative overflow-hidden rl-booking-footer-group has-white-color has-brand-dark-violet-background-color has-text-color has-background" id="booking-footer" style="padding-top:6rem;padding-bottom:6rem">
    <!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":"4rem"}}} -->
    <div class="wp-block-columns are-vertically-aligned-center">
        <!-- wp:column {"width":"50%"} -->
        <div class="wp-block-column" style="flex-basis:50%">
            <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.05","letterSpacing":"-0.03em"}},"textColor":"white","fontSize":"huge"} -->
            <h2 class="wp-block-heading has-white-color has-text-color has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.05">
                Ready to scale your<br>global team?
            </h2>
            <!-- /wp:heading -->

            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"}}} -->
            <p style="line-height:1.6">
                During this meeting we will go over the role you're planning to hire for, what the process looks like, answer any questions you have, and proceed to next steps.
            </p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column {"width":"50%"} -->
        <div class="wp-block-column" style="flex-basis:50%">
            <!-- wp:acf/booking {"name":"acf/booking","data":{"skin":"glass"},"align":"","mode":"preview"} /-->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->
</div>
<!-- /wp:group -->
