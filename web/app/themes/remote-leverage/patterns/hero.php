<?php
/**
 * Title: Hero - Great Talent Changes Everything
 * Slug: remote-leverage/hero
 * Categories: remote-leverage
 * Description: Homepage hero section with headline, FIND MY NEXT HIRE CTA, and talent cards marquee.
 */

$imgBase = get_template_directory_uri() . '/public/images/home';
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"3.5rem","bottom":"1rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background relative overflow-hidden" style="padding-top:3.5rem;padding-bottom:1rem;position:relative">
    <div class="absolute top-2 left-1/2 -translate-x-1/2 w-[1400px] max-w-[96vw] h-[540px] pointer-events-none z-0 opacity-45 bg-no-repeat bg-contain bg-center"
        style="background-image: url('<?php echo esc_url($imgBase . '/Map.webp'); ?>');"></div>

    <!-- wp:group {"style":{"spacing":{"blockGap":"1.5rem"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
    <div class="wp-block-group text-center relative z-10">
        <!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"lineHeight":"1.12","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
        <h1 class="wp-block-heading has-text-align-center has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.12;font-weight:700;color:#000000;max-width:768px;margin-left:auto;margin-right:auto;margin-bottom:1.5rem">
            Great Talent Changes<br>Everything
        </h1>
        <!-- /wp:heading -->

        <!-- wp:paragraph {"textAlign":"center","style":{"typography":{"lineHeight":"1.45"}},"fontSize":"large"} -->
        <p class="has-text-align-center has-large-font-size rl-section-subtitle" style="line-height:1.45;color:#000000;font-weight:400;max-width:896px;margin-left:auto;margin-right:auto;margin-bottom:0.5rem">
            And we'll search the world to find your perfect match.
        </p>
        <!-- /wp:paragraph -->

        <!-- wp:paragraph {"textAlign":"center","style":{"typography":{"lineHeight":"1.45"}},"fontSize":"large"} -->
        <p class="has-text-align-center has-large-font-size rl-section-subtitle" style="line-height:1.45;color:#000000;font-weight:400;max-width:896px;margin-left:auto;margin-right:auto">
            We help companies hire exceptional remote talent across sales, marketing, operations, support, and technology roles &ndash; without the overhead of traditional hiring.
        </p>
        <!-- /wp:paragraph -->

        <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"1.5rem","bottom":"3rem"}}}} -->
        <div class="wp-block-buttons" style="margin-top:1.5rem;margin-bottom:3rem">
            <!-- wp:button {"className":"is-style-pill-purple"} -->
            <div class="wp-block-button is-style-pill-purple"><a class="wp-block-button__link wp-element-button" href="#booking-footer">FIND MY NEXT HIRE</a></div>
            <!-- /wp:button -->
        </div>
        <!-- /wp:buttons -->
    </div>
    <!-- /wp:group -->

    <!-- wp:acf/talent-marquee {"name":"acf/talent-marquee","data":{},"align":"","mode":"preview"} /-->
</div>
<!-- /wp:group -->
