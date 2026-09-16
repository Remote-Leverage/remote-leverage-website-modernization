<?php

/**
 * Title: Reviews - Trust & Impact (centred)
 * Slug: remote-leverage/reviews-trust-and-impact
 * Categories: remote-leverage
 * Description: Production's /reviews/ treatment of the 2,000-businesses social proof — one centred column with the headline above the trust-stat graphic, rather than the homepage's two-column split.
 */

use App\Support\BlockDefaults;

/*
 * Added 2026-09-16. /reviews/ previously included the shared trust-and-impact
 * pattern, which is the homepage's two-column layout (stats left, headline right)
 * — the stakeholder's "looks like the homepage" report.
 *
 * Production's /reviews/ stacks it centred instead. Measured there on 2026-09-16:
 *   band      rgb(228, 236, 252)   headline  42px / 1.18, centred, 440px wide
 *   graphic   467 x 416, centred, below the headline
 *
 * A separate pattern rather than an edit to trust-and-impact.php, because
 * full-homepage-legacy.php still renders that one and must not move.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-map","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-map-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 text-center">

        <div class="flex justify-center gap-1 text-[#9F53E7] mb-4" role="img" aria-label="5 out of 5 stars">
            <?php for ($i = 0; $i < 5; $i++) { ?>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <?php } ?>
        </div>

        <h2 class="font-display mx-auto mb-8 max-w-[440px] text-[30px] sm:text-[42px] font-bold leading-[1.18] tracking-[-0.02em] text-black">
            We&rsquo;ve helped more than 2,000 businesses hire exceptional talent from Latin America, the Caribbean, and Europe.
        </h2>

        <div class="flex justify-center mb-10">
            <a href="#testimonials"
                class="inline-flex items-center gap-3 rounded-pill bg-[#8A2BE2] px-9 py-4 font-display text-[17px] font-bold uppercase tracking-wide text-white transition hover:opacity-90">
                <span>Watch Client Testimonials</span>
                <?php include get_theme_file_path('resources/patterns/partials/circle-arrow.php'); ?>
            </a>
        </div>

        <?php // The stat graphic sits below the headline here, not beside it. ?>
        <div class="mx-auto max-w-[760px]">
            <?php echo BlockDefaults::renderTrustStats(); ?>
        </div>
    </div>
</div>
<!-- /wp:group -->
