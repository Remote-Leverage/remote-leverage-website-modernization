<?php

use App\Support\BlockDefaults;

/**
 * Title: Client Logos Marquee
 * Slug: remote-leverage/client-logos
 * Categories: remote-leverage
 * Description: Infinite logo ticker of verified partners and clients.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"2rem","bottom":"2.5rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:2rem;padding-bottom:2.5rem">
    <?php echo BlockDefaults::renderClientLogosMarquee(); ?>
</div>
<!-- /wp:group -->
