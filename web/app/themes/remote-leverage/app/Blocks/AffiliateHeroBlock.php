<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class AffiliateHeroBlock extends Block
{
    public $name = 'Affiliate Program Hero';

    public $slug = 'affiliate-hero';

    public $description = 'Split hero for the Affiliate Program page: headline, dual CTA, hero image, and a glass earnings-progress card.';

    public $category = 'remote-leverage';

    public $icon = 'money-alt';

    public $keywords = ['affiliate', 'referral', 'hero', 'partner', 'commission'];

    public $view = 'blocks.affiliate-hero';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Remote Leverage Affiliate Program',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');

        return [
            'headline' => BlockDefaults::cleanText(($hasGetField ? get_field('headline') : null) ?: 'Remote Leverage Affiliate Program'),
            'subheadline' => BlockDefaults::cleanText(($hasGetField ? get_field('subheadline') : null) ?: 'Help Your Network Scale. Earn $1,000 for Every Hire &amp; Pass on $500 in Savings.'),
            'primaryCtaText' => ($hasGetField ? get_field('primary_cta_text') : null) ?: 'Apply to Join Now',
            'primaryCtaUrl' => ($hasGetField ? get_field('primary_cta_url') : null) ?: '/referrer-register',
            'secondaryCtaText' => ($hasGetField ? get_field('secondary_cta_text') : null) ?: 'Book a Strategy Call',
            'secondaryCtaUrl' => ($hasGetField ? get_field('secondary_cta_url') : null) ?: '#booking-footer',
            'heroImage' => BlockDefaults::resolveImageUrl(($hasGetField ? get_field('hero_image') : null) ?: ''),
            'statLabelLeft' => ($hasGetField ? get_field('stat_label_left') : null) ?: 'Received',
            'statLabelRight' => ($hasGetField ? get_field('stat_label_right') : null) ?: 'Goal',
            'statAmountLeft' => ($hasGetField ? get_field('stat_amount_left') : null) ?: 'USD 670.84',
            'statAmountRight' => ($hasGetField ? get_field('stat_amount_right') : null) ?: 'USD 3,000.00 Goal',
            'statPercent' => (int) (($hasGetField ? get_field('stat_percent') : null) ?: 51),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('affiliate_hero_block');

        $fields
            ->addTextarea('headline', [
                'label' => 'Headline',
                'default_value' => 'Remote Leverage Affiliate Program',
                'rows' => 2,
            ])
            ->addTextarea('subheadline', [
                'label' => 'Subheadline',
                'default_value' => 'Help Your Network Scale. Earn $1,000 for Every Hire & Pass on $500 in Savings.',
                'rows' => 2,
            ])
            ->addText('primary_cta_text', [
                'label' => 'Primary CTA Text',
                'default_value' => 'Apply to Join Now',
            ])
            ->addUrl('primary_cta_url', [
                'label' => 'Primary CTA URL',
                'default_value' => '/referrer-register',
                'instructions' => 'Points at the existing Referrer Registration route.',
            ])
            ->addText('secondary_cta_text', [
                'label' => 'Secondary CTA Text',
                'default_value' => 'Book a Strategy Call',
            ])
            ->addUrl('secondary_cta_url', [
                'label' => 'Secondary CTA URL',
                'default_value' => '#booking-footer',
            ])
            ->addImage('hero_image', [
                'label' => 'Hero Image',
                'return_format' => 'url',
            ])
            ->addText('stat_label_left', [
                'label' => 'Stat Card: Left Label',
                'default_value' => 'Received',
            ])
            ->addText('stat_label_right', [
                'label' => 'Stat Card: Right Label',
                'default_value' => 'Goal',
            ])
            ->addText('stat_amount_left', [
                'label' => 'Stat Card: Left Amount',
                'default_value' => 'USD 670.84',
            ])
            ->addText('stat_amount_right', [
                'label' => 'Stat Card: Right Amount',
                'default_value' => 'USD 3,000.00 Goal',
            ])
            ->addNumber('stat_percent', [
                'label' => 'Stat Card: Progress Percent',
                'default_value' => 51,
            ]);

        return $fields->build();
    }
}
