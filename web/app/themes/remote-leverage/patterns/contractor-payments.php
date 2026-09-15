<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Contractor Payments
 * Slug: remote-leverage/contractor-payments
 * Categories: remote-leverage
 * Description: Contractor Payments product landing page, rebuilt to match production layout, typography and imagery (2026-09-14).
 */
$img = fn (string $file): string => get_theme_file_uri('public/images/contractor-payments/'.$file);

// Production design tokens, read off the live page with getComputedStyle.
$h2 = 'font-display font-bold text-[32px] leading-[38px] sm:text-[42px] sm:leading-[48px] tracking-[-1.26px]';
$h3 = 'font-display font-bold text-[22px] leading-[27px] sm:text-[27px] sm:leading-[32px] tracking-[-0.81px]';
$body = 'text-[16px] leading-[26px]';
$wrap = 'w-full max-w-[1380px] mx-auto px-5 sm:px-6 lg:px-8';
$btn = 'inline-flex items-center gap-3 rounded-full bg-brand-purple px-8 py-4 sm:px-10 sm:py-5 font-display text-[17px] sm:text-[20px] font-bold uppercase tracking-[-0.6px] text-white transition hover:opacity-90';
$arrow = '<svg class="w-6 h-6 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="11" stroke="currentColor" stroke-width="1.5"/><path d="M10 8l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

$steps = [
    ['01', 'Contractors log hours', 'Hours and deliverables land in one dashboard. We verify every timesheet against contracts.'],
    ['02', 'You approve one invoice', 'We compile the full cycle, every contractor and every country, into one consolidated invoice.'],
    ['03', 'All contractors get paid', 'You issue one payment, then all funds land in each contractor’s virtual wallet, converted to local currency upon withdrawal.'],
];

// Keys match the acf/feature-cards repeater (img, title, desc).
$card = fn (array $c): array => ['img' => $img($c[0]), 'title' => $c[1], 'desc' => $c[2]];

$platformCards = array_map($card, [
    ['Frame-1146.png', 'Every contractor, one invoice', 'Consolidated into a single approval each cycle, no matter how many countries you pay into.'],
    ['Frame-76-1.png', 'Every country, one process', 'The same onboarding, verification, and payout flow everywhere we operate.'],
    ['Every-payment-compliant.webp', 'Every payment, compliant', 'Classification and contracts settled before the first payout goes out.'],
]);

$compliance = [
    ['Correct classification', 'Every contractor classified and contracted under local law, in every country we cover.'],
    ['Localized contracts', 'Country-specific agreements, signed digitally in minutes.'],
    ['Documentation on file', 'Tax ID verification and records maintained with a full audit trail.'],
];

$industries = array_map($card, [
    ['Frame-76-2.webp', 'Construction & Trades', 'Pay contractors handling scheduling, estimates, and back-office admin.'],
    ['Frame-1128.webp', 'Medical & Healthcare', 'Onboard and pay healthcare assistants and providers, compliant and credential-ready.'],
    ['Frame-1129.webp', 'Finance & Accounting', 'Compliantly pay support for bookkeeping, data entry, and client admin.'],
    ['Frame-1130.webp', 'E-commerce & Retail', 'Pay contractors across customer support, order processing, and listings.'],
    ['Frame-1131-2.webp', 'Tech & SaaS', 'Scale faster with contractors ready to start in hours, not weeks.'],
    ['Frame-1132-1.webp', 'Legal', 'Compliantly pay contractors handling intake, document prep, and case admin.'],
]);

$recruiting = array_map(fn (array $c): array => ['img' => $img($c[0]), 'title' => $c[1], 'desc' => $c[2]], [
    ['Frame-1133.webp', 'Elite recruiters', 'Specialized recruiters who know where to find the talent you need.'],
    ['Frame-1134-1.webp', 'Pre-vetted pipeline', 'Pre-screened, English-fluent candidates ready to onboard immediately.'],
    ['Frame-1135.webp', 'Fast fulfillment', 'Most placements completed in under a week.'],
]);
?>
<!-- ============ HERO ============ -->
<!-- wp:acf/contractor-payments-hero {"name":"acf/contractor-payments-hero","data":{},"align":"full","mode":"preview"} /-->

<!-- ============ TRUSTED BY ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull bg-white pt-8 pb-4">
    <p class="text-center text-[11px] font-bold uppercase tracking-[0.18em] text-black/55">Trusted by scaling teams globally</p>
</div>
<!-- /wp:group -->

<!-- wp:acf/client-logos-marquee {"name":"acf/client-logos-marquee","align":"full","mode":"preview"} /-->

<!-- ============ PAY CONTRACTORS ACROSS BORDERS ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background py-16 lg:py-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-16 mb-12">
            <h2 class="<?= $h2 ?> text-black">Pay contractors across borders, all in one platform</h2>
            <div>
                <p class="<?= $body ?> font-bold text-black mb-4">Remote Leverage takes the entire pay cycle off your plate.</p>
                <p class="<?= $body ?> text-black/75">No more wires from three different bank portals. Or invoices chased over email. Say goodbye to spreadsheets that never quite reconcile. And all those tricky classification questions nobody on the team wants to answer.</p>
            </div>
        </div>

        <?= BlockDefaults::renderFeatureCards('3', [], $platformCards, '413/152', 'flush') ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ HOW IT WORKS ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pb-16 lg:pb-20" id="payment-flow" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-black mb-4">How it works</h2>
        <p class="<?= $body ?> text-black/75 max-w-[620px] mb-12">
            You want to scale your team globally so you can grow smarter, faster. Endless admin from complicated payments and payouts should not be holding you back.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 lg:gap-10">
            <?php foreach ($steps as [$num, $title, $desc]) { ?>
                <div>
                    <p class="text-[16px] text-black/60 pb-4"><?= $num ?></p>
                    <div class="h-px w-full bg-black/15 mb-6"></div>
                    <h3 class="<?= $h3 ?> text-black mb-3"><?= $title ?></h3>
                    <p class="text-[14px] leading-[22px] text-black/70"><?= $desc ?></p>
                </div>
            <?php } ?>
        </div>

        <div class="text-center mt-14">
            <a href="/vacalendar" class="<?= $btn ?>">Book a consultation <?= $arrow ?></a>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ CLEAR ON CURRENCY ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull relative overflow-hidden py-16 lg:py-20" style="background-image:linear-gradient(270deg,var(--color-surface-black) 55.97%,var(--color-brand-purple-deep) 100%);">
    <div class="<?= $wrap ?>">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center">
            <div class="rounded-2xl p-9 sm:p-12 backdrop-blur-sm" style="background-color:rgba(244,246,252,0.2);">
                <div class="flex items-start justify-between gap-6 mb-14">
                    <h4 class="font-display font-bold text-[26px] sm:text-[32px] leading-[32px] tracking-[-0.98px] text-white">Countries</h4>
                    <div class="flex -space-x-2 shrink-0">
                        <?php foreach (['#009B3A', '#FCD116', '#75AADB', '#006847'] as $c) { ?>
                            <span class="w-7 h-7 rounded-full border-2 border-white/70" style="background-color:<?= $c ?>;"></span>
                        <?php } ?>
                    </div>
                </div>
                <div class="h-px w-full bg-white/25 mb-8"></div>
                <div class="flex items-end justify-between gap-4">
                    <h4 class="font-display font-bold text-[34px] sm:text-[43px] leading-[43px] tracking-[-1.3px] text-white">USD 50,000</h4>
                    <span class="text-[15px] text-white/75">Paid</span>
                </div>
            </div>

            <div>
                <h2 class="<?= $h2 ?> text-white mb-5">Clear on currency, clear on cost. Always.</h2>
                <p class="<?= $body ?> text-white/85 mb-2">
                    Contractors are paid in USD, with conversion to local currency handled automatically at withdrawal. Your team knows exactly what they are receiving, and you know exactly what you are spending.
                </p>
                <p class="<?= $body ?> font-bold text-white mb-10">One rate, one invoice, no surprises at the end of the month.</p>

                <?php
    $currencyItems = [
        ['<circle cx="12" cy="12" r="9"/><path d="M8.5 12.2l2.4 2.4 4.6-4.8"/>', 'Verified timesheets', 'Every payout is checked against approved hours before it is funded.'],
        ['<path d="M12 3.5l6.5 2.8v5c0 4-2.8 7.6-6.5 8.8-3.7-1.2-6.5-4.8-6.5-8.8v-5L12 3.5z"/><path d="M9.6 12l1.7 1.7 3.2-3.4"/>', 'Identity verification', 'Every contractor is ID-verified at onboarding, so payments go where they are supposed to.'],
        ['<rect x="5.5" y="3.5" width="13" height="17" rx="2"/><path d="M9 8h6M9 12h6M9 16h3.5"/>', 'Full audit trail', 'Every invoice, approval, and payout is logged and exportable.'],
    ];
foreach ($currencyItems as [$icon, $title, $desc]) { ?>
                    <div class="flex gap-4 mb-6 last:mb-0">
                        <span class="shrink-0 w-11 h-11 rounded-xl bg-white/10 border border-white/25 flex items-center justify-center">
                            <svg class="w-[22px] h-[22px] text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icon ?></svg>
                        </span>
                        <div>
                            <h3 class="font-display font-bold text-[20px] leading-[25px] tracking-[-0.6px] text-white mb-1"><?= $title ?></h3>
                            <p class="text-[14px] leading-[22px] text-white/75"><?= $desc ?></p>
                        </div>
                    </div>
                <?php } ?>

                <p class="text-[14px] leading-[22px] text-white/75 mt-8">
                    We handle classification, localized contracts, and tax ID verification handled before a single payment moves.
                </p>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ ONE DASHBOARD ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background py-16 lg:py-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <div class="text-center max-w-[640px] mx-auto mb-12">
            <h2 class="<?= $h2 ?> text-black mb-4">One dashboard for every<br class="hidden sm:block" /> payment you make</h2>
            <p class="<?= $body ?> text-black/75">Contracts, invoices, payouts, and spend for every contractor on your roster.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
            <div class="flex flex-col gap-6">
                <div class="bg-white rounded-2xl p-7 grid grid-cols-1 sm:grid-cols-2 gap-6 items-center">
                    <div>
                        <h2 class="<?= $h3 ?> text-black mb-3">Spend<br class="hidden sm:block" /> Visibility</h2>
                        <p class="text-[14px] leading-[22px] text-black/70">See what you are paying by contractor, country, and role, in real time.</p>
                    </div>
                    <div class="rounded-xl p-5 shadow-[0_10px_40px_rgba(24,17,44,0.08)] border border-black/5 bg-white">
                        <div class="flex items-center justify-between gap-3 mb-5">
                            <div>
                                <p class="font-display font-bold text-[17px] text-black leading-tight">Rafael R.</p>
                                <p class="text-[12px] text-black/55">Designer</p>
                            </div>
                            <img src="<?= $img('Person_04.png') ?>" alt="" width="45" height="45" class="w-[45px] h-[45px] rounded-full object-cover" loading="lazy" />
                        </div>
                        <div class="h-px w-full bg-black/10 mb-4"></div>
                        <p class="text-[12px] text-black/55 mb-1">Montly</p>
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-display font-bold text-[17px] text-black">$ 1,200.00</p>
                            <span class="rounded-md bg-emerald-400 px-3 py-1.5 text-[12px] font-bold text-white">To pay</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-7 grid grid-cols-1 sm:grid-cols-2 gap-6 items-center">
                    <img src="<?= $img('Frame-1131-1.png') ?>" alt="Automated invoicing" width="582" height="550" class="w-full max-w-[291px] h-auto object-contain" loading="lazy" />
                    <div>
                        <h2 class="<?= $h3 ?> text-black mb-3">Automated<br class="hidden sm:block" /> Invoicing</h2>
                        <p class="text-[14px] leading-[22px] text-black/70">Invoices generated on your schedule, with renewals and compliance documents tracked alongside them.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-7 h-full flex flex-col">
                <img src="<?= $img('Frame-1132.webp') ?>" alt="Payment history" width="625" height="409" class="w-full h-auto rounded-xl object-cover mb-6" loading="lazy" />
                <h2 class="<?= $h3 ?> text-black mb-3">Payment History</h2>
                <p class="text-[14px] leading-[22px] text-black/70">A complete record of every payout, ready for your accountant and your auditor.</p>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ PAYING A CONTRACTOR IS THE EASY PART ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background py-16 lg:py-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16">
            <div>
                <h2 class="font-display font-bold text-[34px] leading-[40px] sm:text-[48px] sm:leading-[53px] tracking-[-1.44px] text-black mb-6">
                    Paying a contractor is the easy part.<br class="hidden sm:block" /> Paying them <span class="relative inline-block">correctly<svg class="absolute -inset-x-3 -inset-y-1 w-[calc(100%+24px)] h-[calc(100%+8px)] text-brand-purple" viewBox="0 0 200 60" preserveAspectRatio="none" fill="none" aria-hidden="true"><ellipse cx="100" cy="30" rx="96" ry="26" stroke="currentColor" stroke-width="2.5"/></svg></span> is not.
                </h2>
                <p class="<?= $body ?> text-black/75">
                    Remote Leverage acts as your Contractor of Record. We formally engage each independent contractor under the laws where they live, which means classification, contracts, and tax documentation are our responsibility before they can become your liability. Misclassification is one of the more expensive mistakes in global hiring.
                </p>
            </div>

            <div class="relative pl-8">
                <div class="absolute left-[5px] top-3 bottom-3 w-px bg-black/20"></div>
                <div class="relative mb-10">
                    <span class="absolute -left-8 top-1.5 w-[11px] h-[11px] rounded-full border-[3px] border-black bg-white"></span>
                    <p class="<?= $body ?> text-black/75">This is how you avoid it.</p>
                </div>
                <?php foreach ($compliance as [$title, $desc]) { ?>
                    <div class="relative mb-10 last:mb-0">
                        <span class="absolute -left-8 top-2 w-[11px] h-[11px] rounded-full bg-black"></span>
                        <h3 class="<?= $h3 ?> text-black mb-2"><?= $title ?></h3>
                        <p class="text-[14px] leading-[22px] text-black/70"><?= $desc ?></p>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ BUILT FOR THE WAY YOU HIRE ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pb-16 lg:pb-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-black mb-3">Built for the way you hire</h2>
        <p class="<?= $body ?> text-black/75 mb-10">The service that adapts to your industry and workflow.</p>

        <?= BlockDefaults::renderFeatureCards('3', [], $industries, '431/315', 'flush') ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ CLIENT REVIEWS ============ -->
<!-- wp:pattern {"slug":"remote-leverage/hire-va-4-testimonials"} /-->

<!-- ============ PRICING BUILT AROUND YOUR PAY CYCLE ============ -->
<!-- wp:group {"align":"full","backgroundColor":"black","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-black-background-color has-background relative overflow-hidden" style="background-color:var(--color-surface-black);">
    <div class="<?= $wrap ?>">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center py-16 lg:py-24">
            <div class="hidden lg:flex justify-center">
                <img src="<?= $img('Group-263-1.png') ?>" alt="" width="482" height="427" class="w-full max-w-[482px] h-auto object-contain" loading="lazy" />
            </div>
            <div>
                <h2 class="<?= $h2 ?> text-white mb-5">Pricing built around your pay cycle</h2>
                <p class="<?= $body ?> text-white/80 mb-1">We’ll quote based on your team size, the countries you pay into, and how often you run payouts.</p>
                <p class="<?= $body ?> font-bold text-white mb-10">No contracts, no obligations, and nothing to pay to get started.</p>
                <a href="/vacalendar" class="<?= $btn ?>">Book a demo <?= $arrow ?></a>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ NEED THE TALENT TO HELP YOU GROW ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background py-16 lg:py-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-black mb-5">Need the talent to help you grow?</h2>
        <p class="<?= $body ?> text-black/75 max-w-[660px] mb-10">
            We don’t just offer easy payments through our platform. <strong class="font-bold text-black">We also find you the talent you need to scale your business.</strong> Tell us what you need, and we’ll find you top 1% global talent for 70% less than US hires – in 48 hours.
        </p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
            <?= BlockDefaults::renderFeatureCards('1', [], $recruiting, '287/270', 'horizontal') ?>
            <!-- Production leaves this companion panel empty; kept for layout parity. -->
            <div class="rounded-card bg-white/60 min-h-[320px] hidden lg:block" aria-hidden="true"></div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ FAQ ============ -->
<!-- wp:pattern {"slug":"remote-leverage/hire-va-4-faq"} /-->

<!-- ============ BOOKING FOOTER ============ -->
<!-- wp:acf/booking-footer {"name":"acf/booking-footer","data":{"headline":"Ready to stop chasing contractor payments?","description":"One invoice. Every contractor. Paid on time."},"align":"full","mode":"preview"} /-->
