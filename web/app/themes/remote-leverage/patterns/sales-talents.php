<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Hire Power Dialers from LATAM
 * Slug: remote-leverage/sales-talents
 * Categories: remote-leverage
 * Description: /sales-talents/ — the SDR / appointment-setter landing page, 10 sections.
 *
 * Migrated 1:1 from production (2026-09-15). This is a genuine standalone landing page for the
 * appointment-setter vertical, NOT a variant of the hire-va template — it has its own hero,
 * its own vetting band and its own "How We Work" timeline.
 *
 * Production content bugs reproduced deliberately — do not "fix" them in passing:
 *   - §2 the section sells appointment setters but every dossier card is a MEDICAL role
 *     (Telehealth Provider, Medical Scribe, Patient Intake Coordinator…) with medical tools.
 *     That is what production ships; the copy is transcribed, not invented.
 *   - §4 the heading and subheading are painted WHITE on production's #F2F1ED card, so they
 *     are all but invisible. Reproduced as-is — the fix belongs on production, not here.
 *   - §4 the tool pill reads "PandoDoc" (the asset is PandaDoc.png).
 *   - §5 three of the four vetting cards repeat the *communication* description verbatim.
 *   - §6 step 2 talks about "high-volume cold callers" on an appointment-setter page.
 *
 * Design tokens read off production at 1440px with getComputedStyle:
 *   surface #F4F6FC · container 1320px · Inter Display
 *   h1 46/53 700 -1.44px · h2 42/48 700 -1.26/-1.44px · reviews h2 48/57.6 600
 *   card h3 20/24 700 -0.45px · card body 15/20 · lead 20/25-30
 *   hero card #FFF r10 p30 shadow 0 4px 150px rgba(138,43,226,.3) · hero CTA #F8248A
 *   tick #00D982 · pill CTA #8A2BE2 r100 17/34 · step numeral #8A2BE2 45x44 r50
 *   stack card #F2F1ED r32 · deposit card linear-gradient(90deg,#250D4A 50%,#8A2BE2 100%) r20
 *   booking footer #250D4A
 *
 * Production serves the hero's single business-email step and the footer booking form as three
 * self-posting Gravity Forms (gform_30 / gform_25 / gform_20, all posting to /sales-talents/).
 * This theme's equivalent is the Livewire booking wizard, rendered by acf/booking-footer below.
 */
$img = fn (string $file): string => BlockDefaults::preferWebp(
    BlockDefaults::pageImg('sales-talents', $file)
);

$wrap = 'w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8';
$h1 = 'font-display font-bold text-[34px] leading-[40px] sm:text-[46px] sm:leading-[53px] tracking-[-1.44px]';
$h2 = 'font-display font-bold text-[32px] leading-[38px] sm:text-[42px] sm:leading-[48px] tracking-[-1.26px]';
$h2Reviews = 'font-display font-semibold text-[34px] leading-[42px] sm:text-[48px] sm:leading-[57.6px] tracking-[-1.44px]';
$lead = 'text-[17px] leading-[26px] sm:text-[20px] sm:leading-[25px]';
$leadWide = 'text-[17px] leading-[27px] sm:text-[20px] sm:leading-[30px]';
$pill = 'inline-flex items-center justify-center rounded-pill bg-brand-purple hover:bg-brand-purple-deep px-[34px] py-[17px] text-center text-[17px] font-bold leading-[17px] text-white transition-colors';

// Production's green tick, transcribed from the live DOM (15x15, #00D982 disc + white check).
$tick = '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" class="mt-[3px] shrink-0" aria-hidden="true"><circle cx="7.5" cy="7.5" r="7.5" fill="#00D982"/><path d="M4 7.6 6.4 10 11 5.3" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$star = '<svg width="15" height="14" viewBox="0 0 15 14" fill="#FDD764" aria-hidden="true"><path d="M7.11238 0L9.22229 4.57429L14.2247 5.16741L10.5263 8.58759L11.508 13.5285L7.11238 11.0679L2.71672 13.5285L3.69846 8.58759L0 5.16741L5.00247 4.57429L7.11238 0Z"/></svg>';

// ── §1 hero ──────────────────────────────────────────────────────────────────
$heroChecks = [
    'Hire direct, No intermediary',
    '12-month replacement guarantee',
    'No contract, No recurring fee',
    '30% off future hires',
];

// ── §1b trusted-by strip ─────────────────────────────────────────────────────
// Alt text is production's own — the uploads were never given real names.
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

// ── §2 dossier cards ─────────────────────────────────────────────────────────
// Keys match the acf/talent-dossier-carousel repeater; `tools` is a nested repeater, which
// encodeRepeater() handles. Medical roles on an SDR page are production's, not a slip here.
$tool = fn (string $file, string $alt): array => ['src' => $img('tools/'.$file), 'alt' => $alt];
$dossier = array_map(fn (array $c): array => [
    'photo' => $img('talent/'.$c[0]), 'flag' => $img('flags/'.$c[1]), 'country' => $c[2],
    'rate' => $c[3], 'name' => $c[4], 'role' => $c[5], 'years' => $c[6],
    'experience' => $c[7], 'skills' => $c[8], 'previous_companies' => $c[9], 'tools' => $c[10],
], [
    ['Andres-V.png', 'costa.svg', 'Costa Rica', '$14/hour', 'Andrés Villalobos', 'Telehealth Provider', '6',
        'Delivered virtual general physical consultations and follow-up care for a U.S. direct primary care telehealth practice.',
        'General physical assessment; virtual patient consultation; treatment planning; e-prescribing; patient education',
        'OpenPath Direct Care, TeleHealth Now',
        [$tool('eclinicalworks.png', 'eClinicalWorks'), $tool('doxy.png', 'Doxy.me'), $tool('dosespot.png', 'DoseSpot'), $tool('zoom.png', 'Zoom'), $tool('google-workspace.png', 'Google Workspace')]],
    ['esteania-P.png', 'Dominican.svg', 'Dominican Republic', '$10/hour', 'Estefanía Peña', 'Medical Scribe', '4',
        'Provided scribing and chart clean-up for a cardiology practice, cutting physician after-hours documentation time significantly.',
        'Real-time charting; medical terminology; chart auditing; HIPAA compliance; EHR navigation',
        'Heartland Cardiology Associates, Metro Health Partners',
        [$tool('epic.png', 'Epic'), $tool('cerner.png', 'Cerner'), $tool('zoom.png', 'Zoom'), $tool('google-workspace.png', 'Google Workspace'), $tool('slack.png', 'Slack')]],
    ['Lucia-F.png', 'argentina.svg', 'Argentina', '$11/hour', 'Lucía Fernández', 'Patient Intake Coordinator', '5',
        'Owned front-office intake and insurance verification for a pediatric practice, coordinating pre- and post-consultation admin for 200+ patients monthly.',
        'Insurance verification; patient records; pre/post-consultation admin; scheduling; charting assistance',
        'Sunny Days Pediatrics, GreenLeaf Family Health',
        [$tool('athena.png', 'athenahealth'), $tool('google-workspace.png', 'Google Workspace'), $tool('Availity.png', 'Availity'), $tool('calwndly.png', 'Calendly'), $tool('ringCentral.png', 'RingCentral')]],
    ['Camila-T.jpg', 'Mexico.svg', 'Mexico', '$8/hour', 'Camila Torres', 'Medical Scribe', '3',
        'Supported a 4-provider family medicine practice with real-time visit documentation, chart updates, and post-visit coding handoffs.',
        'Real-time charting; medical terminology; HIPAA compliance; ICD-10 familiarity; note templating',
        'Valley Family Medicine, Sunrise Health Partners',
        [$tool('eclinicalworks.png', 'eClinicalWorks'), $tool('epic.png', 'Epic'), $tool('google-workspace.png', 'Google Workspace'), $tool('zoom.png', 'Zoom'), $tool('dr-chrono.png', 'DrChrono')]],
    ['Rafel-D.png', 'colombia.svg', 'Colombia', '$9/hour', 'Rafael Duarte', 'Patient Intake Coordinator', '4',
        'Managed new patient intake, records requests, and pre-authorization paperwork for a multi-location dermatology group.',
        'Insurance preauthorization; patient records management; scheduling; charting assistance; HIPAA compliance',
        'Clearwater Dermatology, MedFirst Clinics',
        [$tool('athena.png', 'athenahealth'), $tool('calwndly.png', 'Calendly'), $tool('google-workspace.png', 'Google Workspace'), $tool('Availity.png', 'Availity'), $tool('slack.png', 'Slack')]],
    ['juliana.png', 'brazil.svg', 'Brazil', '$9/hour', 'Juliana Ferreira', 'Medical Billing Specialist', '5',
        'Handled end-to-end billing and claims follow-up for an orthopedic practice, reducing outstanding A/R by improving denial resolution turnaround.',
        'Medical coding (CPT/ICD-10); claims submission; denial management; payment posting; AR follow-up',
        'Highline Orthopedics, Coastal Physical Therapy Group',
        [$tool('kareo.png', 'Kareo'), $tool('advanced.png', 'AdvancedMD'), $tool('excel.png', 'Excel'), $tool('Availity.png', 'Availity'), $tool('google-workspace.png', 'Google Workspace')]],
    ['Marco-A.png', 'Honduras.svg', 'Honduras', '$9/hour', 'Marco Antonio Reyes', 'Chronic Care Manager', '3',
        'Ran monthly outreach and care-plan check-ins for a panel of 80+ chronic care patients on behalf of a primary care group.',
        'Care plan tracking; patient outreach; care coordination; documentation; motivational communication',
        'Riverside Primary Care, Family Wellness Associates',
        [$tool('eclinicalworks.png', 'eClinicalWorks'), $tool('caresimple.png', 'CareSimple'), $tool('google-workspace.png', 'Google Workspace'), $tool('zoom.png', 'Zoom'), $tool('ringCentral.png', 'RingCentral')]],
    ['Sherika-c.jpg', 'jamaica.svg', 'Jamaica', '$9/hour', 'Sherika Campbell', 'Telehealth Assistant', '2',
        'Coordinated virtual visit logistics for a telehealth-only practice, troubleshooting tech issues and prepping patient files before each session.',
        'Virtual visit coordination; tech support; patient prep; scheduling; post-visit documentation',
        'VirtuCare Health, Bright Path Telemedicine',
        [$tool('doxy.png', 'Doxy.me'), $tool('zoom.png', 'Zoom'), $tool('google-workspace.png', 'Google Workspace'), $tool('calwndly.png', 'Calendly'), $tool('epic.png', 'Epic')]],
]);

// ── §3 pipeline cards + §5 vetting cards ─────────────────────────────────────
// Keys match the acf/feature-cards repeater (img, title, desc).
$card = fn (array $c): array => ['img' => $img($c[0]), 'title' => $c[1], 'desc' => $c[2]];

$pipeline = array_map($card, [
    ['cards/Frame-76-2.jpg', 'Tell Us<br>the Job Position', 'No monthly markups. Pay once, hire your VA directly, and put the savings into growth.'],
    ['cards/Frame-76-3.jpg', 'Interview Exceptional Talents', 'Only top candidates pass our vetting. You get a short list matched to your needs.'],
    ['cards/Frame-1119-1.jpg', 'Free Replacement Guarantee', 'If your VA isn’t a fit in 12 months, we’ll replace them at no extra cost.'],
    ['cards/Frame-76-4.jpg', '30% Discount on Future Hires', 'Returning clients get 30% off every future hire, no exceptions.'],
]);

// Cards 2 and 4 repeat card 1's description verbatim on production.
$vetting = array_map($card, [
    ['vetting/Make_girl_prettier_remove_icon_202608062321-1.png', 'Communication &amp; English', 'Spoken English, written tone, and live-session confidence. If they can’t communicate clearly, they don’t move forward.'],
    ['vetting/image-13.png', 'Tools &amp; Workflow Proficiency', 'Spoken English, written tone, and live-session confidence. If they can’t communicate clearly, they don’t move forward.'],
    ['vetting/Frame-1119.jpg', 'Judgment &amp; Proactivity Screening', 'How they prioritize, escalate, and decide on their own. We screen for critical thinking, not task completion.'],
    ['vetting/26-1.png', 'Reliability &amp; Remote Discipline', 'Spoken English, written tone, and live-session confidence. If they can’t communicate clearly, they don’t move forward.'],
]);

// acf/feature-cards is the right block for both grids, but its `flush` variant types the card
// title at 27px; production's is 20/24 with a 15/20 body. Scoped overrides keep the block.
$cardType = '[&_h3]:text-[20px] [&_h3]:sm:text-[20px] [&_h3]:leading-[24px] [&_h3]:sm:leading-[24px] [&_h3]:tracking-[-0.45px] [&_h3]:mb-[14px] [&_p]:text-[15px] [&_p]:leading-[20px] [&_.px-7]:px-6 [&_.pt-6]:pt-6 [&_.pb-7]:pb-6';

// ── §4 tool stack rows ───────────────────────────────────────────────────────
$stackRows = array_map(
    fn (array $row): array => array_map(fn (array $t): array => ['icon' => $img('stack/'.$t[0]), 'label' => $t[1]], $row),
    [
        [
            ['GoHighLevel.png', 'GoHighLevel'], ['google.png', 'Google Workspace'], ['hubspot.png', 'Hubspot'],
            ['Apollo.png', 'Apollo'], ['Glyph.png', 'Asana'], ['Canva.png', 'Canva'], ['Hootsuite.png', 'Hootsuite'],
            ['calendly.png', 'Calendly'], ['Close-CRM.png', 'Close CRM'], ['DocuSign.png', 'DocuSign'], ['Excel.png', 'Excel'],
        ],
        [
            ['salesforce.png', 'Salesforce'], ['JustCall.png', 'JustCall'], ['linkedin.png', 'LinkedIn Sales Navigator'],
            ['Loom.png', 'Loom'], ['mailchimp.png', 'Mailchimp'], ['Looker.png', 'Looker'], ['notion.png', 'Notion'],
            ['outreach.png', 'Outreach'], ['PandaDoc.png', 'PandoDoc'], ['Zillow.png', 'Zillow'], ['Pipedrive.png', 'Pipedrive'],
        ],
        [
            ['search.jpeg', 'Seamless.ai'], ['slack.png', 'Slack'], ['Zapier.png', 'Zapier'], ['zoom.png', 'Zoom'],
            ['Dotloop.png', 'Dotloop'], ['Gong.png', 'Gong'], ['SimplePractice.png', 'SimplePractice'], ['Clay.png', 'Clay'],
            ['Follow-Up-Boss.png', 'Follow Up Boss'], ['Amplemarket.png', 'Amplemarket'], ['ZoomInfo.png', 'ZoomInfo'], ['Regie.ai_.png', 'Regie.ai'],
        ],
        [
            ['clearbit.svg', 'Clearbit'], ['hightpot.svg', 'Highspot'], ['weflow.jpeg', 'Weflow'],
            ['9Uq8PUcJ5A7Ca6WKU8fA4Co946k.svg', 'Reply'], ['google-analytics.png', 'Google Analytics'], ['lemlist.png', 'Lemlist'],
            ['mixmax.svg', 'Mixmax'], ['ring.png', 'RingCentral'], ['dialpad.jpeg', 'Dialpad'], ['orum.png', 'Orum'], ['chime.png', 'Chime'],
        ],
    ]
);

// ── §6 "How We Work" steps ───────────────────────────────────────────────────
$steps = [
    ['1', 'Start Your Search', '$100 refundable deposit to begin. Deducted from your final fee, or refunded if you don’t hire.'],
    ['2', 'Review Top Candidates', 'Within 24 hours, we produce a focused shortlist of high-volume cold callers, screened for outbound performance, CRM proficiency, and U.S. English communication.'],
    ['3', 'Interview Your Favorites', 'We coordinate a single session so you can meet all top candidates back-to-back and compare easily.'],
    ['4', 'Hire and Pay', 'Once your candidate accepts, you pay a one-time placement fee. They become your direct employee with zero ongoing markups.'],
];

// ── §7 deposit card ──────────────────────────────────────────────────────────
$whatYouGet = [
    'Candidates ready in 2-3 days',
    'No monthly markups',
    '12-month replacement guarantee',
    'No recurring charges',
    '30% off future hires',
    'No middleman on their pay',
];

// ── §9 FAQ ───────────────────────────────────────────────────────────────────
// Production's own answers, which are longer than the theme's shared defaults —
// passed explicitly so the migrated page reads exactly like the live one.
$faqs = [
    ['What countries do you hire from?', '<p>We focus on four key regions:</p><ul class="list-disc pl-5 mt-2 space-y-1"><li>Latin America and The Caribbean</li><li>The Philippines</li><li>South Africa</li><li>Egypt</li></ul><p class="mt-3">Our Latin American Virtual Assistants are especially popular with US businesses, thanks to their:</p><ul class="list-disc pl-5 mt-2 space-y-1"><li>Exceptional English fluency with minimal accents</li><li>Strong cultural alignment with US business practices</li><li>Convenient time zone overlap with North America</li></ul><p class="mt-3">We’ll guide you on which region best suits your specific needs, but the final choice is always yours.</p>'],
    ['How do taxes &amp; payroll work when hiring Virtual Assistants?', '<p>Our partner company takes care of all payroll and compliance requirements for your Virtual Assistant.</p><p class="mt-2">This means you can focus on growing your business while they handle:</p><ul class="list-disc pl-5 mt-2 space-y-1"><li>Tax compliance</li><li>Payroll processing</li><li>Legal requirements</li><li>International payment regulations</li></ul><p class="mt-3">It’s a simple, worry-free solution that ensures everything is managed properly and legally.</p>'],
    ['How do you get paid?', '<p>It’s simple – we charge a one-time flat fee, but only after you’ve found your perfect match.</p><p class="mt-2">Whatever hourly pay you decide to pay goes directly to the Virtual Assistant you hire.</p>'],
    ['What\'s the difference between Staffing and Recruiting Agencies?', '<p>Staffing agencies charge monthly fees but only pay a small portion to Virtual Assistants. This often results in lower quality talent, as skilled VAs avoid arrangements where agencies keep a large chunk of their earnings.</p><p class="mt-2">At Remote Leverage, we charge just one flat fee after you hire. Your Virtual Assistant receives 100% of what you pay them directly. This attracts higher-quality talent and eliminates ongoing middleman costs, saving you money while getting better results.</p>'],
    ['What if I have questions and need help after hiring?', '<p>After hiring your Virtual Assistant, you’ll have access to a dedicated Customer Success Manager who will help ensure your success.</p><p class="mt-2">They’re here to assist with:</p><ul class="list-disc pl-5 mt-2 space-y-1"><li>Reviewing performance</li><li>Monitoring progress</li><li>Training guidance</li></ul><p class="mt-3">Any other questions or requests</p>'],
    ['What if they don\'t turn out to be a good fit?', '<p>While it’s rare to have issues since candidates are thoroughly vetted by both our team and you, we understand the importance of finding the right fit. That’s why we offer:</p><ul class="list-disc pl-5 mt-2 space-y-1"><li>12-month replacement guarantee at no extra cost</li><li>Unlimited candidate interviews to ensure you find the best match</li></ul><p class="mt-3">This double-screening process (by us and you) helps ensure quality matches prior to hiring an applicant, and our guarantee gives you extra peace of mind that you’ll find the right Virtual Assistant for your business.</p>'],
    ['How is their English and Communication skills?', '<p>We maintain extremely high standards for English fluency. Here’s how we ensure this:</p><ul class="list-disc pl-5 mt-2 space-y-1"><li>All candidates must submit an English voice recording</li><li>We review hundreds of applications daily, and we only select those with fluent English and minimal accents</li><li>Only the best communicators make it through our screening</li></ul><p class="mt-3">This strict vetting process for language skills means you’ll work with a Virtual Assistant who communicates clearly and professionally from day one.</p>'],
    ['Can I start with Part-time?', '<p>Yes, you can start with either part-time or full-time.</p><p class="mt-2">The minimum is 20 hours per week, as our most qualified Virtual Assistants prefer stable positions with consistent hours.</p>'],
    ['What time zone will they be working in?', '<p>Your Virtual Assistant will work according to your schedule and time zone.</p><p class="mt-2">They’re accustomed to US hours, and you get to set the working hours that best fit your needs.</p>'],
    ['How much does the average Virtual Assistant cost?', '<p>Virtual Assistant’s hourly rates depend on the skills and experience they have, and also the region you’re hiring from.</p><ul class="list-disc pl-5 mt-2 space-y-1"><li>Entry Level: $6-$10 per hour</li><li>Highly Experienced: $11-$15 per hour</li></ul><p class="mt-3">The hourly rate you agree to pay goes directly to your Virtual Assistant – we don’t take any fees from their pay.</p>'],
];
$faqRows = array_map(fn (array $f): array => ['question' => $f[0], 'answer' => $f[1]], $faqs);

// ── §10 booking footer ───────────────────────────────────────────────────────
// acf/booking-footer renders `description` raw, so production's reassurance pill and tick list
// ride along inside it rather than forcing a variant onto a block six other pages share.
$footerNote = '<span class="mt-7 block max-w-[619px] space-y-2.5">'
    .implode('', array_map(
        fn (string $i): string => '<span class="flex items-start gap-3 text-[16px] leading-[24px] text-white">'.$tick.'<span>'.$i.'</span></span>',
        ['Hire direct, No intermediary', 'No contract, No recurring fee', '12-month replacement guarantee', '30% off future hires'],
    ))
    .'</span>'
    .'<span class="mt-7 block max-w-[619px] rounded-[9px] bg-[#F2F1ED]/[0.18] px-5 py-5 pl-[17px] text-[15px] leading-[23px] tracking-[-0.46px] text-white">'
    .'This consultation will be done over zoom so it is best if you could be on a computer.</span>';
?>

<!-- ============ §1 HERO ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background py-12 lg:py-[60px]" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <div class="grid grid-cols-1 items-center gap-10 lg:grid-cols-[634px_minmax(0,1fr)] lg:gap-[110px]">

            <div>
                <div class="flex items-center gap-2.5">
                    <img src="<?= esc_url($img('hero/google-logo.png')) ?>" alt="Google" width="63" height="21"
                         loading="eager" decoding="async" class="h-[21px] w-auto" />
                    <span class="text-[15px] font-semibold leading-[15px] text-black">4.8</span>
                    <span class="flex items-center gap-[3px]"><?= str_repeat($star, 5) ?></span>
                </div>
                <p class="mt-1.5 text-[13px] leading-[19px] text-[#333]">235 reviews</p>

                <h1 class="<?= $h1 ?> mt-6 text-black">Fill Your Calendar With Booked Meetings, From $6 to $10/hr</h1>

                <p class="mt-5 max-w-[475px] text-[18px] leading-[26px] text-black">Hire a Latin American appointment setter who books qualified meetings on your calendar through calls, email, and LinkedIn</p>

                <div class="mt-7 grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                    <?php foreach ($heroChecks as $item) { ?>
                        <span class="flex items-start gap-2.5 text-[15px] leading-[20px] text-black"><?= $tick ?><span><?= $item ?></span></span>
                    <?php } ?>
                </div>
            </div>

            <div>
                <div id="consultation" class="w-full rounded-[10px] bg-white p-[30px] shadow-[0_4px_150px_0_rgba(138,43,226,0.3)]">
                    <h2 class="text-center font-display text-[32px] font-semibold leading-[38px] tracking-[-1.44px] text-black sm:text-[42px] sm:leading-[48px]">Book a Free 15-Minute Consultation</h2>

                    <?php /* @bespoke: production isolates the business-email field as step one of a
                             Gravity Form that self-posts back to /sales-talents/. acf/booking exposes
                             only a `skin` field and acf/consult-landing-hero / acf/hire-va-hero are
                             both dark full-band heroes, so neither can paint this light card. This
                             field carries the address to the real wizard in #booking-footer, the same
                             hand-off resources/patterns/va-roles-landing.php already uses. */ ?>
                    <form action="#booking-footer" method="get" class="mt-6">
                        <label for="sales-talents-email" class="block text-[15px] leading-5 text-black">Business Email<span class="text-[#F8248A]" aria-hidden="true">*</span></label>
                        <input id="sales-talents-email" name="email" type="email" autocomplete="email" required
                               class="mt-2 w-full rounded-[4px] border border-[#666] bg-bg-light px-4 py-3.5 text-[16px] leading-6 text-[#1A1A1A] outline-none focus:ring-2 focus:ring-brand-purple/40" />
                        <button type="submit"
                                class="mt-4 flex h-[52px] w-full items-center justify-center gap-2 rounded-pill bg-[#F8248A] px-7 text-[16px] font-bold leading-4 text-white transition-opacity hover:opacity-90">
                            Find me an Assistant
                            <span aria-hidden="true">&rarr;</span>
                        </button>
                    </form>

                    <p class="mt-4 flex items-center justify-center gap-2 text-[15px] font-semibold leading-[20px] tracking-[-0.45px] text-[#D94900]">
                        <img src="<?= esc_url($img('hero/Vector-1.svg')) ?>" alt="" width="13" height="13" loading="lazy" decoding="async" class="h-[13px] w-[13px]" />
                        4 onboarding slots left this week
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §1b TRUSTED BY ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pb-10 lg:pb-14" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <p class="mb-5 text-center text-[11.4px] leading-[17px] tracking-[-0.342px] text-black">TRUSTED BY SCALING TEAMS GLOBALLY</p>
    </div>
    <?= BlockDefaults::renderEcom('client-logos-marquee', $trustedLogos) ?>
</div>
<!-- /wp:group -->

<!-- ============ §2 SETTERS READY TO BOOK FROM DAY 1 ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pt-14 lg:pt-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <h2 class="font-display font-bold text-[34px] leading-[40px] sm:text-[48px] sm:leading-[53px] tracking-[-0.03em] text-black text-center mx-auto max-w-[520px]">Setters Ready To Book <br>From Day 1</h2>
        <p class="<?= $lead ?> mx-auto mt-6 max-w-[800px] text-center text-black">Appointment setter who understands multi-channel booking and supports your team with discipline, persistence, and a focus on booking meetings.</p>
    </div>

    <?php // The heading above is production's centred 48px lockup; the block's own left-aligned
          // header slot stays empty so only its carousel chrome renders. ?>
    <div class="[&>section]:py-0 [&>section]:pt-8">
        <?= BlockDefaults::renderEcom('talent-dossier-carousel', $dossier, [
            'headline' => '',
            'layout' => 'carousel',
        ], ['align' => 'full']) ?>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §3 SALES TALENT THAT FILLS YOUR PIPELINE ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background py-14 lg:py-[70px]" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> mx-auto max-w-[900px] text-center text-black">Sales Talent That Fills Your Pipeline, Hired Directly</h2>
        <p class="<?= $leadWide ?> mx-auto mt-6 max-w-[800px] text-center text-black">No subscriptions, no markups on salary.&nbsp;<br>Just a skilled setter working full-time inside your business.</p>

        <div class="mt-10 <?= $cardType ?>">
            <?= BlockDefaults::renderFeatureCards('4', [], $pipeline, '321/170', 'flush') ?>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §4 HIRE APPOINTMENT SETTERS WHO ALREADY KNOW YOUR STACK ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pb-14 lg:pb-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <div class="overflow-hidden rounded-[32px] bg-[#F2F1ED] pt-[45px] pb-10">
            <div class="px-6 sm:px-10">
                <?php // Production paints both of these WHITE on #F2F1ED. Kept verbatim — see the header note. ?>
                <h2 class="font-display font-bold text-[32px] leading-[38px] sm:text-[42px] sm:leading-[48px] tracking-[-1.44px] text-center text-white">Hire Appointment Setters Who Already Know Your Stack</h2>
                <p class="mt-4 text-center text-[18px] leading-[26px] text-white">Every candidate we place has hands-on experience with the tools and platforms you use.</p>
                <div class="mt-7 text-center">
                    <a href="#consultation" class="<?= $pill ?>">BOOK A CONSULTATION</a>
                </div>
            </div>

            <?php // @bespoke: acf/client-logos-marquee is the theme's only marquee and it renders a
                  // bare logo strip — no label beside each mark, no per-row track, no pill chrome.
                  // These four rows are labelled tool pills scrolling in alternate directions, so
                  // there is no block to reuse; the shared animate-marquee-left/right utilities and
                  // the --mask-marquee-fade token carry the motion and the edge fade. ?>
            <div class="mt-[30px] space-y-3.5 [mask-image:var(--mask-marquee-fade)] [-webkit-mask-image:var(--mask-marquee-fade)]">
                <?php foreach ($stackRows as $rowIndex => $row) { ?>
                    <div class="overflow-hidden">
                        <div class="<?= $rowIndex % 2 === 0 ? 'animate-marquee-left' : 'animate-marquee-right' ?> gap-5">
                            <?php foreach (array_merge($row, $row) as $toolItem) { ?>
                                <span class="flex shrink-0 items-center gap-1.5 rounded-[20px] bg-white px-2.5 py-[5px]">
                                    <img src="<?= esc_url($toolItem['icon']) ?>" alt="" width="24" height="24"
                                         loading="lazy" decoding="async" class="h-6 w-6 shrink-0 object-contain" />
                                    <span class="whitespace-nowrap text-[15px] leading-5 text-black"><?= $toolItem['label'] ?></span>
                                </span>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §5 HOW WE FIND THE TOP 1% TALENT ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pb-14 lg:pb-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2 ?> text-center text-black">How We Find The Top 1% Talent</h2>
        <p class="<?= $leadWide ?> mx-auto mt-6 max-w-[800px] text-center text-black">Our vetting process is built for real work, not resumes. Every candidate is tested on communication, tools, judgment, and reliability before they reach your shortlist.</p>

        <div class="mt-10 <?= $cardType ?>">
            <?= BlockDefaults::renderFeatureCards('4', [], $vetting, '321/203', 'flush') ?>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §6 HOW WE WORK ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pb-14 lg:pb-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <div class="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-16">
            <div>
                <h2 class="<?= $h2 ?> text-black">How We Work</h2>
                <p class="<?= $lead ?> mt-4 max-w-[627px] text-black">A straightforward process with one fee and no ongoing costs, or markups on talent salary.</p>

                <ol class="mt-9">
                    <?php foreach ($steps as $stepIndex => [$num, $title, $text]) { ?>
                        <li class="relative flex gap-[16px] <?= $stepIndex < count($steps) - 1 ? 'pb-[52px]' : '' ?>">
                            <?php if ($stepIndex < count($steps) - 1) { ?>
                                <span class="absolute left-[22px] top-[44px] bottom-0 w-px bg-brand-purple" aria-hidden="true"></span>
                            <?php } ?>
                            <span class="relative z-10 flex h-[44px] w-[45px] shrink-0 items-center justify-center rounded-circle bg-brand-purple text-[20px] font-normal leading-[30px] text-white"><?= $num ?></span>
                            <div class="pt-1.5">
                                <h3 class="font-display text-[20px] font-semibold leading-[25px] tracking-[-0.6px] text-[#101010]"><?= $title ?></h3>
                                <p class="mt-3 max-w-[565px] text-[16px] leading-[22px] text-black"><?= $text ?></p>
                            </div>
                        </li>
                    <?php } ?>
                </ol>
            </div>

            <div class="flex items-start justify-center lg:justify-end">
                <img src="<?= esc_url($img('work/sales-talent.png')) ?>" alt="" width="627" height="713"
                     loading="lazy" decoding="async" class="h-auto w-full max-w-[627px] rounded-[20px]" />
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §7 GET STARTED TODAY ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pb-14 lg:pb-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <div class="grid grid-cols-1 items-center gap-8 rounded-[20px] bg-[linear-gradient(90deg,#250D4A_50%,#8A2BE2_100%)] px-6 py-10 sm:px-[43px] sm:py-[43px] lg:grid-cols-[minmax(0,765px)_minmax(0,1fr)]">
            <div>
                <h2 class="<?= $h2 ?> text-white">Get Started Today</h2>

                <div class="mt-6 flex flex-wrap items-center gap-4">
                    <span class="rounded-[30px] bg-[#00D982] px-3 py-[9px] text-[20px] font-bold leading-[22px] tracking-[-0.25px] text-black">$100</span>
                    <span class="<?= $lead ?> text-white">deposit to begin (applied to your fee)</span>
                </div>

                <p class="<?= $lead ?> mt-8 text-white">What you get:</p>

                <div class="mt-5 grid grid-cols-1 gap-x-8 gap-y-3.5 sm:grid-cols-2">
                    <?php foreach ($whatYouGet as $item) { ?>
                        <span class="flex items-start gap-2.5 text-[16px] leading-[22px] text-white"><?= $tick ?><span><?= $item ?></span></span>
                    <?php } ?>
                </div>

                <div class="mt-9">
                    <a href="#booking-footer" class="<?= $pill ?>">BOOK MY FREE 15-MIN CALL</a>
                </div>
            </div>

            <div class="flex justify-center lg:justify-end">
                <img src="<?= esc_url($img('work/Group-215.png')) ?>" alt="" width="343" height="321"
                     loading="lazy" decoding="async" class="h-auto w-full max-w-[343px]" />
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §8 CLIENT REVIEWS ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pb-14 lg:pb-20" style="background-color:var(--color-bg-light);">
    <div class="<?= $wrap ?>">
        <h2 class="<?= $h2Reviews ?> text-center text-black">Client Reviews</h2>
        <p class="<?= $lead ?> mx-auto mt-6 max-w-[660px] text-center text-black">Don't just take our word for it, hear from business owners who've hired through Remote Leverage. See why quality makes all the difference!</p>

        <div class="mt-10">
            <?= BlockDefaults::renderTestimonials(
                BlockDefaults::withFieldKeys('testimonials_block', ['columns' => '3', 'show_more' => 1, 'visible_count' => 6, 'tone' => 'light'])
            ) ?>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §9 FAQ ============ -->
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pb-14 lg:pb-20 [&_h2]:text-[32px] [&_h2]:sm:text-[42px] [&_h2]:sm:leading-[48px] [&_h2]:tracking-[-1.26px] [&_h2]:mb-10" style="background-color:var(--color-bg-light);">
    <?= BlockDefaults::renderBlockWithRepeater('accordion-faq', 'faqs', 'field_accordion_faq_block_faqs', $faqRows, [
        'headline' => 'Frequently Asked Questions',
        '_headline' => 'field_accordion_faq_block_headline',
    ], ['align' => 'full']) ?>
</div>
<!-- /wp:group -->

<!-- ============ §10 LET'S FIND YOUR SETTER ============ -->
<?= BlockDefaults::patternBlock('booking-footer', [
    'headline' => 'Let\'s find your setter',
    '_headline' => 'field_booking_footer_headline',
    'description' => 'During this meeting we will go over the role you’re planning to hire for, what the process looks like, answer any questions you have, and proceed to next steps.'.$footerNote,
    '_description' => 'field_booking_footer_description',
], ['align' => 'full']) ?>
