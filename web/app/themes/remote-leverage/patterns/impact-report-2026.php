<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - 2026 Impact Report
 * Slug: remote-leverage/impact-report-2026
 * Categories: remote-leverage
 * Description: 2026 Global Workforce Impact Report landing page, rebuilt to match production layout, typography and imagery (2026-09-14).
 */
$img = fn(string $file): string => get_theme_file_uri('public/images/impact-report-2026/' . $file);
$flag = fn(string $file): string => get_theme_file_uri('public/images/impact-report-2026/flags/' . $file);

// Production design tokens, read off the live page with getComputedStyle.
$lav = 'var(--color-lavender-tint)';   // page background
$deep = 'var(--color-brand-dark-violet)';  // dark purple panels
$h1 = 'font-display font-bold text-[34px] leading-[40px] sm:text-[46px] sm:leading-[53px] tracking-[-1.44px]';
$h3 = 'font-display font-bold text-[22px] leading-[27px] sm:text-[27px] sm:leading-[32px] tracking-[-0.81px]';
$body = 'text-[16px] leading-[26px]';
$wrap = 'w-full max-w-[1380px] mx-auto px-5 sm:px-6 lg:px-8';
$arrow = '<svg class="w-[18px] h-[18px] shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="11" stroke="currentColor" stroke-width="1.5"/><path d="M10 8l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

// Keys match the acf/feature-cards repeater (img, title, desc).
$findings = array_map(fn(array $c): array => ['img' => $img($c[0]), 'title' => $c[1], 'desc' => $c[2]], [
    ['magnific_LUCl1DBswO-1.jpg', 'The heart of our global impact.', 'Almost 80% of professionals we place call LATAM and the Caribbean home, reflecting the depth of talent in both regions.'],
    ['Frame-76-8.jpg', 'Life-changing income.', 'Professionals placed through Remote Leverage earn an average of 4.2x their local minimum wage.'],
    ['five-markets.jpg', 'Five markets anchor the regions', 'Mexico, Colombia, Honduras, Jamaica, and Brazil together account for more than half of all hires across the region.'],
    ['deep-skill.jpg', 'Deep, skill-rich range.', 'From Telehealth Providers to Operations Managers, US businesses are tapping specialized talent in dozens of roles.'],
    ['opportunity.jpg', 'Opportunity is everywhere.', 'Even in higher-income markets like Barbados and Costa Rica, Remote Leverage rates still outpace national averages.'],
    ['impact-reaches.jpg', 'Impact reaches families and communities.', 'Higher household income changes lives and communities. In Jamaica, women make up nearly 80% of our placements.'],
]);


$reportContents = [
    ['Country-by-country placement data', '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.4 2.5 3.6 5.7 3.6 9s-1.2 6.5-3.6 9c-2.4-2.5-3.6-5.7-3.6-9s1.2-6.5 3.6-9z"/>'],
    ['The top 10 roles in demand', '<path d="M4 20V9M10 20V4M16 20v-7M21 6l-3-3-3 3M18 3v9"/>'],
    ['Pay comparisons against national wages', '<circle cx="12" cy="12" r="9"/><path d="M14.5 9.5c-.5-.9-1.5-1.4-2.5-1.4-1.4 0-2.5.8-2.5 1.9s1.1 1.6 2.5 1.9 2.5.8 2.5 1.9-1.1 1.9-2.5 1.9c-1 0-2-.5-2.5-1.4M12 6.5v11"/>'],
    ['First-hand stories from the people behind the numbers.', '<path d="M12 3l9 4.5-9 4.5-9-4.5L12 3z"/><path d="M3 12l9 4.5 9-4.5M3 16.5L12 21l9-4.5"/>'],
];
?>
<!-- ============ HERO ============ -->
<!-- wp:acf/impact-report-hero {"name":"acf/impact-report-hero","data":{},"align":"full","mode":"preview"} /-->

<!-- ============ WHAT'S INSIDE + KEY FINDINGS ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull pt-10 pb-16 lg:pb-20" style="background-color:<?= $lav ?>;">
    <div class="<?= $wrap ?>">
        <div class="max-w-[720px] mx-auto text-center mb-12">
            <h2 class="<?= $h1 ?> text-black mb-4">What’s inside</h2>
            <p class="<?= $body ?> text-black/75">
                Our first Impact Report looks at the data behind every Remote Leverage placement since the company was founded through June 2024, and what it reveals about the talent across Latin America and the Caribbean.
            </p>
        </div>

        <h2 class="<?= $h1 ?> text-black text-center mb-10">Key findings</h2>

        <?= BlockDefaults::renderFeatureCards('3', [], $findings, '413/158', 'flush') ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ THE REAL STORY ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull bg-white py-16 lg:py-20">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h1 ?> text-black mb-8">The Real Story Behind the Numbers</h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-16">
            <div>
                <p class="<?= $body ?> text-black/75 mb-5">
                    When Remote Leverage started in late 2024, the goal was simple: connect US businesses with exceptional professionals, on terms that are fair to everyone. A little over a year later, we wanted to look at the data and see what kind of impact that work had made.
                </p>
                <p class="<?= $body ?> font-bold text-black">What we found surprised even us.</p>
            </div>
            <div>
                <p class="<?= $body ?> text-black/75 mb-5">
                    Over 2,000 professionals across 61 countries and six continents have now found work through Remote Leverage in roles spanning Healthcare Providers, Full Stack Developers, Legal Case Managers, Paid Ads Managers, and dozens more.
                </p>
                <p class="<?= $body ?> text-black/75">
                    But two regions stood out above all others. Nearly 79% of every placement worldwide came from Latin America and the Caribbean.
                </p>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ COUNTRY PLACEMENTS ============ -->
<?= BlockDefaults::renderCountryPlacements() ?>

<!-- ============ THE REASON + QUOTE ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull bg-white pb-16 lg:pb-20">
    <div class="<?= $wrap ?>">
        <div class="rounded-2xl border border-black/10 p-6 sm:p-8 grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-6 lg:gap-10 items-center mb-14">
            <div class="flex items-center gap-4">
                <img src="<?= $img('bullseye-1.png') ?>" alt="" class="w-11 h-11 object-contain shrink-0" loading="lazy" />
                <h3 class="font-display font-bold text-[20px] leading-[26px] tracking-[-0.6px] text-black">The reason is straightforward.</h3>
            </div>
            <p class="text-[15px] leading-[24px] text-black/75">
                These regions are home to a large, young, and increasingly educated workforce, with deep cultural alignment to US business, compatible time zones, and a strong tradition of English-language professional work. The talent has always been there.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_420px] gap-10 lg:gap-16 items-start mb-14">
            <div>
                <div class="flex gap-1 mb-6" aria-label="5 out of 5 stars">
                    <?php for ($i = 0; $i < 5; $i++) { ?>
                        <svg class="w-8 h-8 text-[#F5B301]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.3 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z"/></svg>
                    <?php } ?>
                </div>
                <h3 class="<?= $h1 ?> text-black">“This opportunity has honestly changed my life in a big way.”</h3>
            </div>

            <div class="rounded-2xl overflow-hidden border border-black/10">
                <img src="<?= $img('Oscar-Manuel-Herrera-Rosales_Mexico-1-1.png') ?>" alt="Oscar Manuel Herrera Rosales" class="w-full h-auto object-cover" loading="lazy" />
                <div class="flex items-center justify-between gap-4 p-5">
                    <div>
                        <h6 class="font-display font-bold text-[16px] leading-[21px] text-black">Oscar Manuel Herrera Rosales</h6>
                        <p class="text-[13px] text-black/60">Sales Virtual Assistant</p>
                    </div>
                    <div class="text-center shrink-0">
                        <img src="<?= $flag('mexico.png') ?>" alt="" class="w-8 h-8 rounded-full object-cover mx-auto mb-1" loading="lazy" />
                        <p class="text-[11px] text-black/60">Mexico</p>
                    </div>
                </div>
            </div>
        </div>

        <h3 class="font-display font-bold text-[22px] leading-[28px] tracking-[-0.66px] text-black mb-5">What’s often missing is access to employers who recognize it.</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-16">
            <p class="text-[15px] leading-[24px] text-black/75">
                That gap shows up clearly in the numbers. On average, professionals placed through Remote Leverage earn 4.2x their local minimum wage and in some markets, far more. These aren’t just better paychecks. Job placements through Remote Leverage provide income that reshapes a household: a bigger home, better schooling, more time with family.
            </p>
            <p class="text-[15px] leading-[24px] text-black/75">
                For the US businesses we serve, the upside is immediate: experienced, motivated professionals who are ready to contribute from day one. For the professionals, it’s an income that finally reflects their skills – often for the first time.
            </p>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ THE FULL REPORT HAS THE COMPLETE PICTURE ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull pb-16 lg:pb-20" style="background-color:#F4F6FC;">
    <div class="<?= $wrap ?> pt-16 lg:pt-20">
        <h3 class="<?= $h1 ?> text-black mb-8">The full report has the complete picture:</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <?php foreach ($reportContents as [$label, $icon]) { ?>
                <div class="rounded-xl border border-black/10 bg-white/70 px-6 py-6 flex items-center justify-between gap-6">
                    <h4 class="text-[16px] leading-[24px] text-black"><?= $label ?></h4>
                    <svg class="w-8 h-8 shrink-0 text-black/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icon ?></svg>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ SEE THE FULL IMPACT ============ -->
<?= BlockDefaults::renderMediaCopy([
    'headline' => 'See the full impact',
    'body' => '<p>Get the complete 2026 Impact Report, including every country profile, pay-rate comparison, and talent story.</p>',
    'cta_text' => 'Download now',
    'cta_url' => '#impact-report-name',
    'image' => $img('Full-impact-1.jpg'),
    'image_position' => 'right',
    'tone' => 'dark',
]) ?>

<!-- ============ HIRING NOW ============ -->
<?= BlockDefaults::renderCtaBanner([
    'headline' => 'Hiring now?',
    'subheadline' => '',
    'cta_text' => 'Book a consultation',
    'cta_url' => '/vacalendar',
    'variant' => 'band',
]) ?>
