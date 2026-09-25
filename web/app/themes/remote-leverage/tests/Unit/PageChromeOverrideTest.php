<?php

declare(strict_types=1);

use App\Fields\PageChromeFields;
use App\Support\PageChrome;

/*
 * The Header & Footer sidebar panel overrides PageChrome's content-derived choice. Auto has
 * to leave every existing page exactly as it was, and anything else has to win outright —
 * over the content, and over the no-header Landing template.
 */

$ctaContent = '<!-- wp:acf/hire-va-hero {"name":"acf/hire-va-hero","data":{},"mode":"preview"} /-->';
$plainContent = '<!-- wp:heading --><h2>Pricing</h2><!-- /wp:heading -->';

describe('header', function () use ($ctaContent, $plainContent) {
    it('derives the header from the content on Auto', function () use ($ctaContent, $plainContent) {
        expect(PageChrome::resolveHeader(PageChrome::AUTO, false, $ctaContent))->toBe(PageChrome::HEADER_CTA)
            ->and(PageChrome::resolveHeader(PageChrome::AUTO, false, $plainContent))->toBe(PageChrome::HEADER_SITE)
            ->and(PageChrome::resolveHeader(PageChrome::AUTO, false, '<!-- rl:cta-only-header -->'))->toBe(PageChrome::HEADER_CTA);
    });

    it('keeps the Landing template headerless on Auto', function () use ($ctaContent) {
        expect(PageChrome::resolveHeader(PageChrome::AUTO, true, $ctaContent))->toBe(PageChrome::HEADER_NONE);
    });

    it('lets the editor choice beat the content and the template', function () use ($ctaContent, $plainContent) {
        expect(PageChrome::resolveHeader(PageChrome::HEADER_SITE, false, $ctaContent))->toBe(PageChrome::HEADER_SITE)
            ->and(PageChrome::resolveHeader(PageChrome::HEADER_CTA, false, $plainContent))->toBe(PageChrome::HEADER_CTA)
            ->and(PageChrome::resolveHeader(PageChrome::HEADER_NONE, false, $plainContent))->toBe(PageChrome::HEADER_NONE)
            ->and(PageChrome::resolveHeader(PageChrome::HEADER_SITE, true, $plainContent))->toBe(PageChrome::HEADER_SITE);
    });

    it('treats an unknown stored value as Auto', function () use ($ctaContent) {
        expect(PageChrome::resolveHeader('overlay', false, $ctaContent))->toBe(PageChrome::HEADER_CTA);
    });
});

describe('footer', function () use ($plainContent) {
    it('derives the footer from the content on Auto', function () use ($plainContent) {
        expect(PageChrome::resolveFooter(PageChrome::AUTO, $plainContent))->toBe(PageChrome::FOOTER_SLIM)
            ->and(PageChrome::resolveFooter(PageChrome::AUTO, '<!-- rl:full-footer -->'))->toBe(PageChrome::FOOTER_FULL);
    });

    it('lets the editor choice beat the content', function () use ($plainContent) {
        expect(PageChrome::resolveFooter(PageChrome::FOOTER_FULL, $plainContent))->toBe(PageChrome::FOOTER_FULL)
            ->and(PageChrome::resolveFooter(PageChrome::FOOTER_SLIM, '<!-- rl:full-footer -->'))->toBe(PageChrome::FOOTER_SLIM);
    });
});

describe('sidebar panel', function () {
    $group = fn () => (new ReflectionClass(PageChromeFields::class))->newInstanceWithoutConstructor()->fields();

    it('sits in the sidebar of the page editor', function () use ($group) {
        $built = $group();

        expect($built['position'])->toBe('side')
            ->and($built['location'][0][0])->toMatchArray(['param' => 'post_type', 'operator' => '==', 'value' => 'page']);
    });

    it('stores exactly the values PageChrome resolves, defaulting to Auto', function () use ($group) {
        $byName = array_column($group()['fields'], null, 'name');

        expect(array_keys($byName[PageChrome::HEADER_FIELD]['choices']))
            ->toBe([PageChrome::AUTO, PageChrome::HEADER_SITE, PageChrome::HEADER_CTA, PageChrome::HEADER_NONE])
            ->and(array_keys($byName[PageChrome::FOOTER_FIELD]['choices']))
            ->toBe([PageChrome::AUTO, PageChrome::FOOTER_SLIM, PageChrome::FOOTER_FULL])
            ->and($byName[PageChrome::HEADER_FIELD]['default_value'])->toBe(PageChrome::AUTO)
            ->and($byName[PageChrome::FOOTER_FIELD]['default_value'])->toBe(PageChrome::AUTO);
    });
});
