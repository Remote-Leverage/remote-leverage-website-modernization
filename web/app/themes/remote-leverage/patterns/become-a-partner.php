<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Become a Partner
 * Slug: remote-leverage/become-a-partner
 * Categories: remote-leverage
 * Description: /become-a-partner/, the partnership landing page, built from the Partner LP Figma file (WR-278, 2026-09-24).
 *
 * Source: Figma cjCNqpNtBW754d6FStABU9, frame 86:20534 ("Remote Leverage Partner LP Desktop",
 * 1366px). Geometry and colours below were read off that frame's layer tree and a render of
 * it. The comp's own content width is 1104px; per docs/design-system.md rule 1 the sections
 * still sit in the canonical 1380px container, and only the text measures are taken from it.
 *
 * Every section is an existing block. Nine of them gained an option for this page, each
 * defaulting to what every other page already renders — see the WR-278 notes on each block.
 *
 * The people this page talks to are partnership prospects (agencies, SaaS vendors,
 * consultancies, communities), not leads: the closing form writes to its own table and never
 * enters the lead pipeline. See App\Application\Livewire\Partner\PartnershipProspectForm.
 */
$img = fn (string $file): string => BlockDefaults::preferWebp(BlockDefaults::pageImg('become-a-partner', $file));

// Every "Book a (partnership) call" on the page goes to the form that closes it.
$cta = '#booking-footer';

$ctaPill = fn (string $class = ''): string => view('blocks.partials.cta-pill', [
    'text' => 'BOOK A PARTNERSHIP CALL',
    'url' => $cta,
    'size' => 'small',
    'class' => $class,
])->render();

// Section headings and intros, as the comp sets them at every band: 48/53 bold centred (the
// text-section step, tracking included), and an 18/25 intro at -2% 20px under it. Both were
// fitted against the comp's measured line widths, not estimated, and the widths only fit in
// Inter Display: the comp sets its body copy in the display face, not the site's body Inter.
$h2 = 'font-display text-[34px] leading-[1.14] sm:text-5xl lg:text-section font-bold text-black text-center';
$intro = 'mx-auto mt-5 text-center font-display text-base text-black lg:text-lg lg:leading-[25px] lg:tracking-[-0.36px]';

// ── §2 hero ──────────────────────────────────────────────────────────────────
// Two headline lines in one ink: the block's accent line carries the second, set to inherit.
$heroData = BlockDefaults::withFieldKeys('home_hero', [
    'media' => 'none',
    'show_rating' => 1,
    'rating_count' => '235 reviews',
    'headline' => 'Become a Remote',
    'headline_accent' => 'Leverage Partner',
    'headline_accent_tone' => 'inherit',
    'subtitle' => '<span class="font-medium tracking-[-0.6px]">Help Clients Scale. Grow Together.</span>',
    'tick_tone' => 'leverage',
    'cta_lead' => 'Ready to become a partner?',
    'cta_text' => 'BOOK A PARTNERSHIP CALL',
    'cta_url' => $cta,
]);
BlockDefaults::encodeRepeater('checklist', 'field_home_hero_checklist', [
    ['item' => 'Give your clients access to top 1% Latin American and global talent'],
    ['item' => 'Open new referral and co-marketing opportunities for your brand'],
    ['item' => 'Add smarter hiring solutions to what you already offer'],
], $heroData);

// ── §3 trust bar ─────────────────────────────────────────────────────────────
// The comp's order, left to right. The files are sales-talents' white-on-transparent marks,
// greyed by .rl-logo-marquee-muted and sized to the comp by .rl-logo-marquee-compact. The comp reads "GLOBALY"; every other page, and this
// one, spells it GLOBALLY.
$logo = fn (string $file): string => BlockDefaults::pageImg('sales-talents', 'logos/'.$file);
$logos = [
    ['src' => $logo('image-3.png'), 'alt' => 'Garuz Legal Group'],
    ['src' => $logo('1-1.png'), 'alt' => 'G-Bit Wellness'],
    ['src' => $logo('image-5.png'), 'alt' => 'Sivia Law'],
    ['src' => $logo('image-4.png'), 'alt' => 'Setaero'],
    ['src' => $logo('image-10.png'), 'alt' => 'Bench'],
    ['src' => $logo('image-6.png'), 'alt' => 'Carbon Solutions Group'],
    ['src' => $logo('image-8.png'), 'alt' => 'RE/MAX'],
    ['src' => $logo('image-7.png'), 'alt' => 'AdCenter360'],
    ['src' => $logo('image-9.png'), 'alt' => 'BRRRR'],
];

// ── §4 why partner ───────────────────────────────────────────────────────────
// Sampled across the frame on a 7x9 grid: violet along the top (#7741FE at centre, #A656F5 at
// the left edge), deepening to #5640FB a quarter of the way down, then lifting through
// #7C99F2 to #D1E0FF by three quarters. The left edge runs paler and the right stays violet
// longer, which one linear ramp cannot do, so two side washes sit over it.
$whyBand = implode(',', [
    'radial-gradient(30% 34% at 0% 0%, #A656F5 0%, rgba(166,86,245,0) 100%)',
    'radial-gradient(34% 70% at 0% 50%, rgba(186,178,228,0.9) 0%, rgba(186,178,228,0) 100%)',
    'radial-gradient(30% 45% at 100% 55%, rgba(98,80,247,0.85) 0%, rgba(98,80,247,0) 100%)',
    'linear-gradient(180deg, #7741FE 0%, #5A2DFD 22%, #5845F9 38%, #7C99F2 52%, #BAD0F9 64%, #D1E0FF 78%, #CDDCFD 100%)',
]);
$whyCards = [
    [
        'icon' => $img('icons/value.svg'),
        'title' => 'Create More Value<br>for Your Clients',
        'desc' => 'Give clients a trusted solution when lack of capacity, hiring costs, or recruiting becomes a constraint.',
    ],
    [
        'icon' => $img('icons/expand.svg'),
        'title' => 'Expand Your Offering',
        'desc' => 'Add a workforce solution without building an internal recruiting operation.',
    ],
    [
        'icon' => $img('icons/revenue.svg'),
        'title' => 'Generate New Revenue<br>Opportunities',
        'desc' => 'Create value through referrals or a customized partnership structure based on your business and audience.',
    ],
    [
        'icon' => $img('icons/relationships.svg'),
        'title' => 'Strengthen Client<br>Relationships',
        'desc' => 'Instead of telling a client, “We don’t do that,” introduce a trusted resource that helps them execute.',
    ],
];

// ── §5 ways to partner ───────────────────────────────────────────────────────
$waysData = BlockDefaults::withFieldKeys('image_card_grid_block', [
    'headline' => 'There are many ways<br>to partner with Remote Leverage',
    'subheadline' => 'We work with you to identify the model that makes the most sense for your clients, audience, and business.',
    'columns' => 4,
    'align' => 'center',
    'card_title_size' => 'medium',
    'image_ratio' => '270/150',
    'card_cta_style' => 'pill',
]);
$ways = [
    [
        'image' => $img('ways-referral.jpg'),
        'title' => 'Referral<br>Partnerships',
        'text' => 'Introduce clients who need talent and create a repeatable referral channel.',
        'tags' => "Consultants\nFractional executives\nBusiness advisors\nAccountants and bookkeepers\nCoaches\nProfessional service providers",
        'tag_tone' => 'purple',
        'cta_text' => 'BOOK A CALL',
        'cta_url' => $cta,
    ],
    [
        'image' => $img('ways-agency.jpg'),
        'title' => 'Agency<br>Partnerships',
        'text' => 'Help your clients increase operational capacity with us as your complementary talent solution.',
        'tags' => "Marketing agencies\nSales agencies\nRecruiting firms\nOperations consultants\nCreative agencies\nProfessional services firms",
        'tag_tone' => 'teal',
        'cta_text' => 'BOOK A CALL',
        'cta_url' => $cta,
    ],
    [
        'image' => $img('ways-saas.jpg'),
        'title' => 'Technology &amp; SaaS<br>Partnerships',
        'text' => 'Your technology helps businesses operate better, we provide the people who execute.',
        'tags' => "HR technology\nPayroll platforms\nATS providers\nCRM platforms\nEcommerce technology\nPractice-management software\nBusiness SaaS platforms",
        'tag_tone' => 'blue',
        'cta_text' => 'BOOK A CALL',
        'cta_url' => $cta,
    ],
    [
        'image' => $img('ways-community.jpg'),
        'title' => 'Community &amp; Association<br>Partnerships',
        'text' => 'Give your members practical hiring solutions and create additional value for your community.',
        'tags' => "Founder communities\nProfessional associations\nIndustry groups\nAccelerators\nEntrepreneur networks\nMembership organizations",
        'tag_tone' => 'red',
        'cta_text' => 'BOOK A CALL',
        'cta_url' => $cta,
    ],
];

// ── §6 how to partner ────────────────────────────────────────────────────────
$steps = [
    [
        'num' => '01',
        'title' => 'Tell Us Your<br>Goals',
        'desc' => 'Share your audience, customers, network, and what kind of partnership you’re interested in. We’ll look at where our companies and customers overlap and create a custom partnership.',
    ],
    [
        'num' => '02',
        'title' => 'Introduce Clients<br>Easily',
        'desc' => 'Remote Leverage can support the process with messaging, resources, and a clear introduction path.',
    ],
    [
        'num' => '03',
        'title' => 'We Provide Talent<br>Solutions',
        'desc' => 'When one of your clients needs talent, our team handles candidate sourcing, screening, and shortlisting. The client interviews the candidates and decides who they want to hire.',
    ],
];

// ── §7 you identify the need ─────────────────────────────────────────────────
$introductionData = BlockDefaults::withFieldKeys('media_copy_block', [
    'headline' => 'You identify<br>the need. You make<br>the introduction',
    'image' => $img('introduction.jpg'),
    'image_position' => 'right',
    'image_max_width' => 548,
    'image_rounded' => 1,
    'vertical_align' => 'top',
    'padding' => 'roomy',
]);
$introduction = array_map(fn (string $text): array => ['text' => $text], [
    'You identify the need',
    'You make the introduction',
    'Remote Leverage finds the talent',
    'Your client hires directly',
    'Your client gains execution capacity',
    'You become the partner who helped solve the problem',
]);

// ── §8 direct hire ───────────────────────────────────────────────────────────
// Sampled the same way: a lilac #C5CBFC at top centre, a pale blue down the left edge, a pink
// #E3B4FA wash across the bottom left, and the page ground #F4F6FC at the bottom right.
$directBand = implode(',', [
    'radial-gradient(46% 32% at 50% 0%, #C3C9FC 0%, rgba(195,201,252,0) 100%)',
    'radial-gradient(38% 60% at 0% 45%, #D0DAFE 0%, rgba(208,218,254,0) 100%)',
    'radial-gradient(48% 36% at 18% 92%, #DDB2FB 0%, rgba(221,178,251,0) 100%)',
]);
$directStats = [
    ['value' => '2,000+', 'label' => 'long-term contracts'],
    ['value' => '0', 'label' => 'to start'],
    ['value' => '12-month', 'label' => 'replacement guarantee'],
];
$directCards = [
    [
        'icon' => $img('icons/direct-hire.svg'),
        'title' => 'Direct Hire',
        'desc' => 'Clients hire the talent directly rather than remaining permanently dependent on a middleman.',
    ],
    [
        'icon' => $img('icons/no-markup.svg'),
        'title' => 'No Ongoing Staffing Markup',
        'desc' => 'Remote Leverage uses a placement model rather than taking a recurring percentage of the talent’s compensation.',
    ],
    [
        'icon' => $img('icons/savings.svg'),
        'title' => 'Same Talent, 70% Savings',
        'desc' => 'At $6 to $10 per hour, clients average ~70% cost savings versus hiring the same role in the U.S. Our talent has the same experience, the same education – at a drastically different rate.',
    ],
    [
        'icon' => $img('icons/global.svg'),
        'title' => 'Top 1% Global Talent',
        'desc' => 'We review 2,000+ applicants every day and present only the top 1%. Clients receive 4 to 6 pre-vetted, English-fluent candidates per role, with a first shortlist in 48 hours.',
    ],
    [
        'icon' => $img('icons/interview.svg'),
        'title' => 'Interview Before Hiring',
        'desc' => 'Your clients make the final hiring decision, then keep it risk-free with a 12-month replacement guarantee. Other recruiting agencies place talent without allowing clients to interview.',
    ],
    [
        'icon' => $img('icons/roles.svg'),
        'title' => 'Multiple Roles &amp; Industries',
        'desc' => 'Thousands of hires across 61 countries, 6 continents, and 50 roles, from administrative assistants to developers to doctors. We serve all industries and place talent in custom roles.',
    ],
];

// ── §9 who should partner ────────────────────────────────────────────────────
// Row by row, left then right — the comp's two columns read top to bottom.
$who = array_map(fn (string $text): array => ['text' => $text], [
    'Growing but constrained by payroll costs',
    'Expanding sales or customer support',
    'Struggling to hire quickly',
    'Scaling an agency or service business',
    'Overwhelmed by administrative work',
    'Looking for global talent',
    'Losing opportunities because of limited execution capacity',
    'Trying to protect margins while growing',
]);

// ── §10 the form ─────────────────────────────────────────────────────────────
// layout/form/background are read raw by the block (like skin), so they take no field keys.
$formData = array_merge(BlockDefaults::withFieldKeys('booking_footer', [
    'headline' => 'Not every partnership needs to look the same',
    'description' => 'Tell us a little about your company, your clients, and where you see an opportunity. We’ll determine if there’s a fit and how to get started fast!',
]), [
    'layout' => 'stacked',
    'form' => 'partnership',
    'background' => 'map-glow',
    'form_button_text' => 'Book a Partnership Call',
]);
?>
<!-- ============ §2 HERO ============ -->
<?= BlockDefaults::patternBlock('home-hero', $heroData, ['align' => 'full']) ?>

<!-- ============ §3 TRUSTED BY ============ -->
<!-- wp:group {"align":"full","className":"rl-logo-marquee-muted rl-logo-marquee-compact pt-3.5","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull rl-logo-marquee-muted rl-logo-marquee-compact pt-3.5 has-bg-light-background-color has-background">
    <!-- wp:html -->
    <p class="text-center text-[11.4px] leading-[24px] tracking-[-0.342px] text-black">TRUSTED BY SCALING TEAMS GLOBALLY</p>
    <!-- /wp:html -->

    <?= BlockDefaults::renderEcom('client-logos-marquee', $logos) ?>
</div>
<!-- /wp:group -->

<!-- ============ §4 WHY PARTNER ============ -->
<!-- wp:group {"align":"full","className":"px-4 sm:px-6 lg:px-8 py-16 lg:py-[100px]","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull px-4 sm:px-6 lg:px-8 py-16 lg:py-[100px]" style="background-color:#CDDCFD;background-image:<?= esc_attr($whyBand) ?>">
    <!-- wp:html -->
    <div class="mb-10 lg:mb-[50px]">
        <h2 class="<?= $h2 ?> text-white">Why Partner With<br>Remote Leverage?</h2>
        <div class="<?= $intro ?> max-w-[770px] text-white [&_p+p]:mt-[25px]">
            <p>Remote Leverage provides hiring solutions for businesses, placing talent across dozens of roles including administrative, sales, marketing, customer support, bookkeeping, healthcare, legal, ecommerce, and custom roles.</p>
            <p>Your team stays focused on what you do best.<br>We handle the recruiting process.</p>
        </div>
    </div>
    <!-- /wp:html -->

    <?= BlockDefaults::renderFeatureCards('4', [], $whyCards, '', 'icon') ?>

    <!-- wp:html -->
    <div class="mt-10 flex justify-center lg:mt-[38px]"><?= $ctaPill() ?></div>
    <!-- /wp:html -->
</div>
<!-- /wp:group -->

<!-- ============ §5 WAYS TO PARTNER ============ -->
<?= BlockDefaults::renderEcom('image-card-grid', $ways, $waysData, ['align' => 'full']) ?>

<!-- ============ §6 HOW TO PARTNER ============ -->
<!-- wp:group {"align":"full","className":"px-4 sm:px-6 lg:px-8 py-16 lg:py-[100px]","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull px-4 sm:px-6 lg:px-8 py-16 lg:py-[100px] has-bg-light-background-color has-background">
    <!-- wp:html -->
    <div class="mb-12 lg:mb-[50px]">
        <h2 class="<?= $h2 ?>">How to Partner with Us</h2>
        <p class="<?= $intro ?> max-w-[735px]">Partnering with Remote Leverage is easy! We can develop custom partnerships. And, can grow partnerships with additional referrals, campaigns, content, events, and other strategic initiatives.</p>
    </div>
    <!-- /wp:html -->

    <?= BlockDefaults::renderBlockWithRepeater('process-steps', 'steps', 'field_process_steps_block_steps', $steps, BlockDefaults::withFieldKeys('process_steps_block', ['treatment' => 'partner'])) ?>

    <!-- wp:html -->
    <div class="mt-12 flex justify-center lg:mt-[50px]"><?= $ctaPill() ?></div>
    <!-- /wp:html -->
</div>
<!-- /wp:group -->

<!-- ============ §7 YOU IDENTIFY THE NEED ============ -->
<?= BlockDefaults::renderBlockWithRepeater('media-copy', 'timeline', 'field_media_copy_block_timeline', $introduction, $introductionData, ['align' => 'full']) ?>

<!-- ============ §8 DIRECT HIRE ============ -->
<!-- wp:group {"align":"full","className":"px-4 sm:px-6 lg:px-8 py-16 lg:py-[100px]","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull px-4 sm:px-6 lg:px-8 py-16 lg:py-[100px] has-bg-light-background-color has-background" style="background-image:<?= esc_attr($directBand) ?>">
    <!-- wp:html -->
    <div class="mb-10 lg:mb-[50px]">
        <h2 class="<?= $h2 ?> mx-auto max-w-[735px]">Direct hire, no ongoing staffing markup, same talent at a drastically different rate</h2>
    </div>
    <!-- /wp:html -->

    <?= BlockDefaults::renderEcom('stats-band', $directStats, BlockDefaults::withFieldKeys('stats_band_block', ['layout' => 'pills'])) ?>

    <!-- wp:html -->
    <div class="h-10 lg:h-[50px]" aria-hidden="true"></div>
    <!-- /wp:html -->

    <?= BlockDefaults::renderFeatureCards('3', [], $directCards, '', 'icon') ?>

    <!-- wp:html -->
    <div class="mt-12 flex justify-center lg:mt-[50px]"><?= $ctaPill() ?></div>
    <!-- /wp:html -->
</div>
<!-- /wp:group -->

<!-- ============ §9 WHO SHOULD PARTNER ============ -->
<!-- wp:group {"align":"full","className":"px-4 sm:px-6 lg:px-8 py-16 lg:py-[100px]","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull px-4 sm:px-6 lg:px-8 py-16 lg:py-[100px] has-bg-light-background-color has-background">
    <!-- wp:html -->
    <div class="mb-10 lg:mb-[50px]">
        <h2 class="<?= $h2 ?>">Who Should Become<br>a Partner?</h2>
        <p class="<?= $intro ?> max-w-[735px]">We help companies of all sizes grow and scale through smarter talent solutions. Partner with us if you work with businesses that are:</p>
    </div>
    <!-- /wp:html -->

    <?= BlockDefaults::renderEcom('checklist-grid', $who, BlockDefaults::withFieldKeys('checklist_grid_block', ['style' => 'row', 'icon' => 'arrow'])) ?>
</div>
<!-- /wp:group -->

<!-- ============ §10 PARTNERSHIP FORM ============ -->
<?= BlockDefaults::patternBlock('booking-footer', $formData, ['align' => 'full']) ?>
