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
 * inherited. They live in BlockDefaults because the role pages carry them verbatim too.
 */
$cards = BlockDefaults::whyHire2026Cards();

$data = BlockDefaults::withFieldKeys('why_hire', [
    'headline' => 'Why Hire Through Remote Leverage?',
    'heading_align' => 'center',
    'proof_chrome' => 'bare',
    'proof_background' => 'violet',
    'proof_title' => "We've helped more than 2,000 businesses hire top talent across LATAM, the Caribbean and the EU.",
]);
?>
<?= BlockDefaults::renderBlockWithRepeater('why-hire', 'cards', 'field_why_hire_cards', $cards, $data, ['align' => 'full']) ?>
