<?php
/**
 * Shared consultation landing-page template.
 *
 * Not a block pattern. It lives outside the theme's patterns/ directory because WordPress
 * core scans that tree recursively and would reject a headerless file.
 *
 * Included by the six thin config patterns below, each of which defines $consult before
 * including this file:
 *
 *   patterns/1monthonus-flp.php
 *   patterns/hire-va-email.php
 *   patterns/hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant.php
 *   patterns/hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-b.php
 *   patterns/hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-c.php
 *   patterns/hire-real-estate-virtual-assistants-flp.php
 *
 * All six are one page on production: the same ten sections in the same order, differing only
 * in the hero headline, the booking card's title, the tick colour, and — on the real-estate
 * page — the two section headers and the four role cards. Captured at 1440px on 2026-09-15 the
 * six measure 11664-11815px, i.e. within 150px of each other.
 *
 * Where an existing pattern already matches production exactly it is referenced rather than
 * recomposed: the logo strip, "Why Companies Choose", the 4-day process, the guarantee and the
 * booking footer. The rest are composed here from the same blocks those patterns use, because
 * production differs from the homepage in a way the pattern hard-codes:
 *
 *   World's Best Talent / Roles  — the real-estate page needs different headers.
 *   Roles                        — production centres the header; the pattern splits it 55/45.
 *   Trust & Impact               — production stacks it in one narrow centred column;
 *                                  the pattern splits it 48/52.
 *   Results + FAQ                — production runs a different intro line.
 *
 * No block is forked: every section renders the same acf/* block as its homepage counterpart.
 */
use App\Support\BlockDefaults;

if (! isset($consult) || ! is_array($consult)) {
    return;
}

// Everything five of the six pages share, transcribed from production. Only the real-estate
// page overrides the section headers and the role cards; the rest set just the hero keys.
$consult += [
    'hero_intro' => 'Recruiting agency helping businesses hire English speaking Virtual Assistants from&nbsp;<strong>Latin America</strong>&nbsp;for 70% less than U.S. Employees.',
    'hero_checklist' => [],
    'tick_color' => '#10B981',
    'talent_headline' => "World's Best Talent,<br>Hired Directly for You",
    'talent_intro' => 'You hire talent directly into your business – no subscriptions, no monthly fees, and no markups on salary. Just deep-vetted, skilled professionals helping you run operations, manage communication, and stay organized.',
    'roles_headline' => 'Beyond the "Virtual Assistant."',
    'roles_intro' => 'We specialize in globally sourcing English-fluent professionals<br>for roles that require high-level execution.',
    'roles_cards' => null,
];

$heroData = BlockDefaults::withFieldKeys('consult_landing_hero', [
    'headline' => $consult['hero_headline'],
    'intro' => $consult['hero_intro'],
    'tick_color' => $consult['tick_color'],
    'booking_title' => $consult['booking_title'],
]);

// A raw array override is silently ignored, so the tick list goes through the ACF encoder.
if (! empty($consult['hero_checklist'])) {
    BlockDefaults::encodeRepeater(
        'checklist',
        'field_consult_landing_hero_checklist',
        array_map(static fn (string $item): array => ['item' => $item], $consult['hero_checklist']),
        $heroData,
    );
}

$h2 = 'wp-block-heading has-huge-font-size';
$h2Style = 'letter-spacing:-0.03em;line-height:1.08';

?>
<!-- ============ HERO ============ -->
<?= BlockDefaults::patternBlock('consult-landing-hero', $heroData, ['align' => 'full']) ?>

<!-- ============ CLIENT LOGO STRIP ============ -->
<!-- wp:pattern {"slug":"remote-leverage/client-logos"} /-->

<!-- ============ WORLD'S BEST TALENT ============ -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <!-- wp:columns {"style":{"spacing":{"margin":{"bottom":"3.5rem"}}}} -->
    <div class="wp-block-columns" style="margin-bottom:3.5rem">
        <!-- wp:column {"width":"55%"} -->
        <div class="wp-block-column" style="flex-basis:55%">
            <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
            <h2 class="<?= $h2 ?>" style="<?= $h2Style ?>">
                <?= $consult['talent_headline'] ?>
            </h2>
            <!-- /wp:heading -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column {"width":"45%"} -->
        <div class="wp-block-column" style="flex-basis:45%">
            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"}}} -->
            <p style="line-height:1.6">
                <?= $consult['talent_intro'] ?>
            </p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->

    <?= BlockDefaults::renderFeatureCards('3') ?>
</div>
<!-- /wp:group -->

<!-- ============ ROLES ============ -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <!-- wp:heading {"level":2,"textAlign":"center","style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
    <h2 class="<?= $h2 ?> has-text-align-center" style="<?= $h2Style ?>;text-align:center">
        <?= $consult['roles_headline'] ?>
    </h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"align":"center","style":{"typography":{"lineHeight":"1.6"},"spacing":{"margin":{"top":"1rem","bottom":"3.5rem"}}}} -->
    <p class="has-text-align-center" style="margin-top:1rem;margin-bottom:3.5rem;line-height:1.6;text-align:center">
        <?= $consult['roles_intro'] ?>
    </p>
    <!-- /wp:paragraph -->

    <?= BlockDefaults::renderDepartmentCards([], $consult['roles_cards'] ?? null) ?>
</div>
<!-- /wp:group -->

<!-- ============ TRUST & IMPACT ============ -->
<?php /* Composed rather than referencing patterns/trust-and-impact.php: that pattern splits the
         band 48/52 (stat cards left, heading right) the way the homepage shows it, but on this
         page family production stacks the whole thing in one narrow centred column. Same
         acf/trust-stats block, same copy — only the pattern-level layout differs. */ ?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"4rem","bottom":"4rem"}}},"backgroundColor":"bg-map","layout":{"type":"constrained","contentSize":"480px"}} -->
<div class="wp-block-group alignfull has-bg-map-background-color has-background" style="padding-top:4rem;padding-bottom:4rem">
    <!-- wp:html -->
    <div class="flex justify-center gap-1 text-star-purple mb-4" role="img" aria-label="5 out of 5 stars">
        <?php for ($i = 0; $i < 5; $i++) { ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        <?php } ?>
    </div>
    <!-- /wp:html -->

    <!-- wp:heading {"level":2,"textAlign":"center","style":{"typography":{"lineHeight":"1.18","letterSpacing":"-0.02em"}},"fontSize":"huge"} -->
    <h2 class="wp-block-heading has-text-align-center has-huge-font-size" style="letter-spacing:-0.02em;line-height:1.18;text-align:center">
        We've helped more than 2,000 businesses hire exceptional talent from Latin America, the Caribbean, and Europe.
    </h2>
    <!-- /wp:heading -->

    <!-- wp:group {"style":{"spacing":{"margin":{"top":"2.5rem"}}},"layout":{"type":"constrained","contentSize":"480px"}} -->
    <div class="wp-block-group" style="margin-top:2.5rem">
        <?= BlockDefaults::renderTrustStats() ?>
    </div>
    <!-- /wp:group -->

    <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"2.5rem"}}}} -->
    <div class="wp-block-buttons" style="margin-top:2.5rem">
        <!-- wp:button {"className":"is-style-pill-purple"} -->
        <div class="wp-block-button is-style-pill-purple"><a class="wp-block-button__link wp-element-button" href="#testimonials">Watch Client Testimonials</a></div>
        <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
</div>
<!-- /wp:group -->

<!-- ============ WHY COMPANIES CHOOSE REMOTE LEVERAGE ============ -->
<!-- wp:pattern {"slug":"remote-leverage/why-companies-choose"} /-->

<!-- ============ FROM VACANCY TO ONBOARDED IN 4 DAYS ============ -->
<!-- wp:pattern {"slug":"remote-leverage/process-steps"} /-->

<!-- ============ 12-MONTH REPLACEMENT GUARANTEE ============ -->
<!-- wp:pattern {"slug":"remote-leverage/replacement-guarantee"} /-->

<!-- ============ RESULTS, NOT PROMISES + FAQ ============ -->
<?php /* Composed rather than referencing patterns/results-testimonials-faq.php: the grid and the
         FAQ are identical, but that pattern carries the homepage's intro line and production
         runs a different one here. Same acf/testimonials and acf/accordion-faq blocks. */ ?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"6rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" id="testimonials" style="padding-top:6rem;padding-bottom:6rem">
    <!-- wp:columns {"style":{"spacing":{"margin":{"bottom":"3.5rem"}}}} -->
    <div class="wp-block-columns" style="margin-bottom:3.5rem">
        <!-- wp:column {"width":"55%"} -->
        <div class="wp-block-column" style="flex-basis:55%">
            <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
            <h2 class="<?= $h2 ?>" style="<?= $h2Style ?>">
                Results, Not Promises
            </h2>
            <!-- /wp:heading -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column {"width":"45%"} -->
        <div class="wp-block-column" style="flex-basis:45%">
            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"}}} -->
            <p style="line-height:1.6">
                Don't just take our word for it, hear from business owners who've hired through Remote Leverage. See why quality makes all the difference!
            </p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->

    <?= BlockDefaults::renderTestimonials() ?>

    <?= BlockDefaults::renderAccordionFaq() ?>
</div>
<!-- /wp:group -->

<!-- ============ READY TO SCALE YOUR GLOBAL TEAM? ============ -->
<!-- wp:pattern {"slug":"remote-leverage/booking-footer"} /-->
