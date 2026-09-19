<?php

use App\Support\BlockDefaults;

/**
 * Title: Hero - Latin American Virtual Assistants
 * Slug: remote-leverage/hire-va-4-hero
 * Categories: remote-leverage
 * Description: Split hero layout with value proposition, checklist, instant call CTA, and embedded booking wizard.
 */
?>
<?= BlockDefaults::patternBlock('hire-va-hero', BlockDefaults::hireVa4Mobile('hero', [
    'badge_text' => '2,000+ businesses we\'ve helped hire',
    'headline' => 'Latin American<br>Virtual Assistants<br>$6-$10 Per Hour',
    'booking_title' => 'Book a Free 15-Minute Consultation',
    'booking_subtitle' => '',
    'enable_isolated_fields' => '1',
    'hide_profile_header' => '1',
    'hide_progress_bar' => '1',
    'form_button_text' => 'Find me an Assistant',
    'mobile_order' => 'form-first',
    'isolated_steps' => [
        [
            'step_label' => 'Email',
            'step_fields' => ['email'],
        ],
        [
            'step_label' => 'Complete First Step',
            'step_fields' => ['monthly_revenue', 'name', 'phone', 'consent'],
        ],
    ],
]), ['align' => 'full']) ?>
