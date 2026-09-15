<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * Production's /signedup/ confirmation band: a purple surface carrying the completion
 * headline, a "Next steps:" label, the numbered steps each opening with a bold label, a
 * centred trust badge and a closing reassurance line.
 *
 * Checked before building: acf/vacalendar-hero is the nearest dark band but carries only a
 * headline plus two fixed paragraphs and then a scheduling embed. acf/process-steps and
 * acf/progress-steps render steps as light bands with connecting rules and short titles,
 * not a dark band of long labelled prose. acf/about-narrative is the right prose shape but
 * is a light two-column layout with no badge or steps. page-vathankyou.blade.php holds the
 * sibling confirmation page, but its hero is a countdown-and-verify panel, not this one.
 */
class NextStepsPanelBlock extends Block
{
    public $name = 'Next Steps Panel';

    public $slug = 'next-steps-panel';

    public $description = 'Purple confirmation band with numbered next-step prose, a trust badge and a closing line.';

    public $category = 'remote-leverage';

    public $icon = 'yes-alt';

    public $keywords = ['next steps', 'confirmation', 'signed up', 'agreement', 'onboarding'];

    public $view = 'blocks.next-steps-panel';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Agreement Completed.',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $defaults = BlockDefaults::signedUpPanel();

        return [
            'headline' => BlockDefaults::cleanText(get_field('headline') ?: $defaults['headline']),
            // Named intro_label, not steps_label: ACF Composer derives a repeater sub-field's
            // key as field_<group>_<repeater>_<sub>, so a top-level `steps_label` collides with
            // the `steps` repeater's `label` sub-field and silently blanks it.
            'stepsLabel' => BlockDefaults::cleanText(get_field('intro_label') ?: $defaults['intro_label']),
            'steps' => $this->steps($defaults),
            'badgeImage' => BlockDefaults::resolveImageUrl(get_field('badge_image')) ?: '',
            'footnote' => BlockDefaults::cleanText(get_field('footnote') ?: $defaults['footnote']),
        ];
    }

    /**
     * @param  array{steps: array<int, array{label: string, text: string}>}  $defaults
     * @return array<int, array{label: string, text: string}>
     */
    protected function steps(array $defaults): array
    {
        $rows = get_field('steps');

        if (! is_array($rows) || $rows === []) {
            return $defaults['steps'];
        }

        return array_values(array_map(fn (array $row): array => [
            'label' => BlockDefaults::cleanText($row['label'] ?? ''),
            'text' => BlockDefaults::cleanText($row['text'] ?? ''),
        ], $rows));
    }

    public function fields(): array
    {
        $defaults = BlockDefaults::signedUpPanel();

        $fields = Builder::make('next_steps_panel_block');

        $fields
            ->addText('headline', [
                'label' => 'Headline',
                'default_value' => $defaults['headline'],
            ])
            ->addText('intro_label', [
                'label' => 'Steps label',
                'default_value' => $defaults['intro_label'],
            ])
            ->addRepeater('steps', [
                'label' => 'Steps',
                'layout' => 'block',
                'button_label' => 'Add step',
            ])
            ->addText('label', ['label' => 'Bold label'])
            ->addTextarea('text', ['label' => 'Body', 'rows' => 3])
            ->endRepeater()
            ->addImage('badge_image', [
                'label' => 'Trust badge',
                'return_format' => 'url',
            ])
            ->addTextarea('footnote', [
                'label' => 'Closing line',
                'rows' => 2,
                'default_value' => $defaults['footnote'],
            ]);

        return $fields->build();
    }
}
