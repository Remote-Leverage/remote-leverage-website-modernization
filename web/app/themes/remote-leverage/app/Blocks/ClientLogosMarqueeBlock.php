<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ClientLogosMarqueeBlock extends Block
{
    public $name = 'Client Logos Marquee';

    public $slug = 'client-logos-marquee';

    public $description = 'Infinite marquee ticker of verified client and partner logos.';

    public $category = 'remote-leverage';

    public $icon = 'slides';

    public $keywords = ['logos', 'marquee', 'clients', 'ticker', 'partners'];

    public $view = 'blocks.client-logos-marquee';

    public function with(): array
    {
        return [
            'logos' => $this->logos(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('client_logos_marquee_block');

        $fields
            ->addRepeater('logos', [
                'label' => 'Client Logos (Leave empty for default logos)',
                'layout' => 'table',
                'button_label' => 'Add Logo',
            ])
            ->addImage('src', ['label' => 'Logo Image', 'return_format' => 'url'])
            ->addText('alt', ['label' => 'Company Name'])
            ->endRepeater();

        return $fields->build();
    }

    public function logos(): array
    {
        $custom = function_exists('get_field') ? get_field('logos') : null;
        $logos = (! empty($custom) && is_array($custom))
            ? $custom
            : \App\Support\BlockDefaults::logos();

        return array_map(function ($logo) {
            $logo['src'] = \App\Support\BlockDefaults::resolveImageUrl($logo['src'] ?? '');
            return $logo;
        }, $logos);
    }
}
