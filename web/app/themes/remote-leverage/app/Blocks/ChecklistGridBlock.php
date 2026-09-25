<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * A two-column grid of ticked statements, on its own.
 *
 * The markup already existed inside acf/partner-hero as its `badges` grid, locked to that hero.
 * /become-a-partner/'s "Who Should Become a Partner?" list is the same shape standing alone, so
 * the markup moved into blocks/partials/checklist-grid and both render it — a new block rather
 * than a hand-written loop in the pattern, and one markup rather than two.
 *
 * Renders no heading and no band: the pattern supplies both, as it does for acf/process-steps.
 */
class ChecklistGridBlock extends Block
{
    public $name = 'Checklist Grid';

    public $slug = 'checklist-grid';

    public $description = 'Two-column grid of ticked statements: white pills or rows, with a check or arrow disc.';

    public $category = 'remote-leverage';

    public $icon = 'yes-alt';

    public $keywords = ['checklist', 'ticks', 'badges', 'list', 'who is this for'];

    public $view = 'blocks.checklist-grid';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => ['is_preview' => true],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');
        $field = fn (string $key) => $hasGetField ? get_field($key) : null;

        return [
            'items' => $this->items($field('items')),
            'style' => $field('style') ?: 'pill',
            'icon' => $field('icon') ?: 'check',
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('checklist_grid_block');

        $fields
            ->addSelect('style', [
                'label' => 'Style',
                'choices' => [
                    'pill' => 'White pills with a faint border (default, as acf/partner-hero)',
                    'row' => 'White rows, 8px radius (/become-a-partner/)',
                ],
                'default_value' => 'pill',
            ])
            ->addSelect('icon', [
                'label' => 'Icon',
                'choices' => ['check' => 'White tick (default)', 'arrow' => 'Dark arrow'],
                'default_value' => 'check',
            ])
            ->addRepeater('items', [
                'label' => 'Items',
                'instructions' => 'Read row by row: left, right, then the next row.',
                'layout' => 'table',
                'button_label' => 'Add Item',
            ])
            ->addText('text', ['label' => 'Text'])
            ->endRepeater();

        return $fields->build();
    }

    /**
     * @return array<int, string>
     */
    public function items(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($row) => BlockDefaults::cleanText(is_array($row) ? ($row['text'] ?? '') : ''),
            $rows,
        )));
    }
}
