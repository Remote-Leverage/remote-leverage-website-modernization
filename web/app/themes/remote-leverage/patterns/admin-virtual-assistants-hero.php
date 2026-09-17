<?php

use App\Support\BlockDefaults;

/**
 * Title: Admin VA Hero - Administrative Virtual Assistants $6-$10 Per Hour
 * Slug: remote-leverage/admin-virtual-assistants-hero
 * Categories: remote-leverage
 * Description: The role page's first screen: headline, subtitle, checklist, CTA and the square hero composite, over the client-logo strip.
 *
 * Same unit as the homepage's first screen — hero and logo strip share one #F4F6FC ground with
 * no seam and fill the first viewport together — but with acf/home-hero in its `image` media
 * variant rather than the talent-card fan.
 *
 * Copy is the desktop comp's. The mobile comp reads "from Latin America & The Philippines";
 * desktop says "from Latin America", and per CLAUDE.md desktop wins where the two disagree.
 * That is the block's own default subtitle word for word, so it is inherited rather than
 * restated here — if the Philippines claim is ever the one that ships, it changes in one place.
 */

/*
 * Column-major, unlike BlockDefaults::homeHeroChecklist(). The role comps read straight down the
 * left desktop column and then down the right, and their mobile column repeats that same order —
 * so this is already the mobile order, and the block's `image` variant flows the desktop grid
 * down each column in turn to match. The homepage's own list is interleaved for the opposite
 * reason and is deliberately not reused.
 */
$checklist = array_map(fn ($item) => ['item' => $item], [
    'No Contracts, No Ongoing Fees',
    '12-Month Replacement Guarantee',
    'Hire Direct, No Middleman',
    'Interview Before You Hire',
    '30% Discount on Future Hires',
    'Interview in 48 Hours',
]);

$data = BlockDefaults::withFieldKeys('home_hero', [
    'media' => 'image',
    'hero_image' => BlockDefaults::pageImg('admin-virtual-assistants', 'hero.webp'),
    'show_rating' => 0,
    'headline' => "Administrative\nVirtual Assistants",
    'headline_accent' => '$6-$10 Per Hour',
    // All three lines are the same ink in the comp; the homepage sets the third in purple.
    'headline_accent_tone' => 'inherit',
    'tick_tone' => 'emerald',
    'cta_text' => 'BOOK A CONSULTATION',
]);
?>
<!-- wp:group {"align":"full","className":"rl-home-screen-1 rl-screen flex flex-col","backgroundColor":"bg-light","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull rl-home-screen-1 rl-screen flex flex-col has-bg-light-background-color has-background">
    <?= BlockDefaults::renderBlockWithRepeater('home-hero', 'checklist', 'field_home_hero_checklist', $checklist, $data, ['align' => 'full']) ?>

    <div class="w-full pb-10">
        <?= BlockDefaults::renderClientLogosMarquee() ?>
    </div>
</div>
<!-- /wp:group -->
