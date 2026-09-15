<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Signed Up
 * Slug: remote-leverage/signedup
 * Categories: remote-leverage
 * Description: Production's /signedup/ post-agreement confirmation page — next steps, trust badge and the client review wall (migrated 2026-09-15).
 */
$wrap = 'w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8';

// ── Confirmation band: headline, six numbered next steps, badge, closing line ──────────
echo BlockDefaults::renderNextStepsPanel();

// ── Client Reviews ─────────────────────────────────────────────────────────────────────
// Heading and subtitle are word-for-word production's, and match the sibling /vathankyou/
// page (page-vathankyou.blade.php), which already renders this wall with acf/testimonials.
?>
<!-- wp:html -->
<section class="w-full bg-surface-white pt-16 sm:pt-20 lg:pt-24 pb-6">
    <div class="<?= esc_attr($wrap) ?> text-center">
        <h2 class="font-display font-bold text-brand-navy text-[30px] leading-[36px] sm:text-[40px] sm:leading-[48px] tracking-[-1.2px]">
            Client Reviews
        </h2>
        <p class="mt-4 text-[16px] leading-[26px] text-black max-w-[720px] mx-auto">
            Don&rsquo;t just take our word for it &mdash; hear from business owners who&rsquo;ve hired through Remote Leverage. See why quality makes all the difference!
        </p>
    </div>
</section>
<!-- /wp:html -->
<?php

// acf/testimonials renders a bare `w-full` root and leaves the container to its caller
// (see page-vathankyou.blade.php). Without this wrapper the video grid runs edge-to-edge.
?>
<!-- wp:html -->
<div class="w-full bg-surface-white pb-8">
    <div class="<?= esc_attr($wrap) ?>">
        <?= BlockDefaults::renderSignedUpTestimonials() ?>
    </div>
</div>
<!-- /wp:html -->
<?php
