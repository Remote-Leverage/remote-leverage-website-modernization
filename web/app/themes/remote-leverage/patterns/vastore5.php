<?php

use App\Support\BlockDefaults;

/**
 * Title: VA Store 5 - Internal Sales Playbook
 * Slug: remote-leverage/vastore5
 * Categories: remote-leverage
 * Description: Production /vastore5/ (page 10809). Internal sales-enablement collateral, not a
 *   marketing landing page: probing/discovery scripts, the one-time and bundle pricing
 *   breakdowns, deposit payment routes and objection handling. Production serves it
 *   noindex,nofollow.
 *
 *   2026-09-15: visual parity with production was deliberately dropped at the client's request
 *   ("the original design looked hideous"). Production remains the CONTENT and BEHAVIOUR spec —
 *   every price, word and interaction is unchanged — but the presentation is rebuilt on this
 *   theme's tokens. See the @bespoke note below for the block-reuse ladder that was re-walked
 *   under the new, parity-free constraints.
 */

/*
 * @bespoke: the shipped blocks were re-checked against the redesign (not against production's
 * Elementor layout), because with parity dropped a block that was the wrong *shape* before could
 * now be the right answer. Each one below was ruled out on a concrete, reproducible defect rather
 * than "it looks different".
 *
 *   Objections accordion  - acf/accordion-faq is the closest component and was the strongest
 *       candidate. Three blocking defects, all in resources/views/blocks/accordion-faq.blade.php,
 *       none fixable without editing a shared block this agent may not touch:
 *         (a) it hard-splits the rows into two balanced columns (`faqsLeft`/`faqsRight` in
 *             AccordionFaqBlock::with()). A rep works down 13 objections live on a call; a
 *             zig-zag two-column scan is the wrong reading order for that.
 *         (b) AccordionFaqBlock::generateSchemaJson() always emits Schema.org FAQPage
 *             structured data. This page is internal collateral served noindex,nofollow —
 *             publishing customer-facing FAQ markup from it is wrong regardless of indexing.
 *         (c) the answer slot is `text-[15px] leading-relaxed` with no list styling, and the
 *             theme has no global `ul{list-style}` rule (Tailwind preflight strips markers;
 *             only .rl-legal-doc re-adds them). Three of the 13 objection bodies carry <ul>/<ol>
 *             ("Interviewing other companies", "I need to talk to partner/team", "I don't want
 *             to pay upfront") and would silently lose their bullets and their 1-4 numbering.
 *   Hourly-rate + bundle price rows
 *                         - acf/data-table hard-renders a red cross and a green tick on every
 *       row (it is a feature matrix, not a price list) — "$6/hr ✗ ... $4992 ✓" is nonsense.
 *       acf/cost-comparison is label/value rows, which fits, but it is locked to two side-by-side
 *       tables with a single shared CTA and fixed red/green header bars, while this page needs two
 *       separately-captioned tables with two different JotForm links; its value type is 17px,
 *       which is too light for the numbers that are the point of the section.
 *       acf/roles-pricing-grid is per-role photo cards with tasks/tools.
 *   Recruitment pricing card
 *                         - acf/media-copy is exactly the image + heading + rich copy + CTA
 *       shape and was adopted on paper, then rejected: its body slot is hard-coded
 *       `text-card text-black`, so `tone: dark` renders black body copy on the #25104A band and
 *       is unusable, and the block is a full-width <section>, not a card that can sit inside the
 *       pricing band. acf/guarantee-card, acf/image-card-grid and acf/split-compare-cards are all
 *       multi-card grids with no single-card mode.
 *   Discovery / cannot-hire lists
 *                         - acf/feature-cards is the theme's card grid and renders a bare <div>
 *       (the caller supplies the container), so it drops in cleanly. Ruled out because every card
 *       emits its description paragraph unconditionally: these sections are titles with no body,
 *       so the grid would ship 7 (and 8) empty <p> elements, and the card title style
 *       (22px bold) is wrong for a 45-word rule such as the "very different job categories" one.
 *   Probing / deposit tab cards, role directory, call timer, JD generator
 *                         - interactive one-off sales tools; nothing comparable in
 *       docs/block-inventory.md.
 *   Calendly embed        - acf/booking and acf/vacalendar-hero both render the Livewire booking
 *       wizard, not Calendly. Swapping the scheduler changes behaviour, which is out of scope.
 *
 * The page therefore keeps one scoped stylesheet, but it is now built from the theme's own
 * `@theme` custom properties (--color-brand-*, --radius-card, --shadow-card, --font-sans,
 * --width-container), which Tailwind emits to :root under `theme(static)`. Scoped CSS rather than
 * utilities because this page must re-add list markers that Tailwind preflight strips, and
 * because the tab/accordion/dropdown state selectors have no utility equivalent.
 */

$img = BlockDefaults::preferWebp(BlockDefaults::pageImg('vastore5', 'qtq80-BFjDqk-1024x683.jpeg'));

$rateRows = [
    ['$6/hr', '$4992'],
    ['$7/hr', '$5824'],
    ['$8/hr', '$6656'],
    ['$9/hr', '$7488'],
    ['$10/hr', '$8320'],
];

/* [label, headline figure, per-VA note] — split only so the figure can carry the type weight.
   Both spans stay inline, so the rendered line is still "$12,000 ($4k Per VA)". */
$bundleRows = [
    ['3 VAs:', '$12,000', '($4k Per VA)'],
    ['5 VAs:', '$17,500', '($3.5k Per VA)'],
    ['7 VAs:', '$23,000', '($3.28k Per VA)'],
    ['10 VAs:', '$30,000', '($3k Per VA)'],
];

$probing = [
    'hadVA' => [
        'agency' => [
            ['Agency Experience', [
                'Where were the VAs based, and did time-zone overlap work?',
                'What was the fee structure and VA take-home (if known)?',
                'How were day-to-day communication and responsiveness?',
            ]],
            ['Performance &amp; Support', [
                'What went well and what fell short (quality, productivity, attendance)?',
                'What support did the agency provide&mdash;coaching, replacements, contingency?',
                'How were performance issues handled and how fast were replacements?',
            ]],
            ['Terms &amp; Next Step', [
                'Any contract constraints or penalties?',
                'What prompted you to explore alternatives now?',
            ]],
        ],
        'direct' => [
            ['Direct-Hire Experience', [
                'How long did sourcing and hiring usually take?',
                'Project-based or long-term? How was retention?',
                'How much of your own time went into shortlisting/interviews?',
            ]],
            ['Quality &amp; Risk', [
                'How did you verify skills beyond the interview?',
                'Any cases where a strong interview underperformed later?',
            ]],
            ['Why a Partner Now?', [
                'What would a vetted, role-fit shortlist be worth to you?',
                'Do coverage, replacements, or security factor into the decision?',
            ]],
        ],
    ],
    'firstTimer' => [
        ['Role &amp; Context', [
            'Is this a new role or a replacement?',
            'Which three traits define the ideal VA for you?',
            'Which tasks should they handle in the first two weeks?',
        ]],
        ['Tools &amp; Process', [
            'Which tools are in use today?',
            'Do you have SOPs, or should the VA help create them?',
        ]],
        ['Success &amp; Timing', [
            'How will you measure success at 30/60/90 days?',
            'What&rsquo;s your ideal start date?',
        ]],
    ],
    'hiredDirect' => [
        ['Marketplace Experience', [
            'On Upwork/Fiverr/others, how long did hiring take?',
            'Were roles project-based or long-term hires?',
            'How would you rate candidate quality and consistency?',
        ]],
        ['Fit &amp; Efficiency', [
            'How well did time-zone alignment and responsiveness work?',
            'How much time did vetting consume versus managing outcomes?',
            'Any concerns with privacy/security or multi-client juggling?',
        ]],
        ['Moving to a Partner', [
            'Which outcomes are you looking to improve with an agency?',
            'If we present a strong shortlist, when should interviews start?',
        ]],
    ],
    'hasBPO' => [
        ['Current Operation', [
            'How long is your contract and what are the exit terms?',
            'How would you rate talent quality and consistency?',
            'How do current results compare to targets?',
        ]],
        ['Scale &amp; Flexibility', [
            'How quickly can you add or replace team members?',
            'What&rsquo;s your attrition rate and what drives it?',
        ]],
        ['Why Explore Options', [
            'What prompted the search now?',
            'Which gaps matter most&mdash;quality, communication, time-zone fit, or cost?',
        ]],
    ],
];

$calendlyBooking = 'https://calendly.com/d/ctm7-y8b-w6q/remote-leverage-onboarding-applicant-criteria-paid';

/* [tab label, panel heading, bullet items] — production's tab labels are shorter than
   the headings they reveal ("Immediate HM Call" vs "Immediate Hiring Manager Call"). */
$deposit = [
    'schedCall' => ['Client Wants to Schedule &amp; Pay on Their Own', 'Client Wants to Schedule &amp; Pay on Their Own', [
        'Give this link to the client. They can schedule the call and pay the refundable fee on their own:',
        '<a href="https://RemoteLeverage.com/Service-Hiring" target="_blank" rel="noopener noreferrer">https://RemoteLeverage.com/Service-Hiring</a>',
    ]],
    'manual' => ['Apple Pay / Credit Card Link', 'Apple Pay / Credit Card Link', [
        '1) Give this link to the client. They can pay the refundable deposit through Apple Pay or Credit Card: <a href="https://remoteleverage.com/virtual-assistant-hiring-manager-refundable-deposit" target="_blank" rel="noopener noreferrer">https://remoteleverage.com/virtual-assistant-hiring-manager-refundable-deposit</a>. If, for any reason, this link does not work, use <a href="https://remoteleverage.com/deposit" target="_blank" rel="noopener noreferrer">https://remoteleverage.com/deposit</a>.',
        '2) After the client pays, <a href="'.$calendlyBooking.'" target="_blank" rel="noopener noreferrer">click here</a> to go to Calendly and schedule the onboarding call. No payment is needed.',
        '3) Schedule the call and announce it in the group chat to let the Hiring Manager know to handle the new client.',
    ]],
    'hmCall' => ['Immediate HM Call', 'Immediate Hiring Manager Call', [
        '1) Announce in the group chat that you need an immediate Hiring Manager to handle onboarding.',
        '2) Go to: <a href="https://RemoteLeverage.com/Deposit" target="_blank" rel="noopener noreferrer">https://RemoteLeverage.com/Deposit</a>',
        '3) Fill out the payment information and process the payment.',
    ]],
    'zelle' => ['Zelle Transfer', 'Zelle &ndash; Instant Bank Transfer', [
        '1) Send the deposit via Zelle.',
        'Recipient email: <strong>Abbas@RemoteLeverage.com</strong>',
        '2) After the client pays, <a href="'.$calendlyBooking.'" target="_blank" rel="noopener noreferrer">click here</a> to go to Calendly and schedule the onboarding call. No payment is needed.',
        '3) Schedule the call and announce it in the group chat to let the Hiring Manager know to handle the new client.',
    ]],
    'venmo' => ['Venmo', 'Venmo', [
        '1) Send the deposit via Venmo.',
        'Recipient: <strong>@RemoteLeverage</strong>',
        '2) After the client pays, <a href="'.$calendlyBooking.'" target="_blank" rel="noopener noreferrer">click here</a> to go to Calendly and schedule the onboarding call. No payment is needed.',
        '3) Schedule the call and announce it in the group chat to let the Hiring Manager know to handle the new client.',
    ]],
];

$objections = [
    [
        'q' => 'Too expensive',
        'a' => '<p>Average staffing agency charges 2.75x the hourly rate of the VA. </p><p>Let’s assume you hire someone low quality for $5/hr from us or a staffing agency: </p><p>Staffing Agency (everyone else):</p><p>$9,600 (VA Salary) + $26,400 (their fee) = $36,000 ($3k/Month)</p><p>Recruiting Service (Us)</p><p>$9600 (VA Salary) + $3,640 (Our Fee) = $13k (72% Less expensive)</p><p>We don’t recommend $5/hr VAs. Quality too low. We’ll get you far higher quality for less than the cost of a low quality staffing agency, because when you work with us, we’re cutting out the middlemen fees. </p>',
    ],
    [
        'q' => 'Interviewing other companies/checking options',
        'a' => '<ul><li class="">Not enough info to decide</li><li class="">Meet our applicants and other companies’ applicants</li><li class="">Decide based on that. If you don’t hire, full refund.</li></ul><p class="">_____________</p><p class="">You don’t have enough information right now to make a decision.</p><p>Of course you have to interview multiple companies.</p><p>That’s <strong>exactly</strong> why we let business owners interview applicants from us before deciding whether to move forward or not.</p><p>After you put down a refundable deposit, you’ll get to interview our applicants <strong>and</strong> interview other agencies applicants so you can compare side by side. </p><p>If you don’t like our applicants or decide to go with someone else, we’ll process an immediate refund of the deposit. </p>',
    ],
    [
        'q' => 'I need to talk to partner/team',
        'a' => '<ul><li>Agreed, everyone must be on board</li><li>Let’s skip another sales call, meet hiring manager, tell them criteria interview applicants</li><li>Decide with your partner</li></ul><p><br>Everyone must be on board with us working together or else this won’t work.</p><p>Having said that, instead of wasting your team’s time with another sales call or you trying to remember and tell them what we said, let’s have you and your partner meet the hiring manager next time so you can tell them exactly what you’re looking for, and they can get you to interview applicants after that so you can decide whether to proceed or not, instead of us just doing another sales call.</p><p>Can I set you up to meet our hiring manager next time instead? </p>',
    ],
    [
        'q' => 'I don\'t want to do payroll or be Employer of Record',
        'a' => '<p>Yea no problem at all.</p><p>If you don’t want to do payroll, we have a partner company that will handle that for you and hire the Virtual Assistant as their own employee. They call it an Employer of Record service.</p><p>So basically the way it works is like this: Instead of the Virtual Assistant becoming <strong>your</strong> <strong>employee</strong> and you having to do payroll, the Virtual Assistant gets hired by this other company that becomes the Employer, they call it Employer of Record Service, so the Virtual Assistant becomes an Employee of <strong>that</strong> company, <strong>not your company</strong>, and then what you do is you pay <strong>that</strong> company and they run payroll and deal with all the employment laws and compliance. </p><p>Does that make sense?</p>',
    ],
    [
        'q' => 'I can do it myself cheaper',
        'a' => '<p>We spend lots of money on advertising to get 400-500 applicants a day.</p><p>We vet them out to find the ones that are not only high quality, but also meet your criteria.</p><p>What this means is instead of you choosing from a few applicants, we can choose the exact best ones from thousands of applicants that we receive every month and really get you the perfect match.</p>',
    ],
    [
        'q' => 'I want part time. Why do I have to pay based on full time salary?',
        'a' => '<p>It actually takes us <strong>more </strong>time and effort to find high quality part time applicants because most job applicants are trying to get full time jobs. </p><p>Lower quality applicants will settle for anything they can get including part time, but we decline those, so we have to spend more time to only get you the high quality part time applicants.</p>',
    ],
    [
        'q' => 'I can\'t afford it yet / We\'re just starting out',
        'a' => '<p>Do you think you’ll ever hire in the future? </p><p>If you’ll hire in the future to get someone to help you grow the business, you might as well hire now and get the business to grow faster.</p><p>The only exception is if you don’t think the business will grow by adding employees to help you move faster, or if you think the business model is not going to be profitable.</p>',
    ],
    [
        'q' => 'I want someone who will manage the VA for me',
        'a' => '<p>A staffing company isn’t going to know how you run your business, what metrics they should look at, how the tasks are supposed to be done, or anything else related to your business. Nobody will truly manage the VA for you besides running payroll and making sure they’re time tracking.</p><p><strong>So what is the alternative?</strong></p><p>Instead of hiring low quality Virtual Assistants that need constant hand holding and management, we will help you hire <strong>high quality, experienced </strong>virtual assistants that can actually get the job done without needing constant support and input from you.</p>',
    ],
    [
        'q' => 'I want to make sure I\'m decided before I start',
        'a' => '<p>I don’t want to waste our time if you’re not fully on board either because I don’t want us to waste each other’s time. </p><p>If you’re going to decide in a few days, even if it’s a no, let’s decide now so we can get this off our plates and move on to other things. </p><p>What exactly is stopping you from saying yes or no to this?</p>',
    ],
    [
        'q' => 'I don’t want to pay upfront',
        'a' => '<p>Paying upfront is not the real issue here, because you’ll be paying at some point if you want to hire someone.</p><p>The real issue is you don’t want to pay for someone that turns out to be a complete waste of time and money.</p><p class="whitespace-pre-wrap break-words">That’s exactly we have a process we follow to make sure you get the right person for the job:</p><ol><li>After you put down a refundable deposit, you tell us exactly what you’re looking for</li><li>We go and get you 4-6 applicants from our pool of high quality applicants</li><li>You meet them on Zoom, interview them, go over resumes, etc. and you <strong>only</strong> have to pay if you like someone enough and want to move forward. If you don’t like anyone, you can cancel and get your deposit back</li><li>You’ll have 6 months of free replacement guarantee to make sure you like the person you hire</li></ol>',
    ],
    [
        'q' => 'Why can’t I interview without giving a deposit?',
        'a' => '<p>The applicants we work with are high quality and have lots of job offers.</p><p>If we invite them to interviews and the client doesn’t seem serious about hiring, the applicants will stop interviewing with our other clients.</p><p>This ensures you’re serious enough to at least put down a fully <strong>refundable</strong> deposit. </p>',
    ],
    [
        'q' => 'Out of everyone that I talked to so far you are the most expensive',
        'a' => '<p>We bring way higher quality applicants. </p><p class="whitespace-pre-wrap break-words">Most agencies are like a rental car service – they charge you a premium every month to ‘rent’ their VA. You keep paying $3k monthly to rent a VA’s time.</p><p class="whitespace-pre-wrap break-words">We’re more like a car dealership – you pay once to ‘own’ the relationship with your VA. After that, you only pay them directly for their work at $6-10 per hour. No hidden fees, no markups, no ongoing charges.</p><p class="whitespace-pre-wrap break-words">The upfront cost is higher because we invest in finding real talent. We spend more on recruiting because we’re looking for VAs who can handle complex tasks, not just basic $5/hour data entry.</p>',
    ],
    [
        'q' => 'Why do I have to pay you to just recruit?',
        'a' => '<p>We’re not a fit for everyone.</p><p>We’re only a good fit if you’re looking for high quality Virtual Assistants. Most of our clients have had bad experiences with agencies that outsource $5/hr Virtual Assistants, and want high quality for a change.</p><p>We go through 400-500 applicants a day and spend thousands of dollars on marketing to get you the best Virtual Assistants possible.</p><p>Then you pay us once, with no ongoing middleman fees and markups. We help you save thousands of dollars a year and get you talented applicants that are actually competent. </p>',
    ],
];

$roleCategories = [
    [
        'id' => 'it-dev',
        'title' => 'IT & Development',
        'roles' => [
            ['Web Developer', 'Someone to build and maintain websites, implement new features, and ensure optimal performance.'],
            ['QA Tester', 'Someone to test software applications, identify bugs, and ensure quality standards are met.'],
            ['IT Support Specialist', 'Someone to provide technical support, troubleshoot issues, and maintain IT systems.'],
            ['DevOps Engineer', 'Someone to manage deployment processes, maintain infrastructure, and optimize development workflows.'],
        ],
    ],
    [
        'id' => 'design',
        'title' => 'Architecture & Interior Design',
        'roles' => [
            ['3D Rendering Artist', 'Someone to create 3D visualizations of architectural and interior design projects.'],
            ['Interior Design Assistant', 'Someone to assist with design projects, create mood boards, and source materials and furniture.'],
            ['CAD Specialist', 'Someone to create and modify architectural drawings and plans using CAD software.'],
        ],
    ],
    [
        'id' => 'legal',
        'title' => 'Legal',
        'roles' => [
            ['Legal Virtual Assistant', 'Someone to handle legal document preparation, maintain case files, manage calendars, and assist with legal research.'],
            ['Legal Transcriptionist', 'Someone to transcribe legal proceedings, depositions, and court hearings with high accuracy and proper legal terminology.'],
            ['Legal Research Assistant', 'Someone to conduct legal research, analyze case law, and prepare legal summaries and briefings.'],
            ['Contract Review Specialist', 'Someone to review and analyze contracts, identify key terms, and ensure compliance with legal requirements.'],
            ['Paralegal Assistant', 'Someone to assist with case preparation, document filing, client communication, and general paralegal duties.'],
            ['Immigration Law Assistant', 'Someone to help prepare immigration forms, maintain case files, and assist with visa and citizenship applications.'],
        ],
    ],
    [
        'id' => 'medical',
        'title' => 'Medical',
        'roles' => [
            ['Medical Scribe', 'Someone to document patient encounters in real-time, assist with clinical documentation, and support healthcare providers with administrative tasks.'],
            ['Medical Virtual Assistant/Healthcare VA', 'Someone to handle medical scheduling, appointment reminders, and basic healthcare administrative tasks.'],
            ['Medical Transcriptionist', 'Someone to transcribe medical dictations, patient records, and clinical documentation with high accuracy.'],
            ['Medical Billing Specialist', 'Someone to process medical claims, handle insurance verifications, and manage patient billing.'],
            ['Medical Records Coordinator', 'Someone to organize and maintain patient medical records, ensure compliance, and handle record requests.'],
            ['Healthcare Data Analyst', 'Someone to analyze medical data, create healthcare reports, and provide insights for clinical improvements.'],
        ],
    ],
    [
        'id' => 'sales',
        'title' => 'Sales & Business Development',
        'roles' => [
            ['Sales Development Representative (SDR)', 'Someone to prospect new business opportunities through cold calling, email outreach, and LinkedIn.'],
            ['Account Executive/Sales Executive', 'Someone to manage the full sales cycle from qualification through close, including demos, proposals, and negotiations.'],
            ['Account Manager/Client Success Manager', 'Someone to manage existing client relationships, drive renewals, and identify expansion opportunities.'],
            ['Sales Operations Specialist', 'Someone to maintain CRM data, create sales reports, and support the sales process.'],
        ],
    ],
    [
        'id' => 'admin',
        'title' => 'Administrative & Operations',
        'roles' => [
            ['Executive Assistant/Personal Assistant', 'Someone to manage an executive\'s calendar, emails, travel, meeting prep, and personal tasks while exercising strong judgment and discretion.'],
            ['Administrative Assistant', 'Someone to handle general office tasks like scheduling, basic bookkeeping, data entry, and document management.'],
            ['Operations Manager', 'Someone to develop and implement operational processes, manage vendor relationships, and ensure smooth business operations.'],
            ['Data Entry Specialist', 'Someone to input and maintain accurate data in company systems and spreadsheets.'],
        ],
    ],
    [
        'id' => 'marketing',
        'title' => 'Marketing & Social Media',
        'roles' => [
            ['Social Media Manager', 'Someone to create and schedule content across platforms, engage with followers, analyze metrics, and run paid social campaigns.'],
            ['Digital Marketing Specialist', 'Someone to execute marketing campaigns across channels, including email, social, and digital ads.'],
            ['Content Writer/Copywriter', 'Someone to write engaging marketing copy, blog posts, social media content, and email campaigns aligned with brand voice.'],
            ['Email Marketing Specialist', 'Someone to build and execute email campaigns, manage subscriber lists, and optimize performance through A/B testing.'],
            ['PPC Specialist', 'Someone to manage and optimize paid advertising campaigns across Google, Facebook, and other platforms.'],
            ['Media Buyer', 'Someone to plan and execute media buying strategies, negotiate ad placements, and optimize campaign performance across various advertising platforms.'],
        ],
    ],
    [
        'id' => 'customer-service',
        'title' => 'Customer Service & Support',
        'roles' => [
            ['Customer Service Representative', 'Someone to respond to customer inquiries via email, chat, and phone while maintaining high satisfaction rates.'],
            ['Technical Support Specialist', 'Someone to troubleshoot product issues, provide technical assistance, and document solutions.'],
            ['Customer Success Specialist', 'Someone to onboard new customers, provide product training, and ensure customer satisfaction.'],
        ],
    ],
    [
        'id' => 'ecommerce',
        'title' => 'E-commerce Operations',
        'roles' => [
            ['E-commerce Manager', 'Someone to oversee all aspects of online store operations, including inventory, customer service, and platform management.'],
            ['Product Listing Specialist', 'Someone to create and optimize product listings, manage product data, and ensure accuracy across platforms.'],
            ['Order Processing Specialist', 'Someone to process orders, manage shipping logistics, and handle customer inquiries.'],
        ],
    ],
    [
        'id' => 'finance',
        'title' => 'Finance & Bookkeeping',
        'roles' => [
            ['Bookkeeper', 'Someone to manage daily transactions, reconcile accounts, process invoices, and maintain financial records.'],
            ['Accounts Payable Specialist', 'Someone to process vendor invoices, manage payment schedules, and maintain vendor relationships.'],
            ['Accounts Receivable Specialist', 'Someone to process customer payments, follow up on overdue accounts, and maintain accurate records.'],
        ],
    ],
    [
        'id' => 'project',
        'title' => 'Project Management',
        'roles' => [
            ['Project Manager', 'Someone to plan and execute projects, coordinate team members, manage timelines, and ensure deliverables are met.'],
            ['Project Assistant', 'Someone to support project administration, track progress, and facilitate team communication.'],
            ['Scrum Master', 'Someone to facilitate agile processes, remove obstacles, and support team productivity.'],
        ],
    ],
    [
        'id' => 'content-seo',
        'title' => 'Content & SEO',
        'roles' => [
            ['SEO Specialist', 'Someone to optimize website content, conduct keyword research, and improve search engine rankings.'],
            ['Content Manager', 'Someone to develop content strategy, manage editorial calendar, and oversee content creation across channels.'],
            ['Blog Manager', 'Someone to manage blog content, coordinate with writers, and ensure consistent publishing schedule.'],
        ],
    ],
    [
        'id' => 'data',
        'title' => 'Data & Analytics',
        'roles' => [
            ['Data Analyst', 'Someone to analyze business data, create reports, and provide insights for decision-making.'],
            ['Research Analyst', 'Someone to conduct market research, analyze competitors, and identify business opportunities.'],
            ['Business Intelligence Analyst', 'Someone to create dashboards, analyze trends, and generate regular business reports.'],
        ],
    ],
    [
        'id' => 'media',
        'title' => 'Media Production',
        'roles' => [
            ['Video Editor', 'Someone to edit video content, create thumbnails, and manage video publishing workflow.'],
            ['Podcast Producer', 'Someone to edit audio, manage show notes, coordinate with guests, and handle publication.'],
            ['Graphic Designer', 'Someone to create social media graphics, marketing materials, and basic design assets.'],
        ],
    ],
    [
        'id' => 'realestate',
        'title' => 'Real Estate',
        'roles' => [
            ['Real Estate Virtual Assistant', 'Someone to manage property listings, coordinate showings, and handle transaction paperwork.'],
            ['Transaction Coordinator', 'Someone to manage contract deadlines, coordinate with all parties, and ensure smooth closings.'],
            ['Property Management Assistant', 'Someone to handle tenant communications, maintenance requests, and lease paperwork.'],
        ],
    ],
];

$iconPlus = '<svg class="v5-acc__icon v5-acc__icon--plus" viewBox="0 0 448 512" aria-hidden="true"><path d="M416 208H272V64c0-17.67-14.33-32-32-32h-32c-17.67 0-32 14.33-32 32v144H32c-17.67 0-32 14.33-32 32v32c0 17.67 14.33 32 32 32h144v144c0 17.67 14.33 32 32 32h32c17.67 0 32-14.33 32-32V304h144c17.67 0 32-14.33 32-32v-32c0-17.67-14.33-32-32-32z"/></svg>';
$iconMinus = '<svg class="v5-acc__icon v5-acc__icon--minus" viewBox="0 0 448 512" aria-hidden="true"><path d="M416 208H32c-17.67 0-32 14.33-32 32v32c0 17.67 14.33 32 32 32h384c17.67 0 32-14.33 32-32v-32c0-17.67-14.33-32-32-32z"/></svg>';

/* Jump-nav targets. Labels are the sections' own headings verbatim; "Roles" is the one
   coined label, because the role catalogue carries no heading of its own. */
$navItems = [
    ['v5-s-probing', 'Probing Questions'],
    ['v5-s-discovery', 'Discovery'],
    ['v5-s-presentation', 'Presentation'],
    ['v5-s-pricing', 'Pricing'],
    ['v5-s-booking', 'Deposit &amp; Payment Options'],
    ['v5-s-bundles', 'Bundle Pricing Packages'],
    ['v5-s-objections', 'Objections'],
    ['v5-s-roles', 'Roles'],
];

?>
<!-- rl:noindex — internal sales collateral. App\Support\PageRobots reads this marker and
     emits noindex, nofollow, matching production. The page stays published and reachable;
     this keeps it out of search results only, it is NOT access control. -->
<!-- wp:html -->
<style>
/* ------------------------------------------------------------------------------
   /vastore5/ — internal sales playbook.
   Built from the theme's own @theme custom properties (Tailwind emits them to
   :root under `theme(static)`), so this page shares the sitewide palette, radii,
   shadows and type face. Fallback literals are the token values, so the page
   still renders correctly if it is ever viewed without app.css.
   ------------------------------------------------------------------------------ */
.vastore5 {
    --v5-ink: var(--color-brand-midnight, #18112C);
    --v5-body: #4A4560;
    --v5-muted: #6F6B85;
    --v5-line: rgba(24, 17, 44, .10);
    --v5-purple: var(--color-brand-purple, #8A2BE2);
    --v5-deep: var(--color-brand-purple-deep, #6200A4);
    --v5-violet: var(--color-brand-dark-violet, #25104A);
    --v5-orange: var(--color-brand-orange, #FB7501);
    --v5-alert: var(--color-status-alert, #D94900);
    --v5-ground: var(--color-bg-light, #F4F6FC);
    --v5-r: var(--radius-card, 16px);
    --v5-r-sm: 12px;
    --v5-pill: var(--radius-pill, 100px);
    --v5-shadow: 0 6px 24px rgba(24, 17, 44, .06);
    --v5-shadow-lg: 0 24px 60px rgba(24, 17, 44, .13);

    font-family: var(--font-sans, "Inter Variable", "Inter", system-ui, -apple-system, sans-serif);
    color: var(--v5-body);
    font-size: 16px;
    line-height: 1.65;
    -webkit-font-smoothing: antialiased;
}
.vastore5 *, .vastore5 *::before, .vastore5 *::after { box-sizing: border-box; }
.vastore5 h2, .vastore5 h3, .vastore5 h4 { margin: 0; font-weight: 700; color: var(--v5-ink); letter-spacing: -.02em; }
.vastore5 p { margin: 0 0 14px; }
.vastore5 p:last-child { margin-bottom: 0; }
.vastore5 a { color: var(--v5-purple); }
.vastore5 strong, .vastore5 b { font-weight: 700; }
.vastore5 em { font-style: italic; }

.vastore5 .v5-inner { width: 100%; max-width: var(--width-container, 1380px); margin-inline: auto;
    padding-inline: clamp(16px, 3vw, 32px); }
.vastore5 .v5-section { padding-block: clamp(32px, 3.4vw, 56px); }
.vastore5 .v5-section--tight { padding-block: clamp(20px, 2.4vw, 32px); }
.vastore5 [id^="v5-s-"] { scroll-margin-top: calc(var(--v5-navtop, 80px) + 72px); }

/* bands */
.vastore5.v5-band { background: transparent; }
.vastore5.v5-band--white { background: #fff; }
.vastore5.v5-band--deep {
    background:
        radial-gradient(1100px 520px at 12% -10%, rgba(138, 43, 226, .55) 0%, rgba(138, 43, 226, 0) 62%),
        linear-gradient(158deg, var(--v5-violet) 0%, var(--v5-deep) 100%);
    color: rgba(255, 255, 255, .84);
}
/* Only the band's own furniture turns white — anything inside a white card on the band
   (the recruitment price card, the deposit tab card) keeps the light-surface ink. */
.vastore5.v5-band--deep > .v5-inner > .v5-head h2,
.vastore5.v5-band--deep > .v5-inner > .v5-bundle-intro { color: #fff; }

/* section headings */
.vastore5 .v5-head { margin: 0 0 clamp(20px, 2vw, 30px); }
.vastore5 .v5-head h2 { font-size: clamp(25px, 2.5vw, 34px); line-height: 1.14; }
.vastore5 .v5-head--center { text-align: center; max-width: 860px; margin-inline: auto; }

/* generic card */
.vastore5 .v5-card { background: #fff; border: 1px solid var(--v5-line); border-radius: var(--v5-r);
    box-shadow: var(--v5-shadow); color: var(--v5-body); }
.vastore5 .v5-card--pad { padding: clamp(22px, 2.4vw, 36px); }

/* ---------------------------------------------------------------- jump nav -- */
.vastore5.v5-nav { position: sticky; top: var(--v5-navtop, 80px); z-index: 30;
    background: color-mix(in srgb, var(--v5-ground) 88%, transparent);
    -webkit-backdrop-filter: blur(12px) saturate(150%); backdrop-filter: blur(12px) saturate(150%);
    border-bottom: 1px solid var(--v5-line); }
@supports not (background: color-mix(in srgb, red 50%, blue)) {
    .vastore5.v5-nav { background: rgba(244, 246, 252, .92); }
}
.vastore5 .v5-nav__inner { display: flex; align-items: center; gap: 4px; overflow-x: auto;
    scrollbar-width: none; padding-block: 10px; }
.vastore5 .v5-nav__inner::-webkit-scrollbar { display: none; }
.vastore5 .v5-nav__inner a { flex: none; font-size: 13.5px; font-weight: 600; line-height: 1;
    color: var(--v5-muted); text-decoration: none; padding: 9px 14px; border-radius: var(--v5-pill);
    white-space: nowrap; transition: background-color .15s ease, color .15s ease; }
.vastore5 .v5-nav__inner a:hover { background: rgba(138, 43, 226, .09); color: var(--v5-deep); }
.vastore5 .v5-nav__inner a.is-current { background: var(--v5-purple); color: #fff; }

/* ------------------------------------------------------------------- tools -- */
.vastore5 .v5-tools { max-width: 760px; margin-inline: auto; }

/* --------------------------------------------------- tabbed question cards -- */
.vastore5 .v5-tabcard { overflow: hidden; max-width: 1100px; margin-inline: auto; }
.vastore5 .v5-tabcard__head { padding: clamp(20px, 2vw, 26px) clamp(20px, 2.2vw, 30px) 0; }
.vastore5 .v5-tabcard__head h2 { font-size: 21px; line-height: 1.2; }
.vastore5 .v5-tabcard__tabs { display: flex; flex-wrap: wrap; gap: 5px; padding: 5px;
    margin: 16px clamp(20px, 2.2vw, 30px) 0; background: var(--v5-ground); border-radius: var(--v5-pill); }
.vastore5 .v5-tabcard__tabs button { flex: 1 1 140px; min-width: 0; appearance: none; border: 0;
    background: transparent; color: var(--v5-ink); font: inherit; font-size: 13.5px; font-weight: 600;
    line-height: 1.25; padding: 11px 12px; border-radius: var(--v5-pill); cursor: pointer;
    transition: background-color .15s ease, color .15s ease, box-shadow .15s ease; }
.vastore5 .v5-tabcard__tabs button:hover { background: rgba(24, 17, 44, .05); }
.vastore5 .v5-tabcard__tabs button.is-active { background: var(--v5-purple); color: #fff;
    box-shadow: 0 4px 14px rgba(138, 43, 226, .3); }
.vastore5 .v5-subtabs { display: inline-flex; gap: 4px; padding: 4px; margin: 10px clamp(20px, 2.2vw, 30px) 0;
    background: var(--v5-ground); border-radius: var(--v5-pill); }
.vastore5 .v5-subtabs button { appearance: none; border: 0; background: transparent; color: var(--v5-muted);
    font: inherit; font-size: 12.5px; font-weight: 600; line-height: 1; padding: 9px 20px;
    border-radius: var(--v5-pill); cursor: pointer; transition: background-color .15s ease, color .15s ease; }
.vastore5 .v5-subtabs button:hover { color: var(--v5-ink); }
.vastore5 .v5-subtabs button.is-active { background: var(--v5-violet); color: #fff; }
.vastore5 .v5-tabcard__body { padding: 18px clamp(20px, 2.2vw, 30px) clamp(22px, 2.2vw, 30px); }
/* Production copy — styled as a label, but never case-transformed. */
.vastore5 .v5-tabcard__body h4 { font-size: 13.5px; font-weight: 700; letter-spacing: .01em;
    color: var(--v5-deep); margin: 24px 0 10px; padding-bottom: 8px;
    border-bottom: 1px solid rgba(138, 43, 226, .16); }
.vastore5 .v5-tabcard__body h4:first-child { margin-top: 4px; }
.vastore5 .v5-tabcard__body ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 9px; }
.vastore5 .v5-tabcard__body li { position: relative; padding-left: 20px; font-size: 15.5px;
    line-height: 1.55; color: var(--v5-body); }
.vastore5 .v5-tabcard__body li::before { content: ""; position: absolute; left: 3px; top: 10px;
    width: 6px; height: 6px; border-radius: 50%; background: var(--v5-purple); opacity: .5; }
.vastore5 .v5-tabcard__body a { color: var(--v5-deep); font-weight: 600; text-decoration: underline;
    text-underline-offset: 2px; word-break: break-word; }

/* ----------------------------------------------------------- discovery grid -- */
.vastore5 .v5-qgrid { list-style: none; margin: 0; padding: 0; counter-reset: v5q;
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.vastore5 .v5-qgrid li { counter-increment: v5q; position: relative; background: #fff;
    border: 1px solid var(--v5-line); border-radius: var(--v5-r-sm); box-shadow: var(--v5-shadow);
    padding: 18px 22px 18px 60px; font-size: 16px; line-height: 1.45; color: var(--v5-ink); }
.vastore5 .v5-qgrid li::before { content: counter(v5q); position: absolute; left: 18px; top: 16px;
    width: 28px; height: 28px; border-radius: 50%; background: rgba(138, 43, 226, .1);
    color: var(--v5-purple); font-size: 12.5px; font-weight: 700; line-height: 28px; text-align: center; }
.vastore5 .v5-qgrid li strong { font-weight: 600; }

/* ------------------------------------------------------------ long-form prose -- */
.vastore5 .v5-prose { font-size: 16px; line-height: 1.7; color: var(--v5-body); }
.vastore5 .v5-prose > ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 13px; }
.vastore5 .v5-prose > ul > li { position: relative; padding-left: 26px; }
.vastore5 .v5-prose > ul > li::before { content: ""; position: absolute; left: 6px; top: 11px;
    width: 7px; height: 7px; border-radius: 50%; background: var(--v5-purple); }
.vastore5 .v5-prose ul ul { list-style: none; margin: 12px 0 0; padding: 0 0 0 20px; display: grid; gap: 9px;
    border-left: 2px solid rgba(138, 43, 226, .2); }
.vastore5 .v5-prose ul ul li { position: relative; padding-left: 18px; font-size: 15px; color: var(--v5-muted); }
.vastore5 .v5-prose ul ul li::before { content: ""; position: absolute; left: 0; top: 10px;
    width: 5px; height: 5px; border-radius: 50%; background: var(--v5-purple); opacity: .45; }
.vastore5 .v5-callout { margin-top: 26px; border-left: 4px solid var(--v5-purple); border-radius: var(--v5-r-sm);
    background: linear-gradient(90deg, rgba(138, 43, 226, .11), rgba(138, 43, 226, 0));
    padding: 16px 20px; font-size: 17px; font-weight: 700; color: var(--v5-ink); }

/* ------------------------------------------------------- cannot-hire-for list -- */
.vastore5 .v5-warn { border-left: 5px solid var(--v5-alert); }
.vastore5 .v5-warn__title { display: flex; align-items: flex-start; gap: 11px; margin: 0 0 20px;
    font-size: 17px; font-weight: 700; font-style: italic; color: var(--v5-alert); }
.vastore5 .v5-warn__title svg { width: 21px; height: 21px; flex: none; margin-top: 2px; fill: var(--v5-alert); }
.vastore5 .v5-warn ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px;
    grid-template-columns: repeat(2, minmax(0, 1fr)); }
.vastore5 .v5-warn li { position: relative; padding: 14px 16px 14px 42px; background: #FFF8F4;
    border: 1px solid rgba(217, 73, 0, .16); border-radius: var(--v5-r-sm); font-size: 15px;
    line-height: 1.55; color: var(--v5-body); }
.vastore5 .v5-warn li::before { content: "\2715"; position: absolute; left: 17px; top: 13px;
    font-size: 12px; font-weight: 700; color: var(--v5-alert); }
.vastore5 .v5-warn .v5-small { font-size: 14px; color: var(--v5-muted); }

/* --------------------------------------------------------------- rate table -- */
.vastore5 .v5-glass { background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .17);
    border-radius: var(--v5-r); }
.vastore5 .v5-rates { max-width: 840px; margin-inline: auto; padding: 8px clamp(18px, 2.5vw, 34px); }
.vastore5 .v5-rate { display: grid; grid-template-columns: 130px minmax(0, 1fr) 160px; align-items: center;
    gap: 18px; padding: 18px 0; border-bottom: 1px solid rgba(255, 255, 255, .13); }
.vastore5 .v5-rate:last-child { border-bottom: 0; }
.vastore5 .v5-rate__label { font-size: 23px; font-weight: 600; letter-spacing: -.01em;
    color: rgba(255, 255, 255, .78); font-variant-numeric: tabular-nums; }
.vastore5 .v5-rate__lead { overflow: hidden; white-space: nowrap; text-align: center; font-size: 13px;
    letter-spacing: 4px; color: rgba(255, 255, 255, .22); }
.vastore5 .v5-rate__value { text-align: right; font-size: 32px; line-height: 1.1; font-weight: 700;
    letter-spacing: -.025em; color: #fff; font-variant-numeric: tabular-nums; }
.vastore5 .v5-note-pill { display: block; width: fit-content; margin: 22px auto 0; padding: 11px 24px;
    border-radius: var(--v5-pill); background: rgba(255, 255, 255, .12);
    border: 1px solid rgba(255, 255, 255, .2); font-size: 15px; font-weight: 600; color: #fff; }

/* --------------------------------------------------------- bundle price grid -- */
.vastore5 .v5-bundle-intro { max-width: 900px; margin: 0 auto clamp(24px, 2.6vw, 36px); text-align: center;
    font-size: 16px; line-height: 1.65; font-weight: 400; color: rgba(255, 255, 255, .8); }
.vastore5 .v5-bundles { max-width: 1180px; margin-inline: auto; display: grid; gap: 14px;
    grid-template-columns: repeat(4, minmax(0, 1fr)); }
.vastore5 .v5-bundle { padding: 24px 22px; }
.vastore5 .v5-bundle__label { font-size: 14px; font-weight: 600; color: rgba(255, 255, 255, .62); }
.vastore5 .v5-bundle__value { margin-top: 8px; font-size: 14px; color: rgba(255, 255, 255, .62); }
.vastore5 .v5-bundle__value strong { font-size: 31px; font-weight: 700; letter-spacing: -.025em;
    color: #fff; font-variant-numeric: tabular-nums; }

/* ------------------------------------------------------ recruitment price card -- */
.vastore5 .v5-pricecard { max-width: 1000px; margin-inline: auto; background: #fff;
    border-radius: var(--v5-r); box-shadow: var(--v5-shadow-lg); overflow: hidden; color: var(--v5-body); }
.vastore5 .v5-pricecard__head { padding: clamp(24px, 2.6vw, 34px) clamp(24px, 2.6vw, 36px) 0; }
.vastore5 .v5-pricecard__title { font-size: clamp(22px, 2.1vw, 28px); line-height: 1.2; }
.vastore5 .v5-pricecard__grid { display: grid; grid-template-columns: 380px minmax(0, 1fr);
    gap: clamp(20px, 2.6vw, 34px); padding: clamp(20px, 2.2vw, 28px) clamp(24px, 2.6vw, 36px) clamp(24px, 2.6vw, 36px);
    align-items: start; }
.vastore5 .v5-pricecard__media img { display: block; width: 100%; height: 300px; object-fit: cover;
    border-radius: var(--v5-r-sm); }
.vastore5 .v5-pricecard__sub { font-size: clamp(18px, 1.6vw, 21px); line-height: 1.3; color: var(--v5-deep);
    background: linear-gradient(90deg, rgba(138, 43, 226, .12), rgba(138, 43, 226, .02));
    border-left: 4px solid var(--v5-purple); border-radius: var(--v5-r-sm); padding: 15px 18px; margin-bottom: 20px; }
.vastore5 .v5-facts { list-style: none; margin: 0 0 20px; padding: 0; display: grid; gap: 9px; }
.vastore5 .v5-facts li { position: relative; padding-left: 26px; font-size: 15px; line-height: 1.55; }
.vastore5 .v5-facts li::before { content: ""; position: absolute; left: 0; top: 4px; width: 16px; height: 16px;
    border-radius: 50%; background: rgba(138, 43, 226, .12); }
.vastore5 .v5-facts li::after { content: ""; position: absolute; left: 5px; top: 8px; width: 6px; height: 3px;
    border-left: 2px solid var(--v5-purple); border-bottom: 2px solid var(--v5-purple); transform: rotate(-45deg); }
.vastore5 .v5-pricecard__note { border-top: 1px dashed var(--v5-line); padding-top: 16px; margin-bottom: 22px;
    font-size: 14px; line-height: 1.6; color: var(--v5-muted); }
.vastore5 .v5-btn { display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    background: var(--v5-orange); color: #fff; font-size: 17px; font-weight: 700; line-height: 1.2;
    padding: 16px 34px; border-radius: var(--v5-pill); text-decoration: none;
    box-shadow: 0 10px 24px rgba(251, 117, 1, .3);
    transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease; }
.vastore5 .v5-btn:hover { background: #E56A00; transform: translateY(-1px);
    box-shadow: 0 14px 30px rgba(251, 117, 1, .38); }
.vastore5 .v5-btn--block { display: flex; width: 100%; }
.vastore5 .v5-btn-row { display: flex; justify-content: center; margin-top: clamp(24px, 2.6vw, 34px); }

/* ------------------------------------------------------- calendly + failsafe -- */
.vastore5 .v5-calwrap { max-width: 1180px; margin-inline: auto; background: #fff; border-radius: var(--v5-r);
    overflow: hidden; box-shadow: var(--v5-shadow-lg); }
.vastore5 .v5-calwrap .calendly-inline-widget { min-width: 320px; height: 700px; }
.vastore5 .v5-failsafe { max-width: 760px; margin: 20px auto 0; text-align: center; }
.vastore5 .v5-failsafe__toggle { cursor: pointer; background: rgba(255, 255, 255, .12);
    border: 1px solid rgba(255, 255, 255, .28); color: #fff; border-radius: var(--v5-pill);
    padding: 12px 26px; font: inherit; font-size: 15px; font-weight: 600;
    transition: background-color .15s ease; }
.vastore5 .v5-failsafe__toggle:hover { background: rgba(255, 255, 255, .2); }
.vastore5 .v5-failsafe__panel { display: none; margin-top: 14px; }
.vastore5 .v5-failsafe__panel.is-open { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
.vastore5 .v5-failsafe__btn { display: inline-block; padding: 12px 24px; background: #fff;
    color: var(--v5-deep); border-radius: var(--v5-pill); font-size: 15px; font-weight: 700;
    text-decoration: none; transition: transform .15s ease; }
.vastore5 .v5-failsafe__btn:hover { transform: translateY(-1px); }

/* -------------------------------------------------------------- objections -- */
.vastore5 .v5-obj { max-width: 1040px; margin-inline: auto; }
.vastore5 .v5-obj__intro { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px; margin-bottom: clamp(24px, 2.6vw, 34px); align-items: start; }
.vastore5 .v5-obj__panel { padding: clamp(20px, 2.2vw, 28px); }
.vastore5 .v5-obj__lead { font-size: 15px; font-weight: 700; color: var(--v5-ink); margin: 0 0 14px; }
.vastore5 .v5-obj__steps { display: grid; gap: 10px; }
.vastore5 .v5-obj__steps p { margin: 0; padding: 12px 15px; background: var(--v5-ground);
    border-radius: 10px; font-size: 14.5px; line-height: 1.55; }
.vastore5 .v5-obj__steps p b { color: var(--v5-deep); }
.vastore5 .v5-obj__script { display: grid; gap: 8px; }
.vastore5 .v5-obj__script p { margin: 0; font-size: 14.5px; line-height: 1.6; }
.vastore5 .v5-obj__script p.is-client { padding: 10px 14px; border-radius: 10px;
    background: rgba(217, 73, 0, .07); color: #8A4A21; font-weight: 600; }
.vastore5 .v5-obj__script p.is-rep { padding: 10px 14px; border-radius: 10px;
    background: rgba(138, 43, 226, .07); color: var(--v5-ink); }
.vastore5 .v5-sep { margin: 4px 0; font-size: 11px; line-height: 1; letter-spacing: -1px;
    color: rgba(24, 17, 44, .16); overflow: hidden; white-space: nowrap; }
.vastore5 .v5-acc { display: grid; gap: 9px; }
.vastore5 .v5-acc details { background: #fff; border: 1px solid var(--v5-line); border-radius: var(--v5-r-sm);
    overflow: hidden; transition: box-shadow .15s ease, border-color .15s ease; }
.vastore5 .v5-acc details:hover { border-color: rgba(138, 43, 226, .3); }
.vastore5 .v5-acc details[open] { box-shadow: var(--v5-shadow); border-color: rgba(138, 43, 226, .3); }
.vastore5 .v5-acc summary { display: flex; align-items: center; justify-content: space-between; gap: 14px;
    padding: 17px 20px; font-size: 16px; font-weight: 600; color: var(--v5-ink); cursor: pointer;
    list-style: none; }
.vastore5 .v5-acc summary::-webkit-details-marker { display: none; }
.vastore5 .v5-acc__ico { display: flex; align-items: center; justify-content: center; flex: none;
    width: 30px; height: 30px; border-radius: 50%; background: rgba(138, 43, 226, .1);
    transition: background-color .15s ease; }
.vastore5 .v5-acc details[open] .v5-acc__ico { background: var(--v5-purple); }
.vastore5 .v5-acc__icon { width: 12px; height: 12px; fill: var(--v5-purple); }
.vastore5 .v5-acc details[open] .v5-acc__icon { fill: #fff; }
.vastore5 .v5-acc summary .v5-acc__icon--minus { display: none; }
.vastore5 .v5-acc details[open] summary .v5-acc__icon--minus { display: block; }
.vastore5 .v5-acc details[open] summary .v5-acc__icon--plus { display: none; }
.vastore5 .v5-acc__body { padding: 18px 20px 22px; border-top: 1px solid var(--v5-line);
    font-size: 15.5px; line-height: 1.7; }
.vastore5 .v5-acc__body p { margin: 0 0 12px; }
.vastore5 .v5-acc__body ul { list-style: disc; margin: 0 0 14px; padding-left: 22px; }
.vastore5 .v5-acc__body ol { list-style: decimal; margin: 0 0 14px; padding-left: 22px; }
.vastore5 .v5-acc__body li { margin: 0 0 7px; }
.vastore5 .v5-acc__body strong { font-weight: 700; color: var(--v5-ink); }

/* ---------------------------------------------------------- role catalogue -- */
.vastore5 .v5-roles { max-width: 1180px; margin-inline: auto; display: grid; gap: 9px; }
.vastore5 .v5-dd { background: #fff; border: 1px solid var(--v5-line); border-radius: var(--v5-r-sm);
    overflow: hidden; transition: border-color .15s ease; }
.vastore5 .v5-dd:hover { border-color: rgba(138, 43, 226, .3); }
.vastore5 .v5-dd > input[type="checkbox"] { position: absolute; width: 1px; height: 1px; opacity: 0;
    pointer-events: none; }
.vastore5 .v5-dd__btn { display: flex; align-items: center; justify-content: space-between; gap: 14px;
    padding: 17px 22px; font-size: 16.5px; font-weight: 700; color: var(--v5-ink); cursor: pointer;
    transition: background-color .15s ease, color .15s ease; }
.vastore5 .v5-dd__btn:hover { background: var(--v5-ground); }
.vastore5 .v5-dd__btn::after { content: ""; flex: none; width: 9px; height: 9px; margin-right: 4px;
    border-right: 2px solid var(--v5-purple); border-bottom: 2px solid var(--v5-purple);
    transform: rotate(45deg) translate(-2px, -2px); transition: transform .2s ease; }
.vastore5 .v5-dd > input[type="checkbox"]:focus-visible + .v5-dd__btn { outline: 2px solid var(--v5-purple);
    outline-offset: -2px; }
.vastore5 .v5-dd > input[type="checkbox"]:checked + .v5-dd__btn { color: var(--v5-deep);
    background: linear-gradient(90deg, rgba(138, 43, 226, .1), rgba(138, 43, 226, .02)); }
.vastore5 .v5-dd > input[type="checkbox"]:checked + .v5-dd__btn::after {
    transform: rotate(-135deg) translate(-2px, -2px); }
.vastore5 .v5-dd__content { display: none; }
.vastore5 .v5-dd > input[type="checkbox"]:checked ~ .v5-dd__content { display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; padding: 18px 22px 22px;
    border-top: 1px solid var(--v5-line); }
.vastore5 .v5-role { padding: 14px 16px; background: var(--v5-ground); border-radius: 10px; }
.vastore5 .v5-role__title { font-size: 15px; font-weight: 700; color: var(--v5-ink); margin-bottom: 3px; }
.vastore5 .v5-role__desc { font-size: 14px; line-height: 1.55; color: var(--v5-muted); }

/* ------------------------------------------------------------- breakpoints -- */
@media (max-width: 980px) {
    .vastore5 .v5-qgrid,
    .vastore5 .v5-warn ul,
    .vastore5 .v5-obj__intro,
    .vastore5 .v5-dd > input[type="checkbox"]:checked ~ .v5-dd__content { grid-template-columns: 1fr; }
    .vastore5 .v5-bundles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .vastore5 .v5-pricecard__grid { grid-template-columns: 1fr; }
    .vastore5 .v5-pricecard__media img { height: 260px; }
}
@media (max-width: 640px) {
    .vastore5 .v5-bundles { grid-template-columns: 1fr; }
    .vastore5 .v5-rate { grid-template-columns: 1fr auto; gap: 10px; }
    .vastore5 .v5-rate__lead { display: none; }
    .vastore5 .v5-rate__value { font-size: 26px; }
    .vastore5 .v5-tabcard__tabs button { flex: 1 1 100%; }
}
</style>
<!-- /wp:html -->

<!-- wp:html -->
<nav class="vastore5 v5-nav" aria-label="Playbook sections">
    <div class="v5-inner v5-nav__inner">
        <?php foreach ($navItems as $item) { ?>
            <a href="#<?= esc_attr($item[0]) ?>" data-v5-nav="<?= esc_attr($item[0]) ?>"><?= $item[1] ?></a>
        <?php } ?>
    </div>
</nav>
<script>
(function () {
    var nav = document.querySelector('.v5-nav');
    if (!nav) { return; }

    /* The site header is sticky at top:0; park this bar directly beneath it. */
    function place() {
        var header = document.querySelector('#app > header');
        var h = header ? Math.round(header.getBoundingClientRect().height) : 80;
        document.documentElement.style.setProperty('--v5-navtop', h + 'px');
    }
    place();
    window.addEventListener('resize', place);

    var links = Array.prototype.slice.call(nav.querySelectorAll('[data-v5-nav]'));

    /* The sections are further down the document than this inline script, so the
       targets are resolved once the parser has finished — looking them up now
       would find nothing and silently disable the scroll-spy. */
    function start() {
        var targets = links
            .map(function (a) { return document.getElementById(a.getAttribute('data-v5-nav')); })
            .filter(Boolean);
        if (!targets.length) { return; }

        var queued = false;
        function spy() {
            queued = false;
            var line = (window.pageYOffset || 0) + nav.getBoundingClientRect().bottom + 90;
            var current = targets[0].id;
            targets.forEach(function (t) {
                if ((window.pageYOffset || 0) + t.getBoundingClientRect().top <= line) { current = t.id; }
            });
            links.forEach(function (a) {
                a.classList.toggle('is-current', a.getAttribute('data-v5-nav') === current);
            });
        }
        function queue() {
            if (queued) { return; }
            queued = true;
            window.requestAnimationFrame(spy);
        }
        spy();
        window.addEventListener('scroll', queue, { passive: true });
        window.addEventListener('resize', queue);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
</script>
<!-- /wp:html -->

<!-- wp:html -->
<section class="vastore5 v5-band v5-section v5-section--tight" id="v5-s-tools">
    <div class="v5-inner">
        <div class="v5-tools">
            <?php include get_theme_file_path('resources/patterns/vastore5-widgets.php'); ?>
        </div>
    </div>
</section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="vastore5 v5-band v5-section v5-section--tight" id="v5-s-probing">
    <div class="v5-inner">
        <div class="v5-card v5-tabcard" id="v5-probing">
            <div class="v5-tabcard__head">
                <h2>Probing Questions</h2>
            </div>
            <div class="v5-tabcard__tabs" role="tablist" aria-label="Customer profile">
                <button type="button" class="is-active" data-tab="hadVA" role="tab" aria-selected="true">Had VA</button>
                <button type="button" data-tab="firstTimer" role="tab" aria-selected="false">Hasn&rsquo;t had VA</button>
                <button type="button" data-tab="hiredDirect" role="tab" aria-selected="false">Hired via Other Platform</button>
                <button type="button" data-tab="hasBPO" role="tab" aria-selected="false">Has Call Center</button>
            </div>
            <div class="v5-subtabs" role="tablist" aria-label="Past VA type">
                <button type="button" class="is-active" data-sub="agency" role="tab" aria-selected="true">Agency</button>
                <button type="button" data-sub="direct" role="tab" aria-selected="false">Direct</button>
            </div>
            <div class="v5-tabcard__body" id="v5-probing-body" aria-live="polite"></div>
        </div>
    </div>
    <script>
    (function () {
        var Q = <?= wp_json_encode($probing) ?>;
        var root = document.getElementById('v5-probing');
        var body = document.getElementById('v5-probing-body');
        var tabs = root.querySelectorAll('.v5-tabcard__tabs > button');
        var subRow = root.querySelector('.v5-subtabs');
        var subTabs = subRow.querySelectorAll('button');

        function render(groups) {
            body.innerHTML = groups.map(function (g) {
                return '<h4>' + g[0] + '</h4><ul>' + g[1].map(function (i) { return '<li>' + i + '</li>'; }).join('') + '</ul>';
            }).join('');
        }
        function setActive(list, btn) {
            Array.prototype.forEach.call(list, function (b) {
                var on = b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }
        function showSub(show) {
            subRow.style.display = show ? '' : 'none';
            subRow.setAttribute('aria-hidden', show ? 'false' : 'true');
        }

        render(Q.hadVA.agency);

        Array.prototype.forEach.call(tabs, function (btn) {
            btn.addEventListener('click', function () {
                setActive(tabs, btn);
                var key = btn.getAttribute('data-tab');
                if (key === 'hadVA') {
                    showSub(true);
                    render(Q.hadVA[subRow.querySelector('.is-active').getAttribute('data-sub')]);
                } else {
                    showSub(false);
                    render(Q[key]);
                }
            });
        });
        Array.prototype.forEach.call(subTabs, function (btn) {
            btn.addEventListener('click', function () {
                setActive(subTabs, btn);
                render(Q.hadVA[btn.getAttribute('data-sub')]);
            });
        });
    })();
    </script>
</section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="vastore5 v5-band v5-section" id="v5-s-discovery">
    <div class="v5-inner">
        <div class="v5-head">
            <h2>Discovery:</h2>
        </div>
        <ol class="v5-qgrid">
            <li><strong>&nbsp;Can you tell me more about the role and some of the tasks you want someone to do?</strong></li>
            <li><strong>&nbsp;Do you have an hourly budget in mind?</strong></li>
            <li><strong>&nbsp;Do they have to speak Spanish or is just English ok?</strong></li>
            <li><strong>&nbsp;Is this going to be a full time or a part time role?</strong></li>
            <li><strong>&nbsp;Have you hired Virtual Assistants before?</strong></li>
            <li><strong>&nbsp;How soon do you want to hire someone for this role?</strong></li>
            <li><strong>Are you looking for someone long term or short term?</strong></li>
        </ol>
    </div>
</section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="vastore5 v5-band v5-section" id="v5-s-presentation">
    <div class="v5-inner">
        <div class="v5-head">
            <h2>Presentation:</h2>
        </div>
        <div class="v5-card v5-card--pad">
            <div class="v5-prose">
                <ul>
                    <li>Recruiting firm specializing in hiring Virtual Assistants from Latin America, the Philippines, and Europe.</li>
                    <li>Receive 2,000&ndash;3,000 applications every day. What this means is, instead of you choosing from a few applicants, we can choose the exact best ones from thousands of applicants that we receive every month and really get you the perfect match.</li>
                    <li>Vetting Process:
                        <ul>
                            <li>Voice recording to make sure they speak fluent English with little to no accent.</li>
                            <li>If they sound good with little to no accent, we move them to a 1-on-1 interview.</li>
                            <li>If they pass our 1-on-1 interview, we get them to then take a skills assessment to make sure they&rsquo;re competent.</li>
                            <li>The target is to find you 4&ndash;6 applicants that fit all your criteria.</li>
                        </ul>
                    </li>
                    <li>Once we have 4&ndash;6 qualified applicants, we&rsquo;ll have you interview them on Zoom alongside our hiring manager.</li>
                    <li>After you&rsquo;re done with the interviews, choose your top applicants, and then you can have a second round of interviews to get to know them better before hiring.</li>
                    <li>Once you choose someone, we&rsquo;ll help negotiate the hourly rate and schedule.</li>
                    <li>To be fully transparent, 100% of the hourly rate goes directly to the Virtual Assistant. That&rsquo;s actually why we get very high-quality Virtual Assistants, because we don&rsquo;t take a cut out of their pay like other agencies, where they usually only get paid 30% of what the clients pay. So they don&rsquo;t even apply to work with those companies, and the clients there get stuck dealing with low-quality Virtual Assistants.</li>
                    <li>And then, after you choose who you want to hire, we&rsquo;ll help you with onboarding and payroll setup. We work with a company that can handle payroll and everything for you, or we can just teach you how to do payroll yourself. It&rsquo;s pretty easy.</li>
                    <li>Whoever you end up hiring through us, we&rsquo;ll give you a twelve-month replacement guarantee. If they don&rsquo;t turn out to be a good fit, we can replace them for free.</li>
                    <li>We will also give you back-end support for six months, so if you need help with training, payroll setup, setting up monitoring, or whatever, we can help you with back-end things.</li>
                </ul>
                <div class="v5-callout"><b>What do you think about our proposal so far?</b></div>
            </div>
        </div>
    </div>
</section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="vastore5 v5-band v5-section" id="v5-s-cannot">
    <div class="v5-inner">
        <div class="v5-card v5-card--pad v5-warn">
            <p class="v5-warn__title">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 1 21h22L12 2Zm1 14h-2v2h2v-2Zm0-7h-2v5h2V9Z"/></svg>
                <span><strong><em>Optional if a client says something we&nbsp;CANNOT Hire for:&nbsp;</em></strong></span>
            </p>
            <ul>
                <li>US Licensed individuals</li>
                <li>Less than 20 hours per week (has to be part time or full time)&nbsp;</li>
                <li>Specific software experience (unless it&rsquo;s a widely adopted software such as Quickbooks, Hubspot, Gmail, etc.)&nbsp;</li>
                <li>We can&rsquo;t find someone that has experience performing tasks from very different job categories. EX: Can&rsquo;t expect to find an admin that is also great at sales, or a salesperson that&rsquo;s also great at running ads, etc. Very different skill categories.</li>
                <li>No specific cities or countries unless large country such as Mexico for a common role such as Sales, Admin. Hiring from Latin America or other regions is doable, trying to find someone with all the skills we want from a small country is not doable.&nbsp;</li>
                <li>No commission-only arrangements</li>
                <li>No Hourly rate below $6/hr</li>
                <li><span class="v5-small">We can&rsquo;t discriminate based on gender, religion, sexual orientation, etc. similar to the US</span></li>
            </ul>
        </div>
    </div>
</section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="vastore5 v5-band v5-band--deep v5-section" id="v5-s-pricing">
    <div class="v5-inner">
        <div class="v5-head v5-head--center">
            <h2>One Time Payment - Breakdown by Hourly Rate</h2>
        </div>

        <div class="v5-glass v5-rates">
            <?php foreach ($rateRows as $row) { ?>
                <div class="v5-rate">
                    <div class="v5-rate__label"><?= esc_html($row[0]) ?></div>
                    <div class="v5-rate__lead" aria-hidden="true">--------------------------</div>
                    <div class="v5-rate__value"><?= esc_html($row[1]) ?></div>
                </div>
            <?php } ?>
        </div>
        <p class="v5-note-pill">Monthly Payment Plans Available</p>

        <div class="v5-head v5-head--center" style="margin-top:clamp(40px,4vw,64px)">
            <h2>Pricing</h2>
        </div>

        <div class="v5-pricecard">
            <div class="v5-pricecard__head">
                <h2 class="v5-pricecard__title">Recruitment - One Time Payment<br>(Payment Plans Available)</h2>
            </div>
            <div class="v5-pricecard__grid">
                <div class="v5-pricecard__media">
                    <img src="<?= esc_url($img) ?>" width="400" height="280" alt="" loading="lazy" decoding="async">
                </div>
                <div class="v5-pricecard__copy">
                    <h3 class="v5-pricecard__sub">40% of Annual Full Time Salary - One Time Payment</h3>
                    <ul class="v5-facts">
                        <li>After you interview and choose to hire an applicant, we get paid a 1 time payment.</li>
                        <li>Calculated as 40% of Annual Full Time Salary of Applicant.</li>
                        <li>No Hourly, Monthly or Recurring charges to us.</li>
                        <li>Whatever you pay goes direct to Virtual Assistant.</li>
                        <li>VA Salary: Paid hourly based on the amount of hours you want (Part Time or Full Time).</li>
                        <li>12 Month Replacement Guarantee + Back End Support.</li>
                        <li>Scaling? 30% Discount on future placements within 12 months.</li>
                    </ul>
                    <div class="v5-pricecard__note">
                        <p>$100 refundable deposit to begin the recruitment process.<br>The deposit will be deducted from the final hiring invoice.</p>
                    </div>
                    <a class="v5-btn v5-btn--block" href="http://jotform.com/form/252276995438170?utm_source=google">Get Started</a>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="vastore5 v5-band v5-band--deep v5-section v5-section--tight" id="v5-s-booking">
    <div class="v5-inner">
        <div class="v5-calwrap">
            <div class="calendly-inline-widget" data-url="https://calendly.com/d/dtkp-63s-2qv?hide_gdpr_banner=1" style="min-width:320px;height:700px;"></div>
        </div>
        <script src="https://assets.calendly.com/assets/external/widget.js" async></script>

        <div class="v5-failsafe">
            <button type="button" class="v5-failsafe__toggle" aria-expanded="false">Having trouble booking above?</button>
            <div class="v5-failsafe__panel">
                <a class="v5-failsafe__btn" href="https://buy.stripe.com/9AQbKAcnC6NP2ukaFh" target="_blank" rel="noopener">Stripe Payment</a>
                <a class="v5-failsafe__btn" href="<?= esc_url($calendlyBooking) ?>" target="_blank" rel="noopener">Book Meeting</a>
            </div>
        </div>

        <div class="v5-card v5-tabcard" id="v5-deposit" style="margin-top:clamp(28px,3vw,44px)">
            <div class="v5-tabcard__head">
                <h2>Deposit &amp; Payment Options</h2>
            </div>
            <div class="v5-tabcard__tabs" role="tablist" aria-label="Deposit options">
                <?php $first = true;
foreach ($deposit as $key => $group) { ?>
                    <button type="button"<?= $first ? ' class="is-active"' : '' ?> data-tab="<?= esc_attr($key) ?>" role="tab" aria-selected="<?= $first ? 'true' : 'false' ?>"><?= $group[0] ?></button>
                <?php $first = false;
} ?>
            </div>
            <div class="v5-tabcard__body" id="v5-deposit-body" aria-live="polite"></div>
        </div>
    </div>
    <script>
    (function () {
        document.querySelectorAll('.v5-failsafe').forEach(function (wrap) {
            var toggle = wrap.querySelector('.v5-failsafe__toggle');
            var panel = wrap.querySelector('.v5-failsafe__panel');
            toggle.addEventListener('click', function () {
                var open = panel.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });
    })();
    (function () {
        var Q = <?= wp_json_encode($deposit) ?>;
        var root = document.getElementById('v5-deposit');
        var body = document.getElementById('v5-deposit-body');
        var tabs = root.querySelectorAll('.v5-tabcard__tabs > button');

        function render(group) {
            body.innerHTML = '<h4>' + group[1] + '</h4><ul>' +
                group[2].map(function (i) { return '<li>' + i + '</li>'; }).join('') + '</ul>';
        }
        render(Q[tabs[0].getAttribute('data-tab')]);

        Array.prototype.forEach.call(tabs, function (btn) {
            btn.addEventListener('click', function () {
                Array.prototype.forEach.call(tabs, function (b) {
                    var on = b === btn;
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                render(Q[btn.getAttribute('data-tab')]);
            });
        });
    })();
    </script>
</section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="vastore5 v5-band v5-band--deep v5-section" id="v5-s-bundles">
    <div class="v5-inner">
        <div class="v5-head v5-head--center">
            <h2>Bundle Pricing Packages</h2>
        </div>
        <p class="v5-bundle-intro">Fixed price regardless of hourly rate. <br>Price Match Guarantee: If your total cost was to be cheaper on a one time package, we will refund the difference to match the pricing of the one time packages, but typically the bundles are significantly cheaper. </p>

        <div class="v5-bundles">
            <?php foreach ($bundleRows as $row) { ?>
                <div class="v5-glass v5-bundle">
                    <div class="v5-bundle__label"><?= esc_html($row[0]) ?></div>
                    <div class="v5-bundle__value"><strong><?= esc_html($row[1]) ?></strong> <?= esc_html($row[2]) ?></div>
                </div>
            <?php } ?>
        </div>

        <div class="v5-btn-row">
            <a class="v5-btn" href="https://pci.jotform.com/form/252324903849159">Get Started</a>
        </div>
    </div>
</section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="vastore5 v5-band v5-section" id="v5-s-objections">
    <div class="v5-inner">
        <div class="v5-obj">
            <div class="v5-head v5-head--center">
                <h2>Objections</h2>
            </div>

            <div class="v5-obj__intro">
                <div class="v5-card v5-obj__panel">
                    <p class="v5-obj__lead">When confronted with an objection:</p>
                    <div class="v5-obj__steps">
                        <p>1. Make sure to agree with their concern</p>
                        <p>2. Isolate the objection by asking if that's the only thing holding them back or if there are other things they'll want to think about</p>
                        <p>3. Handle those objections one by one</p>
                        <p>4. <b>CLOSE AGAIN</b> after handling the objection, using an assumptive close, as if they now already agree with moving forward.</p>
                    </div>
                    <p class="v5-sep">_______________________</p>
                </div>

                <div class="v5-card v5-obj__panel">
                    <p class="v5-obj__lead"><b>For Example:</b></p>
                    <div class="v5-obj__script">
                        <p class="is-client">"I want to think it over"</p>
                        <p class="is-rep">Yea of course think it over first, i'm curious though, if you think it over and decide to no proceed, what would the reason be?</p>
                        <p class="is-client">"Well the price is too high?" Oh ok, is there anything else besides price?</p>
                        <p class="is-client">"No just the price"</p>
                        <p class="is-rep">"Got it, yea I understand [ HANDLE OBJECTION HERE]</p>
                        <p class="is-rep">So cool, the next step is to do X</p>
                        <p>Then proceed to X as if they just agreed to do whatever it is you want.</p>
                    </div>
                    <p class="v5-sep">________________________________</p>
                </div>
            </div>

            <div class="v5-acc">
                <?php foreach ($objections as $item) { ?>
                    <details>
                        <summary>
                            <span><?= esc_html($item['q']) ?></span>
                            <span class="v5-acc__ico"><?= $iconPlus ?><?= $iconMinus ?></span>
                        </summary>
                        <div class="v5-acc__body"><?= $item['a'] ?></div>
                    </details>
                <?php } ?>
            </div>
        </div>
    </div>
</section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="vastore5 v5-band v5-section" id="v5-s-roles">
    <div class="v5-inner">
        <div class="v5-roles">
            <?php foreach ($roleCategories as $cat) { ?>
                <div class="v5-dd">
                    <input type="checkbox" id="v5-<?= esc_attr($cat['id']) ?>">
                    <label class="v5-dd__btn" for="v5-<?= esc_attr($cat['id']) ?>"><?= esc_html($cat['title']) ?></label>
                    <div class="v5-dd__content">
                        <?php foreach ($cat['roles'] as $role) { ?>
                            <div class="v5-role">
                                <div class="v5-role__title"><?= esc_html($role[0]) ?></div>
                                <div class="v5-role__desc"><?= esc_html($role[1]) ?></div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</section>
<!-- /wp:html -->
