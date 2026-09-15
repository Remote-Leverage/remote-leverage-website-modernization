<?php

/**
 * Title: Full Page - Hire VA (First Month Free)
 * Slug: remote-leverage/hire-va-1st-month-free
 * Categories: remote-leverage
 * Description: Production's /hire-va-1st-month-free/ ad variant of /hire-va-4/ — same nine sections, first-month-free hero and the "Bridge Between Compliance and Execution" hiring process.
 *
 * Config only. The markup lives in resources/patterns/hire-va-campaign.php, shared with
 * /hire-va-6/, because the two pages differ from each other only in the hero headline and in
 * the hiring-process band.
 */
$variant = [
    // Production markup: <h1>First Month Free<br> Latin American Talent<br> $6-$10 Per Hour</h1>
    'headline' => 'First Month Free<br>Latin American Talent<br>$6-$10 Per Hour',
    'subheadline' => '',

    // Production markup: <h2>The Bridge<br> Between Compliance and Execution</h2>
    'process_title' => 'The Bridge<br>Between Compliance and Execution',
    'process_intro' => 'Remote Leverage makes it seamless to find the high-performers who will drive your business forward. Together with Lano, provide a &#8220;Plug-and-Play&#8221; solution for high-growth firms looking to scale their operations without the overhead of U.S. salaries or the friction of traditional recruiting.',

    // This page words its three steps differently from both /hire-va-4/ and /hire-va-6/, so it
    // cannot reuse BlockDefaults::hireVa4ProcessSteps() — the titles match but the bodies do not.
    'process_steps' => [
        [
            'num' => '01',
            'title' => 'Tell us your<br>ideal hire',
            'desc' => 'Book a 15-minute consultation. Describe the role, skills, and experience you need. Remote Leverage handles posting, screening, and interviewing candidates on your behalf.',
        ],
        [
            'num' => '02',
            'title' => 'Meet your<br>top 1% shortlist',
            'desc' => 'Within 48–72 hours, receive 4–6 pre-vetted, fluent English-speaking candidates. You interview, you choose. No contracts, no commitments — you only pay if you hire.',
        ],
        [
            'num' => '03',
            'title' => 'We handle pay &amp; compliance',
            'desc' => 'You hire your favorite, and they are immediately integrated into your Lano payroll and compliance dashboard. No misclassification risk. No surprises.',
        ],
    ],
];

include get_theme_file_path('resources/patterns/hire-va-campaign.php');
