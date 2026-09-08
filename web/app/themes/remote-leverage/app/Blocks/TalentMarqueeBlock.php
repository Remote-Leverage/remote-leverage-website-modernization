<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class TalentMarqueeBlock extends Block
{
    public $name = 'Talent Marquee';

    public $slug = 'talent-marquee';

    public $description = 'Infinite marquee of pre-vetted Latin American talent profile cards.';

    public $category = 'remote-leverage';

    public $icon = 'groups';

    public $keywords = ['talent', 'marquee', 'profiles', 'staffing', 'candidates'];

    public $view = 'blocks.talent-marquee';

    public $supports = [
        'align' => ['full', 'wide'],
    ];

    public function with(): array
    {
        return [
            'cards' => $this->cards(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('talent_marquee_block');

        $fields
            ->addRepeater('talent_cards', [
                'label' => 'Talent Cards (Leave empty for default 11 cards)',
                'layout' => 'block',
                'button_label' => 'Add Talent Profile',
            ])
            ->addText('name', ['label' => 'Full Name'])
            ->addText('title', ['label' => 'Job Title'])
            ->addTextarea('desc', ['label' => 'Bio / Description', 'rows' => 2])
            ->addImage('bg', ['label' => 'Photo Background', 'return_format' => 'url'])
            ->addImage('logo', ['label' => 'Prior Company Logo', 'return_format' => 'url'])
            ->endRepeater();

        return $fields->build();
    }

    public function cards(): array
    {
        $custom = function_exists('get_field') ? get_field('talent_cards') : null;
        if (! empty($custom) && is_array($custom)) {
            return $custom;
        }

        $imgBase = get_template_directory_uri() . '/public/images/home';

        return [
            [
                'name' => 'Daniela Costa',
                'title' => 'Executive Assistant',
                'desc' => 'Experienced Executive Assistant specializing in executive support, meeting coordination, travel planning, and operational workflows. Known for exceptional organization.',
                'logo' => $imgBase . '/rappi_logo-Small.webp',
                'bg' => $imgBase . '/Frame-132-1.webp',
            ],
            [
                'name' => 'Lucas Mendes',
                'title' => 'Marketing Manager',
                'desc' => 'Marketing Manager with 8+ years of experience across demand generation, paid acquisition, lifecycle marketing, and funnel optimization with proven track record scaling pipeline.',
                'logo' => $imgBase . '/clickup.webp',
                'bg' => $imgBase . '/Frame-133-1.webp',
            ],
            [
                'name' => 'Noah Martinez',
                'title' => 'Sales Representative',
                'desc' => 'Sales Development Representative who consistently exceeded quota by building high-quality outbound pipelines for B2B software companies.',
                'logo' => $imgBase . '/image-2.webp',
                'bg' => $imgBase . '/Frame-135-1.webp',
            ],
            [
                'name' => 'Diego Navarro',
                'title' => 'Sales Representative',
                'desc' => 'Revenue-focused sales representative experienced in outbound prospecting, product demonstrations, and account management to convert qualified leads.',
                'logo' => $imgBase . '/image-11.webp',
                'bg' => $imgBase . '/Frame-135-2.webp',
            ],
            [
                'name' => 'André Vilalobos',
                'title' => 'Graphic Designer',
                'desc' => '6+ years of experience helping brands of all sizes, from small and mid-sized businesses to big companies, look professional, polished, and unmistakably them.',
                'logo' => $imgBase . '/State-Farm-01.webp',
                'bg' => $imgBase . '/con-07.webp',
            ],
            [
                'name' => 'Juliana Silva',
                'title' => 'Lead Generation (SDR)',
                'desc' => '6+ years of experience as an SDR, skilled in prospecting, active listening, clear communication, time management, and handling rejection to consistently generate and qualify sales leads.',
                'logo' => $imgBase . '/mercado.webp',
                'bg' => $imgBase . '/cont-02.webp',
            ],
            [
                'name' => 'Valeria Andrea',
                'title' => 'Medical Assistant',
                'desc' => '4+ years of experience in fast-paced clinic and hospital settings. Skilled in EMR systems (Epic, Cerner), patient intake, vital signs, and assisting physicians with exams and procedures.',
                'logo' => $imgBase . '/Allstate-01.webp',
                'bg' => $imgBase . '/con-05.webp',
            ],
            [
                'name' => 'Laura Valentina',
                'title' => 'Customer Support',
                'desc' => '+4 years in B2B SaaS customer support, I\'ve supported customers in North America, Europe, and Latin America, adapting to different cultural expectations and communication styles.',
                'logo' => $imgBase . '/image-12-1.webp',
                'bg' => $imgBase . '/con-08.webp',
            ],
            [
                'name' => 'Sofía Pérez',
                'title' => 'Marketing Assistant',
                'desc' => '4+ years of experience as a results-driven marketing professional, skilled in content creation, social media strategy, campaign management, and data analysis to drive brand awareness.',
                'logo' => $imgBase . '/Frame-74-1.webp',
                'bg' => $imgBase . '/cont-03.webp',
            ],
            [
                'name' => 'Luana Dias',
                'title' => 'Executive Assistant',
                'desc' => '3+ years of experience supporting C-level executives in fast-paced environments. High organization, anticipate needs, and protect executive\'s time like it\'s my own.',
                'logo' => $imgBase . '/NU-bank-01.webp',
                'bg' => $imgBase . '/con-06.webp',
            ],
            [
                'name' => 'Sarah Martinez',
                'title' => 'Sr Executive Assistant',
                'desc' => 'Executive Assistant with 8+ years supporting founders and executives. Expert in calendar management, inbox organization, project coordination, and keeping fast-growing teams operating smoothly.',
                'logo' => $imgBase . '/1655873088shopify-logo-transparent.webp',
                'bg' => $imgBase . '/Frame-131-1.webp',
            ],
        ];
    }
}
