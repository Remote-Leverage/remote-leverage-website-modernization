<?php
/**
 * Title: Reviews - Why Hire VA Through Remote Leverage
 * Slug: remote-leverage/reviews-why-hire
 * Categories: remote-leverage
 * Description: 4 benefit cards (Fluent English, No Recurring Fees, 30% Discount, No Contracts) with proof-card rating.
 */
$data = [
    'headline' => 'Why Hire Virtual Assistants Through Remote Leverage?',
    '_headline' => 'field_why_hire_headline',
    'cards' => 4,
    '_cards' => 'field_why_hire_cards',
    'cards_0_title' => 'Fluent English',
    '_cards_0_title' => 'field_why_hire_cards_title',
    'cards_0_desc' => 'We understand how important it is to speak fluent English with little to no accent. We go through hundreds of applicants a day and only bring you the top 1%.',
    '_cards_0_desc' => 'field_why_hire_cards_desc',
    'cards_1_title' => 'No Recurring Fees - Hire Direct',
    '_cards_1_title' => 'field_why_hire_cards_title',
    'cards_1_desc' => 'Save thousands of Dollars a year by hiring the Virtual Assistant directly. We charge a one time flat hiring fee if you decide to hire one of the Virtual Assistants we bring you, and help you onboard them directly to avoid ongoing fees.',
    '_cards_1_desc' => 'field_why_hire_cards_desc',
    'cards_2_title' => '30% Discount on Future Hires',
    '_cards_2_title' => 'field_why_hire_cards_title',
    'cards_2_desc' => 'Get 30% off placement fees for every additional VA you hire within 12 months of your first placement.',
    '_cards_2_desc' => 'field_why_hire_cards_desc',
    'cards_3_title' => 'No Contracts',
    '_cards_3_title' => 'field_why_hire_cards_title',
    'cards_3_desc' => "You're not locked into any sort of long term commitment with us or any Virtual Assistant you hire through us. If you're not happy with the applicants we bring you, we don't get paid.",
    '_cards_3_desc' => 'field_why_hire_cards_desc',
];
$blockAttrs = [
    'name' => 'acf/why-hire',
    'data' => $data,
    'align' => 'full',
    'mode' => 'preview',
];
?>
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"1rem","bottom":"1rem"}}}} -->
<div class="wp-block-buttons" style="margin-top:1rem;margin-bottom:1rem">
    <!-- wp:button {"className":"is-style-pill-purple"} -->
    <div class="wp-block-button is-style-pill-purple"><a class="wp-block-button__link wp-element-button" href="#booking-footer">Book a consultation</a></div>
    <!-- /wp:button -->
</div>
<!-- /wp:buttons -->

<!-- wp:acf/why-hire <?= json_encode($blockAttrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?> /-->
