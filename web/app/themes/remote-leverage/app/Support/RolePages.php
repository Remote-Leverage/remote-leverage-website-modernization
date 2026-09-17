<?php

namespace App\Support;

/**
 * The fourteen 2026 role landing pages.
 *
 * Every one of them is the same page. The hero, the "World's Best ... Talent" band and the
 * "Why Hire Through Remote Leverage?" banner are structurally identical across all fourteen
 * comps; only the role words and the six task cards change. So the layout lives here once and
 * each page's pattern is a data lookup, rather than fourteen copies of the same markup that
 * drift the moment one of them is edited.
 *
 * The art is shared too: every comp uses the same hero composite and the same globe, which is
 * why they sit in resources/images/pages/role-pages/ rather than under any one role.
 *
 * Copy transcribed from ~/Downloads/Roles pages/*.jpg on 2026-09-16, one comp per role.
 */
class RolePages
{
    /**
     * The hero checklist, shared by all fourteen.
     *
     * Column-major: the comps read down the left desktop column and then down the right, and
     * their mobile column repeats that order. Deliberately not BlockDefaults::homeHeroChecklist(),
     * which is interleaved for the homepage hero's row-flow grid.
     *
     * @return array<int, string>
     */
    public static function checklist(): array
    {
        return [
            'No Contracts, No Ongoing Fees',
            '12-Month Replacement Guarantee',
            'Hire Direct, No Middleman',
            'Interview Before You Hire',
            '30% Discount on Future Hires',
            'Interview in 48 Hours',
        ];
    }

    /** The paragraph under every role's talent heading. Identical in all fourteen comps. */
    public static function talentIntro(): string
    {
        return 'You hire talent directly into your business. No subscriptions, no monthly fees, and no markups on salary. '
            .'<strong>Just deep-vetted professionals who keep your inbox, calendar, and daily operations running without the follow-up.</strong>';
    }

    /**
     * slug => role definition.
     *
     *   role    the words above "Virtual Assistants" in the hero, and the page title
     *   noun    the words between "The World's Best" and "Talent" in the band heading. Usually
     *           the role, but three comps deliberately differ — see the notes on those entries.
     *   cards   the six task cards, titles carrying the comp's own line break.
     *
     * @return array<string, array{role: string, noun: string, cards: array<int, array{title: string, desc: string}>}>
     */
    public static function all(): array
    {
        return [
            'admin-virtual-assistants' => [
                'role' => 'Administrative',
                'noun' => 'Administrative',
                'cards' => [
                    ['title' => 'Inbox &<br>Calendar Management', 'desc' => 'Organizes schedules, manages email, and coordinates meetings so nothing falls through the cracks and your day runs on time.'],
                    ['title' => 'Data Entry &<br>CRM Management', 'desc' => 'Updates records, maintains your CRM, and keeps business data accurate, current, and easy to find.'],
                    ['title' => 'Invoicing &<br>Payment Tracking', 'desc' => 'Prepares invoices, tracks payments, and keeps financial records current and audit-ready.'],
                    ['title' => 'Call &<br>Email Handling', 'desc' => 'Answers calls, responds to routine emails, and manages day-to-day communication with customers and vendors.'],
                    ['title' => 'Document &<br>Records Management', 'desc' => 'Prepares, organizes, and updates spreadsheets, reports, and business files with accuracy and consistency.'],
                    ['title' => 'Custom<br>Support', 'desc' => 'We can provide talent for custom roles to help with anything you need to keep daily operations on track and to grow.'],
                ],
            ],

            'executive-virtual-assistants' => [
                // DEVIATION FROM THE COMP. Comp 02 draws this heading as Administrative, not Executive --
                // it inherited comp 01 heading. Shipped as Executive because the drawn word is plainly a
                // copy-paste slip and would read as a bug on a live Executive VA page. One word to revert.
                'role' => 'Executive',
                'noun' => 'Executive',
                'cards' => [
                    ['title' => 'Calendar &<br>Inbox Management', 'desc' => 'Manages a packed calendar, prioritizes email, and schedules meetings so your time stays protected.'],
                    ['title' => 'Meeting<br>Coordination & Prep', 'desc' => 'Books meetings, preps agendas and briefing notes, and follows up on action items after every call.'],
                    ['title' => 'Travel Planning<br>& Coordination', 'desc' => 'Books flights, hotels, and itineraries, and handles changes so trips run smoothly from start to finish.'],
                    ['title' => 'Document &<br>Reporting Support', 'desc' => 'Prepares reports, decks, and correspondence, keeping every document polished and on brand.'],
                    ['title' => 'Project &<br>Task Coordination', 'desc' => 'Tracks deadlines and deliverables across teams so projects move forward without you having to chase updates.'],
                    ['title' => 'Personal &<br>Executive Support', 'desc' => 'Handles personal scheduling, reminders, and research so you can stay focused on running the business.'],
                ],
            ],

            'customer-support-virtual-assistants' => [
                'role' => 'Customer Support',
                'noun' => 'Customer Support',
                'cards' => [
                    ['title' => 'Customer Support<br>Representative', 'desc' => 'Answers customer questions by phone, chat, and email, and resolves routine issues quickly and professionally.'],
                    ['title' => 'Customer<br>Success Manager', 'desc' => 'Checks in with accounts, tracks customer satisfaction, and flags at-risk customers before they churn.'],
                    ['title' => 'Live Chat &<br>Email Support', 'desc' => 'Staffs live chat and inbox support during business hours so no customer message goes unanswered.'],
                    ['title' => 'Ticket &<br>Escalation Management', 'desc' => 'Manages your support queue, prioritizes tickets, and escalates only what actually needs your attention.'],
                    ['title' => 'Appointment<br>Scheduling', 'desc' => 'Books and confirms customer appointments and follow-up calls so your calendar stays organized.'],
                    ['title' => 'Returns, Refunds &<br>Order Support', 'desc' => 'Processes returns and refunds against your policy and resolves order issues before they become complaints.'],
                ],
            ],

            'sales-virtual-assistants' => [
                'role' => 'Sales',
                'noun' => 'Sales',
                'cards' => [
                    ['title' => 'Account<br>Executive', 'desc' => 'Runs discovery calls, product demos, and sales presentations that move qualified leads toward a signed deal.'],
                    ['title' => 'Account<br>Manager', 'desc' => 'Manages existing client relationships, tracks renewals, and spots upsell opportunities to protect and grow revenue.'],
                    ['title' => 'Cold Outreach &<br>Prospecting', 'desc' => 'Reaches out to new prospects by phone and email to build a steady pipeline of sales opportunities.'],
                    ['title' => 'CRM & Pipeline<br>Management', 'desc' => 'Keeps your CRM updated, logs every touchpoint, and gives you a clear view of where each deal stands.'],
                    ['title' => 'Appointment<br>Setting', 'desc' => 'Books qualified meetings on your calendar so your sales team spends less time chasing and more time closing.'],
                    ['title' => 'Sales Reporting &<br>Process Support', 'desc' => 'Tracks quota progress, builds pipeline reports, and keeps your sales process running on schedule.'],
                ],
            ],

            'lead-generation-virtual-assistants' => [
                'role' => 'Lead Generation',
                'noun' => 'Lead Generation',
                'cards' => [
                    ['title' => 'Sales Development<br>Representative (SDR)', 'desc' => 'Prospects new accounts and runs the first outreach calls and emails that fill your pipeline with qualified leads.'],
                    ['title' => 'Cold Calling &<br>Outbound Prospecting', 'desc' => 'Makes outbound calls to build interest and set the stage for a warm handoff to your sales team.'],
                    ['title' => 'Email & Text<br>Outreach', 'desc' => 'Runs outbound email and text campaigns designed to generate replies and book meetings.'],
                    ['title' => 'CRM & Data<br>Specialist', 'desc' => 'Builds and cleans prospect lists, manages data hygiene, and keeps lead records accurate in your CRM.'],
                    ['title' => 'Lead<br>Qualification', 'desc' => 'Vets inbound and outbound leads against your criteria so your sales team only gets leads worth their time.'],
                    ['title' => 'Appointment<br>Setting', 'desc' => 'Books qualified meetings directly onto your calendar so your pipeline stays full.'],
                ],
            ],

            'social-media-virtual-assistants' => [
                'role' => 'Social Media',
                'noun' => 'Social Media',
                'cards' => [
                    ['title' => 'Social Media<br>Manager', 'desc' => 'Owns your content calendar, posting schedule, and day-to-day account management across platforms.'],
                    ['title' => 'Content Calendar<br>& Scheduling', 'desc' => 'Plans and schedules posts in advance using a content calendar that keeps every channel consistent.'],
                    ['title' => 'Short-Form<br>Video & Reels', 'desc' => 'Edits Reels, TikToks, and short-form video content that keeps your feed active and on-brand.'],
                    ['title' => 'Community<br>Engagement & Replies', 'desc' => 'Responds to comments and DMs and keeps your audience engaged between posts.'],
                    ['title' => 'Content Research<br>& Trend Tracking', 'desc' => 'Researches trending audio, hashtags, and content ideas to keep your strategy current.'],
                    ['title' => 'Performance Reporting<br>& Analytics', 'desc' => 'Tracks follower growth, engagement, and reach so you know what is actually working.'],
                ],
            ],

            'marketing-virtual-assistants' => [
                'role' => 'Marketing',
                'noun' => 'Marketing',
                'cards' => [
                    ['title' => 'Marketing<br>Specialist', 'desc' => 'Executes campaigns across email, content, and social to keep your marketing calendar on track.'],
                    ['title' => 'Paid<br>Ads Manager', 'desc' => 'Builds, runs, and optimizes paid social and search ad campaigns to lower cost per lead.'],
                    ['title' => 'Web<br>Development', 'desc' => 'Builds and updates landing pages designed to convert traffic into leads and customers.'],
                    ['title' => 'SEO & Content<br>Optimization', 'desc' => 'Optimizes pages, titles, and content for search so your site ranks and drives organic traffic.'],
                    ['title' => 'Email<br>Marketing Campaigns', 'desc' => 'Builds and sends email campaigns and nurture sequences that keep leads warm and customers engaged.'],
                    ['title' => 'Ad Creative<br>& Copywriting', 'desc' => 'Writes ad copy and builds supporting graphics for campaigns across social and search.'],
                ],
            ],

            'graphic-design-virtual-assistants' => [
                // Video Editor is the only single-line card title in the whole set.
                'role' => 'Graphic Design',
                'noun' => 'Graphic Design',
                'cards' => [
                    ['title' => 'Logo & Brand<br>Identity Design', 'desc' => 'Designs logos and brand identity systems that give your business a consistent, professional look.'],
                    ['title' => 'Social<br>Media Graphics', 'desc' => 'Creates on-brand graphics and templates for every platform, sized and ready to post.'],
                    ['title' => 'Web<br>Designer', 'desc' => 'Designs website graphics, banners, and landing page visuals that match your brand and convert visitors.'],
                    ['title' => 'Marketing Materials<br>& Flyers', 'desc' => 'Designs flyers, brochures, and print or digital marketing materials that support your campaigns.'],
                    ['title' => 'Presentation<br>& Pitch Deck Design', 'desc' => 'Builds polished presentation and pitch deck designs for sales, investors, or internal use.'],
                    ['title' => 'Video Editor', 'desc' => 'Edits promotional videos, ads, and motion graphics, plus thumbnails, that extend your brand beyond static design.'],
                ],
            ],

            'medical-virtual-assistants' => [
                // The band heading reads Healthcare while the hero reads Medical. Both verified against the
                // pixels and both kept, unlike the Executive slip above: these two words say different things
                // on purpose, the page selling into healthcare practices more broadly than the role title.
                'role' => 'Medical',
                'noun' => 'Healthcare',
                'cards' => [
                    ['title' => 'Medical<br>Scribe', 'desc' => 'Handles real-time documentation in EHR systems, appointment setting, and aftercare follow-up with pharmacies, labs, and more.'],
                    ['title' => 'Patient Intake<br>Coordinator', 'desc' => 'Manages patient records, charting assistance, and pre- and post-consultation admin, including insurance preauthorization.'],
                    ['title' => 'Medical<br>Billing Specialist', 'desc' => 'Manages billing, claims, and payment tracking to keep your practice’s revenue cycle running smoothly.'],
                    ['title' => 'Chronic<br>Care Manager', 'desc' => 'Coordinates ongoing patient outreach, care plan tracking, and follow-up for patients managing long-term conditions.'],
                    ['title' => 'Telehealth<br>Assistant', 'desc' => 'Supports virtual visit logistics, from scheduling and tech troubleshooting to patient prep and post-visit documentation.'],
                    ['title' => 'Telehealth<br>Provider', 'desc' => 'A licensed physician delivering remote general physical consultations and care directly to your patients.'],
                ],
            ],

            'legal-virtual-assistants' => [
                // Band heading reads Legal Support, hero reads Legal. Kept as drawn, as with Medical.
                'role' => 'Legal',
                'noun' => 'Legal Support',
                'cards' => [
                    ['title' => 'Legal<br>Assistant', 'desc' => 'Handles day-to-day administrative support, correspondence, and scheduling so your practice runs smoothly.'],
                    ['title' => 'Legal<br>Case Manager', 'desc' => 'Tracks case status, deadlines, and documentation so nothing slips through the cracks.'],
                    ['title' => 'Legal Research<br>& Case Prep', 'desc' => 'Researches case law, statutes, and precedent to support attorneys preparing for filings and hearings.'],
                    ['title' => 'Client Intake<br>& Scheduling', 'desc' => 'Manages new client intake, consultation scheduling, and follow-up communication.'],
                    ['title' => 'Billing<br>& Invoicing', 'desc' => 'Tracks billable hours, prepares invoices, and follows up on outstanding client payments.'],
                    ['title' => 'Document<br>Drafting & Filing', 'desc' => 'Drafts routine legal documents, organizes case files, and manages court filing deadlines.'],
                ],
            ],

            'insurance-virtual-assistants' => [
                'role' => 'Insurance',
                'noun' => 'Insurance',
                'cards' => [
                    ['title' => 'Claims Processing<br>& Follow-Up', 'desc' => 'Files and tracks claims, follows up on status, and keeps clients updated from start to resolution.'],
                    ['title' => 'Policy<br>Application Handling', 'desc' => 'Processes new policy applications, verifies documentation, and moves files through underwriting.'],
                    ['title' => 'Document Review<br>& Data Entry', 'desc' => 'Reviews policy documents for accuracy and keeps client and policy records up to date.'],
                    ['title' => 'Client Support<br>& Outreach', 'desc' => 'Answers client questions, returns calls, and handles day-to-day policyholder communication.'],
                    ['title' => 'Renewals<br>& Follow-Up', 'desc' => 'Tracks upcoming renewals and follows up with clients before policies lapse.'],
                    ['title' => 'Quoting & Policy<br>Comparisons', 'desc' => 'Prepares quotes and side-by-side policy comparisons to help clients choose the right coverage.'],
                ],
            ],

            'real-estate-virtual-assistants' => [
                'role' => 'Real Estate',
                'noun' => 'Real Estate',
                'cards' => [
                    ['title' => 'Transaction<br>Coordinator', 'desc' => 'Manages contracts, deadlines, and paperwork from executed offer to closing so nothing gets missed.'],
                    ['title' => 'Lead Generation<br>& Cold Calling', 'desc' => 'Prospects new buyer and seller leads through outbound calls and outreach.'],
                    ['title' => 'Lead Follow-Up<br>& Nurturing', 'desc' => 'Follows up with leads consistently so no opportunity goes cold before it converts.'],
                    ['title' => 'Appointment Setting<br>& Showing Coordination', 'desc' => 'Books showings, consultations, and appointments, and keeps your calendar organized.'],
                    ['title' => 'MLS & Listing<br>Management', 'desc' => 'Enters and updates MLS listings, syndicates them across sites, and keeps listing details accurate.'],
                    ['title' => 'Social Media &<br>Marketing Support', 'desc' => 'Manages social content and marketing materials that keep your listings and brand visible.'],
                ],
            ],

            'ecommerce-virtual-assistants' => [
                // ECommerce with the internal capital is how both the hero and the heading are drawn.
                'role' => 'ECommerce',
                'noun' => 'ECommerce',
                'cards' => [
                    ['title' => 'Order<br>Processing', 'desc' => 'Confirm orders, flag payment issues, generate labels, coordinate with your 3PL, and chase stuck shipments before the customer emails you.'],
                    ['title' => 'Catalog &<br>Listings', 'desc' => 'Create and update product listings, standardize titles and descriptions, manage variants and SKUs, and keep Shopify, Amazon, Walmart, and eBay in sync.'],
                    ['title' => 'Inventory<br>Tracking', 'desc' => 'Reconcile stock daily, monitor reorder points, flag slow movers, and run the supplier follow-up loop so ecommerce inventory management stops living in your head.'],
                    ['title' => 'Customer Support<br>& Returns', 'desc' => 'Staff the help desk, run live chat during business hours, process returns against your policy, and escalate only what actually needs you.'],
                    ['title' => 'Marketing<br>Execution', 'desc' => 'Build email flows, update product pages, compile ad reports, publish content, and keep campaigns shipping on schedule.'],
                    ['title' => 'Custom<br>Support', 'desc' => 'Flexible support across the full range of ecommerce operations, from data cleanup to reporting.'],
                ],
            ],

            'bookkeeping-virtual-assistants' => [
                'role' => 'Bookkeeping',
                'noun' => 'Bookkeeping',
                'cards' => [
                    ['title' => 'Account<br>Reconciliation', 'desc' => 'Reconcile bank, credit card, and payment processor accounts on a set schedule, and flag discrepancies while they are still easy to trace.'],
                    ['title' => 'Daily<br>Transaction Entry', 'desc' => 'Categorize transactions as they land, code expenses consistently, and keep the chart of accounts clean instead of cleaning it up in April.'],
                    ['title' => 'AP/AR<br>Processing', 'desc' => 'Enter and schedule bills, match invoices to purchase orders, run collections follow-up, and keep aging reports current.'],
                    ['title' => 'Payroll<br>Support', 'desc' => 'Process payroll cycles, handle benefit and tax deductions, manage contractor payments, and keep records audit-ready.'],
                    ['title' => 'Monthly<br>Reporting', 'desc' => 'Build the same close package every month. P&amp;L, balance sheet, cash flow, and the variance notes that make it useful.'],
                    ['title' => 'Custom<br>Roles', 'desc' => 'QuickBooks and Xero administration, sales tax filing support, 1099 prep, and cleanup projects.'],
                ],
            ],
        ];
    }

    /** One role, by slug. */
    public static function get(string $slug): array
    {
        $all = self::all();

        if (! isset($all[$slug])) {
            throw new \InvalidArgumentException("No role page registered for '{$slug}'.");
        }

        return $all[$slug];
    }

    /** Every role slug, in the order the navigation lists them. */
    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    /** The page title WordPress stores for a role. */
    public static function title(string $slug): string
    {
        return self::get($slug)['role'].' Virtual Assistants';
    }

    /**
     * The hero: headline, subtitle, checklist, CTA and the shared composite, over the logo strip.
     *
     * Hero and logo strip are one unit filling the first viewport, exactly as the homepage's own
     * first screen is.
     */
    public static function renderHero(string $slug): string
    {
        $role = self::get($slug);

        $data = BlockDefaults::withFieldKeys('home_hero', [
            'media' => 'image',
            'hero_image' => BlockDefaults::pageImg('role-pages', 'hero.webp'),
            'show_rating' => 0,
            'headline' => $role['role']."\nVirtual Assistants",
            'headline_accent' => '$6-$10 Per Hour',
            // All three lines are one ink in the comps; the homepage sets the third in purple.
            'headline_accent_tone' => 'inherit',
            'tick_tone' => 'emerald',
            'cta_text' => 'BOOK A CONSULTATION',
        ]);

        $checklist = array_map(fn ($item) => ['item' => $item], self::checklist());

        $hero = BlockDefaults::renderBlockWithRepeater(
            'home-hero',
            'checklist',
            'field_home_hero_checklist',
            $checklist,
            $data,
            ['align' => 'full'],
        );

        $logos = BlockDefaults::renderClientLogosMarquee();

        return <<<HTML
        <!-- wp:group {"align":"full","className":"rl-home-screen-1 rl-screen flex flex-col","backgroundColor":"bg-light","layout":{"type":"default"}} -->
        <div class="wp-block-group alignfull rl-home-screen-1 rl-screen flex flex-col has-bg-light-background-color has-background">
            {$hero}

            <div class="w-full pb-10">
                {$logos}
            </div>
        </div>
        <!-- /wp:group -->
        HTML;
    }

    /**
     * The "World's Best ... Talent" band: six frosted cards over the mesh gradient.
     *
     * The mesh is NOT the homepage's. Fitting the homepage's three stops against 14,308 clean
     * band pixels sampled off the comp leaves an RMS error of 8.52 (max channel error 33) and
     * fails structurally in three places: the homepage has no right-hand pink at all, where this
     * band's right margin is pink from its top edge down; the homepage peaks its pink at
     * bottom-centre, where this band has a lavender valley there between two pink peaks; and its
     * top violet lobe sits too far right and too broad. The four stops below fit the same pixels
     * at RMS 2.57. Dropping the bottom-left lobe alone takes that to 5.53, so it is load-bearing.
     */
    public static function renderTalent(string $slug): string
    {
        $role = self::get($slug);

        $band = implode(',', [
            'radial-gradient(30% 30% at 65% 0%, #D0BBF8 0%, rgba(208,187,248,0) 70%)',
            'radial-gradient(65% 75% at 45% 0%, #CAD5FA 0%, rgba(202,213,250,0) 75%)',
            'radial-gradient(45% 150% at 75% 100%, #EEC1E9 0%, rgba(238,193,233,0) 100%)',
            'radial-gradient(45% 70% at 25% 100%, #E9CBEC 0%, rgba(233,203,236,0) 75%)',
        ]);

        $heading = esc_html($role['noun']);
        $intro = self::talentIntro();
        $cards = BlockDefaults::renderFeatureCards('3', [], $role['cards'], '', 'flush', 'frosted');
        $bandAttr = esc_attr($band);

        return <<<HTML
        <!-- wp:group {"align":"full","className":"rl-role-talent-band flex flex-col justify-center px-4 sm:px-6 lg:px-8","style":{"spacing":{"padding":{"top":"6.75rem","bottom":"6.5rem"}}},"layout":{"type":"constrained","contentSize":"1380px"}} -->
        <div class="wp-block-group alignfull rl-role-talent-band flex flex-col justify-center px-4 sm:px-6 lg:px-8" style="padding-top:6.75rem;padding-bottom:6.5rem;background-color:#F4F6FC;background-image:{$bandAttr}">
            <!-- wp:heading {"textAlign":"center","level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.02em"},"spacing":{"margin":{"bottom":"1.25rem"}}},"fontSize":"huge"} -->
            <h2 class="wp-block-heading has-text-align-center has-huge-font-size" style="letter-spacing:-0.02em;line-height:1.08;margin-bottom:1.25rem">The World's Best {$heading}<br>Talent, Hired Directly for You</h2>
            <!-- /wp:heading -->

            <!-- wp:paragraph {"align":"center","className":"mx-auto max-w-[820px]","style":{"typography":{"fontSize":"1rem","lineHeight":"1.6"},"spacing":{"margin":{"bottom":"3.25rem"}}}} -->
            <p class="has-text-align-center mx-auto max-w-[820px]" style="font-size:1rem;line-height:1.6;margin-bottom:3.25rem">{$intro}</p>
            <!-- /wp:paragraph -->

            {$cards}
        </div>
        <!-- /wp:group -->
        HTML;
    }
}
