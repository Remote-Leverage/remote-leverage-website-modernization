<?php
/**
 * Title: Full Page - Remote Leverage x Oyster
 * Slug: remote-leverage/remote-leverage-x-oyster
 * Categories: remote-leverage
 * Description: 1:1 complete co-branded landing page for Remote Leverage and Oyster partnership.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"6rem","bottom":"6rem"}}},"backgroundColor":"brand-midnight","className":"rl-co-branded-hero relative overflow-hidden bg-[#250D4A] text-white","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull rl-co-branded-hero relative overflow-hidden bg-[#250D4A] text-white has-brand-midnight-background-color has-background" style="padding-top:6rem;padding-bottom:6rem">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-10">
            <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-pill bg-white/10 border border-white/15 text-xs font-bold uppercase tracking-wider text-purple-200 mb-6 backdrop-blur-sm">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>Strategic Global Talent Partnership</span>
            </div>

            <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-white leading-[1.1] mb-6">
                Remote Leverage &times; Oyster
            </h1>

            <p class="text-base sm:text-xl text-slate-200 leading-relaxed mb-8">
                Hire the right people globally. Employ and pay them compliantly in 180+ countries with top-tier Latin American talent and seamless EOR infrastructure.
            </p>

            <div class="flex flex-wrap items-center justify-center gap-4">
                <a href="#booking-footer" class="px-8 py-3.5 rounded-pill bg-gradient-to-r from-brand-purple to-brand-magenta hover:opacity-95 text-white font-bold text-sm shadow-btn transition cursor-pointer">
                    Book a Strategy Call &rarr;
                </a>
                <a href="#partnership-details" class="px-8 py-3.5 rounded-pill bg-white/10 hover:bg-white/15 text-white font-semibold text-sm border border-white/20 transition cursor-pointer backdrop-blur-sm">
                    How We Partner
                </a>
            </div>
        </div>

        <!-- 4 Stat Metric Badges -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto pt-6 border-t border-white/10 text-center">
            <div class="bg-white/5 border border-white/10 rounded-2xl p-4 backdrop-blur-sm">
                <span class="font-display text-3xl font-bold text-white block">70%</span>
                <span class="text-xs font-semibold text-purple-200/80">Lower Cost vs US</span>
            </div>
            <div class="bg-white/5 border border-white/10 rounded-2xl p-4 backdrop-blur-sm">
                <span class="font-display text-3xl font-bold text-white block">48 hrs</span>
                <span class="text-xs font-semibold text-purple-200/80">Average Match Time</span>
            </div>
            <div class="bg-white/5 border border-white/10 rounded-2xl p-4 backdrop-blur-sm">
                <span class="font-display text-3xl font-bold text-white block">180+</span>
                <span class="text-xs font-semibold text-purple-200/80">Countries Covered</span>
            </div>
            <div class="bg-white/5 border border-white/10 rounded-2xl p-4 backdrop-blur-sm">
                <span class="font-display text-3xl font-bold text-white block">100%</span>
                <span class="text-xs font-semibold text-purple-200/80">Replacement Guarantee</span>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- wp:acf/talent-marquee {"name":"acf/talent-marquee","align":"full","mode":"preview"} /-->

<!-- wp:acf/client-logos-marquee {"name":"acf/client-logos-marquee","align":"full","mode":"preview"} /-->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"5rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div id="partnership-details" class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:5rem">
    <!-- wp:acf/feature-cards {"name":"acf/feature-cards","data":{"headline":"The Bridge Between Employment Infrastructure and Execution","columns":"3","cards":[{"title":"Fast Talent Sourcing","desc":"Remote Leverage sources, vets, and screens the top 1% of English-fluent professionals in Latin America for your exact roles.","badge":"Sourcing"},{"title":"Seamless EOR & Payroll","desc":"Oyster provides global employer of record, localized contracts, benefits, and compliant payroll across 180+ countries.","badge":"Compliance"},{"title":"Ongoing Retention & Success","desc":"Dedicated talent success managers ensure seamless day-to-day operations and high long-term retention.","badge":"Support"}]},"align":"full","mode":"preview"} /-->
</div>
<!-- /wp:group -->

<!-- wp:pattern {"slug":"remote-leverage/beyond-virtual-assistant"} /-->

<?= \App\Support\BlockDefaults::renderProcessSteps([
    'headline' => 'Three Steps to a Fully Staffed Team',
    'badge' => 'Simple Co-Branded Onboarding',
    'steps' => [
        [
            'number' => '01',
            'title' => 'Define Your Role Requirements',
            'desc' => 'Share your target job description, required tools, and budget on our 15-minute consultation call.',
        ],
        [
            'number' => '02',
            'title' => 'Interview Pre-Vetted Candidates',
            'desc' => 'Within 48 hours, interview 3 curated professionals with verified references and background checks.',
        ],
        [
            'number' => '03',
            'title' => 'Onboard Compliantly via Oyster',
            'desc' => 'Execute contracts, set up payroll, and welcome your new hire with full compliance protection.',
        ],
    ],
]) ?>

<!-- wp:pattern {"slug":"remote-leverage/hire-va-4-testimonials"} /-->

<!-- wp:pattern {"slug":"remote-leverage/hire-va-4-booking-footer"} /-->
