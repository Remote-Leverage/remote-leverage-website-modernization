<?php

use App\Support\BlockDefaults;

/**
 * Title: Role Pages Why Hire - Why Hire Through Remote Leverage?
 * Slug: remote-leverage/role-why-hire
 * Categories: remote-leverage
 * Description: Full-width purple proof banner over a 2x2 grid of four reasons to hire through Remote Leverage. Shared by all fourteen role pages.
 *
 * Same four reasons as the homepage, word for word, so they come from the shared helper rather
 * than being restated. What differs is the arrangement: the role comps run the proof card the
 * full width with the globe in its own half and drop the four cards into a 2x2 grid beneath,
 * where the homepage puts the proof card in a 5/7 column beside a vertical stack.
 *
 * The proof card also swaps the homepage's five gold stars for a stacked-portrait badge and
 * gains a CTA, and its ground is a vertical #5A1DAF to #25104A ramp rather than the homepage's
 * violet radial — all three measured off the comp, see the block's view for the numbers.
 */
$data = BlockDefaults::withFieldKeys('why_hire', [
    'headline' => 'Why Hire Through Remote Leverage?',
    'heading_align' => 'center',
    'layout' => 'banner',
    'proof_chrome' => 'bare',
    'proof_background' => 'violet-deep',
    'icon_set' => 'descriptive',
    'proof_title' => "We've hired Virtual Assistants for thousands of businesses all across the U.S.",
    'proof_badge_count' => '2.5K+',
    'proof_badge_label' => 'pre-vetted candidates',
    'proof_cta_text' => 'BOOK A CONSULTATION',
    'proof_cta_url' => '#booking-footer',
    'proof_image' => BlockDefaults::pageImg('role-pages', 'globe.png'),
]);
?>
<?= BlockDefaults::renderBlockWithRepeater('why-hire', 'cards', 'field_why_hire_cards', BlockDefaults::whyHire2026Cards(), $data, ['align' => 'full']) ?>
