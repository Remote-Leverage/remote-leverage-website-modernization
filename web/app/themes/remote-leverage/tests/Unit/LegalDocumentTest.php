<?php

declare(strict_types=1);

use App\Support\LegalDocument;

it('adds an id to every section heading', function () {
    $doc = new LegalDocument('<h2 class="wp-block-heading">Consent</h2><p>Body.</p>');

    expect($doc->content())->toContain('id="consent"');
});

it('builds a table of contents from the section headings', function () {
    $html = '<p>Intro.</p><h2 class="wp-block-heading">Consent</h2><p>a</p><h2 class="wp-block-heading">Log Files</h2><p>b</p>';

    expect((new LegalDocument($html))->sections())->toBe([
        ['id' => 'consent', 'text' => 'Consent', 'level' => 2],
        ['id' => 'log-files', 'text' => 'Log Files', 'level' => 2],
    ]);
});

it('ignores heading levels it was not asked to index', function () {
    $html = '<h2 class="wp-block-heading">Section</h2><h3 class="wp-block-heading">Clause</h3>';

    expect((new LegalDocument($html))->sections())->toHaveCount(1)
        ->and((new LegalDocument($html, [2, 3]))->sections())->toHaveCount(2);
});

it('keeps anchors unique when two sections share a title', function () {
    $html = '<h2>Contact Us</h2><h2>Contact Us</h2>';

    $sections = (new LegalDocument($html))->sections();

    expect(array_column($sections, 'id'))->toBe(['contact-us', 'contact-us-2']);
});

it('preserves an id the editor set by hand', function () {
    $doc = new LegalDocument('<h2 id="gdpr">GDPR Data Protection Rights</h2>');

    expect($doc->sections()[0]['id'])->toBe('gdpr')
        ->and(substr_count($doc->content(), 'id='))->toBe(1);
});

it('strips markup and decodes entities in the navigation label', function () {
    $html = '<h2 class="wp-block-heading">Third-Party Links &amp; Ads; <em>Other</em> Users</h2>';

    $section = (new LegalDocument($html))->sections()[0];

    expect($section['text'])->toBe('Third-Party Links & Ads; Other Users')
        ->and($section['id'])->toBe('third-party-links-ads-other-users');
});

it('leaves the heading markup itself untouched apart from the id', function () {
    $doc = new LegalDocument('<h2 class="wp-block-heading">Consent <em>now</em></h2>');

    expect($doc->content())->toContain('<em>now</em>')
        ->and($doc->content())->toContain('class="wp-block-heading"');
});

it('skips empty headings rather than emitting a blank anchor', function () {
    $doc = new LegalDocument('<h2 class="wp-block-heading"></h2><h2>Real</h2>');

    expect($doc->sections())->toHaveCount(1)
        ->and($doc->sections()[0]['id'])->toBe('real');
});

it('returns content unchanged when the document has no headings', function () {
    $html = '<p>Just a paragraph.</p>';

    expect((new LegalDocument($html))->content())->toBe($html)
        ->and((new LegalDocument($html))->sections())->toBe([]);
});
