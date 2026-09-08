<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class TestimonialsBlock extends Block
{
    public $name = 'Client Testimonials';

    public $slug = 'testimonials';

    public $description = 'Video and quote client review cards with self-contained Vimeo playback modal.';

    public $category = 'remote-leverage';

    public $icon = 'format-quote';

    public $keywords = ['testimonials', 'reviews', 'video', 'case study', 'social proof'];

    public $view = 'blocks.testimonials';

    public function with(): array
    {
        return [
            'testimonials' => $this->testimonials(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('testimonials_block');

        $fields
            ->addRepeater('testimonials', [
                'label' => 'Testimonials List',
                'layout' => 'block',
                'button_label' => 'Add Testimonial',
            ])
            ->addText('company', [
                'label' => 'Company Name',
                'default_value' => 'Liberty Hill',
            ])
            ->addTextarea('quote', [
                'label' => 'Client Quote',
                'default_value' => '“If somebody were asking me why they should work with Remote Leverage, I would say it\'s because of the quality of the candidates.”',
                'rows' => 2,
            ])
            ->addUrl('video_url', [
                'label' => 'Vimeo Video URL',
                'default_value' => 'https://vimeo.com/1067577532',
            ])
            ->addImage('image', [
                'label' => 'Video Thumbnail Poster',
                'return_format' => 'url',
            ])
            ->addText('duration', [
                'label' => 'Duration (e.g. 01:21)',
                'default_value' => '01:21',
            ])
            ->endRepeater();

        return $fields->build();
    }

    public function testimonials(): array
    {
        $items = function_exists('get_field') ? get_field('testimonials') : null;

        if (! empty($items) && is_array($items)) {
            return $items;
        }

        $imgBase = get_template_directory_uri() . '/public/images/home';

        return [
            [
                'video_url' => 'https://vimeo.com/1067577208',
                'image' => $imgBase . '/PRES-Property-Management.jpg',
                'duration' => '00:38',
                'quote' => '“I can\'t say enought about how every step of the way it just wowed me.”',
                'company' => 'PRES Property Management',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577369',
                'image' => $imgBase . '/Coldwell-Banker.jpg',
                'duration' => '00:19',
                'quote' => '“I’m very impressed with the quality of my VA, she’s very intelligent and she aims to please.”',
                'company' => 'Coldwell Banker',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577665',
                'image' => $imgBase . '/Carbon-Solutions-Group.jpg',
                'duration' => '00:55',
                'quote' => '“I really recommend Remote Leverage; it was a fast process, and the results are good.”',
                'company' => 'Carbon Solutions Group',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577549',
                'image' => $imgBase . '/The-Zen-Zone-Wellness.jpg',
                'duration' => '04:06',
                'quote' => '“I got to talk to five amazing virtual assistants, and they all were good; it was kind of hard to make a choice at first.”',
                'company' => 'The Zen Zone Wellness',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577688',
                'image' => $imgBase . '/Color-Job.jpg',
                'duration' => '01:56',
                'quote' => '“The transition of working with you guys was absolutely smooth and amazing.”',
                'company' => 'Color Job',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577383',
                'image' => $imgBase . '/Connect-Church-Colorado.jpg',
                'duration' => '02:45',
                'quote' => '“She was just perfect, everything that we were looking for we found it in her.”',
                'company' => 'Connect Church Colorado',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577489',
                'image' => $imgBase . '/Cash-is-King.jpg',
                'duration' => '02:39',
                'quote' => '“Honestly, the reason why we keep hiring is because it is so incredibly easy.”',
                'company' => 'Cash is King',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577248',
                'image' => $imgBase . '/Liberty-Hill.jpg',
                'duration' => '02:31',
                'quote' => '“If somebody were asking me why they should work with Remote Leverage, I would say it\'s because of the quality of the candidates.”',
                'company' => 'Liberty Hill',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577464',
                'image' => $imgBase . '/RE-MAX.jpg',
                'duration' => '01:02',
                'quote' => '“Its been about a year and a half since I\'ve been with them so far, I would definitely say go for it, it\'s been a game changer for me.”',
                'company' => 'RE / MAX',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577620',
                'image' => $imgBase . '/Realty-One-Group.jpg',
                'duration' => '01:43',
                'quote' => '“As I look back, I was on the fence about it, It\'s probably one of the best decisions I ever made if not the best to help grow my business.”',
                'company' => 'Realty One Group',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577228',
                'image' => $imgBase . '/OneUp-Sportz-01.jpg',
                'duration' => '01:06',
                'quote' => '“Very Very happy with the system, you guys system worked well and it was efficient.”',
                'company' => 'OneUp Sportz',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577598',
                'image' => $imgBase . '/OneUp-Sportz.jpg',
                'duration' => '01:40',
                'quote' => '“It was a seamless process, all the applicants that we had they all had Masters in Marketing, which is awesome.”',
                'company' => 'OneUp Sportz',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577293',
                'image' => $imgBase . '/Greener-Hill-Psychiatric.jpg',
                'duration' => '05:59',
                'quote' => '“Remote Leverage, presented six candidates and I did interview all of those very in depth, and I thought all of them were phenomenal.”',
                'company' => 'Greener Hill Psychiatric',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577442',
                'image' => $imgBase . '/Diamond-Detox.jpg',
                'duration' => '00:43',
                'quote' => '“I\'m very impressed with the english, the capability, qualification, timeliness, they were all very timely, patient.”',
                'company' => 'Diamond Detox',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577645',
                'image' => $imgBase . '/Ad-Center-360.jpg',
                'duration' => '00:36',
                'quote' => '“It was awesome the best experience I\'ve ever had as far as hiring.”',
                'company' => 'Ad Center 360',
            ],
        ];
    }
}
