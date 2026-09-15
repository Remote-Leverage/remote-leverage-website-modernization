<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * Production's /services/ page shell (page 26095).
 *
 * Production paints ONE #342567 → #6200A4 vertical gradient band behind the whole page body
 * and stacks white rounded cards inside it with a 20px gap. That is why this is a stack block
 * rather than one block per card: four independent section blocks would each restart the
 * gradient and show a seam at every join.
 *
 * Each card is either a single centred column (the onboarding-guide card) or a two-column
 * split divided by a hairline, with prose + an orange CTA on the left and a stack of
 * gradient "offer pills" plus an optional price footnote on the right. `widget` slots the
 * bundle finance calculator into the left column; it defaults to none so every other card
 * keeps today's behaviour.
 */
class OfferStackBlock extends Block
{
    public $name = 'Offer Stack';

    public $slug = 'offer-stack';

    public $description = 'A gradient band holding a stack of white offer cards — prose and a CTA beside gradient offer pills.';

    public $category = 'remote-leverage';

    public $icon = 'money-alt';

    public $keywords = ['offer', 'bundle', 'pricing', 'upsell', 'services', 'calculator'];

    public $view = 'blocks.offer-stack';

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
            'cards' => $this->cards($field('cards')),
        ];
    }

    /**
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

            $pills = [];

            foreach (is_array($card['pills'] ?? null) ? $card['pills'] : [] as $pill) {
                $text = is_array($pill) ? (string) ($pill['text'] ?? '') : (string) $pill;

                if (trim($text) !== '') {
                    $pills[] = $text;
                }
            }

            $normalised[] = [
                'headline' => BlockDefaults::cleanText((string) ($card['headline'] ?? '')),
                'body' => (string) ($card['body'] ?? ''),
                'ctaText' => BlockDefaults::cleanText((string) ($card['cta_text'] ?? '')),
                'ctaUrl' => (string) ($card['cta_url'] ?? ''),
                'ctaNewTab' => (bool) ($card['cta_new_tab'] ?? false),
                'layout' => ($card['layout'] ?? 'single') === 'split' ? 'split' : 'single',
                'widget' => ($card['widget'] ?? '') === 'bundle-calculator' ? 'bundle-calculator' : '',
                'pills' => $pills,
                // Production drops the bundle card's pill column 88px so the first pill clears
                // the heading beside it. Every other card starts its pills flush with the top.
                'pillsOffset' => (bool) ($card['pills_offset'] ?? false),
                // Production's guarantee and performance cards each carry a 520×347 photo
                // between the pill and the price. It sits behind an Elementor entrance
                // animation, so it reads as empty white space in any capture that scrolls
                // back to the top before shooting.
                'image' => BlockDefaults::preferWebp(BlockDefaults::resolveImageUrl($card['image'] ?? '')),
                'footnote' => (string) ($card['footnote'] ?? ''),
            ];
        }

        return $normalised;
    }

    public function fields(): array
    {
        $fields = Builder::make('offer_stack_block');

        $fields
            ->addRepeater('cards', [
                'label' => 'Cards',
                'layout' => 'block',
                'button_label' => 'Add card',
            ])
            ->addTextarea('headline', ['label' => 'Headline', 'rows' => 2])
            ->addWysiwyg('body', ['label' => 'Body', 'tabs' => 'visual', 'media_upload' => 0])
            ->addSelect('layout', [
                'label' => 'Layout',
                'choices' => [
                    'single' => 'Single centred column (default)',
                    'split' => 'Two columns — copy left, offer pills right',
                ],
                'default_value' => 'single',
            ])
            ->addSelect('widget', [
                'label' => 'Embedded widget',
                'choices' => [
                    '' => 'None (default)',
                    'bundle-calculator' => 'Bundle finance calculator',
                ],
                'default_value' => '',
                'allow_null' => true,
            ])
            ->addRepeater('pills', [
                'label' => 'Offer pills (right column)',
                'layout' => 'table',
                'button_label' => 'Add pill',
            ])
            ->addTextarea('text', ['label' => 'Text', 'rows' => 2])
            ->endRepeater()
            ->addImage('image', [
                'label' => 'Image (right column, under the pills)',
                'return_format' => 'url',
            ])
            ->addTrueFalse('pills_offset', [
                'label' => 'Drop the pill column to clear the heading',
                'default_value' => 0,
                'ui' => 1,
            ])
            ->addWysiwyg('footnote', [
                'label' => 'Footnote (bottom of the right column)',
                'tabs' => 'visual',
                'media_upload' => 0,
            ])
            ->addText('cta_text', ['label' => 'CTA text', 'instructions' => 'Leave blank to hide.'])
            ->addUrl('cta_url', ['label' => 'CTA URL'])
            ->addTrueFalse('cta_new_tab', ['label' => 'Open CTA in a new tab', 'default_value' => 0, 'ui' => 1])
            ->endRepeater();

        return $fields->build();
    }
}
