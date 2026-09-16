<?php

use App\Support\BlockDefaults;

/**
 * Title: Homepage Hero - Latin American Virtual Assistants
 * Slug: remote-leverage/homepage-hero
 * Categories: remote-leverage
 * Description: The 2026 homepage's first screen: rating, headline, checklist, CTA, floating talent cards, and the client-logo strip.
 *
 * Hero and logo strip are one unit, not two sections. The comp runs them together on the same
 * #F4F6FC ground with no seam, and per direction on 2026-09-16 the pair fills the first
 * viewport. `min-h` rather than `h`: on a 1366x768 laptop the content is taller than the screen,
 * and a hard height would crop the CTA rather than let the page scroll.
 */
?>
<!-- wp:group {"align":"full","className":"rl-home-screen-1 rl-screen flex flex-col","backgroundColor":"bg-light","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull rl-home-screen-1 rl-screen flex flex-col has-bg-light-background-color has-background">
    <!-- wp:acf/home-hero {"name":"acf/home-hero","data":{},"align":"full","mode":"preview"} /-->

    <div class="w-full pb-10">
        <?= BlockDefaults::renderClientLogosMarquee() ?>
    </div>
</div>
<!-- /wp:group -->
