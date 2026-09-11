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
 * table of contents, key takeaways, talent carousel, related posts, FAQ, author bio,
 * booking wizard), which belongs to the single-post template in v2, not to
 * post_content. So we keep only the heading/text-editor widgets, in document order.
 */
class ElementorProseExtractor
{
    /** Widgets whose contents are the article itself. */
    private const PROSE_WIDGETS = ['text-editor', 'heading'];

    /** Inline tags worth preserving inside a paragraph. */
    private const INLINE_KEEP = ['a', 'strong', 'b', 'em', 'i', 'code', 'br', 'sup', 'sub'];

    public function extract(string $renderedHtml): string
    {
        if (trim($renderedHtml) === '') {
            return '';
        }

        $doc = $this->loadHtml($renderedHtml);
        $xpath = new DOMXPath($doc);

        $blocks = [];

        $query = '//*[contains(concat(" ", normalize-space(@class), " "), " elementor-widget-text-editor ")]'
            .' | //*[contains(concat(" ", normalize-space(@class), " "), " elementor-widget-heading ")]';

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

            $blocks = array_merge($blocks, $this->textEditor($widget, $doc));
        }

        return implode("\n\n", array_filter($blocks));
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

        if ($name === 'blockquote') {
            $inner = $this->inlineHtml($el, $doc);

            return $inner === '' ? [] : ['<!-- wp:quote -->'."\n".'<blockquote class="wp-block-quote"><p>'.$inner.'</p></blockquote>'."\n".'<!-- /wp:quote -->'];
        }

        if ($name === 'div') {
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
