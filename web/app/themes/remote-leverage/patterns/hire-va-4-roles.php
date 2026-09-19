<?php

use App\Support\BlockDefaults;

/**
 * Title: Roles - The Roles That Buy Back Your Time
 * Slug: remote-leverage/hire-va-4-roles
 * Categories: remote-leverage
 * Description: 8 role specialty cards showcasing administrative, sales, marketing, and custom placements.
 */

/*
 * Mobile treatment for this instance only, fitted to docs/design/hire-va-4.png (375px wide).
 *
 * This replaces the shared BlockDefaults::hireVa4Mobile('roles') payload rather than extending
 * it — the section's own band padding is carried over verbatim as the first rule — because the
 * block renders on /hire-va-6/, /hire-va-1st-month-free/, the homepage and
 * resources/patterns/hire-va-campaign.php, and none of those were measured against this comp.
 * Everything below sits inside `max-width: 639.98px`, so >= 640px (and therefore every desktop
 * width) is byte-identical to what the block rendered before.
 *
 * Measurements are comp pixels at 375px, taken with docs/design/tools/:
 *
 *  - Gutter. Cards run x 20..354 on every row of the comp; the theme's `px-4` put them at
 *    16..358. 20px each side, on the section's own container.
 *  - Heading. Cap height of the `T` in "The" is 20px in the comp against 22px at the block's
 *    `text-3xl`, so 27px (Inter's cap ratio is 0.733 and 27 x 0.733 = 19.8). Baseline-to-
 *    baseline is 32px. The comp breaks after "Buy", which no width below 375 produces on its
 *    own: "The Roles That Buy Back" measures 297px at 27px and the container offers 335. The
 *    max-width forces the comp's break without a <br> that desktop would also inherit.
 *  - Eyebrow. The comp has no white pill: three avatars with white rings measuring 89x42 as a
 *    cluster, a 27px verified badge, then "2.5K+" over "pre-vetted candidates" on a 14px
 *    baseline pitch, left-aligned, the whole cluster centred. The block's own markup is reused:
 *    the same Group-207 strip at 51px (its native 100x51, which measures back as 89x42 of ink
 *    because of its transparent margin), the svg the view emits `hidden`, and the pre-split
 *    copy of the eyebrow text the view also emits `hidden`. No field, so no other page can
 *    pick it up.
 *  - Card titles. The comp's capital `A` in "Administrative" is 19x20 with 171 ink pixels; the
 *    block's `font-bold` at 24px gives 19x18 with 176. Taller, same width, *less* ink is a
 *    larger size at a lighter weight. 27px/500 reproduces the ink box exactly at 19x20/162.
 *  - Body copy. Comp ink runs are 15px tall on a 20px baseline pitch against 14px on 23px
 *    (`text-sm leading-relaxed`) and 12px in the horizontal cards. One size, 15px/20px,
 *    everywhere, and the per-card `max-w-*` clamps dropped so the measured 279px of card
 *    interior is what the copy wraps against.
 *  - Card rhythm. 10px between cards in the comp against the grid's `gap-6` (24px), 28px of
 *    card padding against `p-6` (24px), and a 209px floor on the four horizontal cards against
 *    the block's `min-h-[165px]`.
 *  - Tall cards. All four are 423-427px in the comp; the block's own floors gave 439/377/441/
 *    409 with the cutouts either floating short of the card or stranded by `justify-between`.
 *    The floors are reset to the comp's heights and the two photos grown to fill them. The
 *    support cutout is the odd one: it is a 361x325 landscape source, so it was width-capped
 *    at the 277px interior and rendered a third too small however tall its box was. It gets
 *    its natural width and is clipped by the card's own `overflow-hidden`, which is what puts
 *    its shoulders at the comp's 246px against 188px before.
 */
$css = "@media (max-width: 639.98px) {\n"
    ."    selector { padding-top: 32px; padding-bottom: 40px }\n"
    ."    selector > div { padding-left: 20px; padding-right: 20px }\n"
    ."    h2 { font-size: 27px; line-height: 32px; max-width: 260px; margin-left: auto; margin-right: auto }\n"
    ."    .mb-12 { margin-bottom: 29px }\n"
    ."    h2 + div { display: flex; width: fit-content; margin: 12px auto 0; padding: 0; gap: 0; background: none; border: 0; box-shadow: none }\n"
    ."    h2 + div > img { height: 51px }\n"
    ."    h2 + div > svg { display: block; width: 32px; height: 32px }\n"
    ."    h2 + div > span:first-of-type { display: none }\n"
    ."    h2 + div > span:last-of-type { display: block; margin-left: 8px; text-align: left; font-size: 16px; line-height: 15px; font-weight: 600; letter-spacing: 0 }\n"
    ."    h2 + div > span:last-of-type > span { display: block }\n"
    ."    .gap-6 { gap: 10px }\n"
    ."    .rounded-card { padding: 28px }\n"
    ."    .items-center.justify-between { min-height: 209px }\n"
    ."    h3 { font-size: 27px; font-weight: 500; line-height: 30px }\n"
    ."    p { font-size: 15px; line-height: 20px; max-width: none }\n"
    ."    .overflow-hidden { min-height: 427px }\n"
    ."    .overflow-hidden .h-64 { height: 301px }\n"
    ."    .overflow-hidden.bg-roles-lavender .h-64 { width: 359px; max-width: none; height: auto; flex: none; margin-top: -27px }\n"
    ."    .bg-roles-sky { min-height: 427px }\n"
    ."    .rounded-card.bg-white { min-height: 423px }\n"
    ."    .max-h-52 { max-height: 230px }\n"
    .'}';
?>
<?= BlockDefaults::patternBlock('roles-grid', ['rl_design_css' => $css], ['align' => 'full']) ?>
