<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * Reproduces production's `/Service-Hiring/`, which is one Elementor HTML widget holding a
 * bare Calendly *inline* embed on a gradient band — heading, two paragraphs, iframe, nothing
 * else.
 *
 * This is deliberately NOT acf/booking or acf/vacalendar-hero. Both of those render the
 * in-house Livewire scheduler, which reproduces production's `calendly_multistep` Elementor
 * widget (the one on `/vacalendar/`) and picks its Calendly event type from the lead's
 * revenue tier via CalendlyEventTypeRoleResolver — `t0` or `t10`. `/Service-Hiring/` books a
 * different meeting entirely: the post-deposit "Onboarding + Applicant Criteria" event, which
 * has no role in that resolver. Routing it through the wizard would silently book the wrong
 * event type, so the embed is reproduced literally.
 *
 * acf/jotform-embed is the other third-party embed block and is JotForm-specific down to its
 * height protocol (it listens for JotForm's `setHeight:` postMessage); Calendly sizes its own
 * iframe from the container, so the two cannot share an implementation.
 *
 * The trade this carries: a booking made here goes straight to Calendly and does NOT pass
 * through CaptureLeadAction, so it does not appear in the leads admin or reach HubSpot until
 * the Calendly webhook fires. That is production's existing behaviour on this page.
 */
class CalendlyEmbedBlock extends Block
{
    public $name = 'Calendly Embed';

    public $slug = 'calendly-embed';

    public $description = 'Embeds a Calendly inline scheduling widget, with an optional heading and intro, on a coloured band.';

    public $category = 'remote-leverage';

    public $icon = 'calendar-alt';

    public $keywords = ['calendly', 'booking', 'calendar', 'scheduling', 'embed', 'consultation'];

    public $view = 'blocks.calendly-embed';

    public $supports = [
        'align' => ['full', 'wide'],
    ];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'heading' => 'Meeting With Hiring Manager',
                'background' => 'navy-violet',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        return [
            'embedUrl' => $this->embedUrl(),
            'heading' => BlockDefaults::cleanText((string) (get_field('heading') ?: '')),
            'paragraphs' => $this->paragraphs(),
            'background' => get_field('background') ?: 'navy-violet',
            'minHeight' => max(320, (int) (get_field('min_height') ?: 700)),
            'maxWidth' => max(0, (int) get_field('max_width')),
            'isPreview' => (bool) get_field('is_preview'),
        ];
    }

    /**
     * The URL is written straight into `data-url`, where Calendly's widget.js turns it into an
     * iframe src. Pinning the host to calendly.com keeps a mistyped or pasted field from
     * framing an arbitrary third-party origin inside the page.
     */
    protected function embedUrl(): string
    {
        $url = trim((string) (get_field('calendly_url') ?: ''));

        if ($url === '') {
            return '';
        }

        $parts = wp_parse_url($url);

        if (($parts['scheme'] ?? '') !== 'https') {
            return '';
        }

        $host = strtolower($parts['host'] ?? '');

        if ($host !== 'calendly.com' && ! str_ends_with($host, '.calendly.com')) {
            return '';
        }

        return esc_url_raw($url);
    }

    /**
     * Blank-line separated, so an editor writes the intro as prose rather than filling a
     * fixed `paragraph_1` / `paragraph_2` pair the way acf/vacalendar-hero does.
     *
     * @return array<int, string>
     */
    protected function paragraphs(): array
    {
        $intro = (string) (get_field('intro') ?: '');

        $chunks = preg_split('/\R{2,}/', $intro) ?: [];

        return array_values(array_filter(array_map(
            fn (string $chunk): string => BlockDefaults::cleanText(trim($chunk)),
            $chunks,
        ), fn (string $chunk): bool => $chunk !== ''));
    }

    public function fields(): array
    {
        $fields = Builder::make('calendly_embed_block');

        $fields
            ->addUrl('calendly_url', [
                'label' => 'Calendly URL',
                'instructions' => 'The full scheduling link, including any query string (for example <code>?hide_event_type_details=1&hide_gdpr_banner=1</code>). Must be an https calendly.com address.',
            ])
            ->addText('heading', [
                'label' => 'Heading',
                'instructions' => 'Shown above the embed. Leave blank to hide.',
            ])
            ->addTextarea('intro', [
                'label' => 'Intro copy',
                'rows' => 5,
                'instructions' => 'Separate paragraphs with a blank line.',
            ])
            ->addSelect('background', [
                'label' => 'Band background',
                'choices' => [
                    'navy-violet' => 'Navy → violet gradient (default)',
                    'dark-violet' => 'Dark violet',
                    'light' => 'Pale',
                    'transparent' => 'None',
                ],
                'default_value' => 'navy-violet',
            ])
            ->addNumber('min_height', [
                'label' => 'Embed height (px)',
                'default_value' => 700,
                'instructions' => 'Calendly sizes its iframe to this box, so it also reserves the space and stops the page jumping as the widget loads.',
            ])
            ->addNumber('max_width', [
                'label' => 'Embed max width (px)',
                'default_value' => 0,
                'instructions' => '0 fills the container width, which is what production does.',
            ]);

        return $fields->build();
    }
}
