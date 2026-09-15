<?php

declare(strict_types=1);

/**
 * The strategic partners behind `/partners/` and each co-branded hub.
 *
 * Kept in git for the same reason page content lives in `patterns/` rather than
 * `post_content`: `rl_partner` entries authored only in the database drift from
 * the code and are lost on a database refresh. `wp acorn partners:seed` applies
 * this file; see PartnerSeedCommand.
 *
 * Each entry is `post_name` (the hub URL segment) => title + meta. Meta keys
 * are the `_rl_*` keys PartnerHubFields registers; anything omitted falls back
 * to PartnerHubGlobalData's defaults at render time, so only genuinely
 * partner-specific values belong here.
 */

return [
    'oyster' => [
        'post_title' => 'Remote Leverage × Oyster',
        'meta' => [
            '_rl_partner_name' => 'Oyster',
            '_rl_partner_code' => 'RL-OYSTER',
            '_rl_partner_website' => 'https://oysterhr.com',

            '_rl_directory_category' => 'Software & Tech',
            '_rl_directory_description' => 'Global employment platform handling payroll, benefits and compliance in 180+ countries — so a client can hire the person Remote Leverage found them without opening a local entity.',
            '_rl_directory_perk' => 'Priority onboarding for Remote Leverage referrals',
            '_rl_directory_featured' => '1',

            '_rl_partnership_type' => 'Mutual Referral Partner',
            '_rl_territory' => 'Worldwide',
            '_rl_reporting_period' => 'Quarterly',
            '_rl_initial_term' => '12 months',
            '_rl_renewal_terms' => 'Automatic 12-month renewal, subject to the partnership agreement',

            '_rl_referral_form_url' => 'https://docs.google.com/forms/d/e/1FAIpQLSe4e0rgiazS8snOPcMnIxcCeBJISyAgcYu3WMEZiWhPWHeemQ/viewform',
            '_rl_referral_drive_url' => 'https://docs.google.com/spreadsheets/d/14HoxsY-w_F5sWpvvRTvI-lDAmqUCLk6gYRvkoGjzllA/edit',
            '_rl_intro_email' => 'partnerships@remoteleverage.com',
            '_rl_partner_to_rl_fee' => 'Oyster receives 10% of the net Remote Leverage placement fee actually collected from an eligible referred customer. One-time referral fee, not recurring.',

            '_rl_partner_referral_label' => 'Refer a Client to Oyster',
            // Intentionally unset: Oyster has not yet nominated a referral
            // destination. The hub renders a "pending setup" badge rather than
            // a dead mailto. Do not invent an address here.
            '_rl_partner_referral_email' => 'pending to define',
            '_rl_rl_to_partner_fee' => 'Remote Leverage receives 10% of eligible net subscription fees actually collected by Oyster from an eligible referred customer, up to 12 months.',

            '_rl_rl_resource_title' => 'Remote Leverage Partner Resources',
            '_rl_rl_resource_url' => 'https://drive.google.com/file/d/1Jl907jZo1aqbk1POVXFheVpeozzGSOO5/view',
            '_rl_partner_resource_title' => 'Remote Leverage × Oyster Notion Hub',
            '_rl_partner_resource_url' => 'https://oysterhr.notion.site/Remote-Leverage-x-Oyster-392601b0a58980f1b0b5fb1ebca5dcf4',

            '_rl_enable_comarketing' => '1',
            '_rl_manager_name' => 'Adrián Salvatori',
            '_rl_manager_email' => 'partnerships@remoteleverage.com',
            '_rl_manager_title' => 'Partnerships Director',
        ],
    ],

    'lexgo' => [
        'post_title' => 'Remote Leverage × Lexgo',
        'meta' => [
            '_rl_partner_name' => 'Lexgo',
            '_rl_partner_code' => 'RL-LEXGO',
            '_rl_partner_website' => 'https://lexgo.co',

            '_rl_directory_category' => 'Finance & Legal',
            '_rl_directory_description' => 'Cross-border employment and immigration counsel for companies hiring internationally — contracts, worker classification and visa routes for the talent Remote Leverage places.',
            '_rl_directory_perk' => 'Complimentary contract review for Remote Leverage clients',
            '_rl_directory_featured' => '0',

            '_rl_partnership_type' => 'Mutual Referral Partner',
            '_rl_territory' => 'Worldwide',
            '_rl_reporting_period' => 'Quarterly',
            '_rl_initial_term' => '12 months',
            '_rl_renewal_terms' => 'Automatic 12-month renewal, subject to the partnership agreement',

            // NOTE: Lexgo has no intake form or tracking sheet of its own yet.
            // These were copied from Oyster in the hand-authored database entry,
            // which would have routed Lexgo referrals into Oyster's sheet, so
            // they are deliberately left unset until Lexgo supplies its own.
            '_rl_intro_email' => 'partnerships@remoteleverage.com',
            '_rl_partner_to_rl_fee' => 'Lexgo receives 10% of the net Remote Leverage placement fee actually collected from an eligible referred customer. One-time referral fee, not recurring.',

            '_rl_partner_referral_label' => 'Refer a Client to Lexgo',
            '_rl_partner_referral_email' => 'partnerships@lexgo.co',
            '_rl_rl_to_partner_fee' => 'Remote Leverage receives 10% of eligible net fees actually collected by Lexgo from an eligible referred customer, up to 12 months.',

            '_rl_rl_resource_title' => 'Remote Leverage Partner Resources',
            '_rl_rl_resource_url' => 'https://drive.google.com/file/d/1Jl907jZo1aqbk1POVXFheVpeozzGSOO5/view',
            '_rl_partner_resource_title' => 'Lexgo Partner Resource Hub',
            '_rl_partner_resource_url' => 'https://lexgo.co',

            '_rl_enable_comarketing' => '1',
            '_rl_manager_name' => 'Adrián Salvatori',
            '_rl_manager_email' => 'partnerships@remoteleverage.com',
            '_rl_manager_title' => 'Partnerships Director',
        ],
    ],

    'lano' => [
        'post_title' => 'Remote Leverage × Lano',
        'meta' => [
            '_rl_partner_name' => 'Lano',
            '_rl_partner_code' => 'RL-LANO',
            '_rl_partner_website' => 'https://lano.io',

            '_rl_directory_category' => 'Finance & Legal',
            '_rl_directory_description' => 'Global payroll and contractor payment infrastructure covering 150+ countries — the paying layer for teams Remote Leverage staffs across borders.',
            '_rl_directory_perk' => 'Waived setup fee for Remote Leverage referrals',
            '_rl_directory_featured' => '0',

            '_rl_partnership_type' => 'Global Payroll & Compliance Partner',
            '_rl_territory' => 'Worldwide',
            '_rl_reporting_period' => 'Quarterly',
            '_rl_initial_term' => '12 months',
            '_rl_renewal_terms' => 'Automatic 12-month renewal, subject to the partnership agreement',

            // See the Lexgo note — Lano's intake form and tracking sheet are
            // likewise pending, not Oyster's.
            '_rl_intro_email' => 'partnerships@remoteleverage.com',
            '_rl_partner_to_rl_fee' => 'Lano receives 10% of the net Remote Leverage placement fee actually collected from an eligible referred customer. One-time referral fee, not recurring.',

            '_rl_partner_referral_label' => 'Refer a Client to Lano',
            '_rl_partner_referral_email' => 'partnerships@lano.io',
            '_rl_rl_to_partner_fee' => 'Remote Leverage receives 10% of eligible net fees actually collected by Lano from an eligible referred customer, up to 12 months.',

            '_rl_rl_resource_title' => 'Remote Leverage Partner Resources',
            '_rl_rl_resource_url' => 'https://drive.google.com/file/d/1Jl907jZo1aqbk1POVXFheVpeozzGSOO5/view',
            '_rl_partner_resource_title' => 'Lano Partner Network',
            '_rl_partner_resource_url' => 'https://lano.io',

            '_rl_enable_comarketing' => '1',
            '_rl_manager_name' => 'Adrián Salvatori',
            '_rl_manager_email' => 'partnerships@remoteleverage.com',
            '_rl_manager_title' => 'Partnerships Director',
        ],
    ],
];
