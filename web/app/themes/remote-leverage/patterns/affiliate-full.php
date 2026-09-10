<?php

/**
 * Title: Full Page - Affiliate Program
 * Slug: remote-leverage/affiliate-full
 * Categories: remote-leverage
 * Description: Complete /affiliate-program/ page — hero, benefits, partner pitch, how it works, audience, case studies, CTA, and booking footer.
 */
$uploads = home_url('/app/uploads/2026/09');
?>
<!-- wp:acf/affiliate-hero {"name":"acf/affiliate-hero","data":{"headline":"Remote Leverage Affiliate Program","_headline":"field_affiliate_hero_block_headline","subheadline":"Help Your Network Scale.<br><strong>Earn $1,000 for Every Hire &amp; Pass on $500 in Savings.</strong>","_subheadline":"field_affiliate_hero_block_subheadline","primary_cta_text":"Apply to Join Now","_primary_cta_text":"field_affiliate_hero_block_primary_cta_text","primary_cta_url":"/referrer-register","_primary_cta_url":"field_affiliate_hero_block_primary_cta_url","secondary_cta_text":"Book a Strategy Call","_secondary_cta_text":"field_affiliate_hero_block_secondary_cta_text","secondary_cta_url":"#booking-footer","_secondary_cta_url":"field_affiliate_hero_block_secondary_cta_url","hero_image":"<?= $uploads ?>/3b6cb07dcd46261926ab63d1d3a19c6039817183.png","_hero_image":"field_affiliate_hero_block_hero_image"},"mode":"preview"} /-->

<!-- wp:group {"align":"full","className":"bg-purple-50 min-h-[90vh] flex flex-col justify-center items-stretch","style":{"spacing":{"padding":{"top":"4rem","bottom":"4rem"}}},"layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull bg-purple-50 min-h-[90vh] flex flex-col justify-center items-stretch" style="padding-top:4rem;padding-bottom:4rem">
    <!-- wp:html -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-16 mb-16">
        <h2 class="font-display text-3xl sm:text-4xl font-bold text-black tracking-[-0.03em] leading-[1.15]">
            Earn $1,000 for<br>Every Successful Hire</h2>
        <div class="text-black/80 leading-relaxed space-y-4">
            <p>Monetize your network by offering a solution to the #1 problem businesses face:
                <strong class="text-black">The Execution Gap</strong>. Businesses need to scale their teams but
                don&rsquo;t know how to get started.</p>
            <p><strong class="text-black">Not only will you earn $1,000 when you refer a new client</strong> to us
                and they hire Remote Leverage talent, <strong class="text-black">you pass along $500 in savings to
                    the client you referred!</strong></p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-card w-full">
        <div>
            <img src="<?= $uploads ?>/BAir-M_Tech-10_Single-11-Convertido-01-1.png" alt="" loading="eager"
                decoding="async" class="w-full aspect-[3/2] object-contain rounded-card mb-6 bg-purple-300 p-6 sm:p-8">
            <h4 class="font-display text-xl font-bold text-black tracking-[-0.02em] mb-2">No Earning Caps:</h4>
            <p class="text-base text-black/70 leading-relaxed">The more you refer, the more you earn.</p>
        </div>
        <div>
            <img src="<?= $uploads ?>/BAir-M_Tech-10_Single-11-Convertido-02-1.png" alt="" loading="lazy"
                decoding="async" class="w-full aspect-[3/2] object-contain rounded-card mb-6 bg-purple-300 p-6 sm:p-8">
            <h4 class="font-display text-xl font-bold text-black tracking-[-0.02em] mb-2">High Conversion:</h4>
            <p class="text-base text-black/70 leading-relaxed">We provide the “Top 1%” of talent at a 70% cost reduction.
            </p>
        </div>
        <div>
            <img src="<?= $uploads ?>/BAir-M_Tech-10_Single-11-Convertido-03-1.png" alt="" loading="lazy"
                decoding="async" class="w-full aspect-[3/2] object-contain rounded-card mb-6 bg-purple-300 p-6 sm:p-8">
            <h4 class="font-display text-xl font-bold text-black tracking-[-0.02em] mb-2">Simple Payouts:</h4>
            <p class="text-base text-black/70 leading-relaxed">Direct payments for every successful placement.</p>
        </div>
    </div>
    <!-- /wp:html -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"min-h-[90vh] flex flex-col justify-center items-stretch","style":{"spacing":{"padding":{"top":"4rem","bottom":"4rem"}},"color":{"background":"#E6DEF4"}},"layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-background min-h-[90vh] flex flex-col justify-center items-stretch"
    style="padding-top:4rem;padding-bottom:4rem;background-color:#E6DEF4">
    <!-- wp:html -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-16 mb-28">
        <h2 class="font-display text-3xl sm:text-4xl font-bold text-black tracking-[-0.03em] leading-[1.15]">
            Why Partner with<br>Remote Leverage?</h2>
        <p class="text-black/80 leading-relaxed">Businesses today are struggling with high domestic
            labor costs and slow hiring cycles. As a Remote Leverage Affiliate, you provide the &ldquo;Easy Button&rdquo;
            for scaling.</p>
    </div>
    <!-- /wp:html -->

    <!-- wp:acf/process-steps {"name":"acf/process-steps","data":{"steps":3,"_steps":"field_process_steps_block_steps","steps_0_num":"01","_steps_0_num":"field_process_steps_block_steps_num","steps_0_title":"Solve the<br>“Hiring Bottleneck”","_steps_0_title":"field_process_steps_block_steps_title","steps_0_desc":"Help your audience access top 1% LatAm talent (English-fluent, U.S. time zones) for $6–$12/hr. You look like a hero for saving them 70% on payroll.","_steps_0_desc":"field_process_steps_block_steps_desc","steps_1_num":"02","_steps_1_num":"field_process_steps_block_steps_num","steps_1_title":"High-Performance<br>Execution","_steps_1_title":"field_process_steps_block_steps_title","steps_1_desc":"We don’t just find “assistants.” We provide specialized talent for Sales, Marketing, Customer Support and other specialized roles.","_steps_1_desc":"field_process_steps_block_steps_desc","steps_2_num":"03","_steps_2_num":"field_process_steps_block_steps_num","steps_2_title":"Professional<br>Partner Assets","_steps_2_title":"field_process_steps_block_steps_title","steps_2_desc":"Get access to a library of pre-written email copy, social media assets, and “Hiring Blueprints” that you can share with one click.","_steps_2_desc":"field_process_steps_block_steps_desc"},"align":"","mode":"preview"} /-->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"min-h-[90vh] flex flex-col justify-center items-stretch","style":{"spacing":{"padding":{"top":"4rem","bottom":"4rem"}},"color":{"background":"#F9EAFF"}},"layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-background min-h-[90vh] flex flex-col justify-center items-stretch" style="padding-top:4rem;padding-bottom:4rem;background-color:#F9EAFF">
    <!-- wp:html -->
    <h2 class="w-full font-display text-3xl sm:text-4xl font-bold text-black tracking-[-0.03em] mb-4">How It Works</h2>
    <p class="w-full text-black/80 leading-relaxed mb-10">Joining the program is simple. You can go from signing
        up to earning your first commission in three steps:</p>
    <svg class="w-full h-auto mb-14" width="1231" height="10" viewBox="0 0 1231 10" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M5 5.97583L1230 4.00002" stroke="black" stroke-linecap="round" />
        <path d="M79 5H815" stroke="black" stroke-width="5" stroke-linecap="round" />
        <path d="M5 5H400" stroke="black" stroke-width="10" stroke-linecap="round" />
    </svg>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-card w-full divide-y sm:divide-y-0 sm:divide-x divide-black/15">
        <div class="py-6 sm:py-8 sm:pr-8">
            <h4 class="font-display text-lg font-bold text-black tracking-[-0.02em] mb-2">No Earning Caps:</h4>
            <p class="text-sm text-black/70 leading-relaxed">The more you refer, the more you earn.</p>
        </div>
        <div class="py-6 sm:py-8 sm:px-8">
            <h4 class="font-display text-lg font-bold text-black tracking-[-0.02em] mb-2">High Conversion:</h4>
            <p class="text-sm text-black/70 leading-relaxed">We provide the “Top 1%” of talent at a 70% cost reduction.
            </p>
        </div>
        <div class="py-6 sm:py-8 sm:pl-8">
            <h4 class="font-display text-lg font-bold text-black tracking-[-0.02em] mb-2">Simple Payouts:</h4>
            <p class="text-sm text-black/70 leading-relaxed">Direct payments for every successful placement.</p>
        </div>
    </div>
    <!-- /wp:html -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"min-h-[90vh] flex flex-col justify-center items-stretch","style":{"spacing":{"padding":{"top":"4rem","bottom":"4rem"}},"color":{"background":"#E6DEF4"}},"layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-background min-h-[90vh] flex flex-col justify-center items-stretch"
    style="padding-top:4rem;padding-bottom:4rem;background-color:#E6DEF4">
    <!-- wp:html -->
    <h2 class="w-full font-display text-3xl sm:text-4xl font-bold text-black tracking-[-0.03em] mb-4">Who is this program for?</h2>
    <p class="w-full text-black/80 leading-relaxed mb-16">We welcome partners from all industries who have a
        client-facing presence:</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-card w-full">
        <div>
            <img src="<?= $uploads ?>/ICONS-outlined_Consultants-1.png" alt="" loading="lazy" decoding="async"
                class="w-20 h-20 object-contain mb-6">
            <h4 class="font-display text-lg font-bold text-black tracking-[-0.02em] mb-3">Consultants &amp; Coaches:
            </h4>
            <p class="text-sm text-black/70 leading-relaxed">Help your clients implement your strategies faster.</p>
        </div>
        <div>
            <img src="<?= $uploads ?>/ICONS-outlined_Agencies-1.png" alt="" loading="lazy" decoding="async"
                class="w-20 h-20 object-contain mb-6">
            <h4 class="font-display text-lg font-bold text-black tracking-[-0.02em] mb-3">Agencies:</h4>
            <p class="text-sm text-black/70 leading-relaxed">Provide a “white-glove” staffing solution for your clients’
                back-offices.</p>
        </div>
        <div>
            <img src="<?= $uploads ?>/ICONS-outlined_Blog-2.png" alt="" loading="lazy" decoding="async"
                class="w-20 h-20 object-contain mb-6">
            <h4 class="font-display text-lg font-bold text-black tracking-[-0.02em] mb-3">Publishers &amp; Bloggers:
            </h4>
            <p class="text-sm text-black/70 leading-relaxed">Monetize your content by solving a real business pain
                point.</p>
        </div>
        <div>
            <img src="<?= $uploads ?>/ICONS-outlined_platforms-1.png" alt="" loading="lazy" decoding="async"
                class="w-20 h-20 object-contain mb-6">
            <h4 class="font-display text-lg font-bold text-black tracking-[-0.02em] mb-3">SaaS Platforms:</h4>
            <p class="text-sm text-black/70 leading-relaxed">Offer a “Human Layer” perk to your software users.</p>
        </div>
    </div>
    <!-- /wp:html -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"min-h-[90vh] flex flex-col justify-center items-stretch","style":{"spacing":{"padding":{"top":"4rem","bottom":"4rem"}},"color":{"background":"#F9EAFF"}},"layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-background min-h-[90vh] flex flex-col justify-center items-stretch" style="padding-top:4rem;padding-bottom:4rem;background-color:#F9EAFF">
    <!-- wp:html -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-16 mb-20">
        <h2 class="font-display text-3xl sm:text-4xl font-bold text-black tracking-[-0.03em] leading-[1.15]">
            Proven Results for<br>Global Brands</h2>
        <p class="text-black/80 leading-relaxed">Remote Leverage has already provided thousands of
            staffing solutions across all industries.</p>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-x-10 lg:gap-x-16 gap-y-12 w-full">
        <div class="flex flex-col">
            <img src="<?= $uploads ?>/bench-1.png" alt="" loading="lazy" decoding="async"
                class="h-8 w-auto object-contain object-left mb-6">
            <h4 class="font-display text-base font-bold text-black tracking-[-0.02em] mb-5">The Scale-Up Success: Bench
                Accounting</h4>
            <p class="text-sm text-black/70 leading-relaxed mb-4"><strong class="text-black">The Challenge:</strong>
                Bench needed to scale a large, specialized support operation quickly.</p>
            <p class="text-sm text-black/70 leading-relaxed mb-4"><strong class="text-black">The Solution:</strong>
                Remote Leverage sourced and vetted 31 remote team members across multiple roles.</p>
            <p class="text-sm text-black/70 leading-relaxed"><strong class="text-black">The Result:</strong> Bench
                scaled its team fast, gained reliable capacity for clients, and kept leaders focused on strategy instead
                of hiring.</p>
        </div>
        <div class="flex flex-col">
            <img src="<?= $uploads ?>/smile-1.png" alt="" loading="lazy" decoding="async"
                class="h-8 w-auto object-contain object-left mb-6">
            <h4 class="font-display text-base font-bold text-black tracking-[-0.02em] mb-5">The Growth Engine: Smiley
                Injury Law</h4>
            <p class="text-sm text-black/70 leading-relaxed mb-4"><strong class="text-black">The Challenge:</strong> A
                growing law firm needed reliable support for intake, admin, and client coordination so attorneys could
                spend more time on legal work and less on busywork.</p>
            <p class="text-sm text-black/70 leading-relaxed mb-4"><strong class="text-black">The Solution:</strong>
                Remote Leverage built a high-performing remote team for Smiley Injury Law with trained legal support
                fitting the firm’s culture and processes.</p>
            <p class="text-sm text-black/70 leading-relaxed"><strong class="text-black">The Result:</strong> The firm
                unlocked more billable hours for attorneys without adding expensive in-office headcount.</p>
        </div>
        <div class="flex flex-col">
            <img src="<?= $uploads ?>/fast-real-estate-text-black-logo-1.png" alt="" loading="lazy" decoding="async"
                class="h-8 w-auto object-contain object-left mb-6">
            <h4 class="font-display text-base font-bold text-black tracking-[-0.02em] mb-5">The Efficiency Play: Fast
                Real Estate</h4>
            <p class="text-sm text-black/70 leading-relaxed mb-4"><strong class="text-black">The Challenge:</strong>
                Fast Real Estate wanted more qualified leads and consistent follow-up but lacked capacity in-house.</p>
            <p class="text-sm text-black/70 leading-relaxed mb-4"><strong class="text-black">The Solution:</strong>
                Remote Leverage placed a dedicated cold-calling virtual assistant and helped the team implement scalable
                outreach and follow-up processes.</p>
            <p class="text-sm text-black/70 leading-relaxed"><strong class="text-black">The Result:</strong> The
                business turned one remote hire into a predictable pipeline of conversations and opportunities.</p>
        </div>
    </div>
    <!-- /wp:html -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"min-h-[90vh] flex flex-col justify-center items-stretch","style":{"spacing":{"padding":{"top":"4rem","bottom":"4rem"}},"color":{"background":"#E6DEF4"}},"layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-background min-h-[90vh] flex flex-col justify-center items-stretch"
    style="padding-top:4rem;padding-bottom:4rem;background-color:#E6DEF4">
    <!-- wp:html -->
    <h2 class="w-full font-display text-3xl sm:text-4xl font-bold text-black tracking-[-0.03em] mb-4">Ready to Get Started?</h2>
    <p class="w-full text-black/80 leading-relaxed mb-20">You have two ways to join<br>the Remote Leverage
        ecosystem today:</p>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-card w-full divide-y lg:divide-y-0 lg:divide-x divide-black/15">
        <div class="flex flex-col lg:pr-10">
            <span class="font-display text-3xl font-bold text-black mb-3">01</span>
            <h3 class="font-display text-xl font-bold text-black tracking-[-0.02em] mb-2">I&rsquo;m ready to start now
            </h3>
            <p class="text-sm text-black/70 leading-relaxed mb-6 grow">Skip the intro and get your tracking link
                immediately to start sharing with your network.</p>
            <a href="/referrer-register"
                class="self-start inline-flex items-center justify-center gap-3 px-6 py-3.5 rounded-full bg-black hover:bg-brand-purple text-white font-bold text-sm uppercase tracking-wide transition">
                Join the Affiliate Program Now
                <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <path stroke-width="2.5" d="M10 8l4 4-4 4" />
                </svg>
            </a>
        </div>
        <div class="flex flex-col pt-8 lg:pt-0 lg:pl-10">
            <span class="font-display text-3xl font-bold text-black mb-3">02</span>
            <h3 class="font-display text-xl font-bold text-black tracking-[-0.02em] mb-2">I want to discuss a custom
                partnership</h3>
            <p class="text-sm text-black/70 leading-relaxed mb-6 grow">Have a large audience or want to discuss a deeper
                co-marketing play? Let&rsquo;s jump on a quick call to map out a strategy.</p>
            <a href="#booking-footer"
                class="self-start inline-flex items-center justify-center gap-3 px-6 py-3.5 rounded-full bg-transparent border border-black hover:bg-black hover:text-white text-black font-bold text-sm uppercase tracking-wide transition">
                Book a 15-Minute Strategy Sync
                <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <path stroke-width="2.5" d="M10 8l4 4-4 4" />
                </svg>
            </a>
        </div>
    </div>
    <!-- /wp:html -->
</div>
<!-- /wp:group -->

<!-- wp:acf/booking-footer {"name":"acf/booking-footer","data":{"headline":"Let’s map out your partnership strategy together.","_headline":"field_booking_footer_headline"},"mode":"preview"} /-->