<?php

use App\Support\BlockDefaults;

/**
 * Title: Homepage Guarantee - 12-Month Replacement Guarantee
 * Slug: remote-leverage/homepage-guarantee
 * Categories: remote-leverage
 * Description: Full-bleed purple guarantee band with the medals badge and a magenta CTA.
 *
 * The comp states the guarantee as a flat twelve months; production's other pages qualify it as
 * six extended to twelve. Only the first item differs, so the preset trio is taken and that one
 * line replaced rather than all three restated here.
 */
$items = BlockDefaults::guaranteeReassuranceItems();
$items[0]['text'] = 'Free replacement any time in the first 12 months';
?>
<?= BlockDefaults::renderBlockWithRepeater(
    'guarantee-card',
    'reassurance_items',
    'field_guarantee_card_reassurance_items',
    $items,
    BlockDefaults::withFieldKeys('guarantee_card', [
        'cta_text' => 'BOOK A CONSULTATION',
        'cta_style' => 'pill',
    ]),
    ['align' => 'full'],
) ?>
