<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Pulls the article prose out of an Elementor-rendered post and emits clean
 * Gutenberg blocks.
 *
 * Production stores these posts as full Elementor templates — 110–155KB of markup
 * where the prose is only a third of it. The rest is page furniture (article header,
 * table of contents, talent carousel, related posts, FAQ, author bio, booking
 * wizard), which belongs to the single-post template in v2, not to post_content.
 * So we keep only the heading/text-editor widgets, in document order.
 *
 * The one exception is the "Quick Summary" box: it sits in a raw template div
 * rather than an Elementor widget, but it is hand-written editorial copy unique
 * to each post, so it is kept and re-emitted as a Key Takeaways callout.
 */
class ElementorProseExtractor
{
    /** Widgets whose contents are the article itself. */
    private const PROSE_WIDGETS = ['text-editor', 'heading', 'rl_article_data_table', 'rl_article_also_read'];

    /** The production template's hand-written "Quick Summary" box. */
    private const SUMMARY_SELECTOR = '//*[contains(concat(" ", normalize-space(@class), " "), " rl-summary-content ")]';

    /** Inline tags worth preserving inside a paragraph. */
    private const INLINE_KEEP = ['a', 'strong', 'b', 'em', 'i', 'code', 'br', 'sup', 'sub'];

    /**
     * @param  array<string, string>  $linkMap  Normalised post title => permalink,
     *                                          used to give the "Also read" box a
     *                                          working href (production's are empty).
     */
    public function extract(string $renderedHtml, array $linkMap = []): string
    {
        if (trim($renderedHtml) === '') {
            return '';
        }

        $doc = $this->loadHtml($renderedHtml);
        $xpath = new DOMXPath($doc);

        $blocks = [];

        $clauses = array_map(
            fn (string $widget): string => '//*[contains(concat(" ", normalize-space(@class), " "), " elementor-widget-'.$widget.' ")]',
            self::PROSE_WIDGETS,
        );

        $query = implode(' | ', $clauses);

        $widgets = $xpath->query($query);

        // Not every post is an Elementor build — a handful are plain HTML from
        // before the template existed. Those have no widgets to find, so convert
        // the body directly rather than returning nothing.
        if ($widgets->length === 0) {
            return $this->plainHtml($doc);
        }

        foreach ($widgets as $widget) {
            $classes = $widget->getAttribute('class');

            if (str_contains($classes, 'elementor-widget-heading')) {
                $blocks = array_merge($blocks, $this->heading($widget));

                continue;
            }

            if (str_contains($classes, 'elementor-widget-rl_article_also_read')) {
                $blocks = array_merge($blocks, $this->alsoRead($widget, $linkMap));

                continue;
            }

            $blocks = array_merge($blocks, $this->textEditor($widget, $doc));
        }

        $prose = implode("\n\n", array_filter($blocks));

        if ($prose === '') {
            return '';
        }

        $takeaways = $this->summaryBox($this->summary($renderedHtml));

        return $takeaways === '' ? $prose : $takeaways."\n\n".$prose;
    }

    /**
     * The paragraphs of the post's "Quick Summary" box, as inline HTML.
     *
     * Exposed separately so the importer can use the first one as the post
     * excerpt — production's REST excerpts are auto-generated from the whole
     * Elementor template and open with the header furniture, so they are unusable.
     *
     * @return string[]
     */
    public function summary(string $renderedHtml): array
    {
        if (trim($renderedHtml) === '') {
            return [];
        }

        $doc = $this->loadHtml($renderedHtml);
        $box = (new DOMXPath($doc))->query(self::SUMMARY_SELECTOR)->item(0);

        if (! $box instanceof DOMElement) {
            return [];
        }

        $paragraphs = [];

        // Only <p>: the box opens with its own "Quick Summary" heading, which the
        // callout below re-renders itself.
        foreach ($box->getElementsByTagName('p') as $paragraph) {
            $inner = $this->inlineHtml($paragraph, $doc);

            if ($inner !== '') {
                $paragraphs[] = $inner;
            }
        }

        return $paragraphs;
    }

    /**
     * Re-emit the production template's "Quick Summary" box.
     *
     * Markup and class names are production's, so the ported rl-summary-box rules
     * in blog.css style it without a v2-specific variant.
     *
     * @param  string[]  $paragraphs
     */
    private function summaryBox(array $paragraphs): string
    {
        if (! $paragraphs) {
            return '';
        }

        $inner = '';

        foreach ($paragraphs as $paragraph) {
            $inner .= "\n    <p>".$paragraph.'</p>';
        }

        return '<!-- wp:html -->'."\n"
            .'<div class="rl-summary-box">'."\n"
            .'  <div class="rl-summary-content">'."\n"
            // data-toc="skip" keeps this out of the table of contents, mirroring
            // production's TOC script, which explicitly excludes .rl-summary-box.
            .'    <h2 data-toc="skip" style="text-transform: none"><strong>Quick Summary</strong></h2>'.$inner."\n"
            .'  </div>'."\n"
            .'</div>'."\n"
            .'<!-- /wp:html -->';
    }

    /**
     * The inline "Also read:" cross-link.
     *
     * Production renders this with an empty href on 84 of 91 posts (and "#" on
     * another 5), so the title is resolved against the imported posts instead;
     * anything that does not resolve renders as plain text, as it does today.
     *
     * @param  array<string, string>  $linkMap
     * @return string[]
     */
    private function alsoRead(DOMElement $widget, array $linkMap): array
    {
        $link = $widget->getElementsByTagName('a')->item(0);

        if (! $link instanceof DOMElement) {
            return [];
        }

        $title = $this->cleanText(str_replace(['→', '&rarr;'], '', $link->textContent));

        if ($title === '') {
            return [];
        }

        $href = $linkMap[self::normalise($title)] ?? '';

        // Production's own href, but only when it is a real destination.
        if ($href === '') {
            $own = $link->getAttribute('href');
            $href = ($own !== '' && $own !== '#') ? $own : '';
        }

        $label = esc_html($title).' &rarr;';

        $anchor = $href === ''
            ? '<span class="rl-also-read-link">'.$label.'</span>'
            : '<a href="'.esc_url($href).'" class="rl-also-read-link">'.$label.'</a>';

        return ['<!-- wp:html -->'."\n"
            .'<div class="rl-also-read-box">'."\n"
            .'  <span class="rl-also-read-label">Also read:</span>'."\n"
            .'  '.$anchor."\n"
            .'</div>'."\n"
            .'<!-- /wp:html -->'];
    }

    /**
     * Question/answer pairs from the article's FAQ accordion.
     *
     * The accordion always sits after all the prose, so it is stored as post meta
     * and rendered by the template rather than being frozen into post_content.
     *
     * @return array<int, array{question: string, answer: string}>
     */
    public function faqs(string $renderedHtml): array
    {
        if (trim($renderedHtml) === '') {
            return [];
        }

        $doc = $this->loadHtml($renderedHtml);
        $xpath = new DOMXPath($doc);

        $items = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " rl-accordion-item ")]');
        $faqs = [];

        foreach ($items as $item) {
            $question = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " rl-accordion-question ")]', $item)->item(0);
            $answer = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " rl-accordion-content ")]', $item)->item(0);

            if (! $question instanceof DOMElement || ! $answer instanceof DOMElement) {
                continue;
            }

            $q = $this->cleanText($question->textContent);
            $a = $this->inlineHtml($answer, $doc);

            if ($q === '' || $a === '') {
                continue;
            }

            $faqs[] = ['question' => $q, 'answer' => $a];
        }

        return $faqs;
    }

    /** Title key for matching a cross-link to a post, ignoring case and punctuation. */
    public static function normalise(string $title): string
    {
        $title = html_entity_decode(wp_strip_all_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return (string) preg_replace('/[^a-z0-9]+/', '', strtolower($title));
    }

    /** Convert a non-Elementor post body straight into blocks. */
    private function plainHtml(DOMDocument $doc): string
    {
        $body = $doc->getElementsByTagName('body')->item(0);

        if (! $body) {
            return '';
        }

        $blocks = [];

        foreach ($body->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $blocks = array_merge($blocks, $this->node($child, $doc));
            }
        }

        return implode("\n\n", array_filter($blocks));
    }

    private function loadHtml(string $html): DOMDocument
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc;
    }

    /** @return string[] */
    private function heading(DOMElement $widget): array
    {
        $node = null;

        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
            $found = $widget->getElementsByTagName($tag);
            if ($found->length) {
                $node = $found->item(0);
                break;
            }
        }

        return $node ? $this->headingBlock($node) : [];
    }

    /**
     * Build a heading block from the heading element itself. Taking the element
     * rather than its wrapper matters: a text-editor widget can hold several
     * headings, and searching the wrapper each time would repeat the first one.
     *
     * @return string[]
     */
    private function headingBlock(DOMElement $node): array
    {
        $text = $this->cleanText($node->textContent);

        if ($text === '') {
            return [];
        }

        // Production's article headings are h2/h3; the post title is the page h1,
        // so anything claiming h1 here is demoted to keep one h1 per document.
        $level = (int) substr($node->nodeName, 1);
        $level = max(2, min(4, $level));

        $attrs = $level === 2 ? '' : ' {"level":'.$level.'}';

        return ['<!-- wp:heading'.$attrs.' -->'
            ."\n".'<h'.$level.' class="wp-block-heading">'.esc_html($text).'</h'.$level.'>'
            ."\n".'<!-- /wp:heading -->'];
    }

    /** @return string[] */
    private function textEditor(DOMElement $widget, DOMDocument $doc): array
    {
        $container = $widget->getElementsByTagName('div')->item(0) ?: $widget;
        $blocks = [];

        foreach ($container->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $blocks = array_merge($blocks, $this->node($child, $doc));
        }

        return $blocks;
    }

    /** @return string[] */
    private function node(DOMElement $el, DOMDocument $doc): array
    {
        $name = strtolower($el->nodeName);

        if (in_array($name, ['ul', 'ol'], true)) {
            return $this->list($el, $doc, $name === 'ol');
        }

        if (in_array($name, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            return $this->headingBlock($el);
        }

        if ($name === 'table') {
            return $this->table($el, $doc);
        }

        if ($name === 'blockquote') {
            $inner = $this->inlineHtml($el, $doc);

            return $inner === '' ? [] : ['<!-- wp:quote -->'."\n".'<blockquote class="wp-block-quote"><p>'.$inner.'</p></blockquote>'."\n".'<!-- /wp:quote -->'];
        }

        // Pure wrappers: recurse rather than flatten. Four posts are already native
        // Gutenberg on production and carry their tables inside <figure>, which would
        // otherwise fall through to the paragraph case and run every cell together.
        if (in_array($name, ['div', 'figure', 'section', 'article'], true)) {
            $out = [];
            foreach ($el->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $out = array_merge($out, $this->node($child, $doc));
                }
            }

            return $out;
        }

        // Everything else is treated as a paragraph.
        $inner = $this->inlineHtml($el, $doc);

        return $inner === '' ? [] : ['<!-- wp:paragraph -->'."\n".'<p>'.$inner.'</p>'."\n".'<!-- /wp:paragraph -->'];
    }

    /**
     * Convert a comparison table into a wp:table block.
     *
     * Without this the table falls through to the paragraph case and every cell
     * is concatenated into one run-on line ("Fast (hire new people)Fast (agency
     * adds resources)…"). Production's tables are uniform — no colspan, rowspan,
     * caption, tfoot or nesting — so a straight row/cell walk is enough.
     *
     * @return string[]
     */
    private function table(DOMElement $el, DOMDocument $doc): array
    {
        $head = [];
        $body = [];

        foreach ($el->getElementsByTagName('tr') as $row) {
            $parent = strtolower($row->parentNode?->nodeName ?? '');

            if ($parent === 'thead') {
                $head[] = $row;
            } else {
                $body[] = $row;
            }
        }

        // A fifth of production's tables have no <thead> — their header is just a
        // first row of bold cells. Promote it, but only when the row below is not
        // also bold, which would mean the emphasis is the table's style, not a header.
        if (! $head && count($body) > 1 && $this->isBoldRow($body[0]) && ! $this->isBoldRow($body[1])) {
            $head[] = array_shift($body);
        }

        $html = '';

        foreach ([['thead', $head, 'th'], ['tbody', $body, 'td']] as [$section, $rows, $tag]) {
            if (! $rows) {
                continue;
            }

            $html .= '<'.$section.'>';

            foreach ($rows as $row) {
                $html .= '<tr>';

                foreach ($this->cells($row) as $cell) {
                    $content = $this->cell($cell, $doc);

                    // <th> is already bold; keeping the markup doubles it up.
                    if ($tag === 'th') {
                        $content = (string) preg_replace('#</?(b|strong)>#i', '', $content);
                    }

                    $html .= '<'.$tag.'>'.$content.'</'.$tag.'>';
                }

                $html .= '</tr>';
            }

            $html .= '</'.$section.'>';
        }

        if ($html === '') {
            return [];
        }

        return ['<!-- wp:table -->'."\n"
            .'<figure class="wp-block-table"><table>'.$html.'</table></figure>'."\n"
            .'<!-- /wp:table -->'];
    }

    /**
     * The td/th children of a row.
     *
     * @return DOMElement[]
     */
    private function cells(DOMElement $row): array
    {
        $cells = [];

        foreach ($row->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), ['td', 'th'], true)) {
                $cells[] = $child;
            }
        }

        return $cells;
    }

    /** Every non-empty cell in the row is wholly bold. */
    private function isBoldRow(DOMElement $row): bool
    {
        $bold = 0;
        $filled = 0;

        foreach ($this->cells($row) as $cell) {
            $text = $this->cleanText($cell->textContent);

            if ($text === '') {
                continue;
            }

            $filled++;

            foreach (['b', 'strong'] as $tag) {
                foreach ($cell->getElementsByTagName($tag) as $emphasis) {
                    if ($this->cleanText($emphasis->textContent) === $text) {
                        $bold++;

                        continue 3;
                    }
                }
            }
        }

        return $filled > 0 && $bold === $filled;
    }

    /**
     * Cell contents as inline HTML. Elementor wraps cell text in <p>, and a few
     * cells hold more than one — those would run together without a break.
     */
    private function cell(DOMElement $cell, DOMDocument $doc): string
    {
        $paragraphs = [];

        foreach ($cell->getElementsByTagName('p') as $paragraph) {
            $inner = $this->inlineHtml($paragraph, $doc);

            if ($inner !== '') {
                $paragraphs[] = $inner;
            }
        }

        return $paragraphs ? implode('<br>', $paragraphs) : $this->inlineHtml($cell, $doc);
    }

    /** @return string[] */
    private function list(DOMElement $el, DOMDocument $doc, bool $ordered): array
    {
        $items = [];

        foreach ($el->getElementsByTagName('li') as $li) {
            $inner = $this->inlineHtml($li, $doc);
            if ($inner !== '') {
                $items[] = '<!-- wp:list-item -->'."\n".'<li>'.$inner.'</li>'."\n".'<!-- /wp:list-item -->';
            }
        }

        if (! $items) {
            return [];
        }

        $tag = $ordered ? 'ol' : 'ul';
        $attrs = $ordered ? ' {"ordered":true}' : '';

        return ['<!-- wp:list'.$attrs.' -->'."\n".'<'.$tag.' class="wp-block-list">'."\n"
            .implode("\n", $items)."\n".'</'.$tag.'>'."\n".'<!-- /wp:list -->'];
    }

    /**
     * Inner HTML with Elementor's inline styling stripped. Its editor wraps nearly
     * every run in <span style="font-weight: 400"> — noise that would otherwise be
     * carried into Gutenberg verbatim.
     */
    private function inlineHtml(DOMNode $node, DOMDocument $doc): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $this->serialize($child, $doc);
        }

        return $this->cleanText($html, true);
    }

    private function serialize(DOMNode $node, DOMDocument $doc): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return esc_html($node->textContent);
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $name = strtolower($node->nodeName);
        $inner = '';

        foreach ($node->childNodes as $child) {
            $inner .= $this->serialize($child, $doc);
        }

        if (! in_array($name, self::INLINE_KEEP, true)) {
            return $inner; // unwrap spans/divs, keep their text
        }

        if ($name === 'br') {
            return '<br>';
        }

        if ($name === 'a') {
            $href = $node->getAttribute('href');

            if ($href === '') {
                return $inner;
            }

            $rel = str_contains($href, 'remoteleverage.com') || str_starts_with($href, '/')
                ? ''
                : ' rel="noopener"';

            return '<a href="'.esc_url($href).'"'.$rel.'>'.$inner.'</a>';
        }

        return '<'.$name.'>'.$inner.'</'.$name.'>';
    }

    private function cleanText(string $text, bool $isHtml = false): string
    {
        $text = str_replace(["\u{00a0}", '&nbsp;'], ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}
