<?php

/**
 * Shared template for the older "Virtual Assistant Roles" landing pages.
 *
 * Not a block pattern. It lives outside the theme's patterns/ directory because WordPress core
 * scans that tree recursively and would reject a headerless file.
 *
 * Included by patterns/1monthonus.php and patterns/hire-va-isolated-form.php, each of which
 * defines $page before including this file. The two production pages are the same Elementor
 * template and differ only in the hero headline, the hero checklist and one benefit row, so the
 * markup lives here once.
 *
 * This is the oldest design in the P4 set and does NOT follow the current design system: it is a
 * light page (#FAFAFA / #FFFFFF) with a #6200A4 → #6E1686 hero, #342567 headings and a #FB7501
 * CTA. Every colour, size and box below was read off production with getComputedStyle and
 * verified against screenshot pixels (2026-09-15).
 *
 * Blocks reused: acf/client-logos-marquee, acf/testimonials, acf/booking.
 * See the @bespoke notes at each hand-written section for what was ruled out and why.
 */

use App\Support\BlockDefaults;

if (! isset($page) || ! is_array($page)) {
    return;
}

$img = fn (string $file): string => BlockDefaults::preferWebp(
    BlockDefaults::pageImg('va-roles-landing', $file)
);

// --- Type + surface tokens, measured off production ---------------------------------------
$wrap = 'w-full max-w-[1400px] mx-auto px-5 sm:px-6 lg:px-8';
$h2 = 'font-display font-bold text-[34px] leading-[1.06] sm:text-[54px] sm:leading-[54px] text-brand-navy';
$lead = 'font-display text-[18px] leading-[27px] sm:text-[22px] sm:leading-[33px] text-[#303030]';
$bodyCopy = 'font-display text-[18px] leading-[27px] text-[#1B1234]';
$btnOrange = 'inline-block rounded-[5px] bg-brand-orange px-10 py-4 text-center font-display text-[20px] sm:text-[24px] font-bold leading-6 text-white transition hover:opacity-90';
$bookUrl = '#booking-footer';

// Prose inside the role cards and the FAQ answers: production renders plain <p>/<ul> at 18px/27px
// with disc bullets indented 40px. Each li occupies 39px (27px line + 12px) because Elementor
// wraps most bullets in a nested <p> that keeps its margin, and the blocks either side of the list
// are separated by ~49px. That measured rhythm is reproduced with spacing, not the markup quirk.
$prose = '[&_p]:mb-12 [&_p:last-child]:mb-0 [&_ul]:list-disc [&_ul]:pl-10 [&_ul]:mb-12 [&_li]:mb-3';

// acf/client-logos-marquee ships 28px logos at 90% on a pale band; production's strip is a
// 50px pure-white lockup, 7 across. Overridden here at higher specificity rather than by
// editing the block's CSS, which is shared with every other page that uses it.
$marqueeStrip = implode(' ', [
    '[&_.rl-logo-marquee-wrapper]:py-0',
    '[&_.animate-marquee-logos]:gap-[72px]',
    '[&_.rl-logo-marquee-item]:h-[60px]',
    '[&_.rl-logo-marquee-item_img]:max-h-[50px]',
    '[&_.rl-logo-marquee-item_img]:max-w-[176px]',
    '[&_.rl-logo-marquee-item_img]:opacity-100',
    '[&_.rl-logo-marquee-item_img]:brightness-0',
    '[&_.rl-logo-marquee-item_img]:invert',
]);

// --- Role cards --------------------------------------------------------------------------
// Copy, bullet lists and icon filenames transcribed from production's markup.
$roles = [
    [
        'icon' => 'VA-Role-1-1.svg',
        'title' => 'Administrative Assistant',
        'intro' => 'Virtual Administrative Assistants makes your work life easier. They do tasks like:',
        'bullets' => ['Answering phone calls', 'Responding to emails', 'Daily administrative tasks to keep business running', 'Managing your calendar and appointments', 'Typing up data and notes', 'Handling your invoices and payments'],
        'outro' => 'They take care of these daily tasks so you can focus on the important things.',
    ],
    [
        'icon' => 'VA-Role-2-1.svg',
        'title' => 'Lead Generation (SDR)',
        'intro' => 'Lead Generation Virtual Assistants help find new customers for your business. They do this by:',
        'bullets' => ['Calling new &amp; old prospects', 'Sending outreach emails', 'Texting leads', 'Following up on leads', 'Booking sales meetings'],
        'outro' => 'They are great at connecting with people and setting up opportunities for you to grow your business.',
    ],
    [
        'icon' => 'VA-Role-3-1.svg',
        'title' => 'Sales (BDR)',
        'intro' => 'Sales Virtual Assistants help you generate and close sales. They do this by:',
        'bullets' => ['Finding new leads through calls and emails', 'Following up with interested leads', 'Setting up sales meetings', 'Giving sales presentations and product demos'],
        'outro' => 'They communicate effectively with customers and guide them toward making a purchase.',
    ],
    [
        'icon' => 'VA-Role-4-1.svg',
        'title' => 'Social Media Management',
        'intro' => 'Social Media Virtual Assistants help your business shine online. They do this by:',
        'bullets' => ['Taking care of all your social media accounts', 'Creating and sharing engaging posts', 'Finding new content ideas that your audience will love', 'Talking with your followers and answering their questions', 'Moderating your online groups and communities'],
        'outro' => 'They know how to make your brand look great and connect with people on social media.',
    ],
    [
        'icon' => 'VA-Role-5-1.svg',
        'title' => 'Marketing',
        'intro' => 'Marketing Virtual Assistants help maximize your advertising budget and reach. They do this by:',
        'bullets' => ['Managing your paid advertising campaigns (Facebook, Google, LinkedIn)', 'Monitoring ad performance and adjusting campaigns', 'Creating and testing different ad variations', 'Analyzing data to optimize spending', 'Preparing ROI reports on campaign performance'],
        'outro' => 'They know how to make every advertising dollar count and bring quality leads to your business.',
    ],
    [
        'icon' => 'VA-Role-6-1.svg',
        'title' => 'Graphic Design',
        'intro' => 'Graphic Design Virtual Assistants help bring your brand vision to life. They do this by:',
        'bullets' => ['Creating social media graphics and marketing materials', 'Designing logos and branding elements', 'Making professional presentations and sales decks', 'Editing photos and creating promotional images'],
        'outro' => 'They create eye-catching visuals that make your brand stand out and attract customers.',
    ],
    [
        'icon' => 'VA-Role-7-1.svg',
        'title' => 'Customer Support',
        'intro' => 'Customer Support Virtual Assistants keep your customers happy. They do this by:',
        'bullets' => ['Answering customer questions and solving their problems', 'Managing support tickets to make sure issues get resolved', 'Helping with administrative tasks to keep things running smoothly', 'Scheduling appointments and managing your calendar', 'Communicating with vendors on your behalf'],
        'outro' => 'They excel at caring for your customers and ensuring a good experience.',
    ],
    [
        'icon' => 'VA-Role-8-1.svg',
        'title' => 'Custom Roles',
        'intro' => 'Need help with a unique role? We’ve got you covered.<br /><br />Our team has experience hiring for all kinds of positions.',
        'bullets' => [],
        'outro' => 'Book a consultation with us and let us know what you’re looking for. We’ll work with you to find the perfect fit for your business, no matter how specific or specialized the role may be.',
    ],
];

// --- Hiring process ------------------------------------------------------------------------
$steps = [
    ['num' => 'Number-1.svg', 'img' => 'Hiring-Process-1-1.jpg', 'title' => 'Tell Us the Job Position', 'desc' => 'Tell us what role you want to fill, job requirements, who your ideal fit would be, experience required, and anything else that’s important in who you hire.'],
    ['num' => 'Number-2.svg', 'img' => 'Hiring-Process-2-1.jpg', 'title' => 'We Screen Virtual Assistants', 'desc' => 'We will interview qualified Virtual Assistants and assess their skill level, then pass on 4-6 Virtual Assistants for you to interview that match your requirements.'],
    ['num' => 'Number-3.svg', 'img' => 'Hiring-Process-3-1.jpg', 'title' => 'You Meet &amp; Choose Best Fit', 'desc' => 'After you interview the Virtual Assistants we bring, you get to choose who you feel is the best fit to work in your business.'],
];

// --- FAQ -----------------------------------------------------------------------------------
$faqs = [
    ['q' => 'What countries do you hire from?', 'a' => '<p>We focus on four key regions:</p><ul><li>Latin America</li><li>The Philippines</li><li>South Africa</li><li>Egypt</li></ul><p>Our Latin American Virtual Assistants are especially popular with US businesses, thanks to their:</p><ul><li>Exceptional English fluency with minimal accents</li><li>Strong cultural alignment with US business practices</li><li>Convenient time zone overlap with North America</li></ul><p>We’ll guide you on which region best suits your specific needs, but the final choice is always yours.</p>'],
    ['q' => 'How do taxes &amp; payroll work when hiring Virtual Assistants?', 'a' => '<p>Our partner company takes care of all payroll and compliance requirements for your Virtual Assistant.</p><p>This means you can focus on growing your business while they handle:</p><ul><li>Tax compliance</li><li>Payroll processing</li><li>Legal requirements</li><li>International payment regulations</li></ul><p>It’s a simple, worry-free solution that ensures everything is managed properly and legally.</p>'],
    ['q' => 'How do you get paid?', 'a' => '<p>It’s simple – we charge a one-time flat fee, but only after you’ve found your perfect match.</p><p>Whatever hourly pay you decide to pay goes directly to the Virtual Assistant you hire.</p>'],
    ['q' => 'What\'s the difference between Staffing and Recruiting Agencies?', 'a' => '<p>Staffing agencies charge monthly fees but only pay a small portion to Virtual Assistants. This often results in lower quality talent, as skilled VAs avoid arrangements where agencies keep a large chunk of their earnings.</p><p>At Remote Leverage, we charge just one flat fee after you hire. Your Virtual Assistant receives 100% of what you pay them directly. This attracts higher-quality talent and eliminates ongoing middleman costs, saving you money while getting better results.</p>'],
    ['q' => 'What if I have questions and need help after hiring?', 'a' => '<p>After hiring your Virtual Assistant, you’ll have access to a dedicated Customer Success Manager who will help ensure your success.</p><p>They’re here to assist with:</p><ul><li>Reviewing performance</li><li>Monitoring progress</li><li>Training guidance</li><li>Any other questions or requests</li></ul>'],
    ['q' => 'How much does the average Virtual Assistant cost?', 'a' => '<p>Virtual Assistant’s hourly rates depend on the skills and experience they have, and also the region you’re hiring from.</p><ul><li>Entry Level: $6-$7 per hour</li><li>Highly Experienced: $8-$10 per hour</li></ul><p>The hourly rate you agree to pay goes directly to your Virtual Assistant – we don’t take any fees from their pay.</p>'],
    ['q' => 'Do they work for me or for Remote Leverage?', 'a' => '<p>The Virtual Assistant works directly for you while we make it simple and compliant. Here’s how:</p><ul><li>Your Virtual Assistant is your direct team member</li><li>Our partner company handles all payroll and legal paperwork</li><li>You save thousands annually with no ongoing agency fees</li><li>Everything stays fully compliant without the administrative hassle</li></ul><p>It’s the best of both worlds – direct hires with none of the complex paperwork or compliance concerns.</p>'],
    ['q' => 'What if they don\'t turn out to be a good fit?', 'a' => '<p>While it’s rare to have issues since candidates are thoroughly vetted by both our team and you, we understand the importance of finding the right fit. That’s why we offer:</p><ul><li>6-month replacement guarantee at no extra cost</li><li>Option to extend to 12 months guarantee for a small fee</li><li>Unlimited candidate interviews to ensure you find the best match</li></ul><p>This double-screening process (by us and you) helps ensure quality matches prior to hiring an applicant, and our guarantee gives you extra peace of mind that you’ll find the right Virtual Assistant for your business.</p>'],
    ['q' => 'How is their English and Communication skills?', 'a' => '<p>We maintain extremely high standards for English fluency. Here’s how we ensure this:</p><ul><li>All candidates must submit an English voice recording</li><li>We review hundreds of applications daily, and we only select those with fluent English and minimal accents</li><li>Only the best communicators make it through our screening</li></ul><p>This strict vetting process for language skills means you’ll work with a Virtual Assistant who communicates clearly and professionally from day one.</p>'],
    ['q' => 'What time zone will they be working in?', 'a' => '<p>Your Virtual Assistant will work according to your schedule and time zone.</p><p>They’re accustomed to US hours, and you get to set the working hours that best fit your needs.</p>'],
    ['q' => 'Can I start with Part Time?', 'a' => '<p>Yes, you can start with either part-time or full-time.</p><p>The minimum is 20 hours per week, as our most qualified Virtual Assistants prefer stable positions with consistent hours.</p>'],
];

$check = '<svg class="w-[18px] h-[18px] shrink-0 text-[#3FCB2F]" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M173.9 439.4l-166.4-166.4c-10-10-10-26.2 0-36.2l36.2-36.2c10-10 26.2-10 36.2 0L192 312.7 432.1 72.6c10-10 26.2-10 36.2 0l36.2 36.2c10 10 10 26.2 0 36.2l-294.4 294.4c-10 10-26.2 10-36.2 0z"/></svg>';

?>
<!-- ============ HERO ============ -->
<!-- rl:cta-only-header — production serves this family with no site nav, only a logo and a
     single Get Started pill. App\Support\PageChrome reads this marker and swaps
     sections.header for sections.header-cta. -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull flex min-h-[780px] items-center bg-[linear-gradient(135deg,#6200A4_0%,#6E1686_100%)] py-16">
    <div class="<?= $wrap ?> text-center">
        <h2 class="font-display font-bold text-[40px] leading-[1.1] sm:text-[64px] sm:leading-[70.4px] text-white">
            <?= $page['hero_title_lead'] ?? '' ?><br />
            <span class="bg-[linear-gradient(120deg,#FFA51E_20%,#FFDC10_70%)] bg-clip-text text-transparent">
                <?= $page['hero_title_gradient'] ?? '' ?>
            </span>
        </h2>

        <p class="mx-auto mt-6 max-w-[880px] font-display text-[18px] leading-[27px] sm:text-[22px] sm:leading-[33px] text-white">
            Recruiting agency helping businesses hire English speaking Virtual Assistants from <strong class="font-bold">Latin America</strong> for 70% less than U.S. Employees.
        </p>

        <div class="mx-auto mt-8 flex w-fit flex-col gap-x-14 gap-y-1 sm:grid sm:grid-flow-col sm:grid-rows-3">
            <?php foreach (array_merge($page['hero_checks_left'] ?? [], $page['hero_checks_right'] ?? []) as $item) { ?>
                <span class="flex items-center gap-2 text-left font-display text-[18px] sm:text-[24px] font-semibold leading-10 text-white">
                    <?= $check ?><?= $item ?>
                </span>
            <?php } ?>
        </div>

        <?php if (! empty($page['hero_cta_text'])) { ?>
            <div class="mt-10">
                <a href="<?= esc_url($bookUrl) ?>" class="inline-block rounded-[5px] bg-[#68B93D] px-10 py-4 font-display text-[20px] sm:text-[24px] font-bold leading-6 text-white transition hover:opacity-90"><?= $page['hero_cta_text'] ?></a>
            </div>
        <?php } ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ CLIENT LOGO STRIP ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull hidden h-[120px] items-center overflow-hidden bg-[linear-gradient(340deg,#6200A4_0%,#342567_90%)] lg:flex <?= $marqueeStrip ?>">
    <?= BlockDefaults::renderClientLogosMarquee() ?>
</div>
<!-- /wp:group -->

<!-- ============ VIRTUAL ASSISTANT ROLES ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull bg-[#FAFAFA] pt-[30px] pb-[50px]">
    <?php // Production: 20px section gutter over a 1400px inner, 10px row padding, 30px column
          // gap — which lands each card on exactly 675px at 1440.?>
    <div class="w-full max-w-[1440px] mx-auto px-5">
        <h2 class="<?= $h2 ?> mb-8 text-center">Virtual Assistant Roles</h2>

        <?php
        // @bespoke: Checked acf/roles-grid (photographic role cards, no task list), acf/roles-carousel
        // (dark #250D4A surface, horizontally scrolling), acf/department-cards (photo-backed frosted
        // overlay), acf/feature-cards (img/title/desc, no bullet list) and acf/roles-pricing-grid
        // (requires photo + hourly rate + tools, none of which this older design has). This section is
        // a 2-up white card with an SVG icon inline beside the title, a bulleted task list and an
        // orange CTA per card — no existing block models it, and a new block cannot be added this
        // session because registering one requires regenerating docs/block-inventory.md, which is
        // owned centrally (see the report).
?>
        <div class="grid grid-cols-1 gap-5 p-2.5 md:grid-cols-2 md:gap-x-[30px] md:gap-y-5">
            <?php foreach ($roles as $role) { ?>
                <div class="flex flex-col justify-between rounded-[10px] bg-white p-5 shadow-[2px_2px_10px_rgba(0,0,0,0.3)]">
                    <div class="flex flex-col items-center gap-5 p-2.5">
                        <div class="mt-[30px] flex items-center justify-center gap-5">
                            <img src="<?= esc_url($img($role['icon'])) ?>" alt="" width="76" height="80"
                                 loading="lazy" decoding="async" class="h-20 w-[76px] shrink-0 object-contain" />
                            <div class="font-display text-[26px] leading-[1.05] sm:text-[34px] sm:leading-[34px] font-bold text-brand-navy">
                                <?= $role['title'] ?>
                            </div>
                        </div>

                        <div class="w-full <?= $bodyCopy ?> <?= $prose ?>">
                            <p><?= $role['intro'] ?></p>
                            <?php if ($role['bullets']) { ?>
                                <ul>
                                    <?php foreach ($role['bullets'] as $bullet) { ?>
                                        <li><?= $bullet ?></li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                            <p><?= $role['outro'] ?></p>
                        </div>
                    </div>

                    <div class="pt-2.5 text-center">
                        <a href="<?= esc_url($bookUrl) ?>" class="<?= $btnOrange ?>">Hire Now</a>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ WE'VE HIRED THOUSANDS ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull hidden bg-white py-2.5 md:block">
    <div class="w-full max-w-[1420px] mx-auto">
        <div class="grid grid-cols-1 items-center gap-6 lg:grid-cols-2">
            <div class="p-2.5">
                <img src="<?= esc_url($img('SPU-Image-1.png')) ?>" alt="" width="662" height="568"
                     loading="lazy" decoding="async" class="h-auto w-full max-w-[662px] object-contain" />
            </div>
            <div class="flex flex-col p-2.5 px-5 lg:px-2.5">
                <div class="mb-3 flex gap-1 text-[#FFA51E]" aria-hidden="true">
                    <?php for ($i = 0; $i < 5; $i++) { ?>
                        <svg class="h-5 w-5 fill-current" viewBox="0 0 20 20"><path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.07 3.29a1 1 0 0 0 .95.69h3.46c.97 0 1.37 1.24.59 1.81l-2.8 2.03a1 1 0 0 0-.37 1.12l1.07 3.29c.3.92-.75 1.69-1.54 1.12l-2.8-2.04a1 1 0 0 0-1.17 0l-2.8 2.04c-.78.57-1.84-.2-1.54-1.12l1.07-3.29a1 1 0 0 0-.36-1.12L2.98 8.72c-.78-.57-.38-1.81.59-1.81h3.46a1 1 0 0 0 .95-.69l1.07-3.29z"/></svg>
                    <?php } ?>
                </div>
                <h2 class="<?= $h2 ?> mb-6">We've hired thousands of Virtual Assistants for businesses all across the US.</h2>
                <div><a href="<?= esc_url($bookUrl) ?>" class="<?= $btnOrange ?>">Book a Consultation</a></div>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ WHY HIRE THROUGH REMOTE LEVERAGE ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull bg-[#FAFAFA] pt-2.5 pb-10">
    <div class="<?= $wrap ?>">
        <?php // Production sets 80px between this heading and the two columns, and 40px between rows.?>
        <h2 class="<?= $h2 ?> mx-auto mb-20 max-w-[840px] text-center">Why Hire Virtual Assistants Through Remote Leverage?</h2>

        <div class="grid grid-cols-1 items-center gap-6 lg:grid-cols-2">
            <div class="pr-2.5">
                <img src="<?= esc_url($img('Why-Hire-Image.png')) ?>" alt="" width="620" height="651"
                     loading="lazy" decoding="async" class="h-auto w-full max-w-[620px] object-contain" />
            </div>

            <div class="flex flex-col gap-10 p-2.5">
                <?php foreach ($page['why_cards'] ?? [] as $card) { ?>
                    <div class="border-l-[6px] pl-5" style="border-color:<?= $card['color'] ?>;">
                        <h2 class="font-display text-[28px] leading-[1.05] sm:text-[36px] sm:leading-9 font-bold text-brand-navy"><?= $card['title'] ?></h2>
                        <p class="mt-2.5 font-display text-[18px] leading-[27px] text-[#303030]"><?= $card['desc'] ?></p>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ OUR HIRING PROCESS ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull bg-white py-2.5">
    <div class="w-full max-w-[1420px] mx-auto px-5 sm:px-6 lg:px-2.5">
        <div class="p-2.5 text-center">
            <h2 class="<?= $h2 ?> mb-5">Our Hiring Process</h2>
            <p class="mx-auto max-w-[800px] <?= $lead ?>">Hire top-tier virtual assistants in just 72 hours. We handle the screening, so you only meet the top 1% of candidates — ensuring you find the perfect fit for your business quickly and efficiently.</p>
        </div>

        <?php
// @bespoke: Checked acf/process-steps (numbered timeline on a hairline rule, no imagery),
// acf/process-step-cards (stacked full-width cards with an oversized grey numeral and a UI
// illustration to the right) and acf/progress-steps (heading + segmented progress bar). This
// older design is a 3-up column with a gradient numeral badge stacked above a 15px-radius
// photo, then a centred title and description — a different topology from all three.
?>
        <div class="grid grid-cols-1 gap-5 p-2.5 md:grid-cols-3">
            <?php foreach ($steps as $step) { ?>
                <div class="flex flex-col gap-5 p-2.5 text-center">
                    <img src="<?= esc_url($img($step['num'])) ?>" alt="" width="59" height="59"
                         loading="lazy" decoding="async" class="mx-auto h-[59px] w-[59px]" />
                    <img src="<?= esc_url($img($step['img'])) ?>" alt="" width="430" height="280"
                         loading="lazy" decoding="async" class="h-auto w-full rounded-[15px] object-cover" />
                    <h2 class="font-display text-[28px] leading-[1.05] sm:text-[36px] sm:leading-9 font-bold text-brand-navy"><?= $step['title'] ?></h2>
                    <p class="font-display text-[18px] leading-[27px] text-[#303030]"><?= $step['desc'] ?></p>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ 12-MONTH REPLACEMENT GUARANTEE ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull bg-white pt-[30px]">
    <div class="<?= $wrap ?>">
        <?php
// @bespoke: Checked acf/guarantee-card — production's own guarantee panel on the newer
// pages, but it is a two-column dark radial-gradient section with three icon+copy
// reassurance rows. This older page is a single centred #342567 → #6200A4 card with the
// satisfaction badge above the heading and two yellow closing lines under the CTA.
?>
        <div class="rounded-[30px] bg-[linear-gradient(180deg,#342567_0%,#6200A4_100%)] p-8 text-center sm:p-[50px]">
            <img src="<?= esc_url($img('Satisfaction-badge.png')) ?>" alt="" width="223" height="261"
                 loading="lazy" decoding="async" class="mx-auto mb-6 h-auto w-[223px]" />
            <h2 class="font-display font-bold text-[34px] leading-[1.06] sm:text-[54px] sm:leading-[54px] text-white">12-Month Replacement Guarantee</h2>
            <p class="mx-auto mt-5 max-w-[960px] font-display text-[18px] leading-[27px] sm:text-[22px] sm:leading-[33px] text-white">12-Month free replacement guarantee on any Virtual Assistant you hire through us to ensure you have a perfect fit. You’ll also have a dedicated manager to help you set up training, performance tracking, and any other support you need.</p>
            <div class="mt-8"><a href="<?= esc_url($bookUrl) ?>" class="<?= $btnOrange ?>">Book a Consultation</a></div>
            <h2 class="mt-8 font-display text-[18px] leading-[26px] sm:text-[20px] sm:leading-7 font-normal text-[#FFE532]">
                No contracts or commitments.<br />We get paid a flat hiring fee if you choose to hire a Virtual Assistant after interviewing our applicants.
            </h2>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ CLIENT REVIEWS ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull bg-[#FAFAFA] py-10">
    <div class="w-full max-w-[1280px] mx-auto px-5">
        <h2 class="<?= $h2 ?> text-center">Client Reviews</h2>
        <p class="mx-auto mt-4 mb-8 max-w-[760px] text-center <?= $lead ?>">Don’t just take our word for it — hear from business owners who’ve hired through Remote Leverage. See why quality makes all the difference!</p>

        <?php // Production shows the same 16-review wall as /signedup/: two across, bare tiles, no
      // quote or company chrome. renderSignedUpTestimonials() already encodes exactly that.?>
        <?= BlockDefaults::renderSignedUpTestimonials() ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ FREQUENTLY ASKED QUESTIONS ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull bg-white py-[60px]">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> mb-8 text-center">Frequently Asked Questions</h2>

        <?php
// @bespoke: acf/accordion-faq is the right block for this content but renders a two-column
// balanced grid with a chevron toggle; production here is a single full-width column of
// <details> rows with a +/− toggle and a 2px #D5D8DC rule. That is a `columns` option on
// the existing block rather than a second block, but app/Blocks + resources/views/blocks are
// off-limits this session (another agent holds them) — flagged in the report.
?>
        <div class="p-2.5">
            <?php foreach ($faqs as $faq) { ?>
                <details class="group border-b-2 border-[#D5D8DC]">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-[15px] font-display text-[16px] font-bold text-[#1B1234] marker:hidden">
                        <span><?= $faq['q'] ?></span>
                        <span class="relative h-3.5 w-3.5 shrink-0 text-[#1B1234]" aria-hidden="true">
                            <span class="absolute top-1/2 left-0 h-0.5 w-3.5 -translate-y-1/2 bg-current"></span>
                            <span class="absolute top-0 left-1/2 h-3.5 w-0.5 -translate-x-1/2 bg-current transition-transform group-open:scale-y-0"></span>
                        </span>
                    </summary>
                    <div class="px-5 pt-1 pb-6 <?= $bodyCopy ?> <?= $prose ?>">
                        <?= $faq['a'] ?>
                    </div>
                </details>
            <?php } ?>
        </div>

        <div class="mt-5 text-center"><a href="<?= esc_url($bookUrl) ?>" class="<?= $btnOrange ?>">Book a Consultation</a></div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ 15 MINUTE HIRING CONSULTATION + BOOKING ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull bg-[linear-gradient(135deg,#342567_0%,#6200A4_100%)] py-[60px]" id="booking-footer">
    <div class="<?= $wrap ?>">
        <h2 class="font-display font-bold text-[34px] leading-[1.06] sm:text-[54px] sm:leading-[54px] text-center text-white">15 Minute Virtual Assistant Hiring Consultation</h2>
        <p class="mx-auto mt-5 max-w-[960px] text-center font-display text-[18px] leading-[27px] sm:text-[22px] sm:leading-[33px] text-white">During this meeting we will go over the role you’re planning to hire for, what the process looks like, answer any questions you have, and proceed to next steps.</p>
        <p class="mx-auto mt-4 max-w-[960px] text-center font-display text-[18px] leading-[27px] sm:text-[22px] sm:leading-[33px] text-white">This consultation will be done over zoom so it is best if you could be on a computer!</p>

        <div class="mt-10 grid grid-cols-1 items-start gap-8 rounded-[10px] bg-white p-6 sm:p-10 lg:grid-cols-[1fr_1.6fr] lg:gap-12">
            <div class="flex flex-col items-center text-center lg:pt-6">
                <img src="<?= esc_url(BlockDefaults::homeImg('rl-26-logo.svg')) ?>" alt="Remote Leverage"
                     width="300" height="32" loading="lazy" decoding="async" class="mb-6 h-auto w-[240px] max-w-full" />
                <p class="font-display text-[16px] leading-6 font-bold text-[#1B1234]">Fill out this form to book a 15 Minute Virtual Assistant Hiring Consultation</p>
            </div>
            <div class="lg:border-l lg:border-black/10 lg:pl-12">
                <!-- wp:acf/booking {"name":"acf/booking","data":{"skin":"naked"},"mode":"preview"} /-->
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->
