<?php

use App\Support\BlockDefaults;

/**
 * Title: Footer - Booking Funnel Footer
 * Slug: remote-leverage/hire-va-4-booking-footer
 * Categories: remote-leverage
 * Description: Booking funnel footer carrying production's headline, with the embedded scheduling wizard.
 */

/*
 * Mobile-only trim to docs/design/hire-va-4.png. acf/booking-footer renders on twelve patterns
 * and the wizard inside it on more, so nothing here touches the block or the Livewire view —
 * it is one `max-width: 639.98px` query that BlockDesign scopes to this instance's generated
 * `.rl-d-` class, leaving every other page and every desktop width untouched.
 *
 * Measured off the comp:
 *
 *  - Ground. The comp paints a flat #250D4A across the whole band; the block's map image is
 *    sampled at rgb(44,23,85) where the comp is rgb(37,13,74). The image is an inline style
 *    attribute, so unsetting it needs !important — nothing else here does.
 *  - Gutter. 20px each side, against the block's `px-4`. That also moves the wizard card to
 *    x 20..354, which is where the comp draws it.
 *  - Headline. Comp cap height 21px against our 28px at text-4xl (ink area ratio 0.58, linear
 *    0.76) — 27px/32px. Gap to the paragraph is 16px there, not the block's 24px. The comp
 *    breaks it "Smarter support starts / here. Flexible, skilled, / and ready to go.", which
 *    no font size reproduces on its own: "and" still fits on line two at every size that keeps
 *    the cap height right, so the comp's own text box must be narrower than the band. 285px is
 *    that box — wider than its longest line (268px), narrower than line two carrying "and".
 *  - Description. Ink area ratio 0.91 against our 14px at text-lg (linear 0.96) — 13.4px/20px. The last
 *    decimal is load-bearing: at 13.5px the second line misses its final word by under a pixel
 *    and the paragraph runs to four lines where the comp runs to three.
 *  - Wizard card. The comp's pill is 275px wide inside a 335px card, i.e. 30px of card padding
 *    against the block's `p-6`; setting the padding is what sizes the pill, since the pill is
 *    full-width inside it. Pill is 50px tall and #F90066 — sampled exactly, the same magenta
 *    the page's other CTAs use — against the wizard's 48px #E0E5EC.
 */
$bookingCss = <<<'CSS'
@media (max-width: 639.98px) {
    selector {
        background-image: none !important;
        background-color: #250D4A;
    }

    & > div:first-child {
        padding-top: 40px;
        padding-bottom: 40px;
    }

    & > div:first-child > div:first-child {
        padding-left: 20px;
        padding-right: 20px;
    }

    h2 {
        font-size: 27px;
        line-height: 32px;
        max-width: 285px;
        margin-bottom: 16px;
    }

    h2 + p {
        font-size: 13.4px;
        line-height: 20px;
    }

    .backdrop-blur-md {
        padding: 30px;
    }

    button.w-full.rounded-full {
        min-height: 50px;
        padding-top: 0;
        padding-bottom: 0;
        background-color: #F90066;
        color: #ffffff;
    }

    button.w-full.rounded-full:hover {
        background-color: #D60057;
        color: #ffffff;
    }
}
CSS;
?>
<?= BlockDefaults::patternBlock('booking-footer', [
    'headline' => 'Smarter support starts here. Flexible, skilled, and ready to go.',
    '_headline' => 'field_booking_footer_headline',
    'rl_design_css' => $bookingCss,
], ['align' => 'full']) ?>
