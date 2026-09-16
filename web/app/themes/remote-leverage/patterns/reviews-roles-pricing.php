<?php

use App\Support\BlockDefaults;

/**
 * Title: Reviews - Virtual Assistant Roles Pricing Grid
 * Slug: remote-leverage/reviews-roles-pricing
 * Categories: remote-leverage
 * Description: 8-card pricing grid (Administrative, Sales, Marketing, Legal, etc.) with task checklists and tool logos, in production's 2-up photo-left card shape.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"5rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:5rem">
    <?php // Production's /reviews/ runs these 2-up with the photo on the left, not the 4-up
          // photo-on-top grid the other pages use. Measured 2026-09-16: the section is
          // 2,812px tall on production against 1,661px here before this was set.?>
    <?= BlockDefaults::renderRolesPricingGrid(['variant' => 'photo-split', 'columns' => '2']) ?>
</div>
<!-- /wp:group -->
