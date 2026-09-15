<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * Production's video training grid on /vaonboardingguide/ (page 6830).
 *
 * Each card is a purple gradient panel holding one embedded player, a centred caption and an
 * optional orange CTA. Cards are `full` (one per row) or `half` (two per row); consecutive
 * `half` cards pair up and a `full` card always takes a row of its own, which is how
 * production alternates a 1130px hero player with 505px pairs inside the same 1170px column.
 *
 * This is a grid block rather than one block per video because the row packing is the layout:
 * six independent section blocks could not pair themselves, and the 100px column gutter and
 * 38px row rhythm would have to be restated on every one.
 *
 * Embeds are stored as the complete player URL, not an ID. An unlisted Vimeo video is
 * addressed by ID *and* its `h=` privacy hash; drop the hash and the player renders a
 * restriction notice instead of the video, with no console error to notice it by. Patterns
 * therefore transcribe production's `src` verbatim rather than rebuilding it from an ID.
 *
 * Tokens read off the live page with getComputedStyle (2026-09-15):
 *   card linear-gradient(180deg, #6200A4, #342567) · 10px radius · 10px 10px 20px padding ·
 *   14px between card children · player 16:9 with a 10px radius and a black backdrop ·
 *   caption 24/24 600 white centred · CTA #FB7501, 5px radius, 10px 40px, 24px bold.
 *
 * Production sets the caption and CTA in League Spartan; the theme dropped that face in
 * favour of Inter Display (see the @font-face note in resources/css/app.css), so both use
 * `font-display` here.
 */
class VideoCardGridBlock extends Block
{
    public $name = 'Video Card Grid';

    public $slug = 'video-card-grid';

    public $description = 'Rows of gradient video cards — an embedded player, a caption and an optional CTA, one or two per row.';

    public $category = 'remote-leverage';

    public $icon = 'video-alt3';

    public $keywords = ['video', 'vimeo', 'guide', 'training', 'onboarding', 'grid'];

    public $view = 'blocks.video-card-grid';

    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => false,
    ];

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
            'rows' => $this->rows($this->cards($field('cards'))),
        ];
    }

    /**
     * Normalise the repeater into cards.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cards(mixed $cards): array
    {
        if (! is_array($cards)) {
            return [];
        }

        $normalised = [];

        foreach ($cards as $card) {
            if (! is_array($card)) {
                continue;
            }

            $url = trim((string) ($card['video_url'] ?? ''));

            if ($url === '') {
                continue;
            }

            $normalised[] = [
                'videoUrl' => $url,
                'title' => BlockDefaults::cleanText((string) ($card['title'] ?? '')),
                'titleUrl' => (string) ($card['title_url'] ?? ''),
                'ctaText' => BlockDefaults::cleanText((string) ($card['cta_text'] ?? '')),
                'ctaUrl' => (string) ($card['cta_url'] ?? ''),
                'width' => ($card['width'] ?? 'full') === 'half' ? 'half' : 'full',
            ];
        }

        return $normalised;
    }

    /**
     * Pack cards into production's rows: a `full` card owns its row, `half` cards pair up.
     *
     * A trailing unpaired `half` card stays half-width rather than stretching, which is what
     * production does — the row is a flex row, not a two-column grid.
     *
     * @param  array<int, array<string, mixed>>  $cards
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function rows(array $cards): array
    {
        $rows = [];

        foreach ($cards as $card) {
            $last = array_key_last($rows);

            $canPair = $card['width'] === 'half'
                && $last !== null
                && count($rows[$last]) === 1
                && $rows[$last][0]['width'] === 'half';

            if ($canPair) {
                $rows[$last][] = $card;

                continue;
            }

            $rows[] = [$card];
        }

        return $rows;
    }

    public function fields(): array
    {
        $fields = Builder::make('video_card_grid_block');

        $fields
            ->addRepeater('cards', [
                'label' => 'Video cards',
                'layout' => 'block',
                'button_label' => 'Add video card',
            ])
            ->addUrl('video_url', [
                'label' => 'Player URL',
                'instructions' => 'The complete embed URL. For an unlisted Vimeo video keep its `h=` privacy hash — without it the player shows a restriction notice instead of the video.',
            ])
            ->addText('title', ['label' => 'Caption (under the player)'])
            ->addUrl('title_url', ['label' => 'Caption link', 'instructions' => 'Optional. Leave blank for plain text.'])
            ->addSelect('width', [
                'label' => 'Width',
                'choices' => [
                    'full' => 'Full row (default)',
                    'half' => 'Half row — pairs with the next half card',
                ],
                'default_value' => 'full',
            ])
            ->addText('cta_text', ['label' => 'CTA text', 'instructions' => 'Leave blank to hide.'])
            ->addUrl('cta_url', ['label' => 'CTA URL'])
            ->endRepeater();

        return $fields->build();
    }
}
