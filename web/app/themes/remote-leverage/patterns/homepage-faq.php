<?php

use App\Support\BlockDefaults;

/**
 * Title: Homepage FAQ - Frequently Asked Questions
 * Slug: remote-leverage/homepage-faq
 * Categories: remote-leverage
 * Description: The ten standard questions as white cards in a single centred column.
 *
 * Same ten questions and answers as every other page, but not in the same order.
 * BlockDefaults::hireVa4Faqs() is sequenced for a balanced two-column layout, so reading it
 * straight down one column gives the left column first and the right column after it. The comp
 * reads across instead — countries, good fit, taxes, English, ... — which is that pair
 * interleaved, so the two halves are zipped back together here rather than restating the copy.
 */
$faqs = BlockDefaults::hireVa4Faqs();
$half = (int) ceil(count($faqs) / 2);
$left = array_slice($faqs, 0, $half);
$right = array_slice($faqs, $half);

$ordered = [];
for ($i = 0; $i < $half; $i++) {
    $ordered[] = $left[$i];
    if (isset($right[$i])) {
        $ordered[] = $right[$i];
    }
}
?>
<!-- wp:group {"align":"full","className":"px-4 sm:px-6 lg:px-8","style":{"spacing":{"padding":{"top":"4rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"760px"}} -->
<div class="wp-block-group alignfull px-4 sm:px-6 lg:px-8 has-bg-light-background-color has-background" style="padding-top:4rem;padding-bottom:6rem">
    <?= BlockDefaults::renderHireVa4Faq(BlockDefaults::withFieldKeys('accordion_faq_block', [
        'columns' => '1',
        'style' => 'cards',
        'heading_align' => 'center',
    ]), $ordered) ?>
</div>
<!-- /wp:group -->
