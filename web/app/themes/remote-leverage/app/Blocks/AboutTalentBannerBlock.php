<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class AboutTalentBannerBlock extends Block
{
    /**
     * The block name.
     *
     * @var string
     */
    public $name = 'About Talent Banner';

    /**
     * The block slug.
     *
     * @var string
     */
    public $slug = 'about-talent-banner';

    /**
     * The block description.
     *
     * @var string
     */
    public $description = 'Full-width talent card grid banner with centered frosted-glass "Great talent changes everything" video pill.';

    /**
     * The block category.
     *
     * @var string
     */
    public $category = 'remote-leverage';

    /**
     * The block icon.
     *
     * @var string|array
     */
    public $icon = 'video-alt3';

    /**
     * The default block mode.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * The default block alignment.
     *
     * @var string
     */
    public $align = 'full';

    public $view = 'blocks.about-talent-banner';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'banner_text' => 'Great talent changes everything',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $bgImage = get_field('background_image');
        $resolvedBg = ! empty($bgImage)
            ? BlockDefaults::resolveImageUrl($bgImage)
            : BlockDefaults::resolveImageUrl(BlockDefaults::getAttachmentId('Frame-1130-1.jpg'));

        return [
            'bannerText' => BlockDefaults::cleanText(get_field('banner_text') ?: 'Great talent changes everything'),
            'backgroundImage' => $resolvedBg,
            'videoUrl' => BlockDefaults::cleanText(get_field('video_url') ?: ''),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('about_talent_banner_block');

        $fields
            ->addText('banner_text', [
                'label' => 'Banner Frosted Pill Text',
                'default_value' => 'Great talent changes everything',
            ])
            ->addImage('background_image', [
                'label' => 'Background Talent Grid Image',
                'return_format' => 'url',
            ])
            ->addText('video_url', [
                'label' => 'Video Modal URL (Optional)',
                'default_value' => '',
            ]);

        return $fields->build();
    }
}
