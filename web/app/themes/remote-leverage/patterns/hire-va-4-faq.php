<?php

use App\Support\BlockDefaults;

/**
 * Title: FAQ - Frequently Asked Questions
 * Slug: remote-leverage/hire-va-4-faq
 * Categories: remote-leverage
 * Description: 2-column balanced FAQ accordion with 10 questions and automated Schema.org FAQPage structured data.
 */

/*
 * Mobile-only trim to docs/design/hire-va-4.png, as a per-instance `rl_design_css` payload
 * rather than block edits: acf/accordion-faq renders on other pages and its desktop layout is
 * not in scope, so every rule here sits inside one `max-width: 639.98px` query scoped by
 * BlockDesign to this instance's generated `.rl-d-` class.
 *
 * Measured off the comp rather than estimated:
 *
 *  - Gutter. The comp runs a flat 20px each side; the wrapping wp:group already supplies 16px
 *    and the block's own root adds `px-4`, so the root is trimmed to 4px for 20px total. The
 *    hairline under each row then spans x 20..354 exactly as the comp draws it.
 *  - Heading. Comp cap height 20px against our 27px at text-4xl, and an ink area ratio of
 *    0.59 (linear 0.77) — 27px/32px, wrapping to "Frequently Asked / Questions". The comp puts
 *    the first row's hairline box directly under the second line, so the 48px `mb-12` goes.
 *    The max-width is a wrap guard, not a size: at 27px the whole string measures 337px in a
 *    335px container, so without it the second line is a two-pixel coin flip. 250px is wider
 *    than the comp's longest heading line (206px) and narrower than the unwrapped string.
 *  - Rows. The comp's hairlines sit on a fixed 139px pitch whatever the question wraps to
 *    (138..140 across all ten), which no padding-based row can reproduce — one line and three
 *    lines are the same height there. So the button takes a 138px min-height and no vertical
 *    padding instead, and `items-center` (already on it) centres the question the way the comp
 *    does. That lands every divider within a few px of the comp's, which is the visible thing.
 *  - Question type. 19px/25px: the comp's "How do you get paid?" is 183px wide against our
 *    161px at 16.5px, and its cap height is 15px against our 12px.
 *  - Section close. The comp leaves 60px between the last hairline and the top of the booking
 *    band; the wp:group's 6rem less the -56px above leaves 41, hence the 19px on the root.
 *  - Chevron. A 24px ring with a 2px stroke, against our 28px/1px. The comp insets it 28px
 *    from the hairline's right end rather than aligning the two, hence the button's
 *    padding-right.
 */
$faqCss = <<<'CSS'
@media (max-width: 639.98px) {
    selector {
        margin-top: -40px;
        margin-bottom: -56px;
        padding-top: 6px;
        padding-left: 4px;
        padding-right: 4px;
        padding-bottom: 19px;
    }

    h2 {
        font-size: 27px;
        line-height: 32px;
        max-width: 250px;
        margin-bottom: 0;
    }

    .border-b > button {
        min-height: 138px;
        padding-top: 0;
        padding-bottom: 0;
        padding-right: 28px;
        gap: 16px;
    }

    .border-b > button > span {
        font-size: 19px;
        line-height: 25px;
        padding-right: 0;
    }

    .border-b > button > div {
        width: 24px;
        height: 24px;
        border-width: 2px;
    }

    .border-b > button > div > svg {
        width: 12px;
        height: 12px;
    }
}
CSS;
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem","left":"1rem","right":"1rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-right:1rem;padding-bottom:6rem;padding-left:1rem">
    <?= BlockDefaults::renderHireVa4Faq(['rl_design_css' => $faqCss]) ?>
</div>
<!-- /wp:group -->
