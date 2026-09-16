<?php

use App\Support\BlockDefaults;

/**
 * Title: Homepage Client Logos
 * Slug: remote-leverage/homepage-client-logos
 * Categories: remote-leverage
 * Description: The client logo marquee that sits directly beneath the homepage hero, on the same pale ground.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"0","bottom":"2.5rem"}}},"backgroundColor":"bg-light","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:0;padding-bottom:2.5rem">
    <?= BlockDefaults::renderClientLogosMarquee() ?>
</div>
<!-- /wp:group -->
