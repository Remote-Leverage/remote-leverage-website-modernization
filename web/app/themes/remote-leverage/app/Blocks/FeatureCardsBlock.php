<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class FeatureCardsBlock extends Block
{
    public $name = 'Feature & Benefit Cards';

    public $slug = 'feature-cards';

    public $description = 'Grid of rounded cards with top imagery, bold headings, and descriptions.';

    public $category = 'remote-leverage';

    public $icon = 'grid-view';

    public $keywords = ['features', 'cards', 'benefits', 'grid', 'metrics'];

    public $view = 'blocks.feature-cards';

    public function with(): array
    {
        $columns = (string) ((function_exists('get_field') ? get_field('columns') : null) ?: '3');

        return [
            'columns' => $columns,
            'cards' => $this->cards($columns),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('feature_cards_block');

        $fields
            ->addSelect('columns', [
                'label' => 'Columns Layout',
                'choices' => [
                    '3' => '3 Columns (6 Benefit Cards)',
                    '4' => '4 Columns (4 Metric Cards)',
                ],
                'default_value' => '3',
            ])
            ->addRepeater('cards', [
                'label' => 'Custom Cards (Leave empty to use preset defaults)',
                'layout' => 'block',
                'button_label' => 'Add Card',
            ])
            ->addImage('img', ['label' => 'Image', 'return_format' => 'url'])
            ->addText('title', ['label' => 'Card Title (supports HTML)'])
            ->addTextarea('desc', ['label' => 'Card Description', 'rows' => 2])
            ->endRepeater();

        return $fields->build();
    }

    public function cards(string $columns): array
    {
        $custom = function_exists('get_field') ? get_field('cards') : null;

        if (! empty($custom) && is_array($custom)) {
            return $custom;
        }

        $imgBase = get_template_directory_uri() . '/public/images/home';

        if ($columns === '4') {
            return [
                [
                    'img' => $imgBase . '/hour.webp',
                    'title' => '$6-10 /hr',
                    'desc' => 'Access experienced professionals at highly competitive rates. Most administrative, support, sales, and marketing roles can be filled within this range.',
                ],
                [
                    'img' => $imgBase . '/lower-cost.webp',
                    'title' => '70% Lower Costs',
                    'desc' => 'Reduce hiring costs without sacrificing quality. Reinvest the savings into growth, marketing, product development, or additional hires.',
                ],
                [
                    'img' => $imgBase . '/day-average.webp',
                    'title' => '4-Day Average',
                    'desc' => 'From opening a role to reviewing qualified candidates in days, not weeks. Our recruiting process is designed for speed without compromising quality.',
                ],
                [
                    'img' => $imgBase . '/quality.webp',
                    'title' => 'Vetted for Quality',
                    'desc' => 'Every candidate is screened for English proficiency, experience, communication skills, and role-specific expertise before reaching your inbox.',
                ],
            ];
        }

        return [
            [
                'img' => $imgBase . '/Latin-american.webp',
                'title' => 'Top-tier talents from Latin America and EU',
                'desc' => 'Access exceptional global talent. We identify skilled professionals with the communication, expertise, and reliability needed to make an immediate impact.',
            ],
            [
                'img' => $imgBase . '/no-contracts.webp',
                'title' => 'No contracts<br>No obligations',
                'desc' => 'Evaluate talent, interview candidates, and see our process firsthand before making any commitment. The decision is always yours.',
            ],
            [
                'img' => $imgBase . '/ongoing-middleman.webp',
                'title' => 'No ongoing<br>middleman fees',
                'desc' => 'You hire talent directly into your business. No payroll markups, monthly management fees, or recurring commissions.',
            ],
            [
                'img' => $imgBase . '/payment.webp',
                'title' => "No payment if we don't find the right talent",
                'desc' => 'Our incentives are aligned with yours. We only succeed when you make a successful hire, so we focus relentlessly on finding the right fit.',
            ],
            [
                'img' => $imgBase . '/payment-compliance.webp',
                'title' => 'Payments, compliance,<br>onboarding support',
                'desc' => 'Our Contractor Management solution simplifies onboarding, contracts, payroll, and compliance for international talent.',
            ],
            [
                'img' => $imgBase . '/one-dashboard.webp',
                'title' => 'One dashboard<br>for your entire team',
                'desc' => 'Manage payroll, contracts, compliance, and workforce reporting from a single platform. Stay organized as your global team grows.',
            ],
        ];
    }
}
