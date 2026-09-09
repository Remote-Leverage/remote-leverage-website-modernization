<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Services;

/**
 * Default content shown on a partner hub page's tabs when the partner hasn't
 * overridden it (WR-119). Ported from the legacy rl-partners-hub plugin's
 * RL_Global_Data class. Plain PHP, no WordPress dependency, so it stays
 * unit-testable in isolation.
 */
class PartnerHubGlobalData
{
    /**
     * Company Core Values.
     */
    public static function getCoreValues(): array
    {
        return [
            [
                'value' => 'Integrity',
                'meaning' => 'We act ethically and communicate with honesty and transparency with clients, candidates, and partners.',
            ],
            [
                'value' => 'Excellence',
                'meaning' => "We hold a high standard for our work, our systems, and our results. If it's not good enough for us, it's not good enough for our clients.",
            ],
            [
                'value' => 'Partnership',
                'meaning' => 'We treat every client relationship as a long-term partnership, not a transaction.',
            ],
            [
                'value' => 'Speed',
                'meaning' => 'Speed is a competitive advantage for our company and our clients. Clients expect fast results, requiring us to act with urgency, make decisions quickly, and iterate as we go.',
            ],
            [
                'value' => 'Own the Outcome',
                'meaning' => 'We focus on results and personal responsibility to get the job done.',
            ],
        ];
    }

    /**
     * Key Differentiators / Why Choose RL.
     */
    public static function getWhyChooseRl(): array
    {
        return [
            ['title' => 'No Upfront Fees', 'desc' => 'You only pay once you hire the right candidate.'],
            ['title' => 'Direct-Hire Placement Model', 'desc' => 'Clients interview, select, and hire candidates directly. One-time placement fee with zero recurring staffing markups.'],
            ['title' => 'Global Talent Access', 'desc' => 'Connect with thoroughly vetted professionals from high-quality international talent markets.'],
            ['title' => 'Fast Time to Hire', 'desc' => 'Curated candidate shortlists presented quickly; most roles filled within two to four weeks.'],
            ['title' => 'Dedicated Recruiting Specialists', 'desc' => 'Work with dedicated recruiting specialists who understand your industry and technical requirements.'],
            ['title' => 'End-to-End Support', 'desc' => 'We manage sourcing, multi-stage vetting, background checks, and interview scheduling.'],
            ['title' => 'Built for Long-Term Retention', 'desc' => 'Rigorous behavioral and skill assessments help companies make stronger hires who stay longer and perform better.'],
        ];
    }

    /**
     * Comparison Matrix Data vs Job Boards & Traditional Agencies.
     */
    public static function getComparisonMatrix(): array
    {
        return [
            ['feature' => 'Upfront Fees', 'rl' => 'None ($0 upfront)', 'inhouse' => 'None', 'agency' => 'Often required retainers'],
            ['feature' => 'Fee Structure', 'rl' => 'One-time placement fee on hire', 'inhouse' => 'Subscription + internal recruiter salary', 'agency' => 'Recurring markups or high retainers'],
            ['feature' => 'Hiring Model', 'rl' => 'Direct Hire (Client hires directly)', 'inhouse' => 'Direct Hire', 'agency' => 'Staff augmentation / markup'],
            ['feature' => 'Global Talent Access', 'rl' => 'Yes, vetted global network', 'inhouse' => 'Dependent on inbound job posts', 'agency' => 'Limited local network'],
            ['feature' => 'Time to Hire', 'rl' => '2–4 weeks average', 'inhouse' => '8–16 weeks', 'agency' => '4–10 weeks'],
            ['feature' => 'Dedicated Recruiter', 'rl' => 'Yes, assigned per search', 'inhouse' => 'No dedicated support', 'agency' => 'Shared account manager'],
            ['feature' => 'Candidate Vetting', 'rl' => 'Multi-stage, rigorous screening', 'inhouse' => 'Internal team burden', 'agency' => 'Resume forwarding'],
            ['feature' => 'Ongoing Partnership Support', 'rl' => 'Yes, dedicated partner team', 'inhouse' => 'No support', 'agency' => 'Transactional'],
        ];
    }

    /**
     * Target Industries for ICP.
     */
    public static function getTargetIndustries(): array
    {
        return [
            'Professional Service Firms',
            'Agencies (Marketing, Creative, Digital)',
            'Healthcare Practices & Clinics',
            'Law Firms',
            'Accounting Firms',
            'Technology & SaaS',
            'E-commerce',
            'Real Estate',
            'Construction & Contracting',
            'Manufacturing',
            'Logistics & Supply Chain',
            'Financial Services',
            'Insurance',
            'Consulting Firms',
            'Media & Entertainment',
            'Non-profit Organizations',
        ];
    }

    /**
     * Geographic Markets.
     */
    public static function getGeographicMarkets(): array
    {
        return [
            'United States' => 'Our largest market with strong demand across all sectors.',
            'Canada' => 'Strong demand across all provinces and operational functions.',
            'United Kingdom' => 'Growing presence across professional services and tech.',
            'Europe' => 'Particularly Western Europe (Germany, Netherlands, Spain).',
            'Australia & New Zealand' => 'Active market with high adoption of remote staffing.',
        ];
    }

    /**
     * 10 Core Services Breakdown.
     */
    public static function getServices(): array
    {
        return [
            ['key' => 'direct_hire', 'name' => 'Direct-Hire Recruitment', 'best_for' => 'Companies with specific open roles to fill quickly', 'desc' => 'End-to-end recruitment for permanent roles. We handle candidate sourcing, multi-stage vetting, background checks, and interview coordination.'],
            ['key' => 'executive_search', 'name' => 'Executive Search', 'best_for' => 'Senior leadership and C-suite hires', 'desc' => 'Targeted executive search for COOs, CFOs, VPs, and Directors with proven track records in high-growth environments.'],
            ['key' => 'customer_support', 'name' => 'Customer Support Teams', 'best_for' => 'Scaling customer-facing operations', 'desc' => 'Dedicated omnichannel support agents, CSAT managers, and technical helpdesk staff providing 24/7 coverage.'],
            ['key' => 'sales', 'name' => 'Sales & Lead Generation Talent', 'best_for' => 'Building outbound or inside sales capacity', 'desc' => 'High-performing SDRs, BDRs, and Account Executives fluent in outbound prospecting and modern CRM tools.'],
            ['key' => 'administrative', 'name' => 'Administrative Teams', 'best_for' => 'Operational support and executive assistance', 'desc' => 'Executive Assistants, Virtual Assistants, Project Coordinators, and Schedulers who streamline founder and partner focus.'],
            ['key' => 'bookkeeping', 'name' => 'Bookkeeping & Back Office', 'best_for' => 'Finance, accounting, and data operations', 'desc' => 'Vetted bookkeepers, financial analysts, controllers, and back-office data specialists with US GAAP / IFRS experience.'],
            ['key' => 'marketing', 'name' => 'Marketing & Creative Talent', 'best_for' => 'Content, digital, and growth marketing functions', 'desc' => 'SEO specialists, content strategists, graphic designers, video editors, and media buyers who drive growth.'],
            ['key' => 'healthcare', 'name' => 'Healthcare Staffing', 'best_for' => 'Medical practices, clinics, and telehealth companies', 'desc' => 'Medical billers, patient coordinators, intake specialists, and virtual medical assistants trained in HIPAA compliance.'],
            ['key' => 'contractor_mgmt', 'name' => 'Contractor Management', 'best_for' => 'Businesses managing global contractors compliantly', 'desc' => 'Worry-free global compliance, contract management, currency payouts, and local legal risk mitigation.'],
            ['key' => 'global_hiring', 'name' => 'Global Hiring', 'best_for' => 'Companies expanding into international talent markets', 'desc' => 'Turnkey expansion into top talent hubs across Latin America, Eastern Europe, and Asia.'],
        ];
    }

    /**
     * Default Referral Lifecycle Stages (6 Stages).
     */
    public static function getDefaultLifecycleStages(): array
    {
        return [
            ['status' => 'Submitted', 'key' => 'submitted', 'desc' => 'Referral received and logged in system. Partner receives confirmation.'],
            ['status' => 'Contacted', 'key' => 'contacted', 'desc' => 'Our team reaches out to the referred prospect for initial intro.'],
            ['status' => 'Qualified', 'key' => 'qualified', 'desc' => 'Discovery call completed; client has an active hiring need.'],
            ['status' => 'Active Search', 'key' => 'active_search', 'desc' => 'Recruiting process underway; candidate shortlists presented.'],
            ['status' => 'Placement Made', 'key' => 'placement_made', 'desc' => 'Successful hire completed by client; commission triggered.'],
            ['status' => 'Commission Paid', 'key' => 'commission_paid', 'desc' => 'Partner receives commission payment per agreed terms.'],
        ];
    }

    /**
     * Default Referral Rules (Mutual Referral & Direct-Hire Standard).
     */
    public static function getDefaultReferralRules(): array
    {
        return [
            'Referral must be approved by the receiving partner.',
            'Referral cannot already exist as a current/previous customer, prospect, or pending/previous sales opportunity.',
            'Referred company must become a customer within 6 months of referral submission to qualify.',
            'No self-referrals: partners may not refer their own companies or entities where they hold a financial stake.',
            'Commissions are paid within 30 days of placement fee collection per individual Partner Agreement terms.',
            'Multiple referrals earn multiple commissions — there is no annual or total cap.',
        ];
    }

    /**
     * Default Co-Marketing Opportunities & Guidelines.
     */
    public static function getDefaultComarketing(): array
    {
        return [
            'lead_text' => 'Joint content, webinars, educational resources, campaigns, and partner announcements can be added here.',
            'approval_note' => 'All co-marketing activities and materials require mutual approval before publication.',
            'opportunities' => [
                ['title' => 'Joint Webinars & Panels', 'desc' => 'Host educational webinars on scaling remote teams, global hiring compliance, and operational leverage.'],
                ['title' => 'Thought Leadership & Content Collaboration', 'desc' => 'Co-author case studies, whitepapers, and guides distributed to mutual audiences.'],
                ['title' => 'Co-Branded Educational Resources', 'desc' => 'Develop cheat sheets, hiring scorecards, and rate cards tailored for partner clients.'],
                ['title' => 'Social & Newsletter Features', 'desc' => 'Cross-promotions across email newsletters, LinkedIn thought-leadership posts, and community shoutouts.'],
            ],
        ];
    }

    /**
     * 6 Detailed Industry Case Studies.
     */
    public static function getCaseStudies(): array
    {
        return [
            [
                'industry' => 'Healthcare',
                'client' => 'Multi-Location Medical Practice',
                'challenge' => [
                    'A growing medical practice with 5 locations was struggling to hire qualified medical billers and patient coordinators.',
                    'Their in-house HR team was overwhelmed, and local recruiting produced poor-quality candidates with high turnover.',
                    'Revenue cycle was being impacted by severe billing backlogs.',
                ],
                'solution' => [
                    'Remote Leverage sourced and vetted medical billing specialists from a global talent pool.',
                    'Provided a shortlist of 5 qualified candidates within 10 days.',
                    'Facilitated structured interviews and coordinated onboarding.',
                ],
                'outcome' => [
                    '3 medical billers placed within 3 weeks.',
                    '40% reduction in billing backlog within 60 days of onboarding.',
                    'Client expanded engagement to hire 2 additional patient coordinators the following quarter.',
                ],
            ],
            [
                'industry' => 'Professional Services',
                'client' => 'Regional Accounting Firm',
                'challenge' => [
                    'A 30-person accounting firm needed to scale during tax season but could not afford full-time US-based staff for every role.',
                    'Previous offshore attempts produced unvetted candidates with poor communication skills.',
                ],
                'solution' => [
                    'Remote Leverage identified vetted accounting professionals with fluent English and US GAAP experience.',
                    'Delivered 3 qualified candidates within 2 weeks through structured technical assessments.',
                ],
                'outcome' => [
                    '2 remote accountants placed and fully operational within 30 days.',
                    'Firm managed its highest-volume tax season without additional local hires.',
                    'Reduced staffing costs by ~55% compared to local hires.',
                ],
            ],
            [
                'industry' => 'Customer Support',
                'client' => 'Scaling E-Commerce Brand',
                'challenge' => [
                    'An e-commerce brand receiving 1,000+ daily inquiries with only 3 support agents.',
                    'Customer satisfaction (CSAT) scores declined due to 18-hour response times.',
                ],
                'solution' => [
                    'Remote Leverage built a dedicated customer support team of 8 agents in under 30 days.',
                    'Assessed each agent for written English proficiency, problem-solving, and product retention.',
                ],
                'outcome' => [
                    'Average response time reduced from 18 hours to under 2 hours.',
                    'CSAT score improved from 3.4 to 4.7 within 60 days.',
                    'Client retained all 8 placed agents after 12 months.',
                ],
            ],
            [
                'industry' => 'Sales',
                'client' => 'B2B SaaS Startup',
                'challenge' => [
                    'A SaaS startup needed to build a sales development team from scratch for a product launch.',
                    'Founders were spending too much time on cold outbound.',
                    'Budget constraints made US-based SDRs cost-prohibitive.',
                ],
                'solution' => [
                    'Remote Leverage sourced experienced Latin American SDRs with proven SaaS outbound track records.',
                    'Built a 4-person SDR team within 3 weeks with structured onboarding guidance.',
                ],
                'outcome' => [
                    'Team generated 40+ qualified discovery calls in the first month.',
                    'Cost of the SDR team was 60% lower than equivalent US hires.',
                    'Two SDRs promoted to Account Executives within 8 months.',
                ],
            ],
            [
                'industry' => 'Administrative & Legal',
                'client' => 'Boutique Law Firm',
                'challenge' => [
                    'A law firm needed an experienced legal assistant and executive assistant for senior partners.',
                    'Previous hires lacked attention to detail and legal environment professionalism.',
                ],
                'solution' => [
                    'Remote Leverage ran a specialized search focused on legal administrative experience.',
                    'Delivered 4 shortlisted candidates within 2 weeks with full background check support.',
                ],
                'outcome' => [
                    'Both roles filled within 3 weeks.',
                    'Zero onboarding issues reported; seamless integration into partner workflows.',
                    'Partners saved significant weekly administrative hours.',
                ],
            ],
            [
                'industry' => 'Technology',
                'client' => 'Software Development Agency',
                'challenge' => [
                    'A digital agency secured 3 new client contracts and needed to staff up rapidly with senior developers.',
                    'Local developer talent was expensive and agency hiring cycles were too slow.',
                ],
                'solution' => [
                    'Remote Leverage executed technical vetting and code assessments for senior full-stack engineers.',
                ],
                'outcome' => [
                    '3 senior developers placed and project-ready within 4 weeks.',
                    'All client contracts delivered on schedule.',
                    'Agency expanded relationship into a 12-month staffing retainer.',
                ],
            ],
        ];
    }

    /**
     * 14 Partner FAQs.
     */
    public static function getFaqs(): array
    {
        return [
            ['q' => 'Who is the ideal client for Remote Leverage?', 'a' => 'Growth-oriented companies (SMB to Enterprise) in the US, Canada, UK, Europe, or Australia looking to fill specialized, operational, technical, or leadership roles faster and 40–60% more cost-effectively than local traditional recruiting.'],
            ['q' => 'How does the hiring process work?', 'a' => 'Once a client engages us, we define role specifications, source candidates from our global talent pool, run multi-stage technical and cultural vetting, present a curated shortlist within 2–4 weeks, and support interviewing and onboarding.'],
            ['q' => 'How long does hiring take?', 'a' => 'Most positions are filled within 2 to 4 weeks from project kickoff to candidate placement.'],
            ['q' => 'What roles do you recruit?', 'a' => 'We recruit across all business functions: Executive Leadership (COO, CFO), Tech (Software Engineers, QA), Sales (SDRs, AEs), Marketing, Customer Support, Legal Paralegals, Accounting/Finance, Healthcare Billers, and Administrative Assistants.'],
            ['q' => 'How does pricing work?', 'a' => 'Our primary model is performance-based with zero upfront fees. Clients only pay once a candidate is successfully hired and placed.'],
            ['q' => 'When are referral commissions paid?', 'a' => 'Commissions are triggered upon a successful candidate placement made by the referred client, and paid out within 30 days of placement confirmation.'],
            ['q' => 'Can I refer international companies?', 'a' => 'Yes! We actively serve clients headquartered across North America, the UK, Western Europe, and Australia/New Zealand.'],
            ['q' => 'Who owns the client relationship after I make the introduction?', 'a' => 'You maintain your trusted relationship with your referral. Remote Leverage manages the recruitment process and keeps you updated on status.'],
            ['q' => 'Can I introduce multiple companies?', 'a' => 'Absolutely! There is no cap on referrals or commission earnings.'],
            ['q' => 'What if my referral isn’t ready to hire right now?', 'a' => 'We will stay in warm contact and nurture the relationship until they have an active hiring need, keeping your referral attribution intact.'],
            ['q' => 'What happens if a placed candidate doesn’t work out?', 'a' => 'We offer a standard replacement guarantee period to ensure complete client satisfaction and peace of mind.'],
            ['q' => 'Do you work with companies that already have an internal HR team?', 'a' => 'Yes! We often act as an extended talent acquisition arm (RPO) for internal HR teams that need specialized or international recruiting bandwidth.'],
            ['q' => 'What makes Remote Leverage different from a traditional staffing agency?', 'a' => 'No upfront retainers, global talent access, faster time-to-hire (2-4 weeks), dedicated recruiting teams, and flexible long-term retention support.'],
            ['q' => 'Are the candidates you place English-proficient?', 'a' => 'Yes. All candidates undergo rigorous English verbal and written assessments aligned with North American and European business standards.'],
        ];
    }
}
