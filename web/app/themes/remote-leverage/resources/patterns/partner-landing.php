<?php
/**
 * Shared co-branded partner landing template.
 *
 * Not a block pattern. It lives outside the theme's patterns/ directory because
 * WordPress core scans that tree recursively and would reject a headerless file.
 *
 * Included by patterns/remote-leverage-x-oyster.php and patterns/remote-leverage-x-lano.php,
 * each of which defines $partner before including this file. Both pages are identical in
 * structure on production and differ only in copy and brand colour, so the markup lives
 * here once. Layout, type scale and colours were read off the live pages with
 * getComputedStyle (2026-09-14).
 */
use App\Support\BlockDefaults;

if (! isset($partner) || ! is_array($partner)) {
    return;
}

$pimg = fn(string $file): string => get_theme_file_uri('public/images/partners/' . $file);

$heroBg = $partner['hero_bg'];      // brand blue behind the globe
$deepBg = $partner['deep_bg'];      // darker band used for logo strip / roles / closing CTA
$lav = 'var(--color-lavender-tint)'; // shared page background

$h2 = 'font-display font-bold text-[32px] leading-[38px] sm:text-[42px] sm:leading-[48px] tracking-[-1.26px]';
$h3 = 'font-display font-bold text-[22px] leading-[27px] sm:text-[27px] sm:leading-[32px] tracking-[-0.81px]';
$body = 'text-[15px] leading-[24px]';
$wrap = 'w-full max-w-[1380px] mx-auto px-5 sm:px-6 lg:px-8';
$arrow = '<svg class="w-[22px] h-[22px] shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="11" stroke="currentColor" stroke-width="1.5"/><path d="M10 8l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$btnDark = 'inline-flex items-center gap-3 rounded-full bg-black px-8 py-4 font-display text-[17px] font-bold uppercase tracking-[-0.51px] text-white transition hover:opacity-90';

// Shared across both partner pages — identical on production.
// Keys match the acf/talent-grid repeater (name, title, desc, bg, logo).
$talent = array_map(fn(array $t): array => [
    'name' => $t[1],
    'title' => $t[2],
    'desc' => $t[3],
    'bg' => $pimg($t[0]),
    'logo' => $pimg($t[4]),
], [
    ['con-07.png', 'André Vilalobos', 'Graphic Designer', '6+ years of experience helping brands of all sizes, from small and mid-sized businesses to big companies, look professional, polished, and unmistakably them.', 'State-Farm-01.png'],
    ['cont-02.png', 'Juliana Silva', 'Lead Generation (SDR)', '6+ years of experience as an SDR, skilled in prospecting, active listening, clear communication, time management, and handling rejection to consistently generate and qualify sales leads.', 'mercado.png'],
    ['con-05.png', 'Valeria Andrea', 'Medical / Healthcare', '4+ years of experience in fast-paced clinic and hospital settings. Skilled in EMR systems (Epic, Cerner), patient intake, vital signs, and assisting physicians with exams and procedures.', 'Allstate-01.png'],
    ['con-08.png', 'Laura Valentina', 'Customer Support', '+4 years of experience in B2B SaaS customer support, I’ve supported customers in North America, Europe, and Latin America, adapting to different cultural expectations and communication styles while handling email, chat, and phone support.', 'Bank-of-America-01.png'],
    ['cont-03.png', 'Sofía Pérez', 'Marketing', '4+ years of experience as a results-driven marketing professional, skilled in content creation, social media strategy, campaign management, and data analysis to drive brand awareness and customer engagement.', 'Frame-74-1.png'],
    ['con-06.png', 'Luana Dias', 'Executive Assistant', '3+ years of experience supporting C-level executives in fast-paced environments. High organization, anticipate needs, and protect executive’s time like it’s my own.', 'NU-bank-01.png'],
]);

// Keys match the acf/department-cards repeater (title, desc, img).
$roles = array_map(fn(array $r): array => ['title' => $r[0], 'desc' => $r[1], 'img' => $pimg($r[2])], [
    ['Sales<br>& Growth', 'SDRs, BDRs, and Account Managers to fill your pipeline.', 'freepik__talk__67298-1.png'],
    ['Operations<br>& Fintech', 'KYC Analysts, Billing Specialists, and HubSpot/Salesforce Admins.', 'freepik__photo-a-35yearold-indian-man-with-a-beard-wearing-__5017-1.png'],
    ['Marketing<br>& Demand Gen', 'Content Ops, Social Media Managers, and Campaign Specialists.', 'freepik__talk__81238-1.png'],
    ['Customer<br>Support', 'Technical Support and Customer Success reps.', 'freepik__talk__80195-1.png'],
]);

// Keys match the acf/feature-cards repeater (img, title, desc).
$pricing = array_map(fn(array $c): array => ['img' => $pimg($c[0]), 'title' => $c[1], 'desc' => $c[2]], [
    ['Frame-77-2.png', '$6 – $12/hr', 'Premium global talent that fits your budget.'],
    ['Frame-121.svg', '70% Lower Costs', 'Reinvest your savings back into your product and growth.'],
    ['Frame-127.png', '4-Day Average', 'From vacancy to a fully vetted professional ready for onboarding.'],
    ['Frame-123.svg', 'Vetted for Quality:', 'We filter the top 1% so you only see the absolute best.'],
]);

// Value cards arrive from the partner config as [img => filename, title, desc]; resolve the URL.
$valueCards = array_map(fn(array $c): array => ['img' => $pimg($c['img']), 'title' => $c['title'], 'desc' => $c['desc']], $partner['value_cards']);

// Table rows arrive positional; acf/data-table wants feature/diy/rl.
$tableRows = array_map(fn(array $r): array => ['feature' => $r[0], 'diy' => $r[1], 'rl' => $r[2]], $partner['table_rows']);

// acf/process-steps wants one encoded repeater; production shows the paragraphs joined.
$stepsData = [];
BlockDefaults::encodeRepeater('steps', 'field_process_steps_block_steps', array_map(
    fn(array $step, int $i): array => [
        'num' => sprintf('%02d', $i + 1),
        'title' => $step['title'],
        'desc' => implode(' ', $step['paragraphs']),
    ],
    $partner['steps'],
    array_keys($partner['steps']),
), $stepsData);

$statIcons = [
    '<path d="M4 17.5h16M6.5 17.5V11M11 17.5V7.5M15.5 17.5v-4M20 17.5V5"/>',
    '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.3 2"/>',
    '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.4 2.5 3.6 5.7 3.6 9s-1.2 6.5-3.6 9c-2.4-2.5-3.6-5.7-3.6-9s1.2-6.5 3.6-9z"/>',
    '<path d="M4 18L10 12l3.5 3.5L20 8"/><path d="M15 8h5v5"/>',
];

?>
<!-- ============ HERO ============ -->
<?= BlockDefaults::renderPartnerHero([
    'headline' => $partner['hero_title'],
    'cta_text' => $partner['hero_cta_text'],
    'cta_url' => $partner['hero_cta_url'],
    'brand_color' => $partner['hero_bg'],
    'brand_color_end' => $partner['hero_bg_end'],
], $partner['hero_paragraphs'], $partner['stats']) ?>

<!-- ============ TRUSTED BY ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull py-7 [&_img]:brightness-0 [&_img]:invert" style="background-color:<?= $deepBg ?>;">
    <p class="text-center text-[11px] font-bold uppercase tracking-[0.18em] text-white/80 mb-6">Trusted by scaling teams globally</p>
    <!-- wp:acf/client-logos-marquee {"name":"acf/client-logos-marquee","align":"full","mode":"preview"} /-->
</div>
<!-- /wp:group -->

<!-- ============ THE BRIDGE + 3 STEPS ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull py-16 lg:py-20" style="background-color:<?= $lav ?>;">
    <div class="<?= $wrap ?>">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-16 mb-16">
            <h2 class="<?= $h2 ?> text-black"><?= $partner['bridge_title'] ?></h2>
            <div>
                <?php foreach ($partner['bridge_paragraphs'] as $para) { ?>
                    <p class="<?= $body ?> text-black/80 mb-4 last:mb-0"><?= $para ?></p>
                <?php } ?>
            </div>
        </div>

        <?= BlockDefaults::renderProcessSteps($stepsData) ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ VALUE PROPOSITION ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull pb-16 lg:pb-20" style="background-color:<?= $lav ?>;">
    <div class="<?= $wrap ?>">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-16 mb-12">
            <h2 class="<?= $h2 ?> text-black"><?= $partner['value_title'] ?></h2>
            <div>
                <?php foreach ($partner['value_paragraphs'] as $para) { ?>
                    <p class="<?= $body ?> text-black/80 mb-4 last:mb-0"><?= $para ?></p>
                <?php } ?>
            </div>
        </div>

        <?= BlockDefaults::renderFeatureCards('4', [], $valueCards, '280/147') ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ CLIENT REVIEWS ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull" style="background-color:<?= $lav ?>;">
    <!-- wp:pattern {"slug":"remote-leverage/hire-va-4-testimonials"} /-->
</div>
<!-- /wp:group -->

<!-- ============ BEYOND THE VIRTUAL ASSISTANT ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull py-16 lg:py-20" style="background-color:<?= $deepBg ?>;">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-white text-center mb-3"><?= $partner['roles_title'] ?></h2>
        <p class="<?= $body ?> text-white/80 text-center max-w-[560px] mx-auto mb-10">We specialize in sourcing English-fluent professionals globally for roles that require high-level execution.</p>

        <?= BlockDefaults::renderDepartmentCards([], $roles) ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ SCALE SMARTER, NOT MORE EXPENSIVE ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull py-16 lg:py-20" style="background-color:<?= $lav ?>;">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-black mb-10">Scale Smarter,<br class="hidden sm:block" /> Not More Expensive.</h2>

        <div class="mb-6">
            <?= BlockDefaults::renderFeatureCards('4', [], $pricing, '247/190') ?>
        </div>

        <?= BlockDefaults::renderDataTable([], $tableRows, 'DIY', $partner['table_heading']) ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ THREE STEPS TO A FULLY STAFFED TEAM ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull" style="background-color:<?= $lav ?>;">
    <?= BlockDefaults::renderProgressSteps(
        ['headline' => 'Three Steps<br> to a Fully Staffed Team'],
        [
            ['title' => 'Strategy Sync', 'text' => 'We identify the specialized roles you need to scale.'],
            ['title' => 'The Shortlist', 'text' => $partner['shortlist_desc']],
            ['title' => 'Onboarding', 'text' => $partner['onboarding_desc']],
        ],
    ) ?>
</div>
<!-- /wp:group -->

<!-- ============ CLOSING CTA ============ -->
<?= BlockDefaults::renderCtaBanner([
    'headline' => $partner['final_title'],
    'subheadline' => $partner['final_paragraph'],
    'cta_text' => $partner['final_cta_text'],
    'cta_url' => $partner['hero_cta_url'],
    'variant' => 'band',
]) ?>
