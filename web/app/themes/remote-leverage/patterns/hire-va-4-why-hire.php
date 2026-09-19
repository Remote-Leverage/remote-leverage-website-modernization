<?php

use App\Support\BlockDefaults;

/**
 * Title: Why Hire - Why Hire Through Remote Leverage
 * Slug: remote-leverage/hire-va-4-why-hire
 * Categories: remote-leverage
 * Description: Social proof card with 5-star rating, global talent map, and 4 feature benefit cards.
 *
 * The comp (docs/design/hire-va-4.png) renders this block in the variant the block already
 * ships rather than a new design, so this is configuration and not CSS:
 *
 *   heading_align    centred, as the comp sets it
 *   proof_chrome     'bare' — the comp drops the "5.0 Star Rating" pill and the closing
 *                    paragraph, leaving bare stars over the quote
 *   proof_background 'violet' — #6410A6 with a #7616B6 bloom behind the globe. Sampling the
 *                    comp's own card gives rgb(100,7,166) flat across its width and brightening
 *                    to rgb(129,30,191) behind the globe, which is that pair to within webp
 *                    rounding. The default 'midnight' #250D4A is what this page rendered before.
 *   icon_set         'descriptive' — the comp's glyphs depict their card (barred dollar, EN
 *                    speech bubble, percent, barred document) instead of decorating it.
 *
 * These are block-level options, so they apply at every width, not only on mobile.
 */
$data = BlockDefaults::withFieldKeys('why_hire', [
    'heading_align' => 'center',
    'proof_chrome' => 'bare',
    'proof_background' => 'violet',
    'icon_set' => 'descriptive',
]);
?>
<?= BlockDefaults::patternBlock('why-hire', BlockDefaults::hireVa4Mobile('why-hire', $data), ['align' => 'full']) ?>
