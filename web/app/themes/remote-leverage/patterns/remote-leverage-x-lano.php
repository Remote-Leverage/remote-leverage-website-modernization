<?php
/**
 * Title: Full Page - Remote Leverage x Lano
 * Slug: remote-leverage/remote-leverage-x-lano
 * Categories: remote-leverage
 * Description: 1:1 complete co-branded landing page for Remote Leverage and Lano partnership.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"6rem","bottom":"6rem"}}},"backgroundColor":"brand-midnight","className":"rl-co-branded-hero relative overflow-hidden bg-[#250D4A] text-white","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull rl-co-branded-hero relative overflow-hidden bg-[#250D4A] text-white has-brand-midnight-background-color has-background" style="padding-top:6rem;padding-bottom:6rem">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-10">
            <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-pill bg-white/10 border border-white/15 text-xs font-bold uppercase tracking-wider text-purple-200 mb-6 backdrop-blur-sm">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>Global Workforce &bull; Lano Integration</span>
            </div>

            <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-white leading-[1.1] mb-6">
                Lano &times; Remote Leverage: Your Global Team, Fully Powered.
            </h1>

            <p class="text-base sm:text-xl text-slate-200 leading-relaxed mb-8">
                You have world-class compliance and global payroll through Lano. Now add top-tier English-fluent talent from Latin America to scale with confidence.
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
                <span class="text-xs font-semibold text-purple-200/80">Cost Efficiency</span>
            </div>
            <div class="bg-white/5 border border-white/10 rounded-2xl p-4 backdrop-blur-sm">
                <span class="font-display text-3xl font-bold text-white block">72 hrs</span>
                <span class="text-xs font-semibold text-purple-200/80">Average Time-to-Hire</span>
            </div>
            <div class="bg-white/5 border border-white/10 rounded-2xl p-4 backdrop-blur-sm">
                <span class="font-display text-3xl font-bold text-white block">170+</span>
                <span class="text-xs font-semibold text-purple-200/80">Countries Supported</span>
            </div>
            <div class="bg-white/5 border border-white/10 rounded-2xl p-4 backdrop-blur-sm">
                <span class="font-display text-3xl font-bold text-white block">100%</span>
                <span class="text-xs font-semibold text-purple-200/80">Risk-Free Guarantee</span>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- wp:acf/talent-marquee {"name":"acf/talent-marquee","align":"full","mode":"preview"} /-->

<!-- wp:acf/client-logos-marquee {"name":"acf/client-logos-marquee","align":"full","mode":"preview"} /-->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"5rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div id="partnership-details" class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:5rem">
    <!-- wp:acf/feature-cards {"name":"acf/feature-cards","data":{"headline":"The Bridge Between Compliance and Execution","columns":"3","cards":[{"title":"Specialized Talent Sourcing","desc":"Setting up global payroll is half the battle. Remote Leverage provides pre-vetted nearshore talent to do the work.","badge":"Talent"},{"title":"Streamlined Onboarding","desc":"Candidates are pre-screened for immediate integration into Lanos contractor and EOR platforms.","badge":"Speed"},{"title":"Full Time Zone Alignment","desc":"Professionals based in Latin America work in standard US Eastern and Central time zones for real-time collaboration.","badge":"Alignment"}]},"align":"full","mode":"preview"} /-->
</div>
<!-- /wp:group -->

<!-- wp:pattern {"slug":"remote-leverage/beyond-virtual-assistant"} /-->

<?= \App\Support\BlockDefaults::renderProcessSteps([
    'headline' => 'Scale Smarter, Not More Expensive',
    'badge' => 'Three Steps to a Fully Staffed Team',
    'steps' => [
        [
            'number' => '01',
            'title' => 'Tell Us Your Staffing Needs',
            'desc' => 'We define role requirements, tooling stack, and team culture on a quick alignment session.',
        ],
        [
            'number' => '02',
            'title' => 'Meet Pre-Interviewed Candidates',
            'desc' => 'Review top candidate resumes and video recordings, then interview your favorite profiles.',
        ],
        [
            'number' => '03',
            'title' => 'Deploy on Lano Compliantly',
            'desc' => 'Hire directly or through Lano EOR with zero bureaucratic friction and full peace of mind.',
        ],
    ],
]) ?>

<!-- wp:pattern {"slug":"remote-leverage/hire-va-4-testimonials"} /-->

<!-- wp:pattern {"slug":"remote-leverage/hire-va-4-booking-footer"} /-->
