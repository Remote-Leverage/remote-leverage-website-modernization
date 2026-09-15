<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * The /referral-program/ hero: eyebrow pill, page title, the earnings promise, the two
 * earnings figures as glass cards, the primary CTA and the returning-referrer portal link.
 *
 * Production keeps its four "How it Works" steps inside this band; here they render through
 * acf/process-steps in their own section, and the band is rebuilt on the theme's dark
 * gradient rather than production's flat purple (refresh requested 2026-09-15).
 *
 * Checked before building: acf/affiliate-hero is the closest existing shape and is used by the
 * sibling /affiliate-program/, but it is a split hero built around a required portrait and a
 * fixed gradient backdrop asset, neither of which this page has. acf/partner-hero hard-codes
 * the world-map backdrop and its repeater is icon/value/label tiles. acf/vacalendar-hero is a
 * dark band with a headline and two paragraphs, no repeater and no CTA.
 */
class ReferralProgramHeroBlock extends Block
{
    public $name = 'Referral Program Hero';

    public $slug = 'referral-program-hero';

    public $description = 'Dark gradient hero with the referral promise, earnings figures, CTA and portal link.';

    public $category = 'remote-leverage';

    public $icon = 'megaphone';

    public $keywords = ['referral', 'hero', 'earn', 'program', 'affiliate'];

    public $view = 'blocks.referral-program-hero';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Remote Leverage Referral Program',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $defaults = BlockDefaults::referralProgramHero();

        return [
            'eyebrow' => BlockDefaults::cleanText(get_field('eyebrow') ?: 'Referral Program'),
            'headline' => BlockDefaults::cleanText(get_field('headline') ?: 'Remote Leverage Referral Program'),
            'subheadline' => BlockDefaults::cleanText(get_field('subheadline') ?: $defaults['subheadline']),
            'stats' => $this->stats($defaults),
            'ctaText' => BlockDefaults::cleanText(get_field('cta_text') ?: 'Join Referral Program'),
            'ctaUrl' => get_field('cta_url') ?: '/referral-dashboard/?tab=sign-up',
            'portalPrefix' => BlockDefaults::cleanText(get_field('portal_prefix') ?: 'Are you already registered as a referrer?'),
            'portalText' => BlockDefaults::cleanText(get_field('portal_text') ?: 'Access your referral portal here'),
            'portalUrl' => get_field('portal_url') ?: '/referral-dashboard/?tab=login',
        ];
    }

    /**
     * @param  array{stats: array<int, array{value: string, label: string}>}  $defaults
     * @return array<int, array{value: string, label: string}>
     */
    protected function stats(array $defaults): array
    {
        $rows = get_field('stats');

        if (! is_array($rows) || $rows === []) {
            return $defaults['stats'];
        }

        return array_values(array_map(fn (array $row): array => [
            'value' => BlockDefaults::cleanText($row['value'] ?? ''),
            'label' => BlockDefaults::cleanText($row['label'] ?? ''),
        ], $rows));
    }

    public function fields(): array
    {
        $defaults = BlockDefaults::referralProgramHero();

        $fields = Builder::make('referral_program_hero_block');

        $fields
            ->addText('eyebrow', [
                'label' => 'Eyebrow pill',
                'default_value' => 'Referral Program',
            ])
            ->addText('headline', [
                'label' => 'Headline',
                'default_value' => 'Remote Leverage Referral Program',
            ])
            ->addTextarea('subheadline', [
                'label' => 'Subheadline',
                'rows' => 2,
                'default_value' => $defaults['subheadline'],
            ])
            ->addRepeater('stats', [
                'label' => 'Earnings figures',
                'layout' => 'table',
                'button_label' => 'Add figure',
                'max' => 3,
            ])
            ->addText('value', ['label' => 'Amount'])
            ->addText('label', ['label' => 'Caption'])
            ->endRepeater()
            ->addText('cta_text', [
                'label' => 'CTA text',
                'default_value' => 'Join Referral Program',
            ])
            ->addUrl('cta_url', [
                'label' => 'CTA URL',
                'default_value' => '/referral-dashboard/?tab=sign-up',
            ])
            ->addText('portal_prefix', [
                'label' => 'Returning referrer prefix',
                'default_value' => 'Are you already registered as a referrer?',
            ])
            ->addText('portal_text', [
                'label' => 'Returning referrer link text',
                'default_value' => 'Access your referral portal here',
            ])
            ->addUrl('portal_url', [
                'label' => 'Returning referrer link URL',
                'default_value' => '/referral-dashboard/?tab=login',
            ]);

        return $fields->build();
    }
}
