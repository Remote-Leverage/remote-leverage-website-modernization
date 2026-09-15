<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Services (VA Free Onboarding Guide + Bundle Upgrade)
 * Slug: remote-leverage/services
 * Categories: remote-leverage
 * Description: Production's /services/ — the post-hire onboarding-guide hand-off and the bundle / guarantee / performance upsells (migrated 2026-09-15).
 */

/*
 * Production page 26095, template `elementor_header_footer`. Copy transcribed verbatim from
 * the live page on 2026-09-15; tokens read off it with getComputedStyle.
 *
 * WHAT IS *NOT* HERE, AND WHY
 *
 * 1. Production ships two divergent stacks of this page. The desktop container carries
 *    `elementor-hidden-tablet elementor-hidden-mobile`; a second container carries
 *    `elementor-hidden-desktop`. They are not responsive variants of one design — they are
 *    different content. Below ~1024px production shows NO onboarding guide and NO bundle
 *    section at all, and renames "Performance & Payment Package" to "Performance & Payroll
 *    Package" at different prices ($300/month + $500 setup, vs the desktop's $400/4 weeks +
 *    $500 setup). The desktop stack is the maintained one — it is the only one that carries
 *    the page's stated purpose — so this migration reproduces it and lets it reflow, rather
 *    than shipping the stale mobile copy alongside it.
 *
 * 2. There is no video on this page. The "videos covering the following topics" list is a
 *    list of topic names; the videos themselves live on /vaonboardingguide/ (six Vimeo
 *    embeds), which this page only links out to.
 *
 * 3. The two stock photographs in the guarantee and performance cards ARE reproduced — they
 *    are on the desktop stack too, sitting between the pill and the price. They read as empty
 *    white space in a screenshot that scrolls the page and then returns to the top, because
 *    Elementor's entrance animation re-hides them; capture without scrolling back.
 */

$onboardingBody = <<<'HTML'
<p><strong>Congrats on going through our hiring process and hiring a candidate!</strong></p>
<p><strong>We highly recommend you go through a free onboarding guide we&#8217;ve created with videos covering the following topics:</strong></p>
<ul>
<li>Screen Monitoring</li>
<li>Setting up payroll sheet</li>
<li>Payroll Setup</li>
<li>Work Schedule</li>
<li>How to Train Your VA</li>
<li>Lead Generation Platforms</li>
<li>Phone Systems for your VA</li>
<li>Incentivizing Your Virtual Assistant with Bonuses</li>
</ul>
<p><strong>Here is the link: https://remoteleverage.com/VAOnboardingGuide</strong></p>
HTML;

$bundleBody = <<<'HTML'
<p><strong>Upgrade to Bundle: </strong>Most cost effective option if you plan on hiring more in the next 12 months to grow your business (same or different roles).</p>
<p><strong>Credit:</strong> Credit payment into bundle upgrade (so no money goes to waste) &#8211; <em>Max $8k credit.&nbsp;</em></p>
<p><strong>Locked Rate:</strong> Your fee stays the same regardless of the VA hourly rate, so you can hire even higher quality applicants without paying more.</p>
<p><strong>Price Match Guarantee:</strong> If per-hire would&#8217;ve been cheaper after placements, you get the difference refunded.</p>
<p><strong><em>Limited-Time Upgrade: Only applies today &#8212; after that, future hires revert to standard per-hire pricing (30% discount).</em></strong></p>
HTML;

$guaranteeBody = <<<'HTML'
<p><strong>&#8226; 12-Month Protection</strong> &#8211; Instead of the standard 6-month coverage, enjoy a full year of protection for your hire.</p>
<p><strong>&#8226; Priority Replacements</strong> &#8211; If your VA doesn&#8217;t work out, we&#8217;ll provide a replacement at no additional cost.</p>
HTML;

$performanceBody = <<<'HTML'
<p><strong>&#8226; Screen Tracking</strong> &#8211; Confirms your VA is on the right tasks. Clients save 10&#8211;15% on payroll each month.</p>
<p><strong>&#8226; Higher Productivity</strong> &#8211; VAs perform better when their work is monitored.</p>
<p><strong>&#8226; Payout Management</strong> &#8211; Connect your preferred payment method and we will process pay semi-monthly for you.</p>
<p><strong>&#8226; Dedicated Remote Leverage Rep</strong> &#8211; Your go-to point of contact for VA support, reviews, and questions.</p>
<p><strong>&#8226; Overall Benefit</strong> &#8211; Accurate hours, verified tasks, and complete backend support &#8212; with zero extra effort on your end.</p>
HTML;

$performanceFootnote = <<<'HTML'
<p><strong>4 Week Cycle</strong>: $400/4 Weeks + $500 one-time setup ($5,700/year total)<br><span style="color:#ff6600;">OR</span><br><strong>Annual Plan (Best Value)</strong>: $3,000 one-time annual payment (<strong>save $2,700</strong>)</p>
HTML;

/*
 * Robots posture. Production serves /services/ `noindex, nofollow` — it is post-hire client
 * onboarding collateral, not a marketing page. App\Support\PageRobots reads this marker out of
 * the registered pattern's content and emits that pair.
 *
 * The marker lives here, in git, rather than as Yoast postmeta: postmeta is database state and
 * this project loses database state on a refresh, which would silently make the page indexable.
 * It must be echoed, not written as a PHP comment — PageChrome::patternContent() reads the
 * REGISTERED pattern content, i.e. this file's output, so a comment would never be seen.
 *
 * The page stays published and reachable. This is not access control.
 */
echo "<!-- rl:noindex -->\n";

echo BlockDefaults::renderOfferStack([
    [
        'headline' => 'Virtual Assistant Free Onboarding Guide',
        'body' => $onboardingBody,
        'layout' => 'single',
        'cta_text' => 'Click to View Onboarding Guide',
        'cta_url' => 'https://remoteleverage.com/vaonboardingguide',
        'cta_new_tab' => 1,
    ],
    [
        'headline' => 'Bundle Upgrade With Financing',
        'body' => $bundleBody,
        'layout' => 'split',
        // The calculator is production's own Elementor HTML widget, ported into a partial.
        'widget' => 'bundle-calculator',
        'pills' => [
            ['text' => '3 Virtual Assistants Bundle - $12,000 ($4k Per VA) (~$7k savings)'],
            ['text' => '5 Virtual Assistants Bundle - $17,500 ($3.5k Per VA) (~$10k in savings)'],
            ['text' => '7 Virtual Assistants Bundle - $23,000 ($3.28k Per VA) (~$13k in savings)'],
            ['text' => '10 Virtual Assistants Bundle - $30,000 ($3k Per VA) (~$19k in savings)'],
        ],
        // Production drops this column 88px so the first pill clears the heading opposite.
        'pills_offset' => 1,
        'cta_text' => 'Get Started',
        'cta_url' => 'https://form.jotform.com/252617465041858',
    ],
    [
        'headline' => 'Extended Guarantee: 12 Months',
        'body' => $guaranteeBody,
        'layout' => 'split',
        'pills' => [
            ['text' => '12-Month Guarantee, Fast Replacements'],
        ],
        'image' => BlockDefaults::pageImg('services', 'extended-guarantee.jpeg'),
        'footnote' => '<p>$2,000 One time payment</p>',
        'cta_text' => 'Get Started',
        'cta_url' => 'https://form.jotform.com/252415499863166',
    ],
    [
        'headline' => 'Performance &amp; Payment Package',
        'body' => $performanceBody,
        'layout' => 'split',
        'pills' => [
            ['text' => "Screen-Verified Hours, HR + Fast Replacements,\nPayments Done-For-You"],
        ],
        'image' => BlockDefaults::pageImg('services', 'performance-package.jpeg'),
        'footnote' => $performanceFootnote,
        'cta_text' => 'Get Started',
        'cta_url' => 'https://form.jotform.com/260197622868165',
    ],
]);
