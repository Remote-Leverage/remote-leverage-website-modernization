<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Contractor Management for Remote Teams
 * Slug: remote-leverage/contractor-management
 * Categories: remote-leverage
 * Description: Contractor of Record & Workforce Management landing page, rebuilt to match production layout, typography and imagery (2026-09-14).
 *
 * Production also ships two sections that are display:none at every breakpoint
 * ("One source of truth for your entire workforce" and "Use everything, or only what you
 * need"). They are intentionally not reproduced here — rendering them would put content
 * on the page that production never shows.
 */
$img = fn (string $file): string => get_theme_file_uri('public/images/contractor-management/'.$file);

// Production design tokens, read off the live page with getComputedStyle.
$deep = 'var(--color-brand-dark-violet)';
$h1 = 'font-display font-bold text-[34px] leading-[40px] sm:text-[48px] sm:leading-[54px] tracking-[-1.44px]';
$h2 = 'font-display font-bold text-[32px] leading-[38px] sm:text-[42px] sm:leading-[48px] tracking-[-1.26px]';
$h3 = 'font-display font-bold text-[22px] leading-[27px] sm:text-[27px] sm:leading-[32px] tracking-[-0.81px]';
$body = 'text-[16px] leading-[26px]';
$wrap = 'w-full max-w-[1380px] mx-auto px-5 sm:px-6 lg:px-8';
$arrow = '<svg class="w-[22px] h-[22px] shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="11" stroke="currentColor" stroke-width="1.5"/><path d="M10 8l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

// Keys match the acf/feature-cards repeater (img, title, desc).
$card = fn (array $c): array => ['img' => $img($c[0]), 'title' => $c[1], 'desc' => $c[2]];

$everything = array_map($card, [
    ['Frame-76-9.jpg', 'Top-Tier Latin American Talent Onboard in Minutes', 'Local-ready contracts, ID verification, digital signing.'],
    ['Frame-76-10.jpg', 'Pay Anywhere', 'Pay anyone, regardless of local currency, and manage payouts.'],
    ['Frame-76-11.jpg', 'Manage at Scale', 'Centralized HRIS, work tracking, performance reporting.'],
]);

$service = array_map($card, [
    ['Frame-76-16.jpg', 'Onboard<br>& Verify', 'Localized contracts, ID and tax verification, and digital signing. All in a guided flow your contractors can complete in minutes.'],
    ['Frame-76-17.jpg', 'Global Payments &<br>Payouts', 'Pay every contractor on time, regardless of local currency.'],
    ['Frame-76-18.jpg', 'Compliance, Handled<br>Quietly', 'Tax ID verification, automated invoicing, and a full audit trail.'],
    ['Frame-76-19.jpg', 'Continuous<br>Coverage', 'Even if you change personnel, you’re still covered compliantly.'],
]);

// Keys match the acf/process-step-cards repeater (number, title, text, image).
$steps = array_map(fn (array $s): array => [
    'number' => $s[0], 'title' => $s[1], 'text' => $s[2], 'image' => $img($s[3]),
], [
    ['01', 'Tell us about your team', 'Tell us where your contractors are and how you pay them today.', 'Frame-115.jpg'],
    ['02', 'Onboard your contractors', 'Onboard in less than 24 hours, with the right contracts and forms for each country.', 'Frame-115-1.jpg'],
    ['03', 'Manage, pay, and scale', 'Run payouts and track hours for individuals or entire teams. Add new countries and contractors easily.', 'Frame-115-2.jpg'],
]);

// Keys match the acf/roles-carousel repeater (icon, title, text).
$industries = array_map(fn (array $c): array => ['icon' => $img($c[0]), 'title' => $c[1], 'text' => $c[2]], [
    ['ICONS-16.png', 'Construction<br>& Trades', 'Manage VAs who handle scheduling, estimates, and back-office admin.'],
    ['ICONS-17.png', 'Medical &<br>Healthcare', 'Onboard healthcare assistants and providers, compliant and credential-ready.'],
    ['ICONS-18.png', 'Finance &<br>Accounting', 'Compliantly hire support for bookkeeping, data entry, and client admin.'],
    ['E-commerce-1.png', 'E-commerce<br>& Retail', 'Manage VAs for customer support, order processing, and listings.'],
    ['ICONS-19.png', 'Tech<br>& SaaS', 'Scale faster and manage VAs ready to start in hours, not weeks.'],
    ['ICONS-21.png', 'Legal', 'Compliantly manage VAs to handle intake, document prep, and case admin.'],
]);

$sourcing = array_map($card, [
    ['Group-70.png', 'Elite recruiters', 'Specialized recruiters who know where to find the talent you need.'],
    ['Frame-76-14.jpg', 'Pre-vetted pipeline', 'Pre-screened, English-fluent candidates ready to onboard immediately.'],
    ['Frame-76-15.jpg', 'Fast fulfillment', 'Most placements completed in under a week.'],
]);

$statIcons = [
    '<circle cx="9" cy="8" r="3"/><path d="M3.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/><circle cx="17" cy="9" r="2.2"/><path d="M15 19c0-2.2 1.3-3.8 3.4-3.8 1.3 0 2.1.6 2.6 1.4"/>',
    '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.4 2.5 3.6 5.7 3.6 9s-1.2 6.5-3.6 9c-2.4-2.5-3.6-5.7-3.6-9s1.2-6.5 3.6-9z"/>',
    '<path d="M12 20a8 8 0 1 1 8-8"/><path d="M12 12l4.5-3"/><circle cx="12" cy="12" r="1.4"/>',
];
$faqs = [
    ['question' => 'What is contractor management?', 'answer' => 'It’s everything involved in engaging, paying, and overseeing independent contractors, handled as one service. Remote Leverage acts as your Contractor of Record, formally engaging and managing your contractors anywhere in the world and handling compliance for you. By making sure every contractor is properly classified and contracted, we take on the risk and the headache of potential fines and fees.'],
    ['question' => 'What is a Contractor of Record?', 'answer' => 'A Contractor of Record (COR) is a third party that formally engages and manages independent contractors on your behalf, anywhere in the world. As your COR, Remote Leverage makes sure each contractor is correctly classified and contracted, which keeps you compliant and protects you from misclassification risk. You get the contractors you need without setting up local entities or tracking foreign rules yourself. If you work with independent contractors in other countries, or plan to, contractor management is usually worth it. Book a demo and we’ll tell you honestly whether it’s a fit.'],
    ['question' => 'What countries do you service?', 'answer' => 'We currently support contractors in 25+ countries and are always expanding. Whether your team is in one country or spread across several, we help you onboard, pay, and manage everyone in one place. Tell us where your contractors are and we’ll confirm coverage for each location.'],
    ['question' => 'How fast can I onboard my contractors?', 'answer' => 'Most contractors are onboarded in less than 24 hours. Once your account is set up, we generate the right contracts and forms for each country, and your contractors complete ID verification and signing from their phone in minutes. You can start adding people the same day you get started.'],
    ['question' => 'How do contractors get paid?', 'answer' => 'Once a contractor is onboarded, they’re ready to receive payments in their virtual wallet. We verify contractor timesheets and compile all the information for the pay cycle. You receive one invoice. Once the invoice is approved and payment is funded, contractors receive funds through their virtual wallet. Payments are made in USD.'],
    ['question' => 'How do taxes and payouts work?', 'answer' => 'We run payouts for your entire contractor workforce, across as many countries as you need, compliantly. We make sure each contractor is properly classified and contracted under the laws where they live. That keeps your payments compliant and lowers your risk without adding work on your end. We perform identification verification to ensure all payouts are secure.'],
    ['question' => 'Can you help me find contractors, not just manage them?', 'answer' => 'Yes. Alongside contractor management, we can also help you find talent! Remote Leverage offers one of the most powerful Contractor of Record services in the industry because we can find talent and help you pay them compliantly. Our recruiters work from a pre-vetted, English-fluent talent pool, and most placements are completed in under a week. Once we find your people, we onboard them into the same service you already use.'],
    ['question' => 'How do I get started?', 'answer' => 'Tell us about your team, where your contractors are, and how you pay them today. We’ll get your account set up and have you onboarding contractors within 24 hours so you can manage payouts compliantly. Book a demo or get a quote to get started.'],
];
$faqData = [];
BlockDefaults::encodeRepeater('faqs', 'field_accordion_faq_block_faqs', $faqs, $faqData);
$faqData['headline'] = 'Frequently Asked Questions';
$faqBlock = BlockDefaults::patternBlock('accordion-faq', $faqData, ['align' => 'full']);
?>
<!-- ============ HERO ============ -->
<!-- wp:acf/contractor-management-hero {"name":"acf/contractor-management-hero","data":{},"align":"full","mode":"preview"} /-->

<!-- ============ TRUSTED BY (dark strip) ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull py-7 [&_img]:brightness-0 [&_img]:invert" style="background-color:<?= $deep ?>;">
    <p class="text-center text-[11px] font-bold uppercase tracking-[0.18em] text-white/80 mb-6">Trusted by scaling teams globally</p>
    <!-- wp:acf/client-logos-marquee {"name":"acf/client-logos-marquee","align":"full","mode":"preview"} /-->
</div>
<!-- /wp:group -->

<!-- ============ EVERYTHING YOU NEED ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background py-16 lg:py-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-black mb-10 max-w-[720px]">Everything you need to run a global contractor workforce</h2>

        <?= BlockDefaults::renderFeatureCards('3', [], $everything, '396/244') ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ A SERVICE BUILT FOR HOW YOU ACTUALLY WORK ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pb-16 lg:pb-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-black mb-3">A service built<br class="hidden sm:block" /> for how you actually work</h2>
        <p class="<?= $body ?> text-black/75 mb-10">Contractor management software, the way it should work.</p>

        <?= BlockDefaults::renderFeatureCards('4', [], $service, '297/156') ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ GET STARTED ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull py-16 lg:py-20" style="background-color:rgba(146,180,244,0.2);">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-black mb-8">Get started</h2>

        <!-- acf/process-step-cards renders its own <section> (bg + padding); this page supplies
             the surrounding band, so the block's own chrome is neutralised here. -->
        <div class="[&>section]:bg-transparent [&>section]:py-0 [&_.rl-container]:max-w-none [&>section>div]:px-0">
            <?= BlockDefaults::renderProcessStepCards($steps) ?>
        </div>

        <a href="/vacalendar" class="mt-5 flex w-full items-center justify-center rounded-full bg-black px-8 py-5 font-display text-[19px] font-bold uppercase tracking-[-0.57px] text-white transition hover:opacity-90">
            Book a demo
        </a>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ BUILT FOR THE WAY YOU HIRE ============ -->
<?= BlockDefaults::renderRolesCarousel([
    'headline' => 'Built for the way you hire',
    'subheadline' => 'The service that adapts to your industry and workflow.',
], $industries) ?>

<!-- ============ CLIENT REVIEWS ============ -->
<!-- wp:pattern {"slug":"remote-leverage/hire-va-4-testimonials"} /-->

<!-- ============ PRICING BUILT AROUND YOUR TEAM ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background py-16 lg:py-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <div class="rounded-3xl relative overflow-hidden grid grid-cols-1 lg:grid-cols-2 gap-8 items-end" style="background-color:<?= $deep ?>;">
            <div class="p-9 sm:p-12 lg:pb-14">
                <h2 class="<?= $h2 ?> text-white mb-6">Pricing built<br class="hidden sm:block" /> around your team</h2>
                <p class="text-[22px] leading-[30px] text-white/85 mb-12 max-w-[420px]">We’ll quote based on your team size, regions, and individual needs.</p>
                <a href="/vacalendar" class="inline-flex w-full max-w-[480px] items-center justify-between gap-3 rounded-full bg-white px-8 py-4 font-display text-[17px] font-bold uppercase tracking-[-0.51px] text-black transition hover:opacity-90">
                    Book a demo <?= $arrow ?>
                </a>
            </div>

            <div class="relative min-h-[320px] lg:min-h-[420px]">
                <img src="<?= $img('person.png') ?>" alt="" width="536" height="512" class="absolute right-6 sm:right-16 bottom-0 h-[92%] w-auto object-contain" loading="lazy" />
                <div class="absolute inset-x-4 sm:inset-x-8 bottom-6 grid grid-cols-2 gap-4">
                    <div class="rounded-xl p-4 backdrop-blur-sm" style="background-color:rgba(244,246,252,0.18);">
                        <div class="flex items-center justify-between gap-2 mb-6">
                            <p class="text-[13px] text-white">Contractors</p>
                            <img src="<?= $img('paises.png') ?>" alt="" width="77" height="19" class="h-4 w-auto" loading="lazy" />
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <img src="<?= $img('Frame-24.png') ?>" alt="" width="137" height="41" class="h-8 w-auto" loading="lazy" />
                            <p class="text-[15px] font-bold text-white">+15</p>
                        </div>
                    </div>
                    <div class="rounded-xl p-4 backdrop-blur-sm" style="background-color:rgba(244,246,252,0.18);">
                        <div class="flex items-center justify-between gap-2 mb-4">
                            <p class="text-[13px] text-white">Payments</p>
                            <img src="<?= $img('paises.png') ?>" alt="" width="77" height="19" class="h-4 w-auto" loading="lazy" />
                        </div>
                        <div class="h-px w-full bg-white/30 mb-4"></div>
                        <div class="flex items-end justify-between gap-2">
                            <p class="text-[17px] font-bold text-white">USD 37,841</p>
                            <span class="text-[12px] text-white/75">Paid</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ NEED TO FIND THE RIGHT CONTRACTORS ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull py-16 lg:py-20" style="background-color:<?= $deep ?>;">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-[#F4F6FC] mb-3">Need to find the right contractors?<br class="hidden sm:block" /> We can help.</h2>
        <p class="<?= $body ?> text-white/70 mb-10">The service that adapts to your industry and workflow.</p>

        <?= BlockDefaults::renderFeatureCards('3', [], $sourcing, '402/249') ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ FAQ ============ -->
<?= $faqBlock ?>

<!-- ============ BOOKING FOOTER ============ -->
<!-- wp:acf/booking-footer {"name":"acf/booking-footer","data":{"headline":"Ready to simplify how you manage contractors?","description":"Let’s find the specialized talent your business needs!"},"align":"full","mode":"preview"} /-->
