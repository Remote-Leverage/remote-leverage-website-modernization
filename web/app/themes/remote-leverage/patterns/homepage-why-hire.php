<?php

use App\Support\BlockDefaults;

/**
 * Title: Homepage Why Hire - Why Hire Through Remote Leverage?
 * Slug: remote-leverage/homepage-why-hire
 * Categories: remote-leverage
 * Description: Purple proof card beside four reasons to hire through Remote Leverage.
 *
 * Centred heading and a bare proof card — the comp drops the "5.0 Star Rating" pill and the
 * closing paragraph that /hire-va-4/ carries. The four card bodies are the comp's own wording,
 * which is longer and more specific than the block's presets, so they are passed rather than
 * inherited.
 */
$cards = [
    [
        'title' => 'No Recurring Fees - Hire Direct',
        'desc' => 'Save thousands of Dollars a year by hiring your Virtual Assistant directly. One flat fee, direct onboarding, no ongoing costs.',
    ],
    [
        'title' => 'Fluent English',
        'desc' => 'We understand how important it is to speak fluent English with little to no accent. We go through hundreds of applicants a day and only bring you the top 1%.',
    ],
    [
        'title' => '30% Discount on Future Hires',
        'desc' => 'Get 30% off placement fees for every additional VA you hire within 12 months of your first placement.',
    ],
    [
        'title' => 'No Contracts',
        'desc' => "You're not locked into any sort of long term commitment with us or any Virtual Assistant you hire through us. If you're not happy with the applicants we bring you, we don't get paid.",
    ],
];

$data = BlockDefaults::withFieldKeys('why_hire', [
    'headline' => 'Why Hire Through Remote Leverage?',
    'heading_align' => 'center',
    'proof_chrome' => 'bare',
    'proof_title' => "We've helped more than 2,000 businesses hire top talent across LATAM, the Caribbean and the EU.",
]);
?>
<?= BlockDefaults::renderBlockWithRepeater('why-hire', 'cards', 'field_why_hire_cards', $cards, $data, ['align' => 'full']) ?>
