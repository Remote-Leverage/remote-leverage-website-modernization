<?php

declare(strict_types=1);

namespace App\Fields;

use App\Support\PageChrome;
use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Field;

/**
 * Header & Footer panel in the page editor sidebar.
 *
 * Overrides App\Support\PageChrome's content-derived choice for one page. Both default to
 * Auto, which leaves every existing page exactly as it renders today.
 */
class PageChromeFields extends Field
{
    /**
     * The field group.
     */
    public function fields(): array
    {
        $fields = Builder::make('page_chrome', [
            'title' => 'Header & Footer',
            'position' => 'side',
        ]);

        $fields->setLocation('post_type', '==', 'page');

        $fields->addSelect(PageChrome::HEADER_FIELD, [
            'label' => 'Header',
            'instructions' => 'Auto uses the CTA-only header on hire-va and consult landing pages, and the site header everywhere else.',
            'choices' => [
                PageChrome::AUTO => 'Auto',
                PageChrome::HEADER_SITE => 'Site header (logo + nav)',
                PageChrome::HEADER_CTA => 'CTA-only (logo + button, no nav)',
                PageChrome::HEADER_NONE => 'None',
            ],
            'default_value' => PageChrome::AUTO,
        ]);

        $fields->addSelect(PageChrome::FOOTER_FIELD, [
            'label' => 'Footer',
            'instructions' => 'Auto uses the slim footer unless the page asks for the full one.',
            'choices' => [
                PageChrome::AUTO => 'Auto',
                PageChrome::FOOTER_SLIM => 'Slim',
                PageChrome::FOOTER_FULL => 'Full (four columns)',
            ],
            'default_value' => PageChrome::AUTO,
        ]);

        return $fields->build();
    }
}
