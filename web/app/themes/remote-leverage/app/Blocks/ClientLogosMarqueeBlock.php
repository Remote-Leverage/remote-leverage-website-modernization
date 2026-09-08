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
        if (! empty($custom) && is_array($custom)) {
            return $custom;
        }

        $imgBase = get_template_directory_uri() . '/public/images/home';

        return [
            ['src' => $imgBase . '/brrrr-1.webp', 'alt' => 'BRRRR'],
            ['src' => $imgBase . '/carbon-1.webp', 'alt' => 'Carbon Solutions'],
            ['src' => $imgBase . '/Q-BitNewLogo-Photoroom-1.webp', 'alt' => 'Q-Bit'],
            ['src' => $imgBase . '/boe-1.webp', 'alt' => 'BOE'],
            ['src' => $imgBase . '/greener-hill-1.webp', 'alt' => 'Greener Hill'],
            ['src' => $imgBase . '/Prestige-Landscaping-1.webp', 'alt' => 'Prestige Landscaping'],
            ['src' => $imgBase . '/garuz-1-1.webp', 'alt' => 'Garuz'],
            ['src' => $imgBase . '/vercasa-1.webp', 'alt' => 'Vercasa'],
            ['src' => $imgBase . '/adcenter-2.webp', 'alt' => 'Ad Center 360'],
            ['src' => $imgBase . '/liberty-hill-1.webp', 'alt' => 'Liberty Hill'],
            ['src' => $imgBase . '/chick-fil-a-logo-1.webp', 'alt' => 'Chick-fil-A'],
            ['src' => $imgBase . '/rl-adp.webp', 'alt' => 'ADP'],
            ['src' => $imgBase . '/rl-mainstreet.webp', 'alt' => 'Mainstreet'],
            ['src' => $imgBase . '/rl-farmers.webp', 'alt' => 'Farmers Insurance'],
            ['src' => $imgBase . '/rl-college-hunks.webp', 'alt' => 'College Hunks'],
            ['src' => $imgBase . '/remax-1.webp', 'alt' => 'RE/MAX'],
            ['src' => $imgBase . '/coldwell-1.webp', 'alt' => 'Coldwell Banker'],
            ['src' => $imgBase . '/sivia-law-white-306w-1.webp', 'alt' => 'Sivia Law'],
            ['src' => $imgBase . '/zone-4-1.webp', 'alt' => 'Zone 4'],
        ];
    }
}
