<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - VA Client Onboarding Guide
 * Slug: remote-leverage/onboardingguide
 * Categories: remote-leverage
 * Description: Post-sale client enablement guide for /onboardingguide/ — FAQ, reference links, and 28 Vimeo training videos.
 *
 * Migrated 1:1 from production (2026-09-15). Public, not gated — same as production.
 *
 * The videos ARE the content: 28 Vimeo embeds, each reproduced with production's exact
 * player URL including the `h=` privacy hash where one is present. Dropping a hash makes an
 * unlisted video 404 silently, so the srcs below are transcribed, not reconstructed.
 * tests/Unit/SalesTalentsAndOnboardingTest.php asserts all 28 IDs are still on the page.
 *
 * Production quirks reproduced deliberately — do not "fix" them in passing:
 *   - "How to Call a Specific List of Leads Frequently" appears twice in Part 3, as a second
 *     caption line under both "Mojo: How to Take Notes on Leads" and "Mojo: Calendar".
 *   - "Who else do you know that should be apart of this program?" ("apart" for "a part").
 *   - The last two Part 5 captions end in a zero-width space, left in place.
 *   - "TimeDoctor -VA Screen Monitoring" (missing space) and "Click here" (lower-case h) on
 *     the Cold Calling Script row are production's own casing.
 *
 * Design tokens read off production at 1440px with getComputedStyle:
 *   container 1170px · body/headings Inter Display 500 · H2 32/32 · H3 28/28 · H4 24/24
 *   quote #00791C · add-callers band #E9EEFA over a white 950px card · CTA #0DC863
 *   training-videos band #2984F9 with white type · accordion rows #FFF, 1px #D5D8DC, 16px/700 #333
 */
$img = fn (string $file): string => BlockDefaults::preferWebp(
    BlockDefaults::pageImg('onboardingguide', $file)
);

// Production's inner column is 1170px at 1440px wide (e-con-inner max-width min(100%, 1170px)).
$wrap = 'w-full max-w-[1210px] mx-auto px-5';
$h2 = 'font-display text-[26px] leading-[30px] sm:text-[32px] sm:leading-[32px] font-medium';
$h3 = 'font-display text-[24px] leading-[28px] sm:text-[28px] sm:leading-[28px] font-medium';
$h4 = 'font-display text-[20px] leading-[26px] sm:text-[24px] sm:leading-[24px] font-medium';
$caption = 'font-display text-[18px] leading-[24px] sm:text-[24px] sm:leading-[24px] font-medium text-center text-white';

// Elementor accordion rows: 1px #D5D8DC, white, 15px/20px padding, 16px bold #333.
$accRow = 'group border border-[#D5D8DC] bg-white';
$accSummary = 'flex cursor-pointer list-none items-center gap-2.5 px-5 py-[15px] text-[16px] font-bold leading-4 text-[#333] [&::-webkit-details-marker]:hidden';
$accBody = 'px-5 pb-[15px] pt-0 text-[16px] leading-6 text-[#333] [&_p]:mb-4 [&_p:last-child]:mb-0 [&_a]:text-[#2984F9] [&_a]:underline';
$chevron = '<svg class="h-[15px] w-[15px] shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 320 512" fill="currentColor" aria-hidden="true"><path d="M143 352.3L7 216.3c-9.4-9.4-9.4-24.6 0-33.9l22.6-22.6c9.4-9.4 24.6-9.4 33.9 0l96.4 96.4 96.4-96.4c9.4-9.4 24.6-9.4 33.9 0l22.6 22.6c9.4 9.4 9.4 24.6 0 33.9l-136 136c-9.2 9.4-24.4 9.4-33.8 0z"/></svg>';

/**
 * A Vimeo training embed, reproduced at production's 16:9 with its exact player URL.
 * `$hash` is the video's unlisted-privacy token; it is part of the address, not decoration.
 */
$vimeo = function (string $id, string $hash = ''): string {
    $src = 'https://player.vimeo.com/video/'.$id.'?color&autopause=0&loop=0&muted=0&title=1&portrait=1&byline=1'
        .($hash !== '' ? '&h='.$hash : '').'#t=';

    return '<div class="w-full overflow-hidden bg-black aspect-video">'
        .'<iframe src="'.esc_url($src).'" title="Vimeo video player '.esc_attr($id).'" class="h-full w-full" '
        .'frameborder="0" loading="lazy" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>'
        .'</div>';
};

// ── FAQ accordion ────────────────────────────────────────────────────────────
$faqs = [
    ['PLEASE READ: What’s the average conversion rate?', '<p>Most agents typically convert 1 out of every 20-25 leads. Some do better, some do worse, depending on a lot of factors.</p><p>If you convert 1 out of 25 (over a 12 month period), your return on investment is usually 10x what you spend. That is proof that the system is working, and the next step is to double down by hiring more, not second guessing whether it works or not.&nbsp;</p><p>All that matters is return on cost.&nbsp;&nbsp;</p>'],
    ['If my cold caller is being replaced, do I have to pay for down time?', '<p>You pay your caller based on the hours of work done, so no. This is why it’s a good idea to replace your caller if you’re not getting optimized results because it’s better to have a couple of days of down time (which doesn’t cost you any hourly rate) rather than sticking to a mediocre caller just to maximize calling hours.</p>'],
    ['Should I leave Voicemails if someone doesn’t pick up the phone?', '<p>We do not recommend it at all. It could be illegal depending on how you would do it, and the response rate is very low anyway. We only recommend leaving real voicemails to leads if they don’t pick up, not prospects that haven’t indicated interest in selling.</p>'],
    ['Should I message prospects if they don’t pick up?', '<p>We do not recommend it as it could be illegal if they didn’t opt in to receive messages.</p>'],
    ['Can I use a different postcard than the one you designed?', '<p>You can use any postcard you want. It doesn’t matter to us, but the one we design for you works pretty well.&nbsp;&nbsp;</p>'],
    ['Can I have my Virtual Assistant do other tasks besides calling?', '<p>They directly work for you, not us, so you get to choose however you want to manage them and whatever tasks you want them to do.</p>'],
    ['Can I change the scripts?', '<p>Absolutely, however we recommend starting off with our default script, and once you see results, you can use the results as a benchmark to test and compare against any other script you want to try. </p><p>Personally, we do not recommend changing the script unless it’s not legally compliant in your state. It works extremely well and has been tested for over 8 years.</p>'],
    ['Do I have to send mailers to leads?', '<p>You don’t have to, but sending mailers to people that are saying “I want to sell my house” is probably one of the highest return on investments you can get.</p>'],
    ['Can I use a different CRM to track and follow up on my leads?', '<p>Absolutely, and you can even train your VA to put those leads in your own CRM if you choose to do it that way.</p>'],
    ['Can I call multiple different lists?', '<p>Sure, if you source the data, you can upload it on Mojo, we have video instructions on how to do that.</p>'],
    ['How do I pay my Virtual Assistants?', '<p>We personally use Wise. There is a video below on how to set up an account and use it.</p>'],
    ['How do I know how many hours a Virtual Assistant worked for payroll?', '<p>Every VA is trained to submit a timesheet for you. Ask for their timesheet. Having said that, we also recommend that you use a software to monitor time and screen activity during work hours. We recommend TimeDoctor for that, link to register and training in the guide above.</p>'],
];

// ── Reference links accordion ────────────────────────────────────────────────
// Every row is one "Click Here" link; the label casing is production's.
$links = [
    ['Weekly Mastermind Registration Link (Weekly Meeting with Abbas for Active Members)', 'https://remoteleverage.com/mastermind', 'Click Here'],
    ['Mojo CRM', 'https://www.mojosells.com/', 'Click Here'],
    ['OpenPhone - Virtual Phone Service', 'https://openph.one/referral/AaZS_R4', 'Click Here'],
    ['TimeDoctor -VA Screen Monitoring', 'https://timedoctor.com', 'Click Here'],
    ['Wise - VA Payment', 'https://wise.com/invite/dic/abbasm72', 'Click Here'],
    ['Sample VA Timesheet', 'https://remoteleverage.com/download/2783/?tmstv=1698705237', 'Click Here'],
    ['Cold Calling Script', 'https://docs.google.com/document/d/1DPvbpqY8zCxEiVkcKJ_K231n4wfH6RHWgfl_onWynB8/edit?usp=sharing', 'Click here'],
    ['Follow up Script', 'https://docs.google.com/document/d/1k5tXUwPcznWB9djEQjV1fwPCBQUjs99RXTex0IcnVlo/edit?usp=sharing', 'Click Here'],
    ['6x9 Envelopes Yellow Envelopes', 'https://www.amazon.com/dp/B071W9Z9T5/ref=cm_sw_r_cp_apip_o2ynbug5VzUvE', 'Click Here'],
    ['Avery Labels (To print mailing labels)', 'https://www.amazon.com/Avery-Shipping-Printers-Permanent-TrueBlock/dp/B00004Z6LV/ref=sr_1_3_sspa?crid=2PTPB32TUHO81&keywords=avery+5160+labels&qid=1702875989&sprefix=avery+5160%2Caps%2C203&sr=8-3-spons&sp_csd=d2lkZ2V0TmFtZT1zcF9hdGY&psc=1', 'Click Here'],
    ['Postcard Sending Service - Use to send Postcards', 'http://myrepostcards.com', 'Click Here'],
];

/**
 * Training-video rows, in production order. Each row is up to two cells; a cell is either
 * ['video', id, hash, caption, extra-caption] or ['note', text] for the one right-hand cell
 * that carries copy instead of an embed.
 */
$parts = [
    ['Part 1: Basics', [
        [['video', '905813420', '', 'Step 1: Fill out Onboarding Form (Click Here)'], ['video', '944244329', 'a4b6309539', 'Step 2: Weekly Mastermind (Click Here)']],
        [['video', '950376154', 'c690371637', 'Step 3: How to Pull Data from Mojo (after we set up your Mojo Account after the initial Onboarding Call)'], ['note', 'Next step:<br>Wait for an email from us to assign you a Virtual Assistant.<br><br>Typically it takes 1 week (sometimes up to 2 weeks).']],
    ]],
    ['Part 2: Tools', [
        [['video', '983685984', 'a758f2b579', 'Screen Monitoring - Time Doctor (optional)'], ['video', '980965431', '1401ab0b9d', 'How to Pay Your Virtual Assistant - Wise. Click Here to Sign Up']],
        [['video', '983685143', '00e5f1fc1f', 'Virtual Assistant Work Schedule'], ['video', '983685750', '0a74ac3eed', 'Virtual Assistant Payroll Sheet']],
        [['video', '894198188', '', 'How to Upload New Contacts to Mojo']],
    ]],
    ['Part 3: Mojo Training. Lead to Listing Course Material', [
        [['video', '894703523', '', 'Mojo: Quick Overview'], ['video', '894697064', '', 'Mojo: Reading Contact Cards']],
        [['video', '894698569', '', 'Mojo: How to Take Notes on Leads', 'How to Call a Specific List of Leads Frequently'], ['video', '894700918', '', 'Mojo: Call &amp; Session Reports']],
        [['video', '894717489', '', 'Mojo: Calendar', 'How to Call a Specific List of Leads Frequently']],
    ]],
    ['Part 4: Follow up Training. Lead to Listing Course Material', [
        [['video', '895915016', '', 'Follow up System: Overview (Very important)'], ['video', '895701975', '', 'Follow up: Demo']],
        [['video', '894233375', '', 'Letters &amp; Postcards: Part 1 - Using Mojo to Send Mail'], ['video', '921883297', '168b991e0a', 'Letters &amp; Postcards: Part 2 - Using Mojo to Send Mail']],
        [['video', '894226762', '', 'Seller Follow up: Script Training'], ['video', '894226779', '', 'Seller Follow up: Leaving Voicemails']],
        [['video', '894226803', '', 'Seller Follow up: As Is Script'], ['video', '894226818', '', 'Seller Follow up: 90 Days Script']],
        [['video', '894226835', '', 'Seller Follow up: Setting the Appointment Script'], ['video', '895414254', '', 'How to Build a Team of Cold Callers to Generate Seller Leads']],
    ]],
    ['Part 5: Live Training Recordings. Lead to Listing Course Material.', [
        [['video', '897343399', '', 'Live Webinar: Follow up &amp; Conversion Training'], ['video', '900419772', '', 'Live Webinar: Tips &amp; Tricks to Converting More Leads Using Mojo']],
        [['video', '902369729', '', 'Live Webinar: Follow up Script Training​'], ['video', '931561846', '91af288604', 'Live Webinar: Auditing &amp; Improving Your Results​']],
    ]],
];
?>

<!-- ============ §1 GUIDE: HERO VIDEO, FAQ, REFERENCE LINKS ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1170px"}} -->
<div class="wp-block-group alignfull bg-white pt-10 pb-12 lg:pt-[60px] lg:pb-16">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-center text-black">Client Onboarding Guide</h2>

        <div class="mt-[30px]">
            <?= $vimeo('951742336', '968877cb59') ?>
        </div>

        <h4 class="<?= $h4 ?> mt-[30px] text-[#00791C]">In 2024, we are on track to hit ~$1.2M+ in Commissions with only 1 agent, and multiple virtual assistants, while I am personally completely out of production. If you follow the system the way it&rsquo;s outlined, you&rsquo;ll be able to replicate the same results.</h4>

        <h4 class="<?= $h4 ?> mt-5 text-black">Frequently Asked Questions:</h4>

        <?php // @bespoke: acf/accordion-faq is the theme's only accordion and it is wrong here —
              // it lays out two balanced columns and emits Schema.org FAQPage structured data.
              // This is a single-column client-enablement list on a page that must not advertise
              // itself to search as an FAQ, so the rows are plain <details>. No cards, no images.?>
        <div class="mt-5">
            <?php foreach ($faqs as $i => [$q, $a]) { ?>
                <details class="<?= $accRow ?> <?= $i > 0 ? 'border-t-0' : '' ?>" <?= $i === 0 ? 'open' : '' ?>>
                    <summary class="<?= $accSummary ?>"><?= $chevron ?><span><?= $q ?></span></summary>
                    <div class="<?= $accBody ?>"><?= $a ?></div>
                </details>
            <?php } ?>
        </div>

        <h4 class="<?= $h4 ?> mt-5 text-black">Links for Future Reference</h4>

        <div class="mt-5">
            <?php foreach ($links as $i => [$label, $href, $text]) { ?>
                <details class="<?= $accRow ?> <?= $i > 0 ? 'border-t-0' : '' ?>" <?= $i === 0 ? 'open' : '' ?>>
                    <summary class="<?= $accSummary ?>"><?= $chevron ?><span><?= $label ?></span></summary>
                    <div class="<?= $accBody ?>"><p><a href="<?= esc_url($href) ?>" rel="noopener" target="_blank"><?= $text ?></a></p></div>
                </details>
            <?php } ?>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §2 WANT TO ADD MORE CALLERS? ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1170px"}} -->
<div class="wp-block-group alignfull bg-[#E9EEFA] py-10">
    <div class="mx-auto w-full max-w-[950px] bg-white p-2.5">
        <div class="flex flex-col gap-5">
            <h3 class="<?= $h3 ?> text-center text-black">Want to Add More Callers?</h3>

            <div class="text-center">
                <img src="<?= esc_url($img('Untitled-design-16.png')) ?>" alt=""
                     width="910" height="910" loading="lazy" decoding="async"
                     class="mx-auto h-auto w-full max-w-[910px]" />
            </div>

            <div class="text-[18px] leading-[27px] text-[#333]">
                <p>Add Caller #2-5 for only ONE Additional Fee.</p>
                <p class="mt-[13px]">You still have to pay them individually and pay for additional dialer system costs, but our monthly additional fee to source you up to 4 more callers on top of your first caller is an additional $540 whether you hire 1 more, or 4 more cold callers to scale your lead generation.&nbsp;</p>
            </div>

            <a href="https://calendly.com/d/cp3x-wtv-62j/add-additional-callers-remote-leverage"
               rel="noopener" target="_blank"
               class="block w-full bg-[#0DC863] px-2.5 py-4 text-center text-[17px] font-bold leading-[17px] text-white transition-opacity hover:opacity-90">Click Here to Add More Callers</a>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §3 TRAINING VIDEOS (28 VIMEO EMBEDS) ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1170px"}} -->
<div class="wp-block-group alignfull bg-[#2984F9] pt-[30px] pb-10">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-center text-white">Training Videos</h2>

        <?php foreach ($parts as [$partTitle, $rows]) { ?>
            <h4 class="<?= $h4 ?> mt-[52px] text-center text-white first-of-type:mt-5"><?= $partTitle ?></h4>

            <?php foreach ($rows as $row) { ?>
                <div class="mt-10 grid grid-cols-1 gap-x-[120px] gap-y-10 lg:grid-cols-2">
                    <?php foreach ($row as $cell) { ?>
                        <?php if ($cell[0] === 'note') { ?>
                            <div class="<?= $caption ?>"><?= $cell[1] ?></div>
                        <?php } else { ?>
                            <div>
                                <?= $vimeo($cell[1], $cell[2]) ?>
                                <p class="<?= $caption ?> mt-5"><?= $cell[3] ?></p>
                                <?php if (isset($cell[4])) { ?>
                                    <p class="<?= $caption ?> mt-5"><?= $cell[4] ?></p>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            <?php } ?>
        <?php } ?>

        <h4 class="<?= $h4 ?> mt-[52px] text-white">Who else do you know that should be apart of this program? Connect us through an introduction to get $500 credit for you, and $500 credit to whoever you refer!</h4>
    </div>
</div>
<!-- /wp:group -->
