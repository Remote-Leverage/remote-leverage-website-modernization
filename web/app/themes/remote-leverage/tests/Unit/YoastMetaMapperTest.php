<?php

declare(strict_types=1);

use App\Domains\ContentAudit\Services\YoastMetaMapper;

const YOAST_PROD = 'https://remoteleverage.com';
const YOAST_LOCAL = 'https://remoteleverage-v2.test';

function yoastMapper(array $siteDefaults = []): YoastMetaMapper
{
    return new YoastMetaMapper(YOAST_PROD, YOAST_LOCAL, $siteDefaults);
}

/**
 * Shaped after a real `yoast_head_json` object from production.
 */
function yoastHead(array $overrides = []): array
{
    return array_replace([
        'title' => 'Hire talent for 70% less - Remote Leverage',
        'description' => 'Top 1% talent from Latin America for 70% less than U.S. employees.',
        'robots' => [
            'index' => 'index',
            'follow' => 'follow',
            'max-snippet' => 'max-snippet:-1',
            'max-image-preview' => 'max-image-preview:large',
            'max-video-preview' => 'max-video-preview:-1',
        ],
        'canonical' => YOAST_PROD.'/hire-us-uk-now/',
        'og_locale' => 'en_US',
        'og_title' => 'Hire VA',
        'og_description' => 'Save up to 70% on staffing costs with Remote Leverage.',
        'og_site_name' => 'Remote Leverage',
        'og_image' => [['url' => YOAST_PROD.'/wp-content/uploads/2026/03/image-9.png', 'width' => '', 'height' => '']],
        'twitter_card' => 'summary_large_image',
        'twitter_site' => '@Remote_Leverage',
        'schema' => ['@context' => 'https://schema.org', '@graph' => []],
    ], $overrides);
}

describe('canonical rewriting', function () {
    it('moves a production canonical onto the target origin, path intact', function () {
        expect(yoastMapper()->rewriteUrl(YOAST_PROD.'/case-study/chick-fil-a/'))
            ->toBe(YOAST_LOCAL.'/case-study/chick-fil-a/');
    });

    it('gives a bare production origin a root path', function () {
        // Production consolidates its duplicate landing pages onto the homepage,
        // and writes that canonical without a trailing slash.
        expect(yoastMapper()->rewriteUrl(YOAST_PROD))->toBe(YOAST_LOCAL.'/');
    });

    it('keeps the query string and fragment', function () {
        expect(yoastMapper()->rewriteUrl(YOAST_PROD.'/blog/?page=2#top'))
            ->toBe(YOAST_LOCAL.'/blog/?page=2#top');
    });

    it('treats www and the bare host as the same site', function () {
        expect(yoastMapper()->rewriteUrl('https://www.remoteleverage.com/pricing/'))
            ->toBe(YOAST_LOCAL.'/pricing/');
    });

    it('rewrites regardless of the production scheme', function () {
        expect(yoastMapper()->rewriteUrl('http://remoteleverage.com/pricing/'))
            ->toBe(YOAST_LOCAL.'/pricing/');
    });

    it('leaves a genuinely external canonical alone', function () {
        expect(yoastMapper()->rewriteUrl('https://medium.com/@remoteleverage/post'))
            ->toBe('https://medium.com/@remoteleverage/post');
    });

    it('returns an empty string for an empty canonical', function () {
        expect(yoastMapper()->rewriteUrl(''))->toBe('');
    });
});

describe('meta extraction', function () {
    it('never leaves a production host in the canonical it writes', function () {
        $meta = yoastMapper()->map(yoastHead());

        expect($meta['_yoast_wpseo_canonical'])->toBe(YOAST_LOCAL.'/hire-us-uk-now/')
            ->and($meta['_yoast_wpseo_canonical'])->not->toContain('remoteleverage.com/hire');
    });

    it('carries the title and meta description', function () {
        $meta = yoastMapper()->map(yoastHead());

        expect($meta['_yoast_wpseo_title'])->toBe('Hire talent for 70% less - Remote Leverage')
            ->and($meta['_yoast_wpseo_metadesc'])
            ->toBe('Top 1% talent from Latin America for 70% less than U.S. employees.');
    });

    it('decodes the HTML entities REST returns', function () {
        $meta = yoastMapper()->map(yoastHead([
            'title' => 'Cold Calling Script &#8211; Free Download',
            'description' => 'Sales &amp; support talent',
        ]));

        expect($meta['_yoast_wpseo_title'])->toBe('Cold Calling Script – Free Download')
            ->and($meta['_yoast_wpseo_metadesc'])->toBe('Sales & support talent');
    });

    it('writes no robots keys when the page is index,follow', function () {
        // Yoast's default is '0' = "use the post type setting", so an indexable
        // page is expressed by the absence of the key, not by writing '2'.
        expect(yoastMapper()->map(yoastHead()))
            ->not->toHaveKey('_yoast_wpseo_meta-robots-noindex')
            ->not->toHaveKey('_yoast_wpseo_meta-robots-nofollow');
    });

    it('flags noindex and nofollow', function () {
        $meta = yoastMapper()->map(yoastHead(['robots' => ['index' => 'noindex', 'follow' => 'nofollow']]));

        expect($meta['_yoast_wpseo_meta-robots-noindex'])->toBe('1')
            ->and($meta['_yoast_wpseo_meta-robots-nofollow'])->toBe('1');
    });

    it('ignores the site-wide max-* directives but keeps the advanced ones', function () {
        $meta = yoastMapper()->map(yoastHead(['robots' => [
            'index' => 'index',
            'follow' => 'follow',
            'max-snippet' => 'max-snippet:-1',
            'noarchive' => 'noarchive',
            'noimageindex' => 'noimageindex',
        ]]));

        expect($meta['_yoast_wpseo_meta-robots-adv'])->toBe('noimageindex,noarchive');
    });

    it('omits the canonical Yoast withholds on a noindex URL', function () {
        // Production drops `canonical` from the head object whenever the URL is
        // noindex — that is 28 of its pages, and not a missing canonical.
        $head = yoastHead(['robots' => ['index' => 'noindex', 'follow' => 'follow']]);
        unset($head['canonical']);

        expect(yoastMapper()->map($head))->not->toHaveKey('_yoast_wpseo_canonical');
    });

    it('carries an OpenGraph title that differs from both titles', function () {
        expect(yoastMapper()->map(yoastHead(), 'Hire VA Landing'))
            ->toHaveKey('_yoast_wpseo_opengraph-title', 'Hire VA');
    });

    it('drops an OpenGraph title that is only Yoast echoing the post title', function () {
        expect(yoastMapper()->map(yoastHead(['og_title' => 'Hire VA Landing']), 'Hire VA Landing'))
            ->not->toHaveKey('_yoast_wpseo_opengraph-title');
    });

    it('drops an OpenGraph description that is only the meta description again', function () {
        $head = yoastHead(['og_description' => yoastHead()['description']]);

        expect(yoastMapper()->map($head))->not->toHaveKey('_yoast_wpseo_opengraph-description');
    });

    it('takes the first OpenGraph image and leaves it on the production origin', function () {
        // Bedrock serves uploads from /app/uploads/, so a rewritten media URL
        // would 404 where the production one still resolves.
        expect(yoastMapper()->map(yoastHead()))->toHaveKey(
            '_yoast_wpseo_opengraph-image',
            YOAST_PROD.'/wp-content/uploads/2026/03/image-9.png',
        );
    });

    it('carries the twitter overrides Yoast only renders when they diverge', function () {
        $meta = yoastMapper()->map(yoastHead([
            'twitter_title' => 'Executive Virtual Assistants',
            'twitter_description' => 'Hire an EA for 70% less.',
            'twitter_image' => YOAST_PROD.'/wp-content/uploads/2025/04/Card.png',
        ]));

        expect($meta['_yoast_wpseo_twitter-title'])->toBe('Executive Virtual Assistants')
            ->and($meta['_yoast_wpseo_twitter-description'])->toBe('Hire an EA for 70% less.')
            ->and($meta['_yoast_wpseo_twitter-image'])->toBe(YOAST_PROD.'/wp-content/uploads/2025/04/Card.png');
    });

    it('produces only keys the mapper owns, so the importer can converge', function () {
        $meta = yoastMapper()->map(yoastHead(['twitter_title' => 'X']), 'Post');

        expect(array_diff(array_keys($meta), YoastMetaMapper::META_KEYS))->toBe([]);
    });

    it('is a pure function of its input', function () {
        expect(yoastMapper()->map(yoastHead(), 'Post'))->toBe(yoastMapper()->map(yoastHead(), 'Post'));
    });
});

describe('site default detection', function () {
    $corpus = function (int $withDefault, int $withOwn): array {
        $heads = [];

        for ($i = 0; $i < $withDefault; $i++) {
            $heads[] = yoastHead(['og_description' => 'Save up to 70% on staffing costs with Remote Leverage.']);
        }

        for ($i = 0; $i < $withOwn; $i++) {
            $heads[] = yoastHead(['og_description' => "Unique editorial line {$i}."]);
        }

        return $heads;
    };

    it('spots the post-type social template repeated across the corpus', function () use ($corpus) {
        $defaults = YoastMetaMapper::detectSiteDefaults($corpus(60, 40));

        expect($defaults['og_description'])->toBe(['Save up to 70% on staffing costs with Remote Leverage.']);
    });

    it('does not mistake a handful of pages for a template', function () use ($corpus) {
        // Two pages sharing a line is an editorial coincidence; the floor of
        // three keeps a small corpus from flagging everything.
        expect(YoastMetaMapper::detectSiteDefaults($corpus(2, 8)))->not->toHaveKey('og_description');
    });

    it('reads the og_image out of its list-of-objects shape', function () {
        $heads = array_fill(0, 20, yoastHead());

        expect(YoastMetaMapper::detectSiteDefaults($heads)['og_image'])
            ->toBe([YOAST_PROD.'/wp-content/uploads/2026/03/image-9.png']);
    });

    it('skips a value the corpus proved to be a template', function () {
        $defaults = YoastMetaMapper::detectSiteDefaults(array_fill(0, 20, yoastHead()));

        expect(yoastMapper($defaults)->map(yoastHead(), 'Hire VA Landing'))
            ->not->toHaveKey('_yoast_wpseo_opengraph-description')
            ->not->toHaveKey('_yoast_wpseo_opengraph-image')
            ->not->toHaveKey('_yoast_wpseo_opengraph-title');
    });

    it('still carries the title and description when the social values are templates', function () {
        $defaults = YoastMetaMapper::detectSiteDefaults(array_fill(0, 20, yoastHead()));

        expect(yoastMapper($defaults)->map(yoastHead()))
            ->toHaveKey('_yoast_wpseo_title')
            ->toHaveKey('_yoast_wpseo_metadesc')
            ->toHaveKey('_yoast_wpseo_canonical');
    });
});
