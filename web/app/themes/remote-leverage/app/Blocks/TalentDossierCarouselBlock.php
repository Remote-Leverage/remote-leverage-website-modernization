<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * Production's "Meet Our Ecommerce Talent" band (/ecommerce-virtual-assistant/ §4).
 *
 * Field naming note: ACF Composer derives a repeater sub-field key as
 * `field_<group>_<repeater>_<sub>`, so a top-level field called `cards_<sub>`
 * would silently collide with a `cards` sub-field and blank it. The top-level
 * names here (`headline`, `layout`) are deliberately outside that namespace.
 */
class TalentDossierCarouselBlock extends Block
{
    public $name = 'Talent Dossier Carousel';

    public $slug = 'talent-dossier-carousel';

    public $description = 'Scrolling carousel of full talent dossier cards: photo with country and rate overlays, then experience, skills, tools and previous companies.';

    public $category = 'remote-leverage';

    public $icon = 'id-alt';

    public $keywords = ['talent', 'dossier', 'carousel', 'profiles', 'ecommerce', 'candidates'];

    public $view = 'blocks.talent-dossier-carousel';

    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => false,
    ];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Meet Our Ecommerce Talent',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');
        $field = fn (string $key) => $hasGetField ? get_field($key) : null;

        return [
            'headline' => BlockDefaults::cleanText($field('headline') ?: ''),
            // 'grid' drops the scroll track for pages that show the same dossiers
            // stacked; 'carousel' is production's behaviour and stays the default.
            'layout' => (string) ($field('layout') ?: 'carousel'),
            'cards' => $this->cards((array) ($field('cards') ?: [])),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('talent_dossier_carousel_block');

        $fields
            ->addTextarea('headline', ['label' => 'Headline', 'rows' => 2])
            ->addSelect('layout', [
                'label' => 'Layout',
                'choices' => [
                    'carousel' => 'Scrolling carousel (default)',
                    'grid' => 'Wrapping grid',
                ],
                'default_value' => 'carousel',
            ])
            ->addRepeater('cards', [
                'label' => 'Dossier Cards',
                'layout' => 'block',
                'button_label' => 'Add dossier',
            ])
            ->addImage('photo', ['label' => 'Photo', 'return_format' => 'url'])
            ->addText('country', ['label' => 'Country'])
            ->addImage('flag', ['label' => 'Flag', 'return_format' => 'url'])
            ->addText('rate', ['label' => 'Rate', 'instructions' => 'e.g. $8/hour'])
            ->addText('name', ['label' => 'Name'])
            ->addText('role', ['label' => 'Role'])
            ->addText('years', ['label' => 'Years of Experience', 'instructions' => 'Number only — the label is added by the template.'])
            ->addTextarea('experience', ['label' => 'Experience', 'rows' => 4])
            ->addTextarea('skills', ['label' => 'Skills', 'rows' => 3, 'instructions' => 'Semicolon-delimited, reproduced verbatim.'])
            ->addTextarea('previous_companies', ['label' => 'Previous Companies', 'rows' => 2, 'instructions' => 'Comma-separated, reproduced verbatim.'])
            ->addRepeater('tools', [
                'label' => 'Tool Logos',
                'layout' => 'table',
                'button_label' => 'Add tool',
            ])
            ->addImage('src', ['label' => 'Logo', 'return_format' => 'url'])
            ->addText('alt', ['label' => 'Alt text'])
            ->endRepeater()
            ->endRepeater();

        return $fields->build();
    }

    /**
     * @param  array<int, mixed>  $cards
     * @return array<int, array<string, mixed>>
     */
    public function cards(array $cards): array
    {
        $cards = array_values(array_filter(
            $cards,
            fn ($card) => is_array($card) && ! empty($card['name']),
        ));

        return array_map(fn (array $card): array => [
            'photo' => BlockDefaults::preferWebp(BlockDefaults::resolveImageUrl($card['photo'] ?? '')),
            'country' => BlockDefaults::cleanText($card['country'] ?? ''),
            'flag' => BlockDefaults::resolveImageUrl($card['flag'] ?? ''),
            'rate' => BlockDefaults::cleanText($card['rate'] ?? ''),
            'name' => BlockDefaults::cleanText($card['name'] ?? ''),
            'role' => BlockDefaults::cleanText($card['role'] ?? ''),
            'years' => BlockDefaults::cleanText($card['years'] ?? ''),
            'experience' => BlockDefaults::cleanText($card['experience'] ?? ''),
            'skills' => BlockDefaults::cleanText($card['skills'] ?? ''),
            'previous_companies' => BlockDefaults::cleanText($card['previous_companies'] ?? ''),
            'tools' => $this->tools($card['tools'] ?? []),
        ], $cards);
    }

    /**
     * @return array<int, array{src: string, alt: string}>
     */
    protected function tools(mixed $tools): array
    {
        if (! is_array($tools)) {
            return [];
        }

        $mapped = array_map(fn ($tool): array => [
            'src' => BlockDefaults::preferWebp(BlockDefaults::resolveImageUrl(
                is_array($tool) ? ($tool['src'] ?? '') : $tool
            )),
            'alt' => is_array($tool) ? BlockDefaults::cleanText($tool['alt'] ?? '') : '',
        ], $tools);

        return array_values(array_filter($mapped, fn (array $tool): bool => $tool['src'] !== ''));
    }
}
