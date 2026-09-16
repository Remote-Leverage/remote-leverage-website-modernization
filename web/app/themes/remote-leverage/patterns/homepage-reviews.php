<?php

use App\Support\BlockDefaults;

/**
 * Title: Homepage Reviews - Client Reviews
 * Slug: remote-leverage/homepage-reviews
 * Categories: remote-leverage
 * Description: Two-across wall of bare video testimonials with a SHOW MORE control.
 *
 * `plain` and two columns, not the quote cards /hire-va-4/ ships: the comp's tiles carry only
 * the video, its play control and its duration — no pull quote, no company line. Heading and
 * subheading are centred here where that page left-aligns them.
 *
 * The full fifteen reviews are fed in, collapsed to six. Passing only the six featured rows
 * leaves the block with nothing to reveal, so it correctly suppresses the SHOW MORE control —
 * the same trap this page's sibling fell into on 2026-09-15.
 */
?>
<!-- wp:group {"align":"full","className":"px-4 sm:px-6 lg:px-8","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull px-4 sm:px-6 lg:px-8 has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <!-- wp:heading {"textAlign":"center","level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.02em"},"spacing":{"margin":{"bottom":"1rem"}}},"fontSize":"huge"} -->
    <h2 class="wp-block-heading has-text-align-center has-huge-font-size" style="letter-spacing:-0.02em;line-height:1.08;margin-bottom:1rem">Client Reviews</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"1rem","lineHeight":"1.6"},"spacing":{"margin":{"bottom":"3rem"}}}} -->
    <p class="has-text-align-center" style="font-size:1rem;line-height:1.6;margin-bottom:3rem">Don't just take our word for it,&nbsp; hear from business owners who've hired<br>through Remote Leverage. See why quality makes all the difference!</p>
    <!-- /wp:paragraph -->

    <?= BlockDefaults::renderHireVa4Testimonials(
        BlockDefaults::withFieldKeys('testimonials_block', [
            'layout' => 'plain',
            'plain_chrome' => 'player',
            'columns' => '2',
            'show_more' => 1,
            'visible_count' => 6,
            'tone' => 'light',
        ]),
        BlockDefaults::testimonials(),
    ) ?>
</div>
<!-- /wp:group -->
