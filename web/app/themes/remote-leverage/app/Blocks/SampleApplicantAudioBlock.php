<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class SampleApplicantAudioBlock extends Block
{
    public $name = 'Sample Applicant Audio';

    public $slug = 'sample-applicant-audio';

    public $description = 'Filterable audio voice recordings directory of pre-vetted candidates.';

    public $category = 'remote-leverage';

    public $icon = 'microphone';

    public $keywords = ['samples', 'audio', 'voice', 'recordings', 'candidates', 'applicants'];

    public $view = 'blocks.sample-applicant-audio';

    public $supports = [
        'align' => ['full', 'wide'],
    ];

    public function with(): array
    {
        return [
            'headline' => (function_exists('get_field') ? get_field('headline') : null) ?: 'Voice Audition Samples',
            'items' => $this->items(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('sample_applicant_audio_block');

        $fields
            ->addText('headline', [
                'label' => 'Section Headline',
                'default_value' => 'Voice Audition Samples',
            ])
            ->addRepeater('items', [
                'label' => 'Audio Items',
                'layout' => 'block',
                'button_label' => 'Add Audio Sample',
            ])
            ->addText('name', ['label' => 'Candidate Name'])
            ->addText('role', ['label' => 'Role Title'])
            ->addText('rate', ['label' => 'Hourly Rate', 'default_value' => '$10/hr'])
            ->addText('country', ['label' => 'Country'])
            ->addText('category', ['label' => 'Category Slug (e.g. sales, admin, medical, legal, finance)'])
            ->addText('audio_url', ['label' => 'Audio File URL (MP3)'])
            ->addText('resume_url', ['label' => 'Resume Link / URL', 'default_value' => '#booking-footer'])
            ->endRepeater();

        return $fields->build();
    }

    public function items(): array
    {
        $custom = function_exists('get_field') ? get_field('items') : null;
        if (! empty($custom) && is_array($custom)) {
            return $custom;
        }

        if (method_exists(BlockDefaults::class, 'sampleApplicantAudio')) {
            return BlockDefaults::sampleApplicantAudio();
        }

        return [];
    }
}
