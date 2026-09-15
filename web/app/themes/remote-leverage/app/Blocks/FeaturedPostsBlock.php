<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * Production's "Featured Content" band (/ecommerce-virtual-assistant/ §15).
 *
 * Field naming note: ACF Composer derives a repeater sub-field key as
 * `field_<group>_<repeater>_<sub>`, so no top-level field here is named
 * `cards_*` — that would silently blank the matching `cards` sub-field.
 */
class FeaturedPostsBlock extends Block
{
    public $name = 'Featured Posts';

    public $slug = 'featured-posts';

    public $description = 'Carousel of blog post cards — hand-picked, or pulled live from a category.';

    public $category = 'remote-leverage';

    public $icon = 'admin-post';

    public $keywords = ['posts', 'blog', 'featured', 'content', 'carousel'];

    public $view = 'blocks.featured-posts';

    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => false,
    ];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Featured Content',
                'subheadline' => 'Latest Posts',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');
        $field = fn (string $key) => $hasGetField ? get_field($key) : null;

        $manual = $this->normaliseCards((array) ($field('cards') ?: []));
        $source = (string) ($field('source') ?: 'manual');

        $cards = $manual;

        if ($source === 'query') {
            $queried = $this->queryCards(
                (string) ($field('category') ?: ''),
                (int) ($field('count') ?: 4),
            );

            // An empty query is far more likely to be a mistyped category than an
            // intentionally empty band, so the hand-picked cards stay as the floor.
            $cards = $queried !== [] ? $queried : $manual;
        }

        return [
            'headline' => BlockDefaults::cleanText($field('headline') ?: ''),
            'subheadline' => BlockDefaults::cleanText($field('subheadline') ?: ''),
            'tone' => (string) ($field('tone') ?: 'dark'),
            'layout' => (string) ($field('layout') ?: 'carousel'),
            'cards' => $cards,
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('featured_posts_block');

        $fields
            ->addText('headline', ['label' => 'Headline', 'default_value' => 'Featured Content'])
            ->addText('subheadline', ['label' => 'Subheadline', 'default_value' => 'Latest Posts'])
            ->addSelect('tone', [
                'label' => 'Surface',
                'choices' => [
                    'dark' => 'Black (default)',
                    'light' => 'Pale',
                ],
                'default_value' => 'dark',
            ])
            ->addSelect('source', [
                'label' => 'Card source',
                'choices' => [
                    'manual' => 'Hand-picked cards (default)',
                    'query' => 'Latest published posts',
                ],
                'default_value' => 'manual',
            ])
            ->addText('category', [
                'label' => 'Category slug',
                'instructions' => 'Used when the source is "Latest published posts". Leave blank for all categories.',
            ])
            ->conditional('source', '==', 'query')
            ->addNumber('count', [
                'label' => 'How many posts',
                'default_value' => 4,
                'min' => 1,
            ])
            ->conditional('source', '==', 'query')
            ->addSelect('layout', [
                'label' => 'Layout',
                'choices' => [
                    'carousel' => 'Scrolling carousel (default)',
                    'grid' => 'Wrapping grid',
                ],
                'default_value' => 'carousel',
            ])
            ->addRepeater('cards', [
                'label' => 'Cards',
                'layout' => 'block',
                'button_label' => 'Add card',
            ])
            ->addImage('image', ['label' => 'Image', 'return_format' => 'url'])
            ->addText('title', ['label' => 'Title'])
            ->addUrl('url', ['label' => 'URL'])
            ->endRepeater();

        return $fields->build();
    }

    /**
     * @param  array<int, mixed>  $cards
     * @return array<int, array{image: string, title: string, url: string}>
     */
    public function normaliseCards(array $cards): array
    {
        $cards = array_values(array_filter(
            $cards,
            fn ($card) => is_array($card) && ! empty($card['title']),
        ));

        return array_map(fn (array $card): array => [
            'image' => BlockDefaults::preferWebp(BlockDefaults::resolveImageUrl($card['image'] ?? '')),
            'title' => BlockDefaults::cleanText($card['title'] ?? ''),
            'url' => (string) ($card['url'] ?? ''),
        ], $cards);
    }

    /**
     * Published posts mapped onto the same card shape the repeater produces, so the
     * view never has to know which source it is rendering.
     *
     * @return array<int, array{image: string, title: string, url: string}>
     */
    public function queryCards(string $category, int $count): array
    {
        if (! class_exists(\WP_Query::class) || ! function_exists('get_permalink')) {
            return [];
        }

        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => max(1, $count),
            'orderby' => 'date',
            'order' => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
        ];

        if ($category !== '') {
            $args['category_name'] = $category;
        }

        $query = new \WP_Query($args);
        $cards = [];

        foreach ($query->posts ?? [] as $post) {
            $postId = (int) ($post->ID ?? 0);

            if ($postId === 0) {
                continue;
            }

            $thumbnail = function_exists('get_the_post_thumbnail_url')
                ? (string) (get_the_post_thumbnail_url($postId, 'medium_large') ?: '')
                : '';

            $cards[] = [
                'image' => BlockDefaults::preferWebp($thumbnail),
                'title' => BlockDefaults::cleanText(
                    function_exists('get_the_title') ? get_the_title($postId) : ($post->post_title ?? '')
                ),
                'url' => (string) get_permalink($postId),
            ];
        }

        if (function_exists('wp_reset_postdata')) {
            wp_reset_postdata();
        }

        return $cards;
    }
}
