<?php

use App\Support\BlockDefaults;

/**
 * Title: Reviews - Virtual Assistant Roles Pricing Grid
 * Slug: remote-leverage/reviews-roles-pricing
 * Categories: remote-leverage
 * Description: 8-card uniform pricing grid (Administrative, Sales, Marketing, Legal, etc.) with task checklists and tool logos.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"5rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:5rem">
    <?= BlockDefaults::renderRolesPricingGrid() ?>
</div>
<!-- /wp:group -->
