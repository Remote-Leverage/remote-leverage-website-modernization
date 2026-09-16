<?php
/**
 * Shared template for the paid-ad variants of production's /hire-va-4/.
 *
 * Not a block pattern. It lives outside the theme's patterns/ directory because WordPress
 * core scans that tree recursively and would reject a headerless file.
 *
 * Included by patterns/hire-va-1st-month-free.php and patterns/hire-va-6.php, each of which
 * defines $variant before including this file.
 *
 * A heading-level diff of /hire-va-4/, /hire-va-1st-month-free/ and /hire-va-6/ on production
 * (2026-09-15) shows the same nine-section skeleton with the same blocks; the three pages differ
 * only in the hero headline and in how the hiring-process band is titled and worded. Everything
 * else therefore renders from the existing blocks with their shipped defaults, so a fix to a
 * block reaches all three pages.
 *
 * $variant keys:
 *   headline        Hero H1, HTML allowed (<br> line breaks as production sets them).
 *   subheadline     Optional HTML paragraph under the H1 (only /hire-va-6/ has one).
 *   process_title   Hiring-process H2, HTML allowed.
 *   process_intro   Paragraph beside that H2 (production lays the two out side by side).
 *   process_steps   Three rows of num / title / desc for acf/process-steps.
 *
 * Measured off production with getComputedStyle (2026-09-15):
 *   process H2      42px / 48px, 700, -1.44px tracking, #000
 *   process intro   20px / 25px, 400, -0.6px tracking, #000, 50/50 split, 50px gap
 *   hero subhead    16px / 24px, 400, #fff
 *   section ground  rgb(244, 246, 252) — the theme's bg-light
 */
use App\Support\BlockDefaults;

if (! isset($variant) || ! is_array($variant)) {
    return;
}

// The hero block renders {!! $headline !!} straight into the H1 and has no subheadline field,
// so /hire-va-6/'s sub-line rides along inside the heading. See the note in the pattern files.
$headline = $variant['headline'];

if (! empty($variant['subheadline'])) {
    $headline .= '<span class="block mt-5 text-base font-normal leading-6 tracking-normal">'
        .$variant['subheadline']
        .'</span>';
}

// Mirrors patterns/hire-va-4-hero.php — same badge, booking card, and two-step isolated form.
$hero = [
    'badge_text' => "2,000+ businesses we've helped hire",
    'headline' => $headline,
    'booking_title' => 'Book a Free 15-Minute Consultation',
    'booking_subtitle' => '',
    'enable_isolated_fields' => '1',
    'hide_profile_header' => '1',
    'hide_progress_bar' => '1',
    'form_button_text' => 'Find me an Assistant',
    // Production stacks the booking card above the checklist on phones for this family
    // (measured at 390px on /hire-va-6/ and /hire-va-1st-month-free/); the checklist-first
    // default would push the form's submit button below the fold.
    'mobile_order' => 'form-first',
    'isolated_steps' => [
        ['step_label' => 'Email', 'step_fields' => ['email']],
        ['step_label' => 'Complete First Step', 'step_fields' => ['monthly_revenue', 'name', 'phone', 'consent']],
    ],
];

$heroData = BlockDefaults::withFieldKeys('hire_va_hero', $hero);

// Production's booking footer runs a numbered three-item list under the headline, each item
// behind a 30px #F90066 circle (identical on /hire-va-4/, /hire-va-1st-month-free/ and
// /hire-va-6/). acf/booking-footer renders its `description` field raw, so the list goes in
// there. Rows are <span>s, not <li>/<div>: the view wraps description in a <p>, and only a
// block-level tag would close that <p> early and break the layout.
$footerSteps = [
    'Pick a time on the next screen',
    '15-min Zoom call to map out the role and budget',
    'Interview pre-vetted candidates within 72 hrs',
];

$footerDescription = implode('', array_map(
    static fn (int $i, string $text): string => '<span class="flex items-center gap-4 mb-4 last:mb-0">'
        .'<span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-full bg-[#F90066] text-base font-bold leading-none text-white">'.$i.'</span>'
        .'<span>'.$text.'</span>'
        .'</span>',
    range(1, count($footerSteps)),
    $footerSteps,
));

?>
<!-- ============ 1. HERO ============ -->
<?= BlockDefaults::patternBlock('hire-va-hero', $heroData, ['align' => 'full']) ?>

<!-- ============ 2. THE ROLES THAT BUY BACK YOUR TIME ============ -->
<!-- wp:acf/roles-grid {"name":"acf/roles-grid","data":{},"align":"full","mode":"preview"} /-->

<!-- ============ 3. WHY HIRE THROUGH REMOTE LEVERAGE? ============ -->
<!-- wp:acf/why-hire {"name":"acf/why-hire","data":{},"align":"full","mode":"preview"} /-->

<!-- ============ 4. CLIENT REVIEWS ============ -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"},"spacing":{"margin":{"bottom":"1rem"}}},"fontSize":"huge"} -->
    <h2 class="wp-block-heading has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.08;margin-bottom:1rem">Client Reviews</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"style":{"typography":{"fontSize":"0.95rem"},"spacing":{"margin":{"bottom":"3rem"}}},"textColor":"text-muted"} -->
    <p class="has-text-muted-color has-text-color" style="font-size:0.95rem;margin-bottom:3rem">Don't just take our word for it, hear from business owners who've hired through Remote Leverage. See why quality makes all the difference!</p>
    <!-- /wp:paragraph -->

    <?php /* Production's wall here is the full 15 reviews collapsed to 6, with a SHOW MORE pill
             that expands in place — the same set and order as BlockDefaults::testimonials().
             Feeding only the 6 featured rows (as this did until 2026-09-15) left the block with
             nothing to reveal, so it correctly suppressed the control. */ ?>
    <?= BlockDefaults::renderHireVa4Testimonials(
        BlockDefaults::withFieldKeys('testimonials_block', ['show_more' => 1, 'visible_count' => 6, 'tone' => 'light']),
        BlockDefaults::testimonials(),
    ) ?>
</div>
<!-- /wp:group -->

<!-- ============ 5. 12-MONTH REPLACEMENT GUARANTEE ============ -->
<!-- wp:acf/guarantee-card {"name":"acf/guarantee-card","data":{},"align":"full","mode":"preview"} /-->

<!-- ============ 6. HIRING PROCESS (the one section that differs per variant) ============ -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"50px"},"margin":{"bottom":"3rem"}}}} -->
    <div class="wp-block-columns" style="margin-bottom:3rem">
        <!-- wp:column -->
        <div class="wp-block-column">
            <!-- wp:heading {"level":2} -->
            <h2 class="wp-block-heading"><?= $variant['process_title'] ?></h2>
            <!-- /wp:heading -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column -->
        <div class="wp-block-column">
            <!-- wp:paragraph {"style":{"typography":{"fontSize":"1.25rem","lineHeight":"1.25","letterSpacing":"-0.6px"},"spacing":{"margin":{"top":"0"}}},"textColor":"text-muted"} -->
            <p class="has-text-muted-color has-text-color" style="font-size:1.25rem;letter-spacing:-0.6px;line-height:1.25;margin-top:0"><?= $variant['process_intro'] ?></p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->

    <?= BlockDefaults::renderHireVa4ProcessSteps([], $variant['process_steps']) ?>
</div>
<!-- /wp:group -->

<!-- ============ 7. SKIP THE HIRING HEADACHE ============ -->
<!-- wp:acf/comparison-matrix {"name":"acf/comparison-matrix","data":{},"align":"full","mode":"preview"} /-->

<!-- ============ 8. FREQUENTLY ASKED QUESTIONS ============ -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <?= BlockDefaults::renderHireVa4Faq() ?>
</div>
<!-- /wp:group -->

<!-- ============ 9. BOOKING FUNNEL FOOTER ============ -->
<?= BlockDefaults::patternBlock(
    'booking-footer',
    BlockDefaults::withFieldKeys('booking_footer', [
        'headline' => 'Smarter support starts here. Flexible, skilled, and ready to go.',
        'description' => $footerDescription,
    ]),
    ['align' => 'full'],
) ?>
