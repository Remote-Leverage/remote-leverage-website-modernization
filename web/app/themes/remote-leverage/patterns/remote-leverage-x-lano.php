<?php

/**
 * Title: Full Page - Remote Leverage x Lano
 * Slug: remote-leverage/remote-leverage-x-lano
 * Categories: remote-leverage
 * Description: Co-branded Remote Leverage x Lano landing page, rebuilt section-for-section against production (2026-09-14).
 */
$partner = [
    'name' => 'Lano',
    'hero_bg' => 'var(--color-brand-navy)',
    'hero_bg_end' => 'var(--color-brand-midnight)',
    'deep_bg' => 'var(--color-brand-dark-violet)',
    'final_cta_text' => 'Get Started Now',

    'lockup' => 'lockup-lano.svg',
    'lockup_label' => 'Lano x Remote Leverage',

    'hero_title' => 'Lano × Remote Leverage: Your Global Team, Fully Powered.',
    'hero_paragraphs' => [
        'You have the world-class compliance and payroll infrastructure of Lano. Now, fill your seats with the top 1% of specialized LATAM and global talent in as little as 4 days.',
    ],
    'hero_cta_text' => 'Find my next hire',
    'hero_cta_url' => '/vacalendar',

    'stats' => [
        ['icon' => 'ICONS2-1-1.png', 'value' => '70%', 'label' => 'Cost savings vs. U.S. employees'],
        ['icon' => 'ICONS-1-2.png', 'value' => '72 hrs', 'label' => 'Average time to hire'],
        ['icon' => 'ICONS14-1-1.png', 'value' => '170+', 'label' => 'Countries with Lano compliance'],
        ['icon' => 'ICONS8-1-1.png', 'value' => '100%', 'label' => 'Payroll & tax compliance, handled'],
    ],

    'bridge_title' => 'The Bridge Between Compliance and Execution.',
    'bridge_paragraphs' => [
        'Remote Leverage makes it seamless to find the high-performers who will drive your business forward. Together with Lano, provide a “Plug-and-Play” solution for high-growth firms looking to scale their operations without the overhead of U.S. salaries or the friction of traditional recruiting.',
    ],

    'steps' => [
        [
            'title' => 'Tell us your ideal hire',
            'paragraphs' => [
                'Book a 15-minute consultation. Describe the role, skills, and experience you need. Remote Leverage handles posting, screening, and interviewing candidates on your behalf.',
            ],
        ],
        [
            'title' => 'Meet your top 1% shortlist',
            'paragraphs' => [
                'Within 48–72 hours, receive 4–6 pre-vetted, fluent English-speaking candidates. You interview, you choose. No contracts, no commitments — you only pay if you hire.',
            ],
        ],
        [
            'title' => 'We handle pay & compliance',
            'paragraphs' => [
                'You hire your favorite, and they are immediately integrated into your Lano payroll and compliance dashboard. No misclassification risk. No surprises.',
            ],
        ],
    ],

    'value_title' => 'Find specialized talent. Fast.',
    'value_paragraphs' => [
        'Setting up global payroll is only half the battle. The real challenge is finding specialized talent that can hit the ground running. Many companies lose weeks interviewing low-quality candidates only to overpay by filling roles with domestic talent.',
        'Remote Leverage removes the bottleneck. We provide the execution layer your business needs to stay agile.',
    ],
    'value_cards' => [
        [
            'img' => 'Frame-76-3.png',
            'title' => 'Top-tier Latin American talent',
            'desc' => 'Remote Leverage finds the top 1% of talent for US businesses. Tight time zone overlap, strong cultural alignment, and fluent English.',
        ],
        [
            'img' => 'Frame-76-4.png',
            'title' => 'Zero compliance headaches',
            'desc' => 'Lano handles all tax filings, payroll processing, and international payment regulations. Fully compliant across 170+ countries.',
        ],
        [
            'img' => 'Frame-76-2-copy.png',
            'title' => 'No ongoing middleman fees',
            'desc' => 'Hire direct. No contracts, no hidden fees. Remote Leverage’s 6-month replacement guarantee ensures you get the perfect hire.',
        ],
        [
            'img' => 'Frame-76-2.png',
            'title' => 'One dashboard for your entire global team',
            'desc' => 'Your new hire slots right into your Lano dashboard. Manage payroll cycles, compliance reporting, and multi-currency payments in one platform.',
        ],
    ],

    'roles_title' => 'Beyond the “Virtual Assistant.”',

    'table_heading' => 'Remote Leverage × Lano',
    'table_rows' => [
        ['Time to Hire', '4 - 8 weeks', '72 hrs'],
        ['Vetting Quality', 'Hit or miss', 'Top 1% pre-screened'],
        ['Compliance risk', 'High - misclassification, local laws', 'Zero - 170+ countries covered'],
        ['Ongoing fees', 'often 15-30% monthly markup', 'One-time flat fee only'],
        ['Replacement guarantee', 'None', '6-months, no extra costs'],
        ['Centralized reporting', 'Spreadsheets', 'Lano dashboard'],
    ],

    'shortlist_desc' => 'Within 48-72 hours, we present the top 1% of vetted candidates.',
    'onboarding_desc' => 'You hire your favorite, and they are immediately integrated into dashboard.',

    'final_title' => 'Ready to scale your global team?',
    'final_paragraph' => 'Let’s find the specialized talent your business needs!',
];

include get_theme_file_path('resources/patterns/partner-landing.php');
