<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Store (Contractor of Record playbook)
 * Slug: remote-leverage/store
 * Categories: remote-leverage
 * Description: Production /store/ — the Contractor of Record sales playbook: discovery script,
 *   presentation, seat pricing, deposit routes and bundle packages. Production serves it
 *   noindex,nofollow.
 *
 *   2026-09-15: built on client direction — "this is the same kind of purpose as vastore5.
 *   Build it, follow vastore5 styling decisions. Keep functionality." Production is therefore
 *   the CONTENT and BEHAVIOUR spec (every price, question, script line, link and the call timer
 *   are unchanged) while the presentation is /vastore5/'s, not production's Elementor layout.
 *   /store/ is the Contractor of Record twin of /vastore5/: same artefact, other product.
 */

/*
 * @bespoke: the page is composed from the shared sales-playbook partials rather than blocks,
 * which is the same ladder patterns/vastore5.php walked and re-checked here against /store/'s
 * own sections. What is NOT bespoke: the scoped stylesheet, the sticky jump nav, the call timer
 * and the deposit tab card are all included from resources/patterns/, shared byte-for-byte with
 * /vastore5/, so this pattern adds no second copy of any of them.
 *
 *   Seat pricing card  - acf/media-copy is the image + heading + rich copy + CTA shape and is
 *       the closest block. Ruled out for the same defect that ruled it out on /vastore5/: its
 *       body slot is hard-coded `text-card text-black`, so on the dark pricing band the copy is
 *       black on #25104A, and it renders a full-width <section> rather than a card that can sit
 *       inside a band. acf/image-card-grid, acf/guarantee-card and acf/split-compare-cards are
 *       multi-card grids with no single-card mode.
 *   Bundle price grid  - acf/cost-comparison is label/value rows, the right shape, but it is
 *       locked to two side-by-side tables with fixed red/green header bars and one shared CTA;
 *       /store/ needs one four-across row of figures on a dark band and no CTA at all (unlike
 *       /vastore5/, production's COR bundles carry no button). acf/data-table hard-renders a red
 *       cross and a green tick per row — "$12,000 ✗ ... ✓" is nonsense for a price list.
 *       acf/roles-pricing-grid is per-role photo cards with tasks and tools.
 *   Discovery questions- acf/feature-cards renders a bare <div> and drops in cleanly, but every
 *       card emits its description paragraph unconditionally and these are titles with no body,
 *       so the grid would ship 7 empty <p> elements; its 22px bold card title is also wrong for
 *       a 25-word question.
 *   Presentation prose - no block renders long-form script copy; acf/media-copy is the nearest
 *       and is ruled out above.
 *   Calendly embed     - acf/booking and acf/vacalendar-hero render the in-house Livewire
 *       booking wizard, not Calendly. Production books COR compliance consultations through a
 *       specific Calendly event; swapping the scheduler changes behaviour, which is out of scope.
 *   Call timer         - an interactive sales tool with no block equivalent, and the same widget
 *       production puts on both playbooks. Shared partial, not new markup.
 */

// Production's pricing-card photo. The same stock image production uses on /vastore5/; kept as
// this page's own source art under resources/images/pages/store/ so the page is self-describing.
$img = BlockDefaults::preferWebp(BlockDefaults::pageImg('store', 'qtq80-BFjDqk-1024x683.jpeg'));

// Production's COR Calendly events: the inline embed is the unpaid consultation, the failsafe
// button is the paid one a rep hands over when the embed will not load.
$calendlyInline = 'https://calendly.com/d/cyrw-79t-s5r/remote-leverage-hr-cor-compliance-consultation?hide_event_type_details=1&hide_gdpr_banner=1';
$calendlyPaid = 'https://calendly.com/d/cvmp-p5d-9gz/remote-leverage-hr-cor-compliance-consultation-paid';

$discovery = [
    'Tell me a little about you and your business and why you were interested in a Contractor of Record service?',
    'Do you currently have any remote contractors or Virtual Assistants working for you? If so, how are you handling payroll and legal compliance right now',
    'How many contractors do you have or planning to hire?',
    'How Many contractors do you have? (How many do you plan on hiring?)',
    'What countries are they currently based out of? (what countries are you planning on hiring from?)',
    'Are your people mainly long term hires or project based?',
    '(if they have contractors now) How soon do you need to onboard people with a legal entity and start processing payments?',
];

/* Production's presentation script, verbatim. The two numbered points sit inside the
   screen-monitoring paragraph, so they are rendered as their own list between paragraphs. */
$presentationBefore = [
    'We have two services we can help you with, if you&rsquo;re ever needing to hire for any role, we have a service where we can help you find and hire talent from Latin America and other regions. This is actually how we started and we&rsquo;ve since helped thousands of businesses hire candidates for just about any role you can imagine, usually for $6-$10/hr for most positions.',
    'The other service is our Contractor of Record service, where essentially we handle compliance, payment processing, screen monitoring, and other things.',
    'As you know when you hire talent internationally, every country has its own employment laws you have to follow, and these laws change literally every single day.',
    'So when you hire people, you can either take that on the headache of complying with all these different laws yourself and expose your business to international risk and lawsuits, or you can outsource the compliance to a company like ours where we&rsquo;d handle it for you.',
    'The way that works is instead of you hiring people under your company, what we&rsquo;d do to help protect you from misclassification and compliance risks is we&rsquo;d officially take on anyone you want to hire as a contractor to work for us under our own legal entity, so that if anything happens, you&rsquo;re not the one on the hook. And then what we do is we assign those people to work for you, so there is a degree of separation between you and the contractor.',
    'Instead of paying the contractor directly and having them work for you, you&rsquo;d send the payments to us, and we&rsquo;d pass the payments on to the contractors and handle the legal compliance so as far as you&rsquo;re concerned, you&rsquo;re just paying a US entity, you&rsquo;re not paying international contractors.',
    'This protects you from contractor misclassification risk, which is one of the most common (and expensive) compliance issues US businesses face with remote workers.',
    'Besides compliance, we will also handle payment processing on your behalf. The way it works is your contractor would submit an invoice to us for payment through our system every two weeks, we email you the total amount for your review, and if you approve, we take money out of your account and pass it on to the contractor.',
    'Again you want that degree of separation to protect yourself legally.',
    'Also, one other problem that happens all the time when you hire people that work remotely is they might bill you for hours where they&rsquo;re not actually working, or even if you pay a salary and you&rsquo;re expecting a set 7 or 8 hours of work done per day, they might be goofing off on social media or YouTube and biling you without actually working, so part of our onboarding process with contractors is we have them install a screen monitoring device on their computer. This allows us to do two things:',
];

$monitoringPoints = [
    'It allows us to verify that if they&rsquo;re billing you for hours or work, that they&rsquo;re actually working and not just pretending to work',
    'It also allows us to verify that if they&rsquo;re charging you for say 160 hours a month, we can see if they were in fact logging 160 hours a month or if they&rsquo;re only logging in to work half of that time and billing you as if they were working the whole time.',
];

$presentationAfter = [
    'This will help keep anyone you onboard through us a lot more productive as they know they&rsquo;re being monitored. If you decide to not do monitoring, you can always opt out of that, but we highly recommend it to all our clients as we save a lot of clients from paying contractors inflated payments.',
    'So again we help handle compliance and take on the legal risks there, documentation, as well as handle payments, and screen tracking to make sure they&rsquo;re actually working.',
    'And we can do that for any contractors that you bring on from the outside if you have someone working for you already, or we can also help you build out your team and hire people from scratch (which is another service we provide) if you need to bring people onto your team.',
];

$seatFacts = [
    'One time annual payment (can be split into a payment plan)',
    'Charged per seat so you can swap contractors in and out at no additional charge',
    'Payment processing to international contractors',
    'Legal compliance (including attorney reviewed contracts, document collection, etc)',
    'Screen activity tracking to ensure contractors are working on work tasks',
    'In house compliance legal team to answer all your questions',
    '1 Month contractor salary held as a deposit to pay the contractor&rsquo;s final month payment (extra will be sent back to you)',
];

/* [label, headline figure, per-contractor note] — split only so the figure can carry the type
   weight. Both spans stay inline, so the rendered line is still "$12,000 ($4k Per Contractor)". */
$bundleRows = [
    ['3 Contractors:', '$12,000', '($4k Per Contractor)'],
    ['5 Contractors:', '$17,500', '($3.5k Per Contractor)'],
    ['7 Contractors:', '$23,000', '($3.28k Per Contractor)'],
    ['10 Contractors:', '$30,000', '($3k Per Contractor)'],
];

/* [tab label, panel heading, bullet items] — production's tab labels are shorter than the
   headings they reveal ("Immediate HM Call" vs "Immediate Hiring Manager Call"). */
$depositTabs = [
    'schedCall' => ['Client Wants to Schedule &amp; Pay on Their Own', 'Client Wants to Schedule &amp; Pay on Their Own', [
        'Give this link to the client. They can schedule the call and pay the refundable fee on their own:',
        '<a href="https://RemoteLeverage.com/Service-COR" target="_blank" rel="noopener noreferrer">https://RemoteLeverage.com/Service-COR</a>',
    ]],
    'manual' => ['Apple Pay / Credit Card Link', 'Apple Pay / Credit Card Link', [
        '1) Give this link to the client. They can pay the refundable deposit through Apple Pay or Credit Card: <a href="https://RemoteLeverage.com/CORDeposit" target="_blank" rel="noopener noreferrer">https://RemoteLeverage.com/CORDeposit</a>',
        '2) After the client pays, <a href="'.$calendlyPaid.'" target="_blank" rel="noopener noreferrer">click here</a> to go to Calendly and schedule the onboarding call (no payment needed).',
        '3) Schedule the call and announce in the group chat to let the Hiring Manager know to handle the new client.',
    ]],
    'hmCall' => ['Immediate HM Call', 'Immediate Hiring Manager Call', [
        '1) Announce in chat that you need immediate HM to handle onboarding.',
        '2) Go to: <a href="https://RemoteLeverage.com/CORDeposit" target="_blank" rel="noopener noreferrer">https://RemoteLeverage.com/CORDeposit</a>',
        '3) Fill out payment info &amp; process payment.',
    ]],
    'zelle' => ['Zelle Transfer', 'Zelle &ndash; Instant Bank Transfer', [
        '1) Send deposit via Zelle.',
        'Recipient email: <strong>Abbas@RemoteLeverage.com</strong>',
        '2) After the client pays, <a href="'.$calendlyPaid.'" target="_blank" rel="noopener noreferrer">click here</a> to go to Calendly and schedule the onboarding call (no payment needed).',
        '3) Schedule the call and announce in the group chat to let the Hiring Manager know to handle the new client.',
    ]],
];

/* Jump-nav targets. Labels are the sections' own headings verbatim. Production /store/ has no
   jump nav; it is here because /vastore5/ has one and this page follows /vastore5/'s decisions.
   The ids keep the v5-s- prefix the shared skin's scroll-margin rule keys off. */
$navItems = [
    ['v5-s-discovery', 'Discovery'],
    ['v5-s-presentation', 'Presentation'],
    ['v5-s-pricing', 'Pricing'],
    ['v5-s-booking', 'Deposit &amp; Payment Options'],
    ['v5-s-bundles', 'Bundle Pricing Packages'],
];

?>
<!-- rl:noindex — internal sales collateral. App\Support\PageRobots reads this marker and
     emits noindex, nofollow, matching production, which serves /store/ noindex,nofollow.
     The page stays published and reachable; this keeps it out of search results only, it is
     NOT access control. -->
<!-- wp:html -->
<?php
$skin = ['scope' => 'corstore'];
include get_theme_file_path('resources/patterns/sales-playbook-skin.php');
?>
<!-- /wp:html -->

<!-- wp:html -->
<?php
$nav = ['scope' => 'corstore', 'items' => $navItems];
include get_theme_file_path('resources/patterns/sales-playbook-nav.php');
?>
<!-- /wp:html -->

<!-- wp:html -->
<section class="corstore v5-band v5-section v5-section--tight" id="v5-s-tools">
    <div class="v5-inner">
        <div class="v5-tools">
            <?php include get_theme_file_path('resources/patterns/call-timer-widget.php'); ?>
        </div>
    </div>
</section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="corstore v5-band v5-section" id="v5-s-discovery">
    <div class="v5-inner">
        <div class="v5-head">
            <h2>Discovery</h2>
        </div>
        <ol class="v5-qgrid">
            <?php foreach ($discovery as $question) { ?>
                <li><strong><?= esc_html($question) ?></strong></li>
            <?php } ?>
        </ol>
    </div>
</section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="corstore v5-band v5-section" id="v5-s-presentation">
    <div class="v5-inner">
        <div class="v5-head">
            <h2>Presentation:</h2>
        </div>
        <div class="v5-card v5-card--pad">
            <div class="v5-prose">
                <?php foreach ($presentationBefore as $para) { ?>
                    <p><?= $para ?></p>
                <?php } ?>
                <ol>
                    <?php foreach ($monitoringPoints as $point) { ?>
                        <li><?= $point ?></li>
                    <?php } ?>
                </ol>
                <?php foreach ($presentationAfter as $para) { ?>
                    <p style="margin-top:14px"><?= $para ?></p>
                <?php } ?>
                <div class="v5-callout"><b>Any questions so far?</b></div>
            </div>
        </div>
    </div>
</section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="corstore v5-band v5-band--deep v5-section" id="v5-s-pricing">
    <div class="v5-inner">
        <div class="v5-head v5-head--center">
            <h2>Pricing</h2>
        </div>

        <div class="v5-pricecard">
            <div class="v5-pricecard__head">
                <h2 class="v5-pricecard__title">Contractor of Record - One Time Payment<br>(Payment Plans Available)</h2>
            </div>
            <div class="v5-pricecard__grid">
                <div class="v5-pricecard__media">
                    <img src="<?= esc_url($img) ?>" width="400" height="280" alt="" loading="lazy" decoding="async">
                </div>
                <div class="v5-pricecard__copy">
                    <h3 class="v5-pricecard__sub">$4200 Per Contractor Seat - Annual</h3>
                    <ul class="v5-facts">
                        <?php foreach ($seatFacts as $fact) { ?>
                            <li><?= $fact ?></li>
                        <?php } ?>
                    </ul>
                    <div class="v5-pricecard__note">
                        <p>$100 refundable deposit to begin the onboarding process.<br>No payment required until you and the contractors are onboarded.</p>
                    </div>
                    <?php /* Production's "Get Started" carries no href at all — it is a dead
                             Elementor button sitting directly above the Calendly embed that is
                             the actual next step. Pointed at that embed rather than invented a
                             destination. */ ?>
                    <a class="v5-btn v5-btn--block" href="#v5-s-booking">Get Started</a>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="corstore v5-band v5-band--deep v5-section v5-section--tight" id="v5-s-booking">
    <div class="v5-inner">
        <div class="v5-calwrap">
            <div class="calendly-inline-widget" data-url="<?= esc_url($calendlyInline) ?>" style="min-width:320px;height:700px;"></div>
        </div>
        <script src="https://assets.calendly.com/assets/external/widget.js" async></script>

        <?php
        $playbookDeposit = [
            'failsafe' => [
                ['Stripe Payment', 'https://buy.stripe.com/3cIfZh1B8cQT29D0eWfrW0F'],
                ['Book Meeting', $calendlyPaid],
            ],
            'tabs' => $depositTabs,
        ];
include get_theme_file_path('resources/patterns/sales-playbook-deposit.php');
?>
    </div>
</section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="corstore v5-band v5-band--deep v5-section" id="v5-s-bundles">
    <div class="v5-inner">
        <div class="v5-head v5-head--center">
            <h2>Bundle Pricing Packages</h2>
        </div>

        <div class="v5-bundles">
            <?php foreach ($bundleRows as $row) { ?>
                <div class="v5-glass v5-bundle">
                    <div class="v5-bundle__label"><?= esc_html($row[0]) ?></div>
                    <div class="v5-bundle__value"><strong><?= esc_html($row[1]) ?></strong> <?= esc_html($row[2]) ?></div>
                </div>
            <?php } ?>
        </div>
    </div>
</section>
<!-- /wp:html -->
