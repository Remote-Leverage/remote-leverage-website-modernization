<?php

/**
 * Title: Full Page - Remote Leverage x Oyster
 * Slug: remote-leverage/remote-leverage-x-oyster
 * Categories: remote-leverage
 * Description: Co-branded Remote Leverage x Oyster landing page, rebuilt section-for-section against production (2026-09-14).
 */
$partner = [
    'name' => 'Oyster',
    'hero_bg' => 'var(--color-brand-purple-deep)',
    'hero_bg_end' => 'var(--color-brand-dark-violet)',
    'deep_bg' => 'var(--color-brand-dark-violet)',
    'final_cta_text' => 'Book a Strategy Sync',

    'lockup' => 'lockup-oyster.svg',
    'lockup_label' => 'Remote Leverage x Oyster',

    'hero_title' => 'Remote Leverage × Oyster',
    'hero_paragraphs' => [
        'Hire the right people globally. Employ and pay them compliantly in 180+ countries.',
        'Oyster gives you the employment infrastructure to hire full-time team members anywhere in the world, without opening a legal entity.',
        'Remote Leverage fills those seats with the top 1% of global talent in as little as 48 hours.',
        'Ready to build your global team?',
    ],
    'hero_cta_text' => 'Book a Strategy Sync',
    'hero_cta_url' => '/vacalendar',

    'stats' => [
        ['icon' => 'ICONS2-1-1.png', 'value' => '70%', 'label' => 'Cost savings vs. U.S. employees'],
        ['icon' => 'ICONS-1-2.png', 'value' => '48 hrs', 'label' => 'To a shortlist of top 1% talent'],
        ['icon' => 'ICONS14-1-1.png', 'value' => '180+', 'label' => 'Countries covered by Oyster'],
        ['icon' => 'ICONS8-1-1.png', 'value' => '100%', 'label' => 'Payroll, benefits, and compliance handled'],
    ],

    'bridge_title' => 'The Bridge Between Employment Infrastructure and Execution.',
    'bridge_paragraphs' => [
        'Remote Leverage makes it seamless to find the high performers who will drive your business forward. Oyster makes sure every one of them is employed, paid, and cared for compliantly – from localized employment agreements and statutory benefits to payroll, expenses, and time off across 180+ countries.',
        'Together, we provide a plug-and-play solution for high-growth firms looking to build teams and operate across borders without the overhead of U.S. salaries, the friction of traditional recruiting, or the risk of non-compliant employment.',
    ],

    'steps' => [
        [
            'title' => 'Tell us your ideal hire',
            'paragraphs' => [
                'Book a 15-minute consultation. Describe the role, skills, and experience you need.',
                'Remote Leverage handles posting, screening, and interviewing candidates on your behalf.',
            ],
        ],
        [
            'title' => 'Meet your top 1% shortlist',
            'paragraphs' => [
                'Within 48 hours, receive 4–6 pre-vetted, English-fluent candidates.',
                'You interview, you choose. No contracts, no payment until we find the right fit.',
            ],
        ],
        [
            'title' => 'We handle pay & compliance',
            'paragraphs' => [
                'Your new hire is onboarded as a full-time employee through Oyster, using Oyster’s local entities in 180+ countries. Localized contracts, statutory benefits, payroll, and tax withholding are handled from day one. No entity required. No surprises.',
            ],
        ],
    ],

    'value_title' => 'Scale smarter, faster, and compliantly.',
    'value_paragraphs' => [
        'Standing up global employment and payroll is only half the equation. The other half – finding specialized talent that can hit the ground running – is where most companies stall. Many lose weeks interviewing low-quality candidates only to overpay by filling the role domestically.',
        'Remote Leverage removes the talent bottleneck. Oyster removes the employment one. Together, we provide the execution layer your business needs to hire fast and operate with confidence anywhere in the world.',
    ],
    'value_cards' => [
        [
            'img' => 'Frame-76-3.png',
            'title' => 'Top-tier Latin American talent',
            'desc' => 'Remote Leverage reviews 2,000+ applicants daily and forwards only the top 1% for U.S. businesses – tight time zone overlap, strong cultural alignment, and fluent English. Only the best make it to your inbox.',
        ],
        [
            'img' => 'Frame-76-4.png',
            'title' => 'Compliant employment in 180+ countries',
            'desc' => 'Oyster acts as the legal employer. Employment agreements, statutory benefits, tax withholding, and local labor requirements are handled through Oyster’s own network of local entities, so you can hire in a new market without incorporating in it.',
        ],
        [
            'img' => 'Frame-76-2-copy.png',
            'title' => 'Direct hire. No ongoing markups.',
            'desc' => 'Most staffing agencies pay their placements a fraction of what they charge you, every single month. Not us. You pay a one-time flat placement fee and hire the professional directly. Every hire comes with a 12-month replacement guarantee at no extra cost.',
        ],
        [
            'img' => 'Frame-76-2.png',
            'title' => 'One platform for payroll and people operations',
            'desc' => 'Your new hire slots straight into Oyster. Run consolidated global payroll, manage expenses, time off, documents, and benefits, and give managers and finance the access levels they need, all from a single dashboard.',
        ],
    ],

    'roles_title' => 'Beyond the “Virtual Assistant”',

    'table_heading' => 'Remote Leverage × Oyster',
    'table_rows' => [
        ['Time to Hire', '4 - 8 weeks', '72 hrs'],
        ['Vetting Quality', 'Hit or miss', 'Top 1% pre-screened'],
        ['Ongoing fees', 'often 15-30% monthly markup', 'One-time flat fee only'],
        ['Payroll & taxes', 'DIY or expensive local lawyer', 'Fully handled by Oyster'],
        ['Compliance risk', 'High - misclassification, local laws', 'Zero - 170+ countries covered'],
        ['Replacement guarantee', 'None', '6-months, no extra costs'],
        ['Centralized reporting', 'Spreadsheets', 'Oyster dashboard'],
    ],

    'shortlist_desc' => 'Within 48 hours, we present you 4–6 candidates. You interview and hire your favorite.',
    'onboarding_desc' => 'Your new hire is onboarded into Oyster as a full-time employee, with contracts, benefits, and payroll handled from day one.',

    'final_title' => 'Ready to build your global team on compliant employment infrastructure?',
    'final_paragraph' => 'Remote Leverage finds the talent. Oyster employs, pays, and supports them. Together, we give you everything you need to hire fast, operate confidently, and scale anywhere in the world – without a single unnecessary complication.',
];

include get_theme_file_path('resources/patterns/partner-landing.php');
