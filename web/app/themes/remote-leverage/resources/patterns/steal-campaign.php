<?php
/**
 * Shared template for the "steal your time / steal your job" campaign family.
 *
 * Not a block pattern. It lives outside the theme's patterns/ directory because WordPress
 * core scans that tree recursively and would reject a headerless file.
 *
 * Included by patterns/stealing-jobs.php, patterns/stealing-jobs-lp.php and
 * patterns/steal-back-your-time.php, each of which defines $steal before including it.
 * The three production pages share one section skeleton and differ only in copy and in
 * which surface each band is painted on, so the markup lives here once.
 *
 * ---------------------------------------------------------------------------------------
 * PALETTE WARNING — this family is NOT the site's normal palette.
 *
 * /stealing-jobs/ and /steal-back-your-time/ are a near-black #0D0D0D ground with
 * glassmorphic cards, NOT the brand #250D4A midnight purple used everywhere else.
 * /stealing-jobs-lp/ is the same page inverted to a light #F4F6FC ground. Measured off
 * production with getComputedStyle on 2026-09-15:
 *
 *   ground          dark pages  body #0D0D0D          light page  body #F4F6FC (bg-light)
 *   hero band       dark pages  #0D0D0D               light page  #3D1A5D
 *   roles band      dark pages  #0D0D0D               light page  #13132F (brand-hero)
 *   closing band    dark pages  form-v2-1.jpg photo   light page  #250D4A (roles-surface) + Map-1.png
 *   footer band     all three   #18112C (brand-midnight)
 *   glass card      1px rgba(234,226,247,.2), radius 10px, backdrop-blur(50px),
 *                   linear-gradient(337deg, #0D0D0D33 -11.26%, #8A828233 97.88%)
 *   CTA pill        #F90066 (brand-magenta), radius 100px, 15px/18px padding
 *   h1              46px/63px, -1.89px, 700, #F4F6FC
 *   split h2        48px/53px, -1.44px, 400 (bold spans inline)
 *   centred h2      42px/48px, -1.44px, 700
 *   lead            20px/33px, -0.6px, 500-600, #E6DEF4 on dark
 *   container       1320px (measured — this family is narrower than the 1380px default)
 *
 * ---------------------------------------------------------------------------------------
 * Block reuse (ladder per SKILL.md Phase 2 — what was checked and why):
 *
 *   REUSED  acf/client-logos-marquee  hero logo strip, with production's own logo set
 *   REUSED  acf/testimonials          Client Reviews; BlockDefaults::testimonials() is
 *                                     already production's 15 reviews in production order
 *   REUSED  acf/booking-footer        closing CTA + booking wizard; its map_image field
 *                                     takes production's photographic band background
 *
 *   The remaining sections are hand-written here. Ruled out, section by section:
 *     hero            acf/hire-va-hero (right column is a booking form, not a portrait),
 *                     acf/comparison-hero (no checklist, no CTA side-note),
 *                     acf/partner-hero (centre-aligned, co-branded gradient)
 *     roles bento     acf/department-cards + acf/roles-grid (photo card + title + desc; no
 *                     chip list), acf/roles-carousel (horizontal scroll, icon cards)
 *                     — and none supports the 877/433 asymmetric first row
 *     stat band       acf/trust-stats (sits beside hero copy, avatar stack shape),
 *                     acf/about-stats (midnight highlight cards above stat pairs)
 *     3-step band     acf/process-steps (rule-and-dot timeline, no cards/images),
 *                     acf/progress-steps (segmented progress bar),
 *                     acf/process-step-cards (stacked full-width, not 3 across)
 *     "It's That Simple" / pricing orbit — no block of any shape in the inventory
 *
 *   Every one of those would have needed its surface, type scale and card material
 *   overridden to this family's palette, which is what forks a design system. See the
 *   agent report for the two shared-file changes this would need instead.
 */

use App\Support\BlockDefaults;

if (! isset($steal) || ! is_array($steal)) {
    return;
}

/* Everything the three pages share verbatim. A page config only carries its own deltas:
   theme, hero copy, CTA labels, the roles heading, the fifth role card, the two CTA notes
   and the closing copy. Verified identical across all three rendered pages on 2026-09-15.

   EVERY key this template reads has a default here, deliberately. Pattern files run during
   pattern *registration*, so an undefined-key notice is a fatal on every request site-wide
   — not just on these three pages. A config that omits a key must degrade, never explode. */
$steal += [
    'theme' => 'dark',
    'cta_url' => '#booking-footer',
    'cta_text' => 'Book a free consultation',
    'steps_cta_text' => 'Book a free consultation',

    'hero_headline' => '',
    'hero_sub' => '',
    'hero_note' => '',
    'roles_headline' => '',
    'ownership_headline' => '',
    'steps_note' => '',
    'pricing_note' => '',
    'closing_headline' => '',
    'closing_image' => 'form-v2-1.jpg',
    'roles_fifth' => [
        'title' => '', 'span' => '', 'image' => 'Earth-Illustration-2-1-1.png',
        'media_class' => 'pb-0 flex justify-end', 'chips' => [],
    ],

    'hero_image' => 'stealing-jobs-hero.webp',
    'hero_checklist' => [
        'Top 1% Latin American VAs, $6–10/hr',
        'Fluent English, U.S. working hours',
        'Vetted candidates in as fast as 72 hours',
        'No contracts, no recurring fees',
        'Interview before you pay',
    ],
    // Production's hero logo strip, in production's order. /steal-back-your-time/ overrides it.
    'logos' => [
        ['image-9.png', 'Bench'],
        ['image-7.png', 'SETAERO'],
        ['image-8.png', 'Sivia Law'],
        ['image-6.png', 'Q-Bit Wellness'],
        ['image-10.png', 'Garuz Legal Group'],
        ['image-4.png', 'BRRRR'],
        ['image-5.png', 'AdCenter360'],
        ['1-1.png', 'RE/MAX'],
        ['image-3.png', 'Carbon Solutions Group'],
    ],

    'overworked_headline' => '<strong>You didn&rsquo;t start<br>a business</strong> to become <br>its most overworked<br>employee',
    'overworked_body' => 'Every hour spent doing low-leverage work is an hour you&rsquo;re not spending on growth, strategy, or revenue. It&rsquo;s time to hand it over.',
    // [label, ticked] — production ticks only rows 1 and 4.
    'overworked_checklist' => [
        ['Managing your inbox', true],
        ['Scheduling meetings', false],
        ['Following up with leads', false],
        ['Updating spreadsheets', true],
        ['Preparing reports', false],
        ['Handling repetitive admin', false],
        ['Chasing tasks that someone else could own', false],
    ],
    'ownership_body' => 'Remote Leverage helps you hire experienced Latin American professionals who take real work off your plate, from operations and customer support to marketing and lead follow-up. You&rsquo;ll meet candidates who already know the job.',

    'simple_steps' => [
        ['badge_label' => 'You', 'badge' => 'bg-[#A855F7]', 'title' => 'Delegate the work'],
        ['badge_label' => 'Them', 'badge' => 'bg-[#0FA968]', 'title' => 'Get it done'],
        ['badge_label' => 'You', 'badge' => 'bg-[#A855F7]', 'title' => 'Get your time back', 'tint' => 'bg-[#2B1147]/60'],
    ],

    'stats' => [
        ['image' => 'Group-207.png', 'value' => 'Top 1%', 'label' => 'LatAm talent'],
        ['icon' => '<svg class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M14.5 9.3c-.5-.8-1.5-1.3-2.6-1.3-1.5 0-2.5.8-2.5 1.9 0 2.4 5.2 1.3 5.2 3.9 0 1.2-1.1 2.1-2.7 2.1-1.2 0-2.2-.5-2.7-1.4M12 6.4v11.2"/></svg>', 'icon_color' => 'text-[#F5C518]', 'value' => '$6/hr', 'label' => 'Starting rate'],
        ['icon' => '<svg class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M13 3L5.5 13.5H11L10.5 21 18.5 10.5H13L13 3z"/></svg>', 'icon_color' => 'text-[#00D982]', 'value' => '72 hrs', 'label' => 'To meet candidates'],
        ['icon' => '<svg class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M14 3H7a1.5 1.5 0 0 0-1.5 1.5v15A1.5 1.5 0 0 0 7 21h10a1.5 1.5 0 0 0 1.5-1.5V7.5L14 3z"/><path d="M4 20L20 4"/></svg>', 'icon_color' => 'text-[#A855F7]', 'value' => '$0', 'label' => 'Contracts or agency fees'],
    ],

    'hire_steps' => [
        ['title' => 'Tell us what you want off your plate', 'text' => 'Book a free consultation and tell us which responsibilities are consuming your time.', 'image' => 'Frame-1128.jpg'],
        ['title' => 'Interview your shortlist', 'text' => 'We recruit, screen, and present qualified candidates selected for your specific role.', 'image' => 'Frame-1128-1.jpg'],
        ['title' => 'Hire your favorite', 'text' => 'Choose the person who fits your business. You only pay when you decide to hire.', 'image' => 'Frame-1128-2.jpg'],
    ],

    'closing_body' => 'Hire a top 1% Virtual Assistant to take over the work that&rsquo;s stealing your time — so you can focus on growing the business.',
    'closing_checklist' => ['Top 1% talent from $6/hr', 'No contracts', 'No recurring agency fees'],
];

/* The first four role cards are identical on all three pages; the fifth is the delta
   ('And More' on /steal-back-your-time/, a repeated 'Customer Support' on the other two). */
$steal['roles'] = array_merge([
    [
        'title' => 'Executive &amp; Admin',
        'span' => 'lg:col-span-2',
        'wide' => true,
        'image' => 'Frame-878.png',
        'media_class' => 'px-[30px] pb-[30px] flex justify-end',
        'chips' => ['Inbox and calendar management', 'Meeting coordination', 'Travel arrangements', 'Reports and documentation'],
    ],
    [
        'title' => 'Sales Support',
        'span' => '',
        'image' => 'Group-877.png',
        // Production bleeds the talent-card strip flush to this card's edges (invariant 4).
        'media_class' => 'pb-0',
        'chips' => ['Lead generation', 'Cold calling and follow-ups', 'Appointment setting', 'CRM management'],
    ],
    [
        'title' => 'Marketing Support',
        'span' => '',
        'image' => 'Frame-1618872990.png',
        'media_class' => 'px-[30px] pb-[30px]',
        'chips' => ['Social media management', 'Content scheduling', 'Campaign assistance', 'Graphic design support'],
    ],
    [
        'title' => 'Customer Support',
        'span' => '',
        'image' => 'Frame-1618872991.png',
        'media_class' => 'px-[30px] pb-[30px]',
        'chips' => ['Email and chat support', 'Customer follow-ups', 'Ticket management', 'Order assistance'],
    ],
], [$steal['roles_fifth']]);

$img = fn (string $file): string => BlockDefaults::pageImg('steal-campaign', $file);

$isDark = ($steal['theme'] ?? 'dark') === 'dark';

/* ---- surfaces ------------------------------------------------------------------ */
$ground = $isDark ? '#0D0D0D' : '#F4F6FC';
$heroBg = $isDark ? '#0D0D0D' : '#3D1A5D';
$rolesBg = $isDark ? '#0D0D0D' : '#13132F';

/* ---- ink ----------------------------------------------------------------------- */
$ink = $isDark ? 'text-white' : 'text-black';
$inkLead = $isDark ? 'text-[#E6DEF4]' : 'text-black/75';
$inkSoft = $isDark ? 'text-white/85' : 'text-black/70';

/* ---- card material -------------------------------------------------------------
   Glass on the dark ground and on the dark roles band; flat white on the light ground,
   which is what production's light variant actually paints (invariant 5 is about not
   flattening glass that IS glass — the light page's stat cards are genuinely solid). */
$glass = 'rounded-[10px] border border-[#EAE2F7]/20 backdrop-blur-[50px] bg-[linear-gradient(337deg,#0D0D0D33_-11.26%,#8A828233_97.88%)]';
$card = $isDark ? $glass : 'rounded-[10px] bg-white border border-black/5';
$cardInk = $isDark ? 'text-white' : 'text-black';
$chip = $isDark
    ? 'inline-flex items-center rounded-full bg-white/8 px-[14px] py-[6px] text-[15px] leading-5 text-white/90'
    : 'inline-flex items-center rounded-full bg-black/5 px-[14px] py-[6px] text-[15px] leading-5 text-black/80';

/* ---- type ---------------------------------------------------------------------- */
/* Production's container is a 1440 box with a 60px gutter, NOT a 1320 box with inner
   padding: every left-aligned heading on all three pages starts at x=60 at a 1440 viewport
   and every centred one is centred on 60..1380. Measuring it as max-w-[1320px] + px-6 put
   the whole page 24px to the right of production. Re-measured 2026-09-15.
   The `!` is load-bearing: these bands are `wp:group` blocks with a constrained layout, and
   core's `.wp-container-… > *` rule (specificity 0,2,0) otherwise clamps this div to 1320px
   and the 60px gutter then eats INTO the content instead of sitting outside it — which put
   every band below the hero at x=120. The hero band is the one group core does not emit a
   container class for, which is why it alone looked right without this. */
$wrap = 'w-full max-w-[1440px]! mx-auto px-5 lg:px-[60px]';
$h1 = 'font-display font-bold text-[34px] leading-[42px] sm:text-[46px] sm:leading-[63px] tracking-[-1.89px] text-bg-light';
$h2Split = 'font-display font-normal text-[34px] leading-[40px] sm:text-[48px] sm:leading-[53px] tracking-[-1.44px] [&_strong]:font-bold';
$h2Mid = 'font-display font-bold text-[30px] leading-[36px] sm:text-[42px] sm:leading-[48px] tracking-[-1.44px]';
$h3Card = 'font-display font-medium text-[22px] leading-[26px] sm:text-[27px] sm:leading-[30px] tracking-[-0.81px]';
$lead = 'text-[17px] leading-[28px] sm:text-[20px] sm:leading-[33px] tracking-[-0.6px] font-medium';

/* ---- CTA pill ------------------------------------------------------------------ */
/* The pill carries a magenta bloom on production — box-shadow 0 0 29.9px rgba(249,0,102,.6),
   measured with getComputedStyle. Without it the CTA reads as a flat sticker. The arrow is
   22px and sits 14px after the label (label ends x=264, icon x=278, pill ends x=318 with
   18px right padding). */
$pill = 'group inline-flex items-center gap-[14px] rounded-full bg-brand-magenta hover:bg-brand-magenta-hover px-[18px] py-[15px] font-display text-[14px] leading-[22px] font-bold uppercase tracking-[-0.45px] text-white shadow-[0_0_29.9px_0_rgba(249,0,102,0.6)] transition-colors';
$arrow = '<svg class="w-[22px] h-[22px] shrink-0 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10.25" stroke="currentColor" stroke-width="1.5"/><path d="M10.5 8.5l3.5 3.5-3.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

/* Magenta tick used by the hero and closing checklists. Production's hero rows are an 18px
   icon against an 18px line box, so the icon is flush with the cap line, not nudged down. */
$tick = '<svg class="w-[18px] h-[18px] shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="10" fill="#F90066"/><path d="M5.8 10.2l2.7 2.7 5.5-5.5" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

$cta = $steal['cta_url'];

/* Production's hero logo strip, in production's order. Keys match the
   acf/client-logos-marquee repeater (src, alt). All three pages override the block's own
   presets: the nine-logo default above is /stealing-jobs/ and /stealing-jobs-lp/,
   /steal-back-your-time/ swaps in a fourteen-logo set.

   The `?? []` and the `if` are a deliberate guard, not dead code. Pattern files are
   executed during pattern *registration*, so an undefined-key notice here is a fatal on
   every request site-wide, not just on these three pages. Keep them if you add a config.
   An empty $logoData means "pass no overrides" (block falls back to its presets), which
   is not the same as a repeater with zero rows (renders an empty strip). */
$logoRows = array_map(
    fn (array $l): array => ['src' => $img($l[0]), 'alt' => $l[1]],
    $steal['logos'] ?? [],
);
$logoData = [];
if ($logoRows !== []) {
    BlockDefaults::encodeRepeater('logos', 'field_client_logos_marquee_block_logos', $logoRows, $logoData);
}

/* The pricing orbit is identical on all three pages, so it lives here rather than in each
   config. [file, left % of the 1320 container, top px, diameter px] — production's own
   getBoundingClientRect values at 1440px, rebased on the container (x-60)/1320 and on the
   sphere's top edge (y-296). */
$orbit = [
    ['Ellipse-90.png', 4.39, 39, 34],
    ['Frame-1082.png', 14.24, 39, 94],
    ['Frame-1081.png', 2.20, 159, 49],
    ['Frame-1082-2.png', 11.21, 196, 77],
    ['Frame-1082-1.png', 30.76, 37, 58],
    ['Frame-1080.png', 22.50, 160, 49],
    ['Ellipse-80.png', 63.79, 42, 68],
    ['Ellipse-79.png', 61.67, 178, 30],
    ['Frame-1078.png', 70.00, 147, 86],
    ['Frame-1085.png', 78.18, 32, 71],
    ['Frame-1083.png', 83.56, 224, 50],
    ['Frame-1079.png', 91.14, 97, 38],
];

?>
<!-- ============================== 1. HERO ============================== -->
<!-- rl:cta-only-header — production serves this family with no site nav, only a logo and a
     single Get Started pill. App\Support\PageChrome reads this marker and swaps
     sections.header for sections.header-cta. -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1320px"}} -->
<div class="wp-block-group alignfull relative overflow-hidden isolate" style="background-color:<?= $heroBg ?>;">
    <?php /* Production's .gradient--eclipss-hero ::before / ::after — two brand-purple blobs,
             864x578, blur(197.65px), at left:-200/top:-445 and right:-200/top:-110 once
             rebased on this band rather than on Elementor's inner container. Present on all
             three pages: the hero is the one band the light variant also paints dark, and it
             reads as flat without them. Verified by pixel sampling against production. */ ?>
    <span class="pointer-events-none absolute -z-10 left-[-200px] top-[-445px] w-[864px] h-[578px] rounded-full bg-brand-purple blur-[198px]" aria-hidden="true"></span>
    <span class="pointer-events-none absolute -z-10 right-[-200px] top-[-110px] w-[864px] h-[578px] rounded-full bg-brand-purple blur-[198px]" aria-hidden="true"></span>

    <?php /* The band is 639px tall on all three pages (the portrait's own height) and the
             copy column is CENTRED in it, not bottom-aligned: production leaves 91px above
             and below on /stealing-jobs/ and 43px on /steal-back-your-time/, i.e. exactly
             half the slack in each case. The two columns are 625px each with a 70px gutter
             (625+70+625 = 1320), and the portrait is centred in its own column at x=799,
             not flush to the container edge. */ ?>
    <div class="<?= $wrap ?> relative pt-[110px]">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-x-[70px] lg:gap-y-0 lg:items-center">

            <div class="pb-10 lg:pb-0">
                <h1 class="<?= $h1 ?>"><?= $steal['hero_headline'] ?></h1>

                <p class="<?= $lead ?> text-[#E6DEF4] font-semibold mt-5"><?= $steal['hero_sub'] ?></p>

                <?php /* 15px/18px, 500, #E6DEF4 with a 14px row gap — a 32px row pitch and a
                         146px list, matching production. An 18px icon on an 18px line box
                         needs no vertical nudge, and the icon gap is 10px (icon x=60,
                         label x=88). */ ?>
                <ul class="mt-10 space-y-[14px]">
                    <?php foreach ($steal['hero_checklist'] as $item) { ?>
                        <li class="flex items-start gap-[10px] text-[15px] leading-[18px] font-medium text-[#E6DEF4]">
                            <?= $tick ?><span><?= $item ?></span>
                        </li>
                    <?php } ?>
                </ul>

                <?php /* The note sits to the RIGHT of the pill on all three pages, 20px after
                         it, taking the rest of the 625px column. /steal-back-your-time/ has a
                         two-line note long enough to wrap the whole paragraph onto its own row
                         without the flex-1 basis, which is what broke that page's hero. */ ?>
                <?php /* z-3 so the band's upward bloom (z-2, below) does not wash over the
                         pill and its note — production lifts exactly this row the same way,
                         and leaves the checklist above it inside the bloom. */ ?>
                <div class="relative z-[3] mt-10 flex flex-wrap items-center gap-5">
                    <a href="<?= esc_url($cta) ?>" class="<?= $pill ?>">
                        <span><?= $steal['cta_text'] ?></span><?= $arrow ?>
                    </a>
                    <p class="flex-1 basis-[200px] text-[15px] leading-[18px] font-medium text-[#E6DEF4]"><?= $steal['hero_note'] ?></p>
                </div>
            </div>

            <?php /* The portrait is a 537x639 cut-out served at its natural size, and
                     production puts no fade over it — the photo's own ground carries the
                     dissolve. The overlay this markup used to carry spanned the whole 1fr
                     column, which painted a visible dark rectangle out over the purple
                     gradient to the left of the subject. */ ?>
            <div class="hidden lg:flex justify-center items-end">
                <img src="<?= $img($steal['hero_image']) ?>" alt="" width="537" height="639"
                     loading="eager" decoding="async" class="w-[537px] h-[639px] object-fill">
            </div>

        </div>
    </div>

    <?php /* The strip is transparent so the right-hand blob's bloom carries down through it
             the way it does on production; the band behind it is already $heroBg. Logos are
             sized on WIDTH (104px, height auto) — production keeps each mark's own aspect,
             so Bench sits tall and SETAERO wide. The whole rail is at 50% opacity and is NOT
             inverted: the source PNGs are already white. */ ?>
    <?php /* Production's `.shadow-gradientt0` band. It is not just a logo rail: it carries a
             70% #0D0D0D scrim (57% #1E0C38 on the light variant) AND a box-shadow thrown 100px
             upward with an 80px blur (50/70 on the light variant), which is what dissolves the
             bottom of the portrait into the band. Measured with getComputedStyle on
             2026-09-15 — without it the cut-out ends on a hard edge, which was the single
             largest visual difference left in this hero. z-index 2 so the bloom lands over the
             portrait, as it does on production. */ ?>
    <?php /* The block's own CSS (.rl-logo-marquee-item img in resources/css/app.css) caps every
             mark at max-height:28px and paints it with filter:brightness(0), which flattens
             nine different logo shapes into one letterbox. Production sizes on WIDTH instead —
             a 104px box with the mark at its natural size when it is narrower (Sivia Law stays
             99px) — keeps each aspect and applies no filter because the source PNGs are already
             white. That is exactly the .rl-logo-marquee-natural size modifier, so it is picked
             here rather than re-declared at higher specificity. The 50% dim stays page-local:
             it is the rail's treatment on this family, not a property of the size. Production
             also runs the rail edge-to-edge with no fade mask, so the wrapper's mask and
             max-width come off too. */ ?>
    <div class="rl-logo-marquee-natural relative z-[2] py-[10px] <?= $isDark ? 'bg-[rgba(13,13,13,0.7)] shadow-[0_-100px_80px_0_#0D0D0D]' : 'bg-[rgba(30,12,56,0.57)] shadow-[0_-50px_70px_0_#1E0C38]' ?>
                [&_.rl-logo-marquee-wrapper]:max-w-none [&_.rl-logo-marquee-wrapper]:px-0
                [&_.rl-logo-marquee-wrapper]:[mask-image:none] [&_.rl-logo-marquee-wrapper]:[-webkit-mask-image:none]
                [&_.animate-marquee-logos]:gap-[60px] [&_.animate-marquee-logos]:opacity-50">
        <?= BlockDefaults::renderClientLogosMarquee($logoData) ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ================= 2. YOU DIDN'T START A BUSINESS ==================== -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1320px"}} -->
<div class="wp-block-group alignfull py-[70px] lg:py-[90px]" style="background-color:<?= $ground ?>;">
    <div class="<?= $wrap ?>">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-[70px] lg:items-center">
            <div>
                <h2 class="<?= $h2Split ?> <?= $ink ?>"><?= $steal['overworked_headline'] ?></h2>
                <p class="<?= $lead ?> <?= $inkLead ?> mt-8 max-w-[577px]"><?= $steal['overworked_body'] ?></p>
            </div>
            <?php /* Production is a 633x579 cover-cropped screenshot collage with a live
                     93%-white checklist card inset 65px/117px inside it — the checklist is
                     real text on production, not part of the artwork. */ ?>
            <div class="flex justify-center lg:justify-end">
                <div class="w-full max-w-[633px] aspect-[633/579] rounded-[10px] bg-cover bg-center flex items-start"
                     style="background-image:url('<?= $img('image-11-1.png') ?>');">
                    <div class="mx-[8%] my-[16%] w-full rounded-[5px] bg-white/93 px-[30px] py-[25px]">
                        <h3 class="text-[20px] leading-[33px] font-semibold text-black">You're still doing all of this yourself:</h3>
                        <ul class="mt-5">
                            <?php foreach ($steal['overworked_checklist'] as $item) { ?>
                                <li class="flex items-center gap-3 pb-[5px] text-[16px] leading-[24px] text-[#333]">
                                    <span class="w-[18px] h-[18px] shrink-0 rounded-[4px] <?= $item[1] ? 'bg-[#1A1A1A]' : 'bg-black/10' ?> flex items-center justify-center">
                                        <?php if ($item[1]) { ?>
                                            <svg class="w-[11px] h-[11px]" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2 6.3l2.6 2.6L10 3.4" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        <?php } ?>
                                    </span>
                                    <span><?= $item[0] ?></span>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ===================== 3. NOT HERE TO HELP OUT ======================= -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1320px"}} -->
<div class="wp-block-group alignfull py-[70px] lg:py-[90px]" style="background-color:<?= $ground ?>;">
    <div class="<?= $wrap ?>">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-[70px] lg:items-center">
            <?php /* Production is a 633x581 teal gradient card (Frame-1124.jpg) with the
                     phone screenshot centred and bleeding off its bottom edge — invariant 4,
                     the mock is flush to the card foot, not floated inside padding. */ ?>
            <div class="flex justify-center lg:justify-start">
                <div class="w-full max-w-[633px] aspect-[633/581] overflow-hidden rounded-[10px] bg-cover bg-center flex items-end justify-center"
                     style="background-image:url('<?= $img('Frame-1124.jpg') ?>');">
                    <img src="<?= $img('Group-879.jpg') ?>" alt="" width="406" height="527"
                         loading="lazy" decoding="async"
                         class="w-[64%] h-auto rounded-t-[10px]">
                </div>
            </div>
            <div>
                <h2 class="<?= $h2Split ?> <?= $ink ?>"><?= $steal['ownership_headline'] ?></h2>
                <p class="<?= $lead ?> <?= $inkLead ?> mt-8 max-w-[577px]"><?= $steal['ownership_body'] ?></p>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ======================== 4. IT'S THAT SIMPLE ======================== -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1320px"}} -->
<div class="wp-block-group alignfull relative overflow-hidden isolate pt-[70px] pb-[80px]" style="background-color:<?= $ground ?>;">
    <?php if ($isDark) { ?>
        <?php /* Production's .eclipps--grad::after — 660x496 #8A2BE2 at 40%, blur(197.65px),
                 top:-100 right:-198. Disabled below 1024px on production, so desktop only. */ ?>
        <span class="pointer-events-none absolute -z-10 hidden lg:block right-[-198px] top-[-100px] w-[660px] h-[496px] rounded-full bg-[#8A2BE2] opacity-40 blur-[198px]" aria-hidden="true"></span>
    <?php } ?>
    <div class="<?= $wrap ?> relative">
        <h2 class="<?= $h2Mid ?> <?= $ink ?> text-center">It’s That Simple</h2>

        <div class="mt-6 flex items-center justify-center gap-4">
            <img src="<?= $img('Group-207.png') ?>" alt="" width="100" height="51"
                 loading="lazy" decoding="async" class="h-[30px] w-auto">
            <span class="flex items-center gap-2">
                <img src="<?= $img('Group-213.svg') ?>" alt="" width="22" height="22"
                     loading="lazy" decoding="async" class="h-[22px] w-[22px]">
                <span class="text-[14px] leading-[17px] font-medium <?= $inkSoft ?>">
                    <strong class="font-bold">2.5K+</strong><br>pre-vetted candidates
                </span>
            </span>
        </div>

        <div class="mt-12 grid grid-cols-1 md:grid-cols-[1fr_auto_1fr_auto_1fr] items-center gap-4 md:gap-0">
            <?php foreach ($steal['simple_steps'] as $i => $step) { ?>
                <?php if ($i > 0) { ?>
                    <span class="hidden md:flex w-[36px] items-center justify-center <?= $isDark ? 'text-white/60' : 'text-black/40' ?>" aria-hidden="true">
                        <svg class="w-4 h-4" viewBox="0 0 16 16" fill="none"><path d="M1 8h13M10 4l4 4-4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                <?php } ?>
                <div class="<?= $card ?> <?= $step['tint'] ?? '' ?> flex items-center gap-5 px-[26px] py-[30px]">
                    <span class="w-[50px] h-[50px] shrink-0 rounded-full <?= $step['badge'] ?> flex items-center justify-center text-[11px] font-bold uppercase tracking-[0.02em] text-white">
                        <?= $step['badge_label'] ?>
                    </span>
                    <span class="font-display text-[17px] leading-[22px] font-bold <?= $cardInk ?>"><?= $step['title'] ?></span>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ========================= 5. ROLES BENTO ============================ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1320px"}} -->
<div class="wp-block-group alignfull py-[70px] lg:py-[80px]" style="background-color:<?= $rolesBg ?>;">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2Mid ?> text-white text-center max-w-[700px] mx-auto"><?= $steal['roles_headline'] ?></h2>

        <?php
        /* Production's first row is deliberately asymmetric — 877px + 433px against a 1320px
           container with a 10px gutter — and every card carries a chip list, which no card
           block in the inventory models. */
        // @bespoke: checked acf/department-cards, acf/roles-grid (photo card + title + desc,
        // no chip list), acf/roles-carousel (horizontal icon cards) and acf/feature-cards
        // (img/title/desc, uniform columns). None renders a chip list or the 877/433 row.
?>
        <div class="mt-[60px] grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-[10px]">
            <?php foreach ($steal['roles'] as $role) { ?>
                <div class="<?= $glass ?> <?= $role['span'] ?> relative overflow-hidden flex <?= $role['wide'] ?? false ? 'flex-col lg:flex-row lg:items-end' : 'flex-col' ?> min-h-[461px]">
                    <div class="p-[30px] pb-0 <?= $role['wide'] ?? false ? 'lg:w-[348px] lg:shrink-0 lg:self-start' : '' ?>">
                        <h3 class="<?= $h3Card ?> text-white"><?= $role['title'] ?></h3>
                        <?php /* Production stacks the chips one per line on a 4px rhythm —
                                 348px column, 32px rows — not an inline wrap. */ ?>
                        <ul class="mt-5 flex flex-col items-start gap-[4px]">
                            <?php foreach ($role['chips'] as $c) { ?>
                                <li class="<?= $chip ?>"><?= $c ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                    <div class="mt-auto min-w-0 <?= $role['wide'] ?? false ? 'lg:mt-0 lg:flex-1' : 'w-full' ?> <?= $role['media_class'] ?>">
                        <img src="<?= $img($role['image']) ?>" alt="" loading="lazy" decoding="async"
                             class="w-full h-auto object-contain object-bottom">
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ================= 6. NOT JUST AVAILABLE. PROVEN. ==================== -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1320px"}} -->
<div class="wp-block-group alignfull py-[70px] lg:py-[80px]" style="background-color:<?= $ground ?>;">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2Mid ?> <?= $ink ?> text-center">Not just available. Proven.</h2>

        <div class="mt-[60px] grid grid-cols-2 lg:grid-cols-4 gap-[10px]">
            <?php foreach ($steal['stats'] as $stat) { ?>
                <div class="<?= $card ?> p-[30px] flex flex-col items-center justify-center text-center gap-5 min-h-[187px]">
                    <?php if (! empty($stat['image'])) { ?>
                        <img src="<?= $img($stat['image']) ?>" alt="" width="83" height="34"
                             loading="lazy" decoding="async" class="h-[34px] w-auto">
                    <?php } else { ?>
                        <span class="w-[44px] h-[44px] rounded-full <?= $isDark ? 'bg-white/8' : 'bg-black/5' ?> flex items-center justify-center <?= $stat['icon_color'] ?>">
                            <?= $stat['icon'] ?>
                        </span>
                    <?php } ?>
                    <span class="block">
                        <span class="block font-display text-[22px] leading-[26px] font-bold <?= $cardInk ?>"><?= $stat['value'] ?></span>
                        <span class="block mt-1 text-[15px] leading-[20px] <?= $isDark ? 'text-white/75' : 'text-black/65' ?>"><?= $stat['label'] ?></span>
                    </span>
                </div>
            <?php } ?>
        </div>

        <div class="<?= $card ?> mt-[10px] px-[30px] py-[22px] flex flex-col sm:flex-row items-center justify-between gap-5">
            <p class="font-display text-[17px] leading-[24px] font-bold <?= $cardInk ?>">We handle the recruiting. You choose who earns the job.</p>
            <a href="<?= esc_url($cta) ?>" class="<?= $pill ?>"><span><?= $steal['cta_text'] ?></span><?= $arrow ?></a>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ===================== 7. MEET YOUR NEXT HIRE ======================== -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1320px"}} -->
<div class="wp-block-group alignfull py-[70px] lg:py-[80px]" style="background-color:<?= $ground ?>;">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2Mid ?> <?= $ink ?> text-center max-w-[700px] mx-auto">Meet your next hire&nbsp; in 3 simple steps</h2>

        <?php
/* Three equal cards, each numeral badge + copy + a 375x179 illustration pinned to the
   card foot, with a connector arrow between them. */
// @bespoke: checked acf/process-steps (rule-and-dot timeline, no card, no image),
// acf/progress-steps (segmented bar, no image) and acf/process-step-cards (stacked
// full-width rows with an oversized numeral). None is a 3-across image card.
?>
        <div class="mt-[60px] grid grid-cols-1 lg:grid-cols-[1fr_auto_1fr_auto_1fr] gap-5 lg:gap-0 items-stretch">
            <?php foreach ($steal['hire_steps'] as $i => $step) { ?>
                <?php if ($i > 0) { ?>
                    <span class="hidden lg:flex w-[34px] items-center justify-center <?= $isDark ? 'text-white/60' : 'text-black/40' ?>" aria-hidden="true">
                        <svg class="w-4 h-4" viewBox="0 0 16 16" fill="none"><path d="M1 8h13M10 4l4 4-4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                <?php } ?>
                <div class="<?= $card ?> p-[20px] flex flex-col gap-5">
                    <span class="w-[34px] h-[34px] rounded-full <?= $isDark ? 'bg-white/10 text-white' : 'bg-black/5 text-black' ?> flex items-center justify-center text-[15px] font-medium"><?= $i + 1 ?></span>
                    <div>
                        <h3 class="font-display text-[17px] leading-[22px] font-bold <?= $cardInk ?>"><?= $step['title'] ?></h3>
                        <p class="mt-2 text-[14px] leading-[20px] <?= $isDark ? 'text-white/75' : 'text-black/65' ?>"><?= $step['text'] ?></p>
                    </div>
                    <img src="<?= $img($step['image']) ?>" alt="" width="375" height="179"
                         loading="lazy" decoding="async" class="mt-auto w-full h-auto rounded-[5px]">
                </div>
            <?php } ?>
        </div>

        <div class="mt-[60px] flex flex-col items-center gap-5">
            <a href="<?= esc_url($cta) ?>" class="<?= $pill ?>"><span><?= $steal['steps_cta_text'] ?></span><?= $arrow ?></a>
            <p class="text-[14px] leading-[20px] <?= $inkSoft ?> text-center"><?= $steal['steps_note'] ?></p>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ========================= 8. CLIENT REVIEWS ========================= -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1320px"}} -->
<div class="wp-block-group alignfull py-[70px] lg:py-[80px]" style="background-color:<?= $ground ?>;">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2Mid ?> <?= $ink ?> text-center">Client Reviews</h2>
        <p class="mt-4 text-[15px] leading-[24px] <?= $inkSoft ?> text-center max-w-[620px] mx-auto">
            Don't just take our word for it, hear from business owners who've hired through Remote Leverage.
            See why quality makes all the difference!
        </p>

        <?php
/* acf/testimonials renders the wall and now owns the "show more" collapse too — production
   shows one row of three here, against six on the hire-va pages, so the count is a field.
   On the dark pages the block's light card type is re-toned in place. */
$reviewTone = $isDark
    ? '[&_h3]:text-white! [&_span]:text-white/70! [&_.group:hover]:bg-white/5! [&_.group:hover]:border-white/10!'
    : '';
?>
        <div class="<?= $reviewTone ?> mt-[50px]">
            <?= BlockDefaults::renderTestimonials(BlockDefaults::withFieldKeys('testimonials_block', [
                'show_more' => 1,
                'visible_count' => 3,
                'tone' => $isDark ? 'dark' : 'light',
            ])) ?>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ====================== 9. ELITE TALENT / PRICING ==================== -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1320px"}} -->
<div class="wp-block-group alignfull relative overflow-hidden pt-[70px] pb-[60px]" style="background-color:<?= $ground ?>;">
    <?php if ($isDark) { ?>
        <img src="<?= $img('Map.png') ?>" alt="" aria-hidden="true" width="1884" height="744" loading="lazy" decoding="async"
             class="pointer-events-none absolute left-[3.5%] top-[470px] w-[90%] h-auto select-none">
    <?php } ?>

    <div class="<?= $wrap ?> relative">
        <h2 class="<?= $h2Mid ?> <?= $ink ?> text-center">Elite talent, without the elite payroll</h2>
        <p class="mt-6 text-[15px] leading-[29px] <?= $inkSoft ?> text-center max-w-[640px] mx-auto">
            Hire experienced Latin American professionals starting at just $6/hr — build the support your
            business needs while saving significantly compared with traditional U.S. hiring or high-markup
            VA agencies.
        </p>

        <?php
/* The orbital composition: a $6 sphere centred over the world map with twelve candidate
   portraits pinned around it. Coordinates are production's, read with
   getBoundingClientRect at 1440px and expressed as percentages of the 1320 container. */
?>
        <div class="relative mx-auto mt-6 hidden lg:block h-[290px] w-full max-w-[1320px]" aria-hidden="true">
            <?php foreach ($orbit as $o) { ?>
                <img src="<?= $img($o[0]) ?>" alt="" loading="lazy" decoding="async"
                     class="absolute rounded-full"
                     style="left:<?= $o[1] ?>%;top:<?= $o[2] ?>px;width:<?= $o[3] ?>px;height:<?= $o[3] ?>px;">
            <?php } ?>

            <div class="absolute left-1/2 top-0 -translate-x-1/2 w-[232px] h-[232px] rounded-full
                        bg-[radial-gradient(circle_at_32%_26%,#A855F7_0%,#7C2DD4_42%,#4C1192_100%)]
                        shadow-[0_24px_60px_rgba(124,45,212,0.45)] flex flex-col items-center justify-center text-white">
                <span class="text-[15px] leading-[18px] font-medium opacity-90">from</span>
                <span class="font-display text-[64px] leading-[68px] font-bold tracking-[-1.89px]">$6</span>
                <span class="text-[19px] leading-[24px] font-medium">Per Hour</span>
            </div>
        </div>

        <div class="mt-10 lg:mt-[30px] flex flex-col items-center gap-5">
            <a href="<?= esc_url($cta) ?>" class="<?= $pill ?>"><span><?= $steal['steps_cta_text'] ?></span><?= $arrow ?></a>
            <p class="text-[14px] leading-[20px] <?= $inkSoft ?> text-center"><?= $steal['pricing_note'] ?></p>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ==================== 10. CLOSING CTA + BOOKING ====================== -->
<?php
/* acf/booking-footer renders its `description` field raw, so production's three-item
   reassurance list rides along with the paragraph rather than needing a block change. */
$closingBody = $steal['closing_body'].'<span class="mt-8 flex flex-col gap-[7px]">';
foreach ($steal['closing_checklist'] as $item) {
    /* This list keeps the 25px line box, so the tick needs the 3px nudge the hero's
       18px rows do not. */
    $closingTick = str_replace('shrink-0', 'shrink-0 mt-[3px]', $tick);
    $closingBody .= '<span class="flex items-start gap-3 text-[15px] leading-[25px] text-white">'.$closingTick.'<span>'.$item.'</span></span>';
}
$closingBody .= '</span>';
?>
<?php /* acf/booking-footer reads `headline` off $this->block->data but still resolves
         `map_image` through get_field(), which returns null for a block this deep in the
         page — so the band falls back to its default map and loses production's photographic
         background. Scoped override until the block reads map_image the same way; see the
         migration report. */ ?>
<style>.rl-steal-closing #booking-footer{background-image:url('<?= $img($steal['closing_image']) ?>');background-size:cover;background-position:50% 50%;}</style>
<div class="rl-steal-closing">
<?= BlockDefaults::patternBlock('booking-footer', BlockDefaults::withFieldKeys('booking_footer', [
    'headline' => $steal['closing_headline'],
    'description' => $closingBody,
    'map_image' => $img($steal['closing_image']),
]), ['align' => 'full']) ?>
</div>

