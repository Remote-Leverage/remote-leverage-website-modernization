<?php

use App\Support\BlockDefaults;

/**
 * Title: Reviews - Frequently Asked Questions
 * Slug: remote-leverage/reviews-faq
 * Categories: remote-leverage
 * Description: Standalone accordion FAQ (no testimonials grid — the /reviews/ page already has its own full testimonial grid above).
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"2rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:2rem;padding-bottom:6rem">
    <?= BlockDefaults::renderAccordionFaq() ?>
</div>
<!-- /wp:group -->
