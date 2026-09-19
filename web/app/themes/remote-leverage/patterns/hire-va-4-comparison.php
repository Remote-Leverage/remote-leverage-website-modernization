<?php

use App\Support\BlockDefaults;

/**
 * Title: Comparison - Skip the Hiring Headache
 * Slug: remote-leverage/hire-va-4-comparison
 * Categories: remote-leverage
 * Description: Side-by-side comparison of hiring on your own vs hiring with Remote Leverage with 70% lower cost proposition.
 */

/*
 * Mobile parity with docs/design/hire-va-4.png (375px comp, band at y 9598..10187).
 *
 * This replaces BlockDefaults::hireVa4Mobile('band') — its only rule (the 40px top/bottom
 * padding) is kept verbatim as the first declaration below. Everything here is per-instance
 * CSS rather than Blade or Tailwind changes because acf/comparison-matrix also renders on
 * patterns/homepage-headache.php and resources/patterns/hire-va-campaign.php, and the block
 * has no field to gate a second mobile treatment behind.
 *
 * Every rule sits inside the max-width:639.98px query, so >=1024px is untouched.
 *
 * Sizes were taken off the comp by cap-height, not by line width: the comp is set in a
 * narrower grotesque than the theme's Inter, so a comp line is ~10% shorter than the same
 * string in Inter at the same size. Where the two disagreed, cap height won.
 *
 *  - h2 "Skip the Hiring Headache": comp 'S' ink 20x21 vs ours 24x24 at 30px -> 26px.
 *  - h3 card titles: comp 'H' ink 17x15 vs ours 25x22 at 30px -> 20px. At 20px
 *    "Hiring with Remote Leverage" is 253px of ink and fits the 299px card box on one line,
 *    which is the headline defect this band had.
 *  - Subheadline and body copy are NOT resized: the comp's cap heights there (12px and 11px)
 *    are identical to ours at 16px and 15px. They only read smaller because of the face.
 *  - Card padding is 18px at the sides rather than the comp's apparent ~30px. That is
 *    deliberate: 18px gives an inner box of 299px, which is what reproduces the comp's line
 *    breaks in Inter ("...screening, and / interviewing. Payroll, taxes, and compliance /
 *    all on you."). The text is centred, so the side padding is invisible except through the
 *    wrap, and the wrap is the thing the comp actually shows.
 *  - The left card's two body lines are one flowing paragraph in the comp (its second line
 *    starts "interviewing. Payroll,"), the right card's two are not. Hence display:inline on
 *    the first column only.
 *  - Arrow: the comp uses the same Union.svg head at the same scale with a 44px shorter
 *    shaft (60px tall, not 104px). clip-path trims the tail in the element's own coordinate
 *    space, before rotate-90 is applied; translate re-centres what is left.
 *  - CTA: comp pill is 279x50 with no glow and a 24px chevron. Fixed box + justify-content
 *    rather than padding maths, so the pill is exactly 279x50 whatever the label measures.
 *    letter-spacing is dropped to normal (the comp's label is not tracked out) which also
 *    pulls the label in to 22px from the pill edge, against the comp's 28px.
 */
$css = <<<'CSS'
@media (max-width: 639.98px) {
    selector { padding-top: 40px; padding-bottom: 40px }
    selector > div { padding-left: 20px; padding-right: 20px }
    selector > div > div:first-child { margin-bottom: 25px }
    h2 { font-size: 26px; margin-bottom: 10px }
    .bg-white { padding: 40px 18px 32px; margin-bottom: 26px }
    .bg-white h3 { font-size: 20px; line-height: 22.8px; margin-bottom: 10px }
    .bg-white .grid { gap: 10px }
    .bg-white .grid > div:first-child { font-size: 15px; line-height: 20px }
    .bg-white .grid > div:first-child p { display: inline }
    .bg-white .grid > div:nth-child(2) { height: 60px; margin-bottom: 7px }
    .bg-white .grid > div:nth-child(2) img { clip-path: inset(0 0 0 44px); translate: 0 -22px }
    a { width: 279px; height: 50px; padding: 0; justify-content: center; letter-spacing: normal; box-shadow: none }
    a svg { width: 26px; height: 26px }
}
CSS;
?>
<?= BlockDefaults::patternBlock('comparison-matrix', ['rl_design_css' => $css], ['align' => 'full']) ?>
