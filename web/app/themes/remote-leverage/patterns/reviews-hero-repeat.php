<?php

use App\Support\BlockDefaults;

/**
 * Title: Reviews - Pricing Funnel Intro
 * Slug: remote-leverage/reviews-hero-repeat
 * Categories: remote-leverage
 * Description: Production's dark "$6-$10 Per Hour" band on /reviews/ — centred heading, CTA, a static 4-across talent grid and the six trust badges, all on one #250D4A section.
 */

/*
 * Rebuilt 2026-09-16 against production after stakeholder QA flagged this section.
 * It previously rendered light-background, left-aligned, with a scrolling talent
 * marquee and the badges in a separate light band below. Production runs one dark
 * band with everything centred and the talent cards as a static 4-across grid.
 *
 * Every value below was read off production with getComputedStyle, not estimated:
 *   band        #250D4A        heading  48px / 53px, #FFF, centred
 *   paragraph   20px, #FFF     CTA      #8A2BE2, 17px, 50px radius
 *   badges      15px, #DDE2F6, left-aligned, 1px left rule as the divider
 *
 * This pattern and the badges are used by /reviews/ only, so folding them into one
 * band affects nothing else.
 */
?>
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull" style="background-color:#250D4A">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-14">

        <h2 class="font-display text-center text-[34px] leading-[40px] sm:text-[48px] sm:leading-[53px] font-bold tracking-[-0.03em] text-white mb-5">
            Most Virtual Assistants $6-$10<br class="hidden sm:inline"> Per Hour
        </h2>

        <p class="text-center text-[17px] sm:text-[20px] leading-[1.5] text-white max-w-[680px] mx-auto mb-8">
            Hire English Speaking Virtual Assistants From Latin America &amp; The Philippines <strong>for 70% Less Than U.S. Employees</strong>.
        </p>

        <div class="flex justify-center mb-12">
            <a href="#booking-footer"
                class="inline-flex items-center gap-3 rounded-pill bg-[#8A2BE2] px-9 py-4 font-display text-[17px] font-bold uppercase tracking-wide text-white transition hover:opacity-90">
                <span>Book a Consultation</span>
                <?php include get_theme_file_path('resources/patterns/partials/circle-arrow.php'); ?>
            </a>
        </div>

        <?= BlockDefaults::renderTalentGrid([
            'layout' => 'grid',
            '_layout' => 'field_talent_grid_block_layout',
            'aspect' => 'square',
            '_aspect' => 'field_talent_grid_block_aspect',
        ]) ?>

        <?php // Six trust badges, inside the dark band as on production.?>
        <div class="mt-14 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6">
            <?php foreach ([
                'No Contracts',
                'Interview<br>Before You Hire',
                'No<br>Recurring Fees',
                '30% Discount<br>on Future Hires',
                'Hire Direct –<br>No Middleman',
                'Hire Within<br>72 Hours',
            ] as $badge) { ?>
                <div class="border-l border-[#DDE2F6]/60 px-5 py-2 text-[15px] leading-[1.35] text-[#DDE2F6]">
                    <?= $badge ?>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<!-- /wp:group -->
