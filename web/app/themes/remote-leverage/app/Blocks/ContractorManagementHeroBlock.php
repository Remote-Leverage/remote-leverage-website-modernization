<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ContractorManagementHeroBlock extends Block
{
    public $name = 'Hero — Light with World Map';

    public $slug = 'contractor-management-hero';

    public $description = 'Pale hero over a faint world-map graphic. Copy and purple pill CTA left, product composite right, three divider-separated stat metrics beneath.';

    public $category = 'remote-leverage';

    public $icon = 'admin-site';

    public $keywords = ['hero', 'light', 'world map', 'stats', 'product', 'contractor'];

    public $view = 'blocks.contractor-management-hero';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => ['is_preview' => true],
        ],
    ];

    public $supports = [
        'align' => ['full'],
    ];

    public function with(): array
    {
        $field = fn (string $key) => function_exists('get_field') ? get_field($key) : null;
        $img = fn (string $file): string => BlockDefaults::pageImg('contractor-management', $file);

        return [
            'headline' => $field('headline') ?: 'Contractor Management for Remote Teams',
            'subtitle' => $field('subtitle') ?: 'Onboard, pay, and manage your contractors anywhere in the world. All in one service.',
            'ctaText' => $field('cta_text') ?: 'Book a demo',
            'ctaUrl' => $field('cta_url') ?: '/vacalendar',
            'mapImage' => BlockDefaults::resolveImageUrl($field('map_image') ?: '') ?: $img('Union-4.png'),
            'heroImage' => BlockDefaults::resolveImageUrl($field('hero_image') ?: '') ?: $img('hero-main.png'),
            'stats' => $this->stats(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('contractor_management_hero_block');

        $fields
            ->addTextarea('headline', ['label' => 'Headline', 'rows' => 2])
            ->addTextarea('subtitle', ['label' => 'Subtitle', 'rows' => 2])
            ->addText('cta_text', ['label' => 'CTA Text', 'default_value' => 'Book a demo'])
            ->addText('cta_url', ['label' => 'CTA URL', 'default_value' => '/vacalendar'])
            ->addImage('map_image', ['label' => 'Background Map', 'return_format' => 'url'])
            ->addImage('hero_image', ['label' => 'Product Composite', 'return_format' => 'url'])
            ->addRepeater('stats', ['label' => 'Stat Metrics (3)', 'layout' => 'table', 'button_label' => 'Add stat'])
            ->addText('value', ['label' => 'Value', 'placeholder' => '2,000+'])
            ->addText('label', ['label' => 'Label', 'placeholder' => 'Contractors managed'])
            ->addSelect('icon', [
                'label' => 'Icon',
                'choices' => ['people' => 'People', 'globe' => 'Globe', 'speed' => 'Speedometer'],
                'default_value' => 'people',
            ])
            ->endRepeater();

        return $fields->build();
    }

    /**
     * @return array<int, array{value: string, label: string, icon: string}>
     */
    protected function stats(): array
    {
        $custom = function_exists('get_field') ? get_field('stats') : null;

        if (is_array($custom) && $custom !== []) {
            return array_values(array_map(static fn (array $s): array => [
                'value' => BlockDefaults::cleanText($s['value'] ?? ''),
                'label' => BlockDefaults::cleanText($s['label'] ?? ''),
                'icon' => (string) ($s['icon'] ?? 'people'),
            ], array_filter($custom, 'is_array')));
        }

        return [
            ['value' => '2,000+', 'label' => 'Contractors managed', 'icon' => 'people'],
            ['value' => '25+', 'label' => 'Countries covered', 'icon' => 'globe'],
            ['value' => '24 hrs', 'label' => 'Average onboarding time', 'icon' => 'speed'],
        ];
    }
}
