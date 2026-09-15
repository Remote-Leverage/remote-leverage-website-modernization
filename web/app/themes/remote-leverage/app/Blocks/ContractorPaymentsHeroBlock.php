<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ContractorPaymentsHeroBlock extends Block
{
    public $name = 'Hero — Gradient with Bleeding Portrait';

    public $slug = 'contractor-payments-hero';

    public $description = 'Black-to-purple gradient hero. Copy left, portrait + floating UI cards bleeding off the right edge, three stat metrics with outline icons beneath.';

    public $category = 'remote-leverage';

    public $icon = 'align-pull-right';

    public $keywords = ['hero', 'gradient', 'portrait', 'stats', 'payments', 'product'];

    public $view = 'blocks.contractor-payments-hero';

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
        $img = fn (string $file): string => BlockDefaults::pageImg('contractor-payments', $file);

        return [
            'headline' => $field('headline') ?: "Contractor Payments for\nYour Global Team, Simplified",
            'subtitle' => $field('subtitle') ?: 'One invoice, one pay cycle, every contractor paid on time in 25+ countries and growing',
            'ctaText' => $field('cta_text') ?: 'Book a consultation',
            'ctaUrl' => $field('cta_url') ?: '/vacalendar',
            'shape' => BlockDefaults::resolveImageUrl($field('shape_image') ?: '') ?: $img('Union.png'),
            'portrait' => BlockDefaults::resolveImageUrl($field('portrait_image') ?: '') ?: $img('cor-hero-sec.png'),
            'cardTop' => BlockDefaults::resolveImageUrl($field('card_top_image') ?: '') ?: $img('Contractors-2-1.webp'),
            'cardBottom' => BlockDefaults::resolveImageUrl($field('card_bottom_image') ?: '') ?: $img('Contractors-1.webp'),
            'stats' => $this->stats(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('contractor_payments_hero_block');

        $fields
            ->addTextarea('headline', ['label' => 'Headline', 'rows' => 2, 'instructions' => 'Line breaks are preserved.'])
            ->addTextarea('subtitle', ['label' => 'Subtitle', 'rows' => 2])
            ->addText('cta_text', ['label' => 'CTA Text', 'default_value' => 'Book a consultation'])
            ->addText('cta_url', ['label' => 'CTA URL', 'default_value' => '/vacalendar'])
            ->addImage('shape_image', ['label' => 'Background Shape', 'return_format' => 'url'])
            ->addImage('portrait_image', ['label' => 'Portrait', 'return_format' => 'url'])
            ->addImage('card_top_image', ['label' => 'Floating Card (top right)', 'return_format' => 'url'])
            ->addImage('card_bottom_image', ['label' => 'Floating Card (bottom left)', 'return_format' => 'url'])
            ->addRepeater('stats', [
                'label' => 'Stat Metrics (3)',
                'layout' => 'table',
                'button_label' => 'Add stat',
            ])
            ->addText('value', ['label' => 'Value', 'placeholder' => '2,000+'])
            ->addText('label', ['label' => 'Label', 'placeholder' => 'Contractors paid'])
            ->addSelect('icon', [
                'label' => 'Icon',
                'choices' => ['dollar' => 'Dollar', 'globe' => 'Globe', 'shield' => 'Shield'],
                'default_value' => 'dollar',
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
                'icon' => (string) ($s['icon'] ?? 'dollar'),
            ], array_filter($custom, 'is_array')));
        }

        return [
            ['value' => '2,000+', 'label' => 'Contractors paid', 'icon' => 'dollar'],
            ['value' => '25+', 'label' => 'Countries covered', 'icon' => 'globe'],
            ['value' => '01', 'label' => 'Invoice per pay cycle', 'icon' => 'shield'],
        ];
    }
}
