<?php

use App\Support\BlockDefaults;

/**
 * Title: Guarantee - 12-Month Replacement Guarantee
 * Slug: remote-leverage/hire-va-4-guarantee
 * Categories: remote-leverage
 * Description: 12-month replacement guarantee card with radial gradient and 3D badge illustration.
 *
 * ## Why this instance carries its own CSS instead of `BlockDefaults::hireVa4Mobile('band')`
 *
 * The shared `band` payload is only the 40px top/bottom padding, which is kept verbatim below.
 * Everything else here is fitted to `docs/design/hire-va-4.png` (375px, mobile) and is specific
 * to this one instance: `acf/guarantee-card` also renders on the three comparison pages,
 * /ecommerce-virtual-assistant/, the hire-va campaign partial and the homepage, none of which
 * were drawn to this comp. Per-instance `rl_design_css` keeps it here; `App\Support\BlockDesign`
 * scopes every selector below under this block's generated `.rl-d-*` class.
 *
 * Every rule is inside `max-width: 639.98px`, so **desktop is byte-identical to before** — the
 * one exception is `cta_style`, see below.
 *
 * ## Measured off the comp (ink boxes, not estimates)
 *
 * | thing            | comp                                        | before                        |
 * | ---------------- | ------------------------------------------- | ----------------------------- |
 * | band background  | flat #250D4A                                | radial #8A2BE2 → #250D4A wash |
 * | gutter           | 20px                                        | 16px (`px-4`)                 |
 * | heading          | 26px/32px, 3 lines, #F4F6FC                 | 30px/33px, 2 lines, #FFF      |
 * | list icon        | bare 30px glyph, no tile                    | 24px glyph in a 44px tile     |
 * | list title       | 13px/22px regular, #E6DEF4                  | 18px/28px bold, #FFF          |
 * | list body        | 13px/22px, #E6DEF4                          | 14px/22.75px, white/80        |
 * | item gap         | 20px                                        | 28px (`space-y-7`)            |
 * | medals           | 255px wide, opaque edge ends 207px into band | 343px wide, ends at 292px     |
 * | CTA              | full-bleed #F90066 pill, 51px, no ring/glow | 306x52 #8A2BE2 + purple glow  |
 *
 * The background is set here rather than through the block's own `background => flat-midnight`
 * option because that option is not breakpoint-aware: it would flip desktop to flat #250D4A
 * too, and also swap the heading measure from `lg:max-w-[424px]` to `lg:max-w-[326px]`. There is
 * no desktop comp to justify either. It needs `!important` only because the view paints the
 * band with an inline `style` attribute, which no stylesheet rule can outrank; nothing else
 * below does, and nothing else below needs it.
 *
 * `cta_style => 'pill'` IS the block's own option, and it is the only one of these that also
 * lands on desktop — the circled-chevron glyph the comp draws lives in the pill partial and
 * cannot be reached from CSS. See the note on the field below.
 *
 * The 13px list type is paired with `letter-spacing: normal` and a 290px measure on the text
 * column, and both are load-bearing. `text-sm`/`text-lg` carry Tailwind's own negative tracking,
 * which the comp does not: left in, every list line renders ~3% narrow (the title "Not the right
 * fit?" comes out 92px against the comp's 99px ink). Reset it and the measure matters too — at
 * the full 295px the first item's "…in the first 6 months," fits by 1.8px and the item collapses
 * to two lines, where the comp breaks it after "6".
 *
 * One thing here is deliberately NOT matched. The comp draws a different medals lockup from the
 * one this theme ships: `Group-59-1-e1780958571501.png` is a symmetric fan with the largest medal
 * centred and front, the comp's is a left-to-right staircase with the largest at the right, and
 * it carries no baked-in halo. Its box is matched exactly — 253x207 of ink starting at x51, one
 * pixel off the comp on the bottom edge — but the artwork inside it cannot be without the comp's
 * source file.
 */
$css = <<<'CSS'
@media (max-width: 639.98px) {
    selector { padding-top: 40px; padding-bottom: 40px; background: #250D4A !important }
    & > div { padding-left: 20px; padding-right: 20px }
    & > div > div { row-gap: 0 }

    .order-1 { margin-top: -56px; margin-bottom: -20px }
    .order-1 img { max-width: 255px; margin-right: 20px }

    .order-2 { padding-top: 2px }
    h2 { font-size: 26px; line-height: 32px; max-width: 200px; margin-bottom: 30px; color: #F4F6FC }

    .space-y-7 { margin-bottom: 20px }
    .space-y-7 > * { margin-top: 0; margin-bottom: 0 }
    .space-y-7 > * + * { margin-top: 20px }
    .space-y-7 > div { gap: 10px }
    .space-y-7 .rounded-xl {
        width: 30px; height: 30px; margin-top: 0; padding: 0;
        background: none; border: 0; border-radius: 0; box-shadow: none;
    }
    .space-y-7 .rounded-xl img { width: 30px; height: 30px }
    .space-y-7 > div > div + div { max-width: 290px }
    h3, .space-y-7 p {
        font-size: 13px; line-height: 22px; font-weight: 400; letter-spacing: normal;
        margin-bottom: 0; color: #E6DEF4;
    }

    .order-2 a {
        display: flex; width: 100%; height: 51px; padding: 0; gap: 19px;
        font-size: 14px; letter-spacing: -0.75px; outline: none;
    }
    .order-2 a svg { width: 26px; height: 26px }
}
CSS;
?>
<?= BlockDefaults::patternBlock(
    'guarantee-card',
    array_merge(
        BlockDefaults::withFieldKeys('guarantee_card', ['cta_style' => 'pill']),
        ['rl_design_css' => $css],
    ),
    ['align' => 'full'],
) ?>
