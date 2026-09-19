<?php

use App\Support\BlockDefaults;

/**
 * Title: Process Steps - The Bridge
 * Slug: remote-leverage/hire-va-4-process-steps
 * Categories: remote-leverage
 * Description: Production's "Our Hiring Process" — 3-step sequence from consultation to hire.
 */

/*
 * Mobile treatment, measured off docs/design/hire-va-4.png (375 x 13204).
 *
 * The comp sets each step as a white card on the grey band: left-aligned, an 84px pale numeral
 * top-left, and a hairline rule to its right carrying a filled dot that advances one step per
 * card. That arrangement already exists in the block as the `cards` variant — the 2026
 * homepage's treatment, see `.rl-process-container--cards` in resources/css/app.css — so the
 * variant is selected here rather than a second layout being written. Everything below is the
 * delta between that variant and the comp, kept per-instance because the same block ships on
 * /hire-va-6/, /hire-va-1st-month-free/ and resources/patterns/hire-va-campaign.php.
 *
 * Numbers, all card-relative and taken from the comp at 375px (card box x 20..354, y 8464..8810):
 *
 *   card          20px gutters each side, 10px between cards, 10px radius
 *   numeral       ink top +37, ink left +23  -> 84px line box at padding 29/20
 *   rule + dot    centre line at +51, rule x 197..310 abs, dot 25px, 6px white core
 *                 dot left edge per step: 197 / 242 / 309 abs — i.e. rule start, rule centre,
 *                 rule end. Reproduced with :nth-child() rather than a field, because the
 *                 position is the step index and nothing else.
 *   title         ink top +130, 32px line pitch
 *   description   ink top +202, 20px line pitch, measure ~228px
 *
 * Type: the comp's step title sets "Tell us your" 131px wide against our 127px at 24px, and its
 * ink box is 21px tall against our 19px. Those two only reconcile at 27px with -0.04em tracking
 * — 24px at looser tracking matches the width but not the height. 27px is also what the review
 * band above settled on for the same comp (`.rl-hv4-reviews` in app.css), so the band heading
 * and the step titles are one size here, which is what the comp shows: its "Compliance" (band
 * heading) is 141px against its "compliance" (step title) at 137px, the 4px being C against c.
 *
 * `.rl-process-container` is the block's own root, so it carries the generated `.rl-d-` scope
 * class itself and a rule written against the class name is scoped to `.rl-d-x .rl-process-
 * container` — a descendant that does not exist. That is why the 24px top margin this band has
 * carried since the mobile pass never applied and the block kept its 60px default. The rule is
 * left in place verbatim and the `&` rule below is what actually lands it, alongside the 4px
 * that takes the wrapper's 16px gutter to the comp's 20px without touching the wrapper.
 *
 * The variant's own rules run to 768px, so selecting it would also have restyled 640-768px,
 * where this page previously rendered the centred stack and where the comp says nothing. The
 * second media query below puts that range back on the block's base values exactly, so the
 * change is confined to the width the comp is drawn at. Desktop was never in range either way.
 *
 * The rule is drawn from `.rl-process-marker::before` rather than the variant's
 * `.rl-process-dot::before`, because a dot that moves along the rule cannot also be its anchor,
 * and a pseudo-element on the dot paints over the dot's own ring — the comp shows the rule
 * stopping at the ring, not crossing it. The marker's ::before paints under the dot (z-index 4).
 */
$processMobileCss = <<<'CSS'
@media (max-width: 639.98px) {
    selector { margin-bottom: -56px }
    .rl-process-container { margin-top: 24px }
    & { margin-top: 24px; padding-left: 4px; padding-right: 4px }
    .rl-process-grid { gap: 10px; grid-auto-rows: 1fr }
    .rl-process-item { padding: 29px 20px; border-radius: 10px; gap: 9px }
    .rl-process-marker { height: 87px }
    .rl-process-number { font-size: 84px; font-weight: 700; color: #DCCDE0; position: relative; top: -4px }
    .rl-process-marker::before {
        content: '';
        position: absolute;
        top: 23px;
        left: 53.2%;
        width: 38.6%;
        height: 2px;
        background-color: #C495F0;
        transform: translateY(-50%);
    }
    .rl-process-dot {
        top: 23px;
        left: 53.2%;
        right: auto;
        width: 25px;
        height: 25px;
        border: 0;
        transform: translate(0, -50%);
    }
    .rl-process-dot::before { content: none }
    .rl-process-item:nth-child(2) .rl-process-dot { left: calc(72.5% - 12.5px) }
    .rl-process-item:nth-child(3) .rl-process-dot { left: auto; right: 0 }
    .rl-process-title { font-size: 27px; font-weight: 700; line-height: 32px; letter-spacing: -0.04em; margin-left: 5px }
    .rl-process-description { font-size: 13.5px; line-height: 20px; max-width: 228px; margin-left: 9px }
}
@media (min-width: 640px) and (max-width: 768px) {
    .rl-process-grid { gap: 48px }
    .rl-process-item {
        align-items: center;
        text-align: center;
        gap: 16px;
        background-color: transparent;
        border-radius: 0;
        padding: 0;
    }
    .rl-process-marker { justify-content: center; width: 140px; height: 108px }
    .rl-process-number { font-size: 84px }
    .rl-process-dot { top: 50%; left: 50%; right: auto; transform: translate(-50%, -50%) }
    .rl-process-dot::before { content: none }
    .rl-process-description { max-width: 300px; margin: 0 auto }
}
CSS;
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem","left":"1rem","right":"1rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-right:1rem;padding-bottom:6rem;padding-left:1rem">
    <?php /* `max-sm:` only: every rule here is the comp's mobile layout and desktop keeps the
         48px margin, the 36px heading and the 16px side padding app.css gives a constrained
         group's direct children. `!` because those app.css rules are themselves !important. */ ?>
    <!-- wp:group {"className":"mb-12 max-sm:mb-0","layout":{"type":"constrained","contentSize":"768px","justifyContent":"left"}} -->
    <div class="wp-block-group mb-12 max-sm:mb-0">
        <!-- wp:heading {"level":2,"className":"max-sm:text-[27px]! max-sm:leading-[32px]! max-sm:mb-0! max-sm:px-1!","style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
        <h2 class="wp-block-heading max-sm:text-[27px]! max-sm:leading-[32px]! max-sm:mb-0! max-sm:px-1! has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.08">Our Hiring Process</h2>
        <!-- /wp:heading -->

        <!-- wp:paragraph {"className":"max-sm:text-[13.5px]! max-sm:leading-[20px]! max-sm:mt-1! max-sm:px-1!","style":{"typography":{"lineHeight":"1.6"},"spacing":{"margin":{"top":"1.25rem"}}},"textColor":"text-muted"} -->
        <p class="max-sm:text-[13.5px]! max-sm:leading-[20px]! max-sm:mt-1! max-sm:px-1! has-text-muted-color has-text-color" style="line-height:1.6;margin-top:1.25rem">Hire top-tier talent in just 48 hours. We screen thousands of applicants daily, so you only meet the top 1%. Move from open role to working team member in days, not weeks.</p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:group -->

    <?= BlockDefaults::renderHireVa4ProcessSteps(array_merge(
        BlockDefaults::withFieldKeys('process_steps_block', ['variant' => 'cards']),
        ['rl_design_css' => $processMobileCss],
    )) ?>
</div>
<!-- /wp:group -->
