<?php

use App\Support\BlockDefaults;

/**
 * Title: Reviews - Client Reviews
 * Slug: remote-leverage/hire-va-4-testimonials
 * Categories: remote-leverage
 * Description: Video testimonial cards with client quotes, company branding, and self-contained Vimeo video modals.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"},"spacing":{"margin":{"bottom":"1rem"}}},"fontSize":"huge"} -->
    <h2 class="wp-block-heading has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.08;margin-bottom:1rem">Client Reviews</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"style":{"typography":{"fontSize":"0.95rem"},"spacing":{"margin":{"bottom":"3rem"}}},"textColor":"text-muted"} -->
    <p class="has-text-muted-color has-text-color" style="font-size:0.95rem;margin-bottom:3rem">Don't just take our word for it, hear from business owners who've hired through Remote Leverage. See why quality makes all the difference!</p>
    <!-- /wp:paragraph -->

    <?php /* Production's wall here is the full 15 reviews collapsed to 6, with a SHOW MORE pill
             that expands in place — the same set and order as BlockDefaults::testimonials().
             Feeding only the 6 featured rows (as this did until 2026-09-15) left the block with
             nothing to reveal, so it correctly suppressed the control. */ ?>
    <?= BlockDefaults::renderHireVa4Testimonials(
        BlockDefaults::withFieldKeys('testimonials_block', ['show_more' => 1, 'visible_count' => 6, 'tone' => 'light']),
        BlockDefaults::testimonials(),
    ) ?>
</div>
<!-- /wp:group -->
