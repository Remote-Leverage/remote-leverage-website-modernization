<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Ecommerce Virtual Assistants
 * Slug: remote-leverage/ecommerce-virtual-assistant
 * Categories: remote-leverage
 * Description: Complete /ecommerce-virtual-assistant/ landing page, 17 sections.
 *
 * Reproduced 1:1 from production on Adrián's instruction ("just copy as is"), INCLUDING
 * production's own content bugs. Do not "fix" these in passing — they are deliberate:
 *   - §9  heading reads "Hourly Rates for Medical and Healthcare VAs" and a pricing card
 *         reads "Remote Leverage Medical VA Average" on an ecommerce page.
 *   - §4  every talent card's TOOLS strip is medical software (Epic, Cerner, eClinicalWorks…).
 *   - §15 Featured Content links four telehealth articles.
 *   - §5  "41,920,00" and "Economic Impact Create" are both malformed on production.
 *   - §3  cards 5 and 6 duplicate the descriptions of cards 3 and 4; "Montly" is misspelled.
 *   - §2  card 6's description duplicates card 5's.
 * The page <title> on production is "Hire Power Dialers from LATAM", also carried over.
 */
$img = fn (string $file): string => BlockDefaults::ecomImg($file);

// Production design tokens, read off the live page with getComputedStyle at 1440px.
$wrap = 'w-full max-w-[1380px] mx-auto px-5 sm:px-6 lg:px-8';
$h2 = 'font-display font-bold text-[32px] leading-[38px] sm:text-[42px] sm:leading-[48px] tracking-[-1.26px]';
$h2Big = 'font-display font-bold text-[34px] leading-[40px] sm:text-[48px] sm:leading-[53px] tracking-[-0.03em]';
$h3 = 'font-display font-bold text-[22px] leading-[30px] sm:text-[27px] sm:leading-[41px] tracking-[-0.03em]';
$lead = 'text-[16px] leading-[26px] sm:text-[20px] sm:leading-[30px] tracking-[-0.03em]';
$pill = 'mt-2.5 flex w-full items-center justify-center rounded-pill bg-brand-purple hover:bg-brand-purple-deep px-8 py-5 text-center font-bold uppercase text-white text-[20px] leading-[30px] transition-colors';

// ── §1b talent marquee ───────────────────────────────────────────────────────
// Portrait/logo pairing matches BlockDefaults::talentCards(); production serves the
// same 11 cards from this page's own uploads, so they resolve through ecomImg().
$marquee = array_map(fn (array $c): array => [
    'name' => $c[0], 'title' => $c[1], 'desc' => $c[2],
    'bg' => $img('marquee/'.$c[3]), 'logo' => $img('marquee/'.$c[4]),
], [
    ['André Vilalobos', 'Graphic Designer', '6+ years of experience helping brands of all sizes, from small and mid-sized businesses to big companies, look professional, polished, and unmistakably them.', 'con-07.png', 'State-Farm-01.png'],
    ['Juliana Silva', 'Lead Generation (SDR)', '6+ years of experience as an SDR, skilled in prospecting, active listening, clear communication, time management, and handling rejection to consistently generate and qualify sales leads.', 'cont-02.png', 'mercado.png'],
    ['Valeria Andrea', 'Medical Assistant', '4+ years of experience in fast-paced clinic and hospital settings. Skilled in EMR systems (Epic, Cerner), patient intake, vital signs, and assisting physicians with exams and procedures.', 'con-05.png', 'Allstate-01.png'],
    ['Laura Valentina', 'Customer Support', '+4 years in B2B SaaS customer support, I’ve supported customers in North America, Europe, and Latin America, adapting to different cultural expectations and communication styles while handling email, chat, and phone support.', 'con-08.png', 'image-12-1.png'],
    ['Sofía Pérez', 'Marketing Assistant', '4+ years of experience as a results-driven marketing professional, skilled in content creation, social media strategy, campaign management, and data analysis to drive brand awareness and customer engagement.', 'cont-03.png', 'Frame-74-1.png'],
    ['Luana Dias', 'Executive Assistant', '3+ years of experience supporting C-level executives in fast-paced environments. High organization, anticipate needs, and protect executive’s time like it’s my own.', 'con-06.png', 'NU-bank-01.png'],
    ['Sarah Martinez', 'Sr Executive Assistant', 'Executive Assistant with 8+ years supporting founders and executives. Expert in calendar management, inbox organization, project coordination, and keeping fast-growing teams operating smoothly.', 'Frame-131-1.png', '1655873088shopify-logo-transparent.webp'],
    ['Daniela Costa', 'Executive Assistant', 'Experienced Executive Assistant specializing in executive support, meeting coordination, travel planning, and operational workflows. Known for exceptional organization, proactive communication, and attention to detail.', 'Frame-132-1.png', 'rappi_logo-Small.png'],
    ['Lucas Mendes', 'Marketing Manager', 'Marketing Manager with 8+ years of experience across demand generation, paid acquisition, lifecycle marketing, and funnel optimization. Proven track record scaling pipeline, improving conversion rates, and building marketing systems that drive measurable growth.', 'Frame-133-1.png', 'clickup.png'],
    ['Noah Martinez', 'Sales Representative', 'Sales Development Representative who consistently exceeded quota by building high-quality outbound pipelines for B2B software companies. Experienced in multi-channel prospecting, account research, and converting cold prospects into qualified opportunities.', 'Frame-135-1.png', 'image-2.png'],
    ['Diego Navarro', 'Sales Representative', 'Revenue-focused sales representative experienced in outbound prospecting, product demonstrations, and account management. Helped drive growth by consistently converting qualified leads into long-term customers.', 'Frame-135-2.png', 'image-11.png'],
]);

// ── §1c trusted-by strip ─────────────────────────────────────────────────────
// Alt text is production's own (the uploads were never given real names).
$trustedLogos = array_map(fn (array $l): array => ['src' => $img('logos/'.$l[0]), 'alt' => $l[1]], [
    ['image-9.png', 'image 9'],
    ['image-7.png', 'image 7'],
    ['image-8.png', 'image 8'],
    ['image-6.png', 'image 6'],
    ['image-10.png', 'image 10'],
    ['image-4.png', 'image 4'],
    ['image-5.png', 'image 5'],
    ['1-1.png', '1 1'],
    ['image-3.png', 'image 3'],
]);

// ── §2 "World's Best Ecommerce Talent" ───────────────────────────────────────
// Card 6 repeats card 5's description verbatim. That is production's copy, not a slip here.
$talentCards = array_map(fn (array $c): array => ['img' => $img('cards/'.$c[0]), 'title' => $c[1], 'desc' => $c[2]], [
    ['Frame-76.png', 'Order Processing', 'Consolidated into a single approval each cycle, no matter how many countries you pay into.'],
    ['Frame-76-1-1.png', 'Catalog &amp; Listings', 'Create and update product listings, standardize titles and descriptions, manage variants and SKUs, and keep Shopify, Amazon, Walmart, and eBay in sync.'],
    ['Frame-76-3.png', 'Inventory Tracking', 'Reconcile stock daily, monitor reorder points, flag slow movers, and run the supplier follow-up loop so ecommerce inventory management stops living in your head.'],
    ['Frame-76-5.jpg', 'Customer Support &amp; Returns', 'Staff the help desk, run live chat during business hours, process returns against your policy, and escalate only what actually needs you.'],
    ['Frame-76-5.png', 'Marketing Execution', 'Build email flows, update product pages, compile ad reports, publish content, and keep campaigns shipping on schedule.'],
    ['Frame-76-6.png', 'Custom Support', 'Build email flows, update product pages, compile ad reports, publish content, and keep campaigns shipping on schedule.'],
]);

// ── §3 split-chip role cards ─────────────────────────────────────────────────
// Cards 5 and 6 re-use the titles and descriptions of cards 3 and 4; "Montly" is
// production's spelling of the price label. Both reproduced deliberately.
$roleCards = array_map(fn (array $c): array => [
    'photo' => $img('avatars/'.$c[0]), 'chip_name' => $c[1], 'chip_role' => $c[2],
    'price_label' => 'Montly', 'price' => $c[3], 'cta_text' => 'Hire', 'cta_url' => '#booking-footer',
    'title' => $c[4], 'intro' => $c[5],
], [
    ['mariana.png', 'Mariana R.', 'Virtual Assistant (Generalist)', '$ 1,200.00', 'Ecommerce Virtual Assistant (Generalist)', 'Handles the daily mix: order processing, listing uploads, customer emails, and backend data cleanup. The highest-impact first hire for a founder-led store.'],
    ['Maria.png', 'María F.', 'Catalog Specialist', '$ 1,100.00', 'Product Listing & Catalog Specialist', 'Owns ecommerce catalog management. Builds and optimizes listings, manages variants and SKUs, standardizes copy against a template, and keeps multichannel catalogs in sync.'],
    ['Jose.png', 'José P.', 'Customer Support', '$ 1,200.00', 'Customer Support & Returns Specialist', 'Runs the help desk, live chat, and ecommerce returns management. Works to documented response and resolution targets, and protects the review profile.'],
    ['juan.png', 'Juan F.', 'Inventory & Supplier Coordinator', '$ 1,100.00', 'Inventory & Supplier Coordinator', 'Daily inventory reconciliation, reorder point monitoring, purchase order confirmation, and supplier follow-up. Reduces oversells and dead stock.'],
    ['Ana.png', 'Ana Lucía', 'Returns Specialist', '$ 1,200.00', 'Customer Support & Returns Specialist', 'Runs the help desk, live chat, and ecommerce returns management. Works to documented response and resolution targets, and protects the review profile.'],
    ['carmen.png', 'Carmen R.', 'Supplier Coordinator', '$ 1,200.00', 'Inventory & Supplier Coordinator', 'Daily inventory reconciliation, reorder point monitoring, purchase order confirmation, and supplier follow-up. Reduces oversells and dead stock.'],
]);

// ── §4 dossier cards ─────────────────────────────────────────────────────────
// The TOOLS strip is medical software on every card — wrong for an ecommerce page and
// exactly what production ships. Transcribed from the live DOM, not invented.
$tool = fn (string $file, string $alt): array => ['src' => $img('tools/'.$file), 'alt' => $alt];
$dossier = array_map(fn (array $c): array => [
    'photo' => $img('talent/'.$c[0]), 'flag' => $img('flags/'.$c[1]), 'country' => $c[2],
    'rate' => $c[3], 'name' => $c[4], 'role' => $c[5], 'years' => $c[6],
    'experience' => $c[7], 'skills' => $c[8], 'previous_companies' => $c[9], 'tools' => $c[10],
], [
    ['Andres-M.jpg', 'costa.svg', 'Costa Rica', '$14/hour', 'Andrés Molina', 'Ecommerce PPC & Marketplace Ads Specialist', '12',
        'Managed Sponsored Products and Meta campaigns for a household goods and department store chains, monitoring ACoS, pausing out-of-stock products before spend leaked.',
        'Sponsored Products management; bid and budget adjustment; ACoS monitoring; competitor price tracking; performance reporting',
        'Grupo Monge, Unimart',
        [$tool('eclinicalworks.png', 'eClinicalWorks'), $tool('doxy.png', 'Doxy.me'), $tool('dosespot.png', 'DoseSpot'), $tool('zoom.png', 'Zoom'), $tool('google-workspace.png', 'Google Workspace')]],
    ['Sofia-G.jpg', 'Dominican.svg', 'Dominican Republic', '$10/hour', 'Sofía Guerrero', 'Ecommerce Virtual Assistant (Amazon focus)', '7',
        'Handled Seller Central case logs, FBA inventory tracking, and reimbursement claims for a third-party seller across multiple marketplaces.',
        'Seller Central case handling; FBA inventory tracking; reimbursement claims; account health monitoring; listing fixes',
        'Alorica (Santo Domingo), Grupo Ramos',
        [$tool('epic.png', 'Epic'), $tool('cerner.png', 'Cerner'), $tool('zoom.png', 'Zoom'), $tool('google-workspace.png', 'Google Workspace'), $tool('slack.png', 'Slack')]],
    ['Rafael-P.jpg', 'peru-01.svg', 'Peru', '$11/hour', 'Rafael Pinto', 'Ecommerce Operations Assistant', '5',
        'Coordinated fulfillment, 3PL communication, and weekly sales and margin reporting for a store. Also fluent with Shopify and Walmart Marketplace.',
        'Fulfillment coordination; 3PL and vendor communication; sales and margin reporting; data cleanup; process documentation',
        'Ripley Perú, Juntoz',
        [$tool('athena.png', 'athenahealth'), $tool('google-workspace.png', 'Google Workspace'), $tool('Availity.png', 'Availity'), $tool('calwndly.png', 'Calendly'), $tool('ringCentral.png', 'RingCentral')]],
    ['Mariana-R.jpg', 'colombia.svg', 'Colombia', '$8/hour', 'Mariana Ríos', 'Ecommerce Virtual Assistant', '7',
        'Supported complex operations across daily order processing, listing uploads, and front-line customer email.',
        'Order processing; listing uploads; customer email; data entry; basic reporting',
        'Alkosto, Merqueo',
        [$tool('eclinicalworks.png', 'eClinicalWorks'), $tool('epic.png', 'Epic'), $tool('google-workspace.png', 'Google Workspace'), $tool('zoom.png', 'Zoom'), $tool('dr-chrono.png', 'DrChrono')]],
    ['Diego-S.jpg', 'mexico.svg', 'Mexico', '$11/hour', 'Diego Salazar', 'Product Listing & Catalog Specialist', '11',
        'Managed 1000s of SKUs for a major retail brand, standardizing titles and variant structures ahead of a peak season launch. Adept with Shopify and Amazon.',
        'Catalog management; variant and SKU structuring; listing optimization; multichannel sync; bulk uploads',
        'Liverpool, Linio México',
        [$tool('athena.png', 'athenahealth'), $tool('calwndly.png', 'Calendly'), $tool('google-workspace.png', 'Google Workspace'), $tool('Availity.png', 'Availity'), $tool('slack.png', 'Slack')]],
    ['Camila-D.jpg', 'brazil.svg', 'Brazil', '$8/hour', 'Camila Duarte', 'Customer Support & Returns Specialist', '3',
        'Ran live chat and help desk coverage for major DTC retailers, processing returns and exchanges against documented policies.',
        'Live chat; help desk; returns and exchanges; review and feedback management; escalation handling',
        'Lojas Renner, Amaro',
        [$tool('kareo.png', 'Kareo'), $tool('advanced.png', 'AdvancedMD'), $tool('excel.png', 'Excel'), $tool('Availity.png', 'Availity'), $tool('google-workspace.png', 'Google Workspace')]],
    ['Kemar-B.jpg', 'jamaica.svg', 'Jamaica', '$10/hour', 'Kemar Bennett', 'Inventory & Supplier Coordinator', '5',
        'Owned daily inventory reconciliation and supplier follow-up for a large, multi-warehouse retailer, tracking purchase orders and manufacturing schedules.',
        'Inventory reconciliation; reorder point monitoring; purchase order tracking; supplier communication; demand forecasting support',
        'GraceKennedy, Massy Distribution Jamaica',
        [$tool('eclinicalworks.png', 'eClinicalWorks'), $tool('caresimple.png', 'CareSimple'), $tool('google-workspace.png', 'Google Workspace'), $tool('zoom.png', 'Zoom'), $tool('ringCentral.png', 'RingCentral')]],
    ['Valentina-M.jpg', 'argentina.svg', 'Argentina', '$9/hour', 'Valentina Moreno', 'Ecommerce SEO Specialist', '5',
        'Led product and category page optimization for stores with 1000s of SKUs, resolving cannibalization and rebuilding internal linking ahead of a site migration.',
        'Keyword research; product and category page optimization; technical audits; internal linking; Search Console and GA4 reporting',
        'Frávega, Despegar',
        [$tool('doxy.png', 'Doxy.me'), $tool('zoom.png', 'Zoom'), $tool('google-workspace.png', 'Google Workspace'), $tool('calwndly.png', 'Calendly'), $tool('epic.png', 'Epic')]],
]);

// ── §5 stats band ────────────────────────────────────────────────────────────
// "41,920,00" and "Economic Impact Create" are both malformed on production. Kept as-is.
$stats = [
    ['eyebrow' => '', 'value' => '+2500', 'label' => 'Contractors paid'],
    ['eyebrow' => '', 'value' => '+50', 'label' => 'Countries covered'],
    ['eyebrow' => 'USD', 'value' => '41,920,00', 'label' => 'Economic Impact Create'],
];

// ── §6 "Built to help you grow" — text-only 2-up ─────────────────────────────
$growCards = array_map(fn (array $c): array => ['title' => $c[0], 'desc' => $c[1]], [
    ['Pre-Vetted for<br>Platform Experience', 'Candidates are screened on the platforms you actually run. Shopify, Amazon Seller Central, Walmart, eBay, and the help desk and accounting tools in your stack.'],
    ['Careful with Customer<br>and Payment Data', 'Every candidate has prior experience handling order records, addresses, and refund workflows, and understands that customer data is not something you experiment with.'],
    ['Role-Appropriate Access', 'Permission scope should match the role. We help you define what a listing specialist needs versus what an operations assistant needs, so nobody gets admin access by default.'],
    ['You Set the Terms', 'As the direct employer, you put your own confidentiality agreements, password manager policies, and access rules in place with your hire.'],
]);

// ── §7 "Why Remote Leverage?" — numbered, image-less 4-up ────────────────────
$whyCards = array_map(fn (array $c): array => ['eyebrow' => $c[0], 'title' => $c[1]], [
    ['01', 'Latin America’s top 1% VAs, from $6/hr'],
    ['02', 'Fluent English, college-educated, working U.S. hours'],
    ['03', 'Over 1,000 applicants screened daily'],
    ['04', 'Go from interview to onboarding in days, not weeks'],
    ['05', 'No contracts. No recurring fees.'],
    ['06', '12-month replacement guarantee'],
    ['07', 'Specialized and general support'],
    ['08', 'Hire part-time or full-time'],
]);

// ── §8 comparison table ──────────────────────────────────────────────────────
$tableRows = array_map(fn (array $r): array => ['feature' => $r[0], 'diy' => $r[1], 'rl' => $r[2]], [
    ['Time to hire', '4 - 8 weeks', '72 hrs'],
    ['Vetting quality', 'Hit or miss', 'Top 1% pre-screened'],
    ['Payroll & taxes', 'DIY or expensive local lawyer', 'Fully handled'],
    ['Compliance risk', 'High - misclassification, local laws', 'Zero – 170+ countries covered'],
    ['Ongoing fees', 'Often 15-30% monthly markup', 'One-time flat fee only'],
    ['Replacement guarantee', 'None', '6-months, no extra costs'],
    ['Centralized reporting', 'Spreadsheets', 'Remote Leverage dashboard'],
]);

// ── §9a pricing cards ────────────────────────────────────────────────────────
// Card 3's label says "Medical VA Average" on an ecommerce page. Production's copy.
//
// Production's markup points these cards at pricing/green-1.png and pricing/green.png. Both
// are broken on the LIVE page (the <img> reports naturalWidth 0), so production renders a
// blank card top — but the files themselves are intact, and Adrián's call on 2026-09-15 was
// to wire them in. They render through the block's new `icon` field, which draws them at
// their natural ~88px/146px rather than full-bleed at h-[168px]; painting them full-bleed
// was what would have stamped a 433px green coin on each card.
$pricingCards = [
    ['icon' => $img('pricing/green-1.png'), 'icon_width' => '88', 'title' => '$6 – 10 hr', 'text' => 'Experienced Professionals', 'cta_text' => 'FIND MY NEXT HIRE', 'cta_url' => '#booking-footer'],
    ['icon' => $img('pricing/green.png'), 'icon_width' => '146', 'title' => '$11 – 15 hr', 'text' => 'Senior Level Support', 'cta_text' => 'FIND MY NEXT HIRE', 'cta_url' => '#booking-footer', 'emphasis' => 1],
    ['icon' => $img('pricing/green-1.png'), 'icon_width' => '88', 'title' => '$9.21 hr', 'text' => 'Remote Leverage Medical VA Average', 'cta_text' => 'FIND MY NEXT HIRE', 'cta_url' => '#booking-footer'],
];

// ── §9b talent carousel ──────────────────────────────────────────────────────
$carouselProfiles = array_map(fn (array $p): array => [
    'image' => $img('talent/'.$p[0]), 'name' => $p[1], 'role' => 'Administrative Assistant', 'rate' => $p[2],
], [
    ['Frame-51-2.jpg', 'María Fernanda', '$8 Per Hour'],
    ['Frame-51.jpg', 'Luciana Lopez', '$10 Per Hour'],
    ['Frame-51-1.jpg', 'Catalina García', '$12 Per Hour'],
]);

// ── §12a case-study cards ────────────────────────────────────────────────────
$resultCards = [
    [
        'image' => $img('logos/Bench.png'),
        'tag_1' => 'Accounting', 'tag_2' => 'Large Team', 'tag_3' => 'Enterprise',
        'title' => 'Bench Accounting',
        'desc' => 'Bench Accounting needed to scale its remote bookkeeping team fast – without lowering the bar on quality. They were so impressed with the quality of our talent, that after their first hire, they came back for 30 more.',
        'quote_text' => '“We wouldn\'t have hired 31 people if it wasn\'t working. The process, the people, the partnership – it\'s been excellent.”',
        'quote_author' => '– Hannah, Hiring Lead, Bench Accounting',
        'stat_1_value' => '31', 'stat_1_label' => 'hires in 4 months',
        'stat_2_value' => '60%', 'stat_2_label' => 'less time screening',
        'stat_3_value' => '7–10 days', 'stat_3_label' => 'to replace a hire',
        'link_url' => '#', 'link_text' => 'READ THE FULL STORY',
    ],
    [
        'image' => $img('logos/Chick-filla.png'),
        'tag_1' => 'Food & Beverage', 'tag_2' => 'Franchise', 'tag_3' => 'Mid-size',
        'title' => 'Chick-fil-A',
        // Production's copy opens with a stray "Team " — kept.
        'desc' => 'Team As this Chick-fil-A franchise grew into a second location, the ownership team needed experienced support staff. They wanted to hire 1 VA, but when presented with 6 candidates, they hired 2 instead.',
        'quote_text' => '“As soon as I interviewed everyone, I realized that this was what I needed to be doing this whole time to save my business money and push my business to the next level.”',
        'quote_author' => '– Traci Danmeyer, Chick-fil-A',
        'stat_1_value' => '70%', 'stat_1_label' => 'savings in cost',
        'stat_2_value' => '7 days', 'stat_2_label' => 'avg. time to hire',
        'stat_3_value' => '2', 'stat_3_label' => 'staff hired',
        'link_url' => '#', 'link_text' => 'READ THE FULL STORY',
    ],
];

// ── §14 applicant videos ─────────────────────────────────────────────────────
// Production labels every country cell literally "Country" and points cards 2-5 at the
// same Shumpei K. video and résumé. Both reproduced verbatim.
$shumpei = 'https://remoteleverage.com/wp-content/uploads/2026/01/Shumpei-K.-Data-Analyst-Remote-Leverage.mp4';
$shumpeiCv = 'https://remoteleverage.com/wp-content/uploads/2026/01/Shumpei-Resume-Data-Analyst-Remote-Leverage.png';
$videoCards = array_map(fn (array $c): array => [
    'name' => $c[0], 'rate' => $c[1], 'role' => $c[2], 'country' => 'Country',
    'poster_url' => $img('posters/'.$c[3]), 'video_url' => $c[4], 'resume_url' => $c[5], 'bio' => $c[6],
], [
    ['Diego P.', '$12/hr', 'Front-End Developer', 'Diego-P.jpg',
        'https://remoteleverage.com/wp-content/uploads/2026/01/Nazarena-T.-Customer-Service-Representative.mp4',
        'https://remoteleverage.com/wp-content/uploads/2026/01/Screenshot-2026-01-31-at-5.01.04-PM.png',
        'I’m Roberto, and I’m applying for the WordPress developer position. I have extensive experience in designing, developing, and managing WordPress websites, focusing on responsive layouts, theme customization, and plugin functionality. My background includes managing content, optimizing technical aspects, and ensuring a smooth user experience'],
    ['Daniela G.', '$11/hr', 'Digital Marketing & SEO Specialist', 'Daniela-G.jpg', $shumpei, $shumpeiCv,
        'I’m Danila, a digital marketing and creative design specialist with over nine years of experience, helping brands stand out in competitive markets. I’ve worked across various industries with several brands, crafting effective marketing strategies.'],
    ['Diana M.', '$9/hr', 'Customer Support', 'Diana-M.-1.jpg', $shumpei, $shumpeiCv,
        'I started my career in the sales industry and customer service in the insurance industry with State Farm, where I focused mostly on cold calling, setting appointments, and completing paperwork, as well as assisting existing clients with payments and product information.'],
    ['Ruth R.', '$9/hr', 'Customer Support', 'Ruth-R.-1.jpg', $shumpei, $shumpeiCv,
        'I have ten years of experience in customer service, education, and operations. Most recently, I’ve worked in the transportation industry, providing excellent customer service in both Spanish and English while simultaneously improving operations.'],
    ['Mariana A.', '$9/hr', 'Social Media Manager', 'Mariana-A.jpg', $shumpei, $shumpeiCv,
        'I started working in social media 7 years ago. My first job was at a creative agency, where I worked as an executive assistant and later as an executive manager, but mostly as a social media content creator. I stayed there for almost 3 years. For the last 3 years, I’ve worked with a US company, a financial firm, where I created social media content for Instagram, Facebook, TikTok, and LinkedIn.'],
]);

// ── §15 featured content ─────────────────────────────────────────────────────
// All four posts are telehealth articles on an ecommerce page. Production's selection.
$posts = array_map(fn (array $p): array => ['image' => $img('cards/'.$p[0]), 'title' => $p[1], 'url' => $p[2]], [
    ['How-to-Hire-a-Virtual-Medical-Assistant.png', 'How to Hire a Virtual Medical Assistant (Without Wasting Time or Money)', 'https://remoteleverage.com/blog/how-to-hire-a-virtual-medical-assistant/'],
    ['7.-How-Telehealth-Providers-Are-Using-Virtual-Assistants-to-Reduce-Admin-Burden-and-See-More-Patients.png', 'How Telehealth Virtual Assistants Reduce Admin Burden (Pricing Breakdown for Healthcare Providers)', 'https://remoteleverage.com/blog/telehealth-assistants-reduce-admin/'],
    ['8.-How-to-Hire-Telehealth-Physicians-and-Providers_-A-Guide-for-Healthcare-Operators.png', 'How to Hire Telehealth Physicians and Providers: A Guide for Healthcare Operators', 'https://remoteleverage.com/blog/how-to-hire-telehealth-physicians/'],
    ['13.-How-Much-Does-a-Telehealth-Virtual-Assistant-Cost_-Pricing-Breakdown-for-Healthcare-Providers.png', 'How Much Does a Telehealth Virtual Assistant Cost? (Pricing Breakdown for Healthcare Providers)', 'https://remoteleverage.com/blog/telehealth-virtual-assistant-cost/'],
]);
?>

<!-- ============ §1 HERO ============ -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"0","bottom":"0"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:0;padding-bottom:0">

    <?= BlockDefaults::renderEcom('partner-hero', [
        ['text' => 'No Contracts'],
        ['text' => 'Interview Before You Hire'],
        ['text' => 'No Recurring Fees'],
        ['text' => '30% Discount on Future Hires'],
        ['text' => 'Hire Direct, No Middleman'],
        ['text' => 'Get Matched Within 48 Hour'],
    ], [
        'tone' => 'light',
        'headline' => 'Ecommerce Virtual Assistants $6-10 Per Hour for Most Roles',
        'backdrop_image' => $img('hero/Group-306-1.png'),
        'cta_text' => 'BOOK A CONSULTATION',
        'cta_url' => '#booking-footer',
        'show_talent' => '0',
    ], ['align' => 'full']) ?>

</div>
<!-- /wp:group -->

<!-- ============ §1b TALENT MARQUEE ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pb-10 lg:pb-14" style="background-color:var(--color-bg-light);">
    <?= BlockDefaults::renderEcom('talent-marquee', $marquee, [], ['align' => 'full']) ?>
</div>
<!-- /wp:group -->

<!-- ============ §1c TRUSTED BY ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pb-12 lg:pb-16" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <p class="text-center text-[13px] font-bold uppercase tracking-[0.12em] text-black/60 mb-6">Trusted by scaling teams globally</p>
    </div>
    <?= BlockDefaults::renderEcom('client-logos-marquee', $trustedLogos) ?>
</div>
<!-- /wp:group -->

<!-- ============ §2 WORLD'S BEST ECOMMERCE TALENT ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background py-14 lg:py-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-black text-center mx-auto mb-4 max-w-[620px]">World’s Best Ecommerce Talent, Hired Directly for You</h2>
        <p class="<?= $lead ?> text-black text-center mx-auto mb-10 max-w-[620px]">You hire talent directly into your business. No subscriptions, no monthly fees, and no markups on salary. Just deep-vetted professionals who know online retail workflows and support your store with the precision and reliability that drives repeat orders.</p>

        <?= BlockDefaults::renderFeatureCards('3', [], $talentCards, '411/157') ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §3 SCALE YOUR BRAND SMARTER, FASTER ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull py-14 lg:py-20" style="background-color:#DDE2F6;">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2Big ?> text-black text-center mx-auto mb-4">Scale your brand<br>smarter, faster</h2>
        <p class="<?= $lead ?> text-black text-center mx-auto mb-10 max-w-[700px]">Ecommerce Virtual Assistant” covers a wide range of tasks that can help your brand grow. Tell us which of these looks like your open role and we will shortlist against it specifically.</p>
    </div>

    <?php // A blank headline now genuinely renders no heading (the real 48px centred one is
          // above); the block no longer forces its own default in.?>
    <?= BlockDefaults::renderEcom('roles-pricing-grid', $roleCards, [
        'headline' => '',
        'variant' => 'split-chip',
        'columns' => '2',
    ]) ?>

    <div class="<?= $wrap ?>">
        <a href="#booking-footer" class="<?= $pill ?>">FIND MY NEXT HIRE</a>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §4 MEET OUR ECOMMERCE TALENT ============ -->
<?= BlockDefaults::renderEcom('talent-dossier-carousel', $dossier, [
    'headline' => 'Meet Our Ecommerce Talent',
    'layout' => 'carousel',
], ['align' => 'full']) ?>

<!-- ============ §5 STATS BAND ============ -->
<?= BlockDefaults::renderEcom('stats-band', $stats, ['tone' => 'dark'], ['align' => 'full']) ?>

<!-- ============ §6 BUILT TO HELP YOU GROW ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background py-14 lg:py-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-black text-center mx-auto mb-4">Built to help you grow</h2>
        <p class="<?= $lead ?> text-black text-center mx-auto mb-10 max-w-[620px]">Giving someone the keys to your store is the real hesitation, not the hourly rate. Every candidate in our ecommerce talent pool comes prepared to work inside your platforms with the right permissions and the right habits.<br><strong>We’ve helped thousands of businesses scale faster.</strong></p>

        <?= BlockDefaults::renderFeatureCards('2', [], $growCards, '', 'flush') ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §7 WHY REMOTE LEVERAGE? ============ -->
<?= BlockDefaults::renderEcom('image-card-grid', $whyCards, [
    'headline' => 'Why Remote Leverage?',
    'subheadline' => '',
    'columns' => 4,
    'card_title_size' => 'small',
    'cta_text' => '',
    'cta_url' => '#booking-footer',
], ['align' => 'full']) ?>

<!-- ============ §8 SCALE SMARTER, NOT MORE EXPENSIVE ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pt-14 lg:pt-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-black mb-10">Scale Smarter,<br>Not More Expensive.</h2>

        <?= BlockDefaults::renderDataTable([
            'col_1_header' => 'DIY',
            'col_2_header' => 'Remote Leverage',
        ], $tableRows) ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §9a THE MATH BEHIND 70% SAVINGS ============ -->
<?php
// Production centres this heading trio and puts a real <h3> between the intro and the
// grid. image-card-grid renders its headline/subheadline left-aligned in a 660px column
// and has no field for a sub-heading, so the copy is rendered here and the block is left
// to draw the cards alone. The block wants an alignment option and a sub-heading field.
?>
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pt-14 lg:pt-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2Big ?> text-black text-center mx-auto mb-4 max-w-[680px]">The math behind 70% savings</h2>
        <p class="<?= $lead ?> text-black text-center mx-auto max-w-[800px]">Hire the same talent, with the same experience &ndash; at 70% the cost of a US hire. No recurring fees, no contracts. 100% of the agreed-upon wage goes to your hire.</p>
        <h3 class="<?= $h3 ?> text-black text-center mx-auto mt-10 max-w-[620px]">Hourly Rates for Medical and Healthcare VAs</h3>
    </div>
</div>
<!-- /wp:group -->

<?= BlockDefaults::renderEcom('image-card-grid', $pricingCards, [
    'headline' => '',
    'subheadline' => '',
    'columns' => 3,
    'card_title_size' => 'large',
    'cta_text' => '',
    'cta_url' => '#booking-footer',
], ['align' => 'full']) ?>

<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pb-6" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <p class="text-[15px] leading-[24px] text-black mb-8">For comparison, an in-house ecommerce specialist in the U.S. averages $19.84 an hour before payroll taxes, benefits, and overhead push the real number 25% to 40% higher.</p>
        <p class="<?= $lead ?> text-black">Remote Leverage provides professionals with highly competitive salaries that domestic markets simply can&rsquo;t match</p>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §9b TALENT CAROUSEL ============ -->
<?= BlockDefaults::renderEcom('talent-carousel', $carouselProfiles, [
    'headline' => '',
    'body' => '',
    'cta_text' => '',
    'cta_url' => '#booking-footer',
    'layout' => 'full',
], ['align' => 'full']) ?>

<!-- ============ §10 COMMITTED TO HELPING BUSINESSES GROW ============ -->
<?= BlockDefaults::renderCtaBanner([
    'headline' => 'We’re committed to helping businesses and professionals grow – wherever they are in the world.',
    'subheadline' => '',
    'cta_text' => 'FIND MY NEXT HIRE',
    'cta_url' => '#booking-footer',
    'variant' => 'band',
    'tone' => 'light',
    'globe_image' => $img('guarantee/Group-59-1-e1780958571501.png'),
]) ?>

<!-- ============ §11 12-MONTH REPLACEMENT GUARANTEE ============ -->
<!-- wp:acf/guarantee-card {"name":"acf/guarantee-card","data":{"headline":"12-Month Replacement Guarantee","_headline":"field_guarantee_card_headline","background":"flat-midnight","_background":"field_guarantee_card_background","show_reassurance_items":0,"_show_reassurance_items":"field_guarantee_card_show_reassurance_items","cta_text":"BOOK A CONSULTATION","_cta_text":"field_guarantee_card_cta_text","cta_url":"#booking-footer","_cta_url":"field_guarantee_card_cta_url"},"align":"full","mode":"preview"} /-->

<!-- ============ §12a REAL BUSINESSES, REAL RESULTS ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background py-14 lg:py-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <?= BlockDefaults::renderEcom('results-preview', $resultCards, [
            'section_title' => 'Real Businesses, Real Results',
            'section_desc' => 'Every business needs the same thing – talent that helps them move faster, cut costs, and grow with confidence',
        ]) ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §12b VIDEO TESTIMONIAL ============ -->
<?= BlockDefaults::renderMediaCopy([
    'headline' => '“It wasn’t about paying less for an employee – it was really about finding someone with work ethic.”',
    'body' => '<p>Stacy Do, Indoor Air Programs, Cincinnati, Ohio</p>',
    'video_url' => 'https://remoteleverage.com/wp-content/uploads/2026/08/5-minute-VSL_Horizontal_V01.mp4',
    'image' => '',
    'image_position' => 'left',
    'image_max_width' => 629,
    'cta_text' => '',
    'cta_url' => '#booking-footer',
    'text_align' => 'left',
    'tone' => 'light',
]) ?>

<!-- ============ §13 SEE WHAT OTHER CLIENTS ARE SAYING ============ -->
<?= BlockDefaults::renderCtaBanner([
    'headline' => 'See what other clients are saying',
    'subheadline' => '',
    'cta_text' => 'READ MORE REVIEWS',
    'cta_url' => '#',
    'variant' => 'band',
    'align' => 'split',
    'gradient_start' => '#250D4A',
    'gradient_end' => '#581FB0',
]) ?>

<!-- ============ §14 MEET OUR TALENT ============ -->
<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull pt-14 lg:pt-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-black text-center mx-auto mb-4">Meet Our Talent</h2>
        <p class="<?= $lead ?> text-black text-center mx-auto max-w-[620px]">All of our talent is rigorously vetted, particularly for English fluency. Hear real recordings of Remote Leverage talent.</p>
    </div>
</div>
<!-- /wp:group -->

<?= BlockDefaults::renderEcom('sample-applicant-videos', $videoCards, ['headline' => ''], ['align' => 'full']) ?>

<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull pb-14 lg:pb-20" style="background-color:#F4F6FC;">
    <div class="<?= $wrap ?>">
        <a href="#booking-footer" class="<?= $pill ?>">FIND MY NEXT HIRE</a>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §15 FEATURED CONTENT ============ -->
<?= BlockDefaults::renderEcom('featured-posts', $posts, [
    'headline' => 'Featured Content',
    'subheadline' => 'Latest Posts',
    'tone' => 'dark',
    'source' => 'manual',
    'layout' => 'carousel',
], ['align' => 'full']) ?>

<!-- ============ §16 FAQ ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background py-14 lg:py-20" style="background-color:var(--color-bg-light);">
    <?= BlockDefaults::renderAccordionFaq(['headline' => 'Frequently Asked Questions']) ?>
</div>
<!-- /wp:group -->

<!-- ============ §17 BOOKING FOOTER ============ -->
<!-- wp:acf/booking-footer {"name":"acf/booking-footer","data":{"headline":"Ready to scale your global team?","_headline":"field_booking_footer_headline","description":"During this meeting we will go over the role you’re planning to hire for, what the process looks like, answer any questions you have, and proceed to next steps.","_description":"field_booking_footer_description"},"align":"full","mode":"preview"} /-->
