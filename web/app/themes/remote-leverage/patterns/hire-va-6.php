<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Hire VA 6 ($0 to Start)
 * Slug: remote-leverage/hire-va-6
 * Categories: remote-leverage
 * Description: Production's /hire-va-6/ ad variant of /hire-va-4/ — same nine sections, "$0 to Start" hero and the /hire-for-less/ wording of the hiring process.
 *
 * Config only. The markup lives in resources/patterns/hire-va-campaign.php, shared with
 * /hire-va-1st-month-free/.
 */
$variant = [
    // Production markup:
    //   <h1><span class="headline-emphasis">$0 to Start.</span><br> Hire Experienced<br>
    //   Latin American Talent<br> from $6/hr</h1>
    // .headline-emphasis carries no CSS anywhere on production, so it is dropped here.
    'headline' => '$0 to Start.<br>Hire Experienced<br>Latin American Talent<br>from $6/hr',

    // Production renders this as a sibling <p> after the H1. acf/hire-va-hero has no field for
    // it, and app/Blocks/ is off-limits to this migration, so it is appended inside the H1 as a
    // block-level span styled to production's 16px/24px/400. Visually identical; the page's H1
    // text content is longer than production's. A `subheadline` field on HireVaHeroBlock would
    // fix that properly — flagged to the lead agent rather than edited here.
    'subheadline' => 'Meet, assess, and <strong class="font-bold">hire top 1% vetted professionals</strong> without the monthly markups.',

    'process_title' => 'Our Hiring Process',
    'process_intro' => 'Hire top-tier talent in just 48 hours. We screen thousands of applicants daily, so you only meet the top 1%. Move from open role to working team member in days, not weeks.',

    // Identical to /hire-for-less/ on production, down to the step bodies — reuse rather than
    // restate, so a copy fix reaches both. Production sets no <br> in these titles (unlike
    // /hire-va-4/), so the explicit breaks are stripped and the titles wrap naturally.
    'process_steps' => array_map(
        static fn (array $step): array => [...$step, 'title' => str_replace('<br>', ' ', $step['title'])],
        BlockDefaults::hireForLessProcessSteps(),
    ),
];

include get_theme_file_path('resources/patterns/hire-va-campaign.php');
