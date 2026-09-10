<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class RolesPricingGridBlock extends Block
{
    public $name = 'Roles Pricing Grid';

    public $slug = 'roles-pricing-grid';

    public $description = 'Uniform grid of role cards with photo, hourly price, task checklist, tool logos, and CTA — used on pricing/roles pages.';

    public $category = 'remote-leverage';

    public $icon = 'list-view';

    public $keywords = ['roles', 'pricing', 'virtual assistant', 'tasks', 'tools'];

    public $view = 'blocks.roles-pricing-grid';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Virtual Assistant Roles',
                'is_preview' => true,
            ],
        ],
    ];

    public $supports = [
        'align' => ['full'],
    ];

    public function with(): array
    {
        return [
            'headline' => BlockDefaults::cleanText(get_field('headline') ?: 'Virtual Assistant Roles'),
            'cards' => $this->cards(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('roles_pricing_grid_block');

        $fields
            ->addText('headline', [
                'label' => 'Section Headline',
                'default_value' => 'Virtual Assistant Roles',
            ])
            ->addRepeater('cards', [
                'label' => 'Role Cards (Leave empty for default 8 roles)',
                'layout' => 'block',
                'button_label' => 'Add Role Card',
            ])
                ->addImage('photo', ['label' => 'Role Photo', 'return_format' => 'url'])
                ->addText('title', ['label' => 'Role Title'])
                ->addText('price', ['label' => 'Hourly Price', 'default_value' => '$6-$10 Per Hour'])
                ->addText('intro', ['label' => 'Intro Line (e.g. "X Assistants help you by:")'])
                ->addTextarea('tasks', [
                    'label' => 'Task Checklist (one per line, last line is the closing sentence)',
                    'rows' => 6,
                ])
                ->addTextarea('tools', [
                    'label' => 'Tool Logo URLs (one per line)',
                    'rows' => 4,
                ])
                ->addText('cta_text', ['label' => 'CTA Text', 'default_value' => 'Interview Assistants'])
                ->addText('cta_url', ['label' => 'CTA URL', 'default_value' => '#booking-footer'])
            ->endRepeater();

        return $fields->build();
    }

    public function cards(): array
    {
        $custom = function_exists('get_field') ? get_field('cards') : null;
        $cards = (! empty($custom) && is_array($custom))
            ? $custom
            : BlockDefaults::rolesPricingGridCards();

        return array_map(function ($card) {
            $card['title'] = BlockDefaults::cleanText($card['title'] ?? '');
            $card['price'] = BlockDefaults::cleanText($card['price'] ?? '');
            $card['intro'] = BlockDefaults::cleanText($card['intro'] ?? '');
            $card['photo'] = BlockDefaults::resolveImageUrl($card['photo'] ?? '');
            $card['cta_text'] = BlockDefaults::cleanText($card['cta_text'] ?? 'Interview Assistants');
            $card['cta_url'] = $card['cta_url'] ?? '#booking-footer';

            $tasksRaw = is_string($card['tasks'] ?? null) ? $card['tasks'] : '';
            $card['tasks'] = array_values(array_filter(array_map(fn ($line) => BlockDefaults::cleanText(trim($line)), explode("\n", $tasksRaw)), fn ($line) => $line !== ''));

            $toolsRaw = $card['tools'] ?? '';
            if (is_string($toolsRaw) && str_starts_with(trim($toolsRaw), '[')) {
                $decoded = json_decode($toolsRaw, true);
                $card['tools'] = is_array($decoded) ? array_values(array_filter($decoded)) : [];
            } elseif (is_string($toolsRaw)) {
                $card['tools'] = array_values(array_filter(array_map('trim', explode("\n", $toolsRaw)), fn ($line) => $line !== ''));
            } else {
                $card['tools'] = [];
            }

            return $card;
        }, $cards);
    }
}
