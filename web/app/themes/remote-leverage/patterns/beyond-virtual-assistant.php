<?php
/**
 * Title: Beyond Virtual Assistant - 4 Specialty Roles
 * Slug: remote-leverage/beyond-virtual-assistant
 * Categories: remote-leverage
 * Description: 4 department specialty cards showcasing administrative, healthcare, sales, and operations roles.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <!-- wp:columns {"style":{"spacing":{"margin":{"bottom":"3.5rem"}}}} -->
    <div class="wp-block-columns" style="margin-bottom:3.5rem">
        <!-- wp:column {"width":"55%"} -->
        <div class="wp-block-column" style="flex-basis:55%">
            <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
            <h2 class="wp-block-heading has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.08">
                Beyond a "Virtual Assistant"
            </h2>
            <!-- /wp:heading -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column {"width":"45%"} -->
        <div class="wp-block-column" style="flex-basis:45%">
            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"}}} -->
            <p style="line-height:1.6">
                From administrative coordination to specialized operational functions, we match you with dedicated professionals who have direct experience in your industry.
            </p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->

    <?php echo \App\Support\BlockDefaults::renderDepartmentCards(); ?>
</div>
<!-- /wp:group -->
