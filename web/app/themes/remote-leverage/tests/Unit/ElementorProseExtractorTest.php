<?php

use App\Domains\ContentAudit\Services\ElementorProseExtractor;

function elementorPost(string $summary = '', string $prose = ''): string
{
    $prose = $prose ?: '<p>Opening paragraph.</p>';

    $box = $summary === '' ? '' : <<<HTML
        <div class="rl-summary-box">
          <div class="rl-summary-content">
            <h2><strong>Quick Summary</strong></h2>
            {$summary}
          </div>
        </div>
        HTML;

    return <<<HTML
        <div class="rl-article-header"><h1 class="rl-header-title">Title</h1></div>
        {$box}
        <div class="elementor-widget elementor-widget-text-editor"><div>{$prose}</div></div>
        HTML;
}

it('pulls the quick summary paragraphs, skipping the box heading', function () {
    $html = elementorPost('<p>First point.</p><p>Second <b>point</b>.</p>');

    expect((new ElementorProseExtractor)->summary($html))
        ->toBe(['First point.', 'Second <b>point</b>.']);
});

it('returns no summary when the post has no box', function () {
    expect((new ElementorProseExtractor)->summary(elementorPost()))->toBe([]);
});

it('emits the summary as a summary box above the prose', function () {
    $out = (new ElementorProseExtractor)->extract(elementorPost('<p>First point.</p>'));

    expect($out)->toContain('<div class="rl-summary-box">')
        ->and($out)->toContain('<div class="rl-summary-content">')
        ->and($out)->toContain('<p>First point.</p>')
        ->and(strpos($out, 'rl-summary-box'))->toBeLessThan(strpos($out, 'Opening paragraph.'));
});

it('emits no summary box when there is no summary', function () {
    expect((new ElementorProseExtractor)->extract(elementorPost()))
        ->not->toContain('rl-summary-box');
});

it('emits no summary box when the post has no prose to carry it', function () {
    $orphan = '<div class="rl-summary-box"><div class="rl-summary-content"><p>Only a summary.</p></div></div>';

    expect((new ElementorProseExtractor)->extract($orphan))->not->toContain('rl-summary-content');
});

it('converts a table with a thead into a wp:table block', function () {
    $table = '<table><thead><tr><th>Factor</th><th>Cost</th></tr></thead>'
        .'<tbody><tr><td>Ramp Time</td><td>8–12 weeks</td></tr></tbody></table>';

    $out = (new ElementorProseExtractor)->extract(elementorPost('', $table));

    expect($out)->toContain('<!-- wp:table -->')
        ->and($out)->toContain('<figure class="wp-block-table">')
        ->and($out)->toContain('<thead><tr><th>Factor</th><th>Cost</th></tr></thead>')
        ->and($out)->toContain('<tbody><tr><td>Ramp Time</td><td>8–12 weeks</td></tr></tbody>');
});

it('promotes a leading all-bold row to a header when there is no thead', function () {
    $table = '<table><tbody>'
        .'<tr><td><p><b>Cost Component</b></p></td><td><p><b>In-House</b></p></td></tr>'
        .'<tr><td><p>Hourly Rate</p></td><td><p>$40</p></td></tr>'
        .'</tbody></table>';

    $out = (new ElementorProseExtractor)->extract(elementorPost('', $table));

    // <th> is bold already, so the <b> markup is dropped on the way in.
    expect($out)->toContain('<thead><tr><th>Cost Component</th><th>In-House</th></tr></thead>')
        ->and($out)->toContain('<tbody><tr><td>Hourly Rate</td><td>$40</td></tr></tbody>');
});

it('ignores a blank corner cell when judging the header row', function () {
    $table = '<table><tbody>'
        .'<tr><td><p><span style="font-weight: 400;"> </span></p></td><td><p><b>BPO</b></p></td></tr>'
        .'<tr><td><p><b>Structure</b></p></td><td><p>Shared seats</p></td></tr>'
        .'</tbody></table>';

    expect((new ElementorProseExtractor)->extract(elementorPost('', $table)))
        ->toContain('<thead><tr><th></th><th>BPO</th></tr></thead>');
});

it('leaves a leading data row in the body', function () {
    $table = '<table><tbody>'
        .'<tr><td><p>US / Domestic Agency</p></td><td><p>$40 – $75 / hr</p></td></tr>'
        .'<tr><td><p>Nearshore</p></td><td><p>$10 / hr</p></td></tr>'
        .'</tbody></table>';

    $out = (new ElementorProseExtractor)->extract(elementorPost('', $table));

    expect($out)->not->toContain('<thead>')
        ->and($out)->toContain('<td>US / Domestic Agency</td>');
});

it('does not promote when the emphasis is the whole table\'s style', function () {
    $table = '<table><tbody>'
        .'<tr><td><p><b>One</b></p></td><td><p><b>Two</b></p></td></tr>'
        .'<tr><td><p><b>Three</b></p></td><td><p><b>Four</b></p></td></tr>'
        .'</tbody></table>';

    expect((new ElementorProseExtractor)->extract(elementorPost('', $table)))
        ->not->toContain('<thead>');
});

it('separates multiple paragraphs in one cell', function () {
    $table = '<table><tbody><tr><td><p>First line.</p><p>Second line.</p></td></tr></tbody></table>';

    expect((new ElementorProseExtractor)->extract(elementorPost('', $table)))
        ->toContain('<td>First line.<br>Second line.</td>');
});

it('keeps links and emphasis inside body cells', function () {
    $table = '<table><tbody><tr><td><p>See <a href="/x">this</a> and <b>that</b></p></td></tr></tbody></table>';

    expect((new ElementorProseExtractor)->extract(elementorPost('', $table)))
        ->toContain('<td>See <a href="/x">this</a> and <b>that</b></td>');
});

it('recurses into a figure wrapper rather than flattening it', function () {
    // Four posts are already native Gutenberg on production and wrap their tables.
    $body = '<figure class="wp-block-table"><table><tbody>'
        .'<tr><td>Cost Category</td><td>Annual Estimate</td></tr>'
        .'</tbody></table></figure>';

    expect((new ElementorProseExtractor)->extract($body))
        ->toContain('<!-- wp:table -->')
        ->and((new ElementorProseExtractor)->extract($body))
        ->toContain('<td>Cost Category</td><td>Annual Estimate</td>');
});

it('reads the custom data-table widget as prose', function () {
    $html = '<div class="elementor-element elementor-widget elementor-widget-rl_article_data_table">'
        .'<div class="elementor-widget-container"><div class="rl-data-table-wrapper">'
        .'<h4 class="rl-table-headline">Table: Athena cost compared</h4>'
        .'<table class="rl-data-table"><thead><tr><th>Provider</th></tr></thead>'
        .'<tbody><tr><td>Athena</td></tr></tbody></table>'
        .'</div></div></div>';

    $out = (new ElementorProseExtractor)->extract($html);

    expect($out)->toContain('Table: Athena cost compared')
        ->and($out)->toContain('<thead><tr><th>Provider</th></tr></thead>')
        ->and($out)->toContain('<td>Athena</td>');
});

function alsoReadWidget(string $href, string $title): string
{
    return '<div class="elementor-widget elementor-widget-rl_article_also_read">'
        .'<div class="elementor-widget-container"><div class="rl-also-read-box">'
        .'<span class="rl-also-read-label">Also read:</span>'
        .'<a href="'.$href.'" class="rl-also-read-link">'.$title.' &rarr;</a>'
        .'</div></div></div>';
}

it('resolves an also-read cross-link by title', function () {
    $html = elementorPost().alsoReadWidget('', 'Speed Up Video Editing');
    $map = [ElementorProseExtractor::normalise('Speed Up Video Editing') => 'https://example.test/blog/speed-up/'];

    expect((new ElementorProseExtractor)->extract($html, $map))
        ->toContain('<a href="https://example.test/blog/speed-up/" class="rl-also-read-link">');
});

it('renders an unresolvable also-read link as plain text', function () {
    // Production ships these with an empty href; without a match there is nowhere to go.
    $out = (new ElementorProseExtractor)->extract(elementorPost().alsoReadWidget('', 'Missing Post'));

    expect($out)->toContain('<span class="rl-also-read-link">Missing Post &rarr;</span>')
        ->and($out)->not->toContain('<a href="" class="rl-also-read-link">');
});

it('ignores production\'s placeholder "#" href', function () {
    $out = (new ElementorProseExtractor)->extract(elementorPost().alsoReadWidget('#', 'Unlinked'));

    expect($out)->toContain('<span class="rl-also-read-link">');
});

it('keeps the also-read box in document order', function () {
    $html = '<div class="elementor-widget elementor-widget-text-editor"><div><p>Before.</p></div></div>'
        .alsoReadWidget('', 'Middle')
        .'<div class="elementor-widget elementor-widget-text-editor"><div><p>After.</p></div></div>';

    $out = (new ElementorProseExtractor)->extract($html);

    expect(strpos($out, 'Before.'))->toBeLessThan(strpos($out, 'rl-also-read-box'))
        ->and(strpos($out, 'rl-also-read-box'))->toBeLessThan(strpos($out, 'After.'));
});

it('extracts the faq accordion as question and answer pairs', function () {
    $html = '<div class="rl-faq-section"><div class="rl-accordion">'
        .'<div class="rl-accordion-item"><button class="rl-accordion-header">'
        .'<span class="rl-accordion-question">What should I outsource first?</span></button>'
        .'<div class="rl-accordion-content"><p>Start with <b>high-volume</b> work.</p></div></div>'
        .'<div class="rl-accordion-item"><button class="rl-accordion-header">'
        .'<span class="rl-accordion-question">How long does it take?</span></button>'
        .'<div class="rl-accordion-content"><p>Three weeks.</p></div></div>'
        .'</div></div>';

    expect((new ElementorProseExtractor)->faqs($html))->toBe([
        ['question' => 'What should I outsource first?', 'answer' => 'Start with <b>high-volume</b> work.'],
        ['question' => 'How long does it take?', 'answer' => 'Three weeks.'],
    ]);
});

it('returns no faqs when the accordion is absent or empty', function () {
    expect((new ElementorProseExtractor)->faqs(elementorPost()))->toBe([])
        ->and((new ElementorProseExtractor)->faqs('<div class="rl-accordion-item"></div>'))->toBe([]);
});

it('normalises titles for cross-link matching', function () {
    expect(ElementorProseExtractor::normalise('Here&#8217;s Why You Need a COR!'))
        ->toBe(ElementorProseExtractor::normalise('Here’s why you need a COR'));
});
