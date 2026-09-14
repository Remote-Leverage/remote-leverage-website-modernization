<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class SampleApplicantVideosBlock extends Block
{
    public $name = 'Sample Applicant Videos';

    public $slug = 'sample-applicant-videos';

    public $description = 'Grid of candidate video introductions with role, hourly rate, and bio.';

    public $category = 'remote-leverage';

    public $icon = 'video-alt3';

    public $keywords = ['samples', 'video', 'applicants', 'candidates', 'interviews'];

    public $view = 'blocks.sample-applicant-videos';

    public $supports = [
        'align' => ['full', 'wide'],
    ];

    public function with(): array
    {
        return [
            'headline' => (function_exists('get_field') ? get_field('headline') : null) ?: '',
            'cards' => $this->cards(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('sample_applicant_videos_block');

        $fields
            ->addText('headline', [
                'label' => 'Section Headline',
                'default_value' => '',
            ])
            ->addRepeater('cards', [
                'label' => 'Video Cards',
                'layout' => 'block',
                'button_label' => 'Add Video Card',
            ])
            ->addText('name', ['label' => 'Candidate Name'])
            ->addText('role', ['label' => 'Role Title'])
            ->addText('rate', ['label' => 'Hourly Rate', 'default_value' => '$10/hr'])
            ->addText('country', ['label' => 'Country'])
            ->addText('flag', ['label' => 'Country Flag'])
            ->addText('poster_file', ['label' => 'Poster File Path'])
            ->addText('poster_url', ['label' => 'Poster Image URL'])
            ->addText('resume_url', ['label' => 'Resume URL'])
            ->addTextarea('bio', ['label' => 'Bio Description', 'rows' => 3])
            ->addText('video_url', ['label' => 'Video File URL'])
            ->endRepeater();

        return $fields->build();
    }

    public function cards(): array
    {
        $custom = function_exists('get_field') ? get_field('cards') : null;
        $items = (! empty($custom) && is_array($custom))
            ? $custom
            : (method_exists(BlockDefaults::class, 'sampleApplicantVideos') ? BlockDefaults::sampleApplicantVideos() : []);

        return $this->enrichCards($items);
    }

    protected function enrichCards(array $cards): array
    {
        $nameMap = [
            'Nazarena T.' => 'Nazarena-T.png',
            'Laura V.' => 'Screenshot-2026-07-21-122538-1.png',
            'Gabriela P.' => 'Gabriela-P.png',
            'Davleen S.' => 'Davleen-S.png',
            'Emily E.' => 'Emily-E.png',
            'Mishelle R.' => 'Mishelle-R.png',
            'Leonardo A.' => 'Leonardo-A.png',
            'Keleme M.' => 'Keleme-M.png',
            'Manuela R.' => 'Screenshot-2026-07-21-134342-1-1.png',
            'Melissa S.' => 'Melissa-S.png',
            'Sebastian D.' => 'Sebastian-D.png',
            'Emma M.' => 'Emma-M.png',
            'Kory E.' => 'Kory-E.png',
            'Shumpei K.' => 'Shumpei-K.png',
        ];

        return array_map(function ($card) use ($nameMap) {
            if (empty($card['poster_file']) && ! empty($card['poster_url'])) {
                $base = basename($card['poster_url']);
                if (file_exists(get_theme_file_path('public/images/samples/posters/' . $base))) {
                    $card['poster_file'] = 'public/images/samples/posters/' . $base;
                }
            }
            if (empty($card['poster_file']) && ! empty($card['name']) && isset($nameMap[$card['name']])) {
                $card['poster_file'] = 'public/images/samples/posters/' . $nameMap[$card['name']];
            }
            return $card;
        }, $cards);
    }
}
