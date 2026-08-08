<?php

namespace App\Services\Knowledge\Extraction;

use App\Contracts\Knowledge\DocumentExtractorInterface;
use App\Services\Knowledge\Data\ExtractedContent;
use App\Services\Knowledge\Support\ContentNormaliser;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * HTML → readable article text, for both uploaded .html files and crawled pages.
 *
 * Two jobs beyond stripping tags:
 *
 *  - Boilerplate removal. Navigation, footers and cookie banners repeat on
 *    every page of a site; embedded verbatim they dominate the index and every
 *    query retrieves the nav bar. This drops them structurally.
 *  - Sanitisation. Scripts, styles, comments and attributes are discarded
 *    outright, so nothing that reaches the knowledge base can execute when it
 *    is later rendered in a preview pane.
 */
class HtmlExtractor implements DocumentExtractorInterface
{
    /** Elements that never carry article content. */
    private const STRIP_TAGS = [
        'script', 'style', 'noscript', 'iframe', 'svg', 'canvas', 'template',
        'nav', 'footer', 'header', 'aside', 'form', 'button', 'select', 'input',
    ];

    /** Class/id fragments that mark chrome on virtually every CMS. */
    private const BOILERPLATE_HINTS = [
        'nav', 'menu', 'sidebar', 'footer', 'header', 'breadcrumb', 'cookie',
        'consent', 'banner', 'advert', 'ads', 'social', 'share', 'subscribe',
        'newsletter', 'related-posts', 'comments', 'pagination', 'skip-link',
    ];

    /** Containers most likely to hold the actual article, best guess first. */
    private const CONTENT_SELECTORS = [
        '//main', '//article', '//*[@role="main"]',
        '//*[contains(@class,"documentation")]', '//*[contains(@class,"article-body")]',
        '//*[contains(@class,"post-content")]', '//*[contains(@class,"entry-content")]',
        '//*[@id="content"]', '//*[contains(@class,"content")]',
    ];

    public function supports(string $mimeType, string $extension): bool
    {
        return in_array($mimeType, ['text/html', 'application/xhtml+xml'], true)
            || in_array(strtolower($extension), ['html', 'htm'], true);
    }

    public function extract(string $path, string $originalFilename): ExtractedContent
    {
        return $this->extractFromHtml((string) @file_get_contents($path));
    }

    /** @param  array<string, bool>  $options */
    public function extractFromHtml(string $html, array $options = []): ExtractedContent
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;
            // Real-world HTML is malformed; loadHTML recovers where loadXML
            // would abort. LIBXML_NONET blocks any external fetch attempt.
            $document->loadHTML(
                '<?xml encoding="UTF-8">'.$html,
                LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);

        $title = $this->firstText($xpath, '//title') ?: $this->firstText($xpath, '//h1');
        $metaDescription = $this->attributeValue($xpath, '//meta[@name="description"]/@content');
        $canonical = $this->attributeValue($xpath, '//link[@rel="canonical"]/@href');
        $language = $this->attributeValue($xpath, '//html/@lang');
        $hasTables = $xpath->query('//table')?->length > 0;

        $this->removeNodes($xpath, '//comment()');

        foreach (self::STRIP_TAGS as $tag) {
            // <header> inside <article> is a legitimate article header, so only
            // page-level chrome is removed.
            $query = in_array($tag, ['header', 'aside'], true)
                ? "//{$tag}[not(ancestor::article)]"
                : "//{$tag}";

            $this->removeNodes($xpath, $query);
        }

        if ($options['remove_boilerplate'] ?? true) {
            $this->removeBoilerplate($xpath);
        }

        $root = $this->locateContentRoot($xpath);

        $segments = [];
        $text = $root ? $this->renderNode($root, $segments) : '';
        $text = ContentNormaliser::normalise($text);

        return new ExtractedContent(
            text: $text,
            segments: $segments,
            metadata: array_filter([
                'title' => $title,
                'meta_description' => $metaDescription,
                'canonical_url' => $canonical,
                'html_lang' => $language,
            ]),
            pageCount: 1,
            hasTables: (bool) $hasTables,
            detectedTitle: $title,
        );
    }

    public function priority(): int
    {
        return 20;
    }

    private function removeBoilerplate(DOMXPath $xpath): void
    {
        foreach (self::BOILERPLATE_HINTS as $hint) {
            foreach (['class', 'id'] as $attribute) {
                $this->removeNodes(
                    $xpath,
                    "//*[contains(translate(@{$attribute},'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'{$hint}')]"
                );
            }
        }
    }

    /**
     * Picks the element most likely to hold the article.
     *
     * Selectors are tried in confidence order, but a match is only accepted if
     * it holds a meaningful share of the page's text — a `<div class="content">`
     * wrapping a two-word label is not the article. The comparison is relative
     * to the body rather than an absolute character count, so genuinely short
     * pages (a brief FAQ answer, a stub help article) still resolve to their
     * real content container instead of falling through.
     */
    private function locateContentRoot(DOMXPath $xpath): ?DOMNode
    {
        $body = $xpath->query('//body')?->item(0);
        $bodyLength = $body ? mb_strlen(trim((string) $body->textContent)) : 0;

        if ($bodyLength === 0) {
            return $body;
        }

        // Accept a candidate holding at least a third of the body text, with a
        // small absolute floor so a near-empty page cannot select a stray node.
        $minimumLength = max(60, (int) ($bodyLength * 0.33));

        foreach (self::CONTENT_SELECTORS as $selector) {
            $nodes = $xpath->query($selector);

            if (! $nodes || $nodes->length === 0) {
                continue;
            }

            $best = null;
            $bestLength = 0;

            foreach ($nodes as $node) {
                $length = mb_strlen(trim((string) $node->textContent));

                if ($length > $bestLength) {
                    $best = $node;
                    $bestLength = $length;
                }
            }

            if ($best && $bestLength >= $minimumLength) {
                return $best;
            }
        }

        // Fall back to <body>, never the document element — the latter drags
        // <head> in, and the page title would be indexed as body prose.
        return $body;
    }

    /**
     * Walks the tree building text while recording heading boundaries and
     * turning tables into Markdown, so the structure survives into chunking
     * and citations instead of being flattened away.
     *
     * @param  array<int, array<string, mixed>>  $segments
     */
    private function renderNode(DOMNode $node, array &$segments, ?string $heading = null): string
    {
        $output = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $output[] = (string) $child->nodeValue;

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::STRIP_TAGS, true)) {
                continue;
            }

            if (preg_match('/^h[1-6]$/', $tag)) {
                $heading = trim((string) $child->textContent);
                $output[] = "\n\n{$heading}\n";
                $segments[] = ['text' => $heading, 'heading' => $heading, 'type' => 'heading'];

                continue;
            }

            if ($tag === 'table') {
                $markdown = $this->renderTable($child);
                $output[] = "\n\n{$markdown}\n";
                $segments[] = ['text' => $markdown, 'heading' => $heading, 'type' => 'table'];

                continue;
            }

            if (in_array($tag, ['li'], true)) {
                $output[] = "\n- ".trim($this->renderNode($child, $segments, $heading));

                continue;
            }

            if (in_array($tag, ['p', 'div', 'section', 'tr', 'br', 'pre', 'blockquote'], true)) {
                $inner = trim($this->renderNode($child, $segments, $heading));

                if ($inner !== '') {
                    $output[] = "\n\n{$inner}";
                    $segments[] = ['text' => $inner, 'heading' => $heading, 'type' => 'paragraph'];
                }

                continue;
            }

            $output[] = $this->renderNode($child, $segments, $heading);
        }

        return implode(' ', $output);
    }

    /**
     * Tables become Markdown rather than a run of loose words. A pricing table
     * flattened to "Starter 19 Pro 49" loses which number belongs to which plan.
     */
    private function renderTable(DOMElement $table): string
    {
        $rows = [];

        foreach ($table->getElementsByTagName('tr') as $tr) {
            $cells = [];

            foreach ($tr->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                    $cells[] = trim(preg_replace('/\s+/u', ' ', (string) $cell->textContent) ?? '');
                }
            }

            if ($cells) {
                $rows[] = '| '.implode(' | ', $cells).' |';
            }
        }

        if (! $rows) {
            return '';
        }

        // Insert the Markdown header separator after the first row.
        $columnCount = substr_count($rows[0], '|') - 1;
        array_splice($rows, 1, 0, ['|'.str_repeat(' --- |', max(1, $columnCount))]);

        return implode("\n", $rows);
    }

    private function removeNodes(DOMXPath $xpath, string $query): void
    {
        $nodes = $xpath->query($query);

        if (! $nodes) {
            return;
        }

        // Materialise first: removing while iterating a live NodeList skips nodes.
        foreach (iterator_to_array($nodes) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function firstText(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)?->item(0);

        return $node ? (trim((string) $node->textContent) ?: null) : null;
    }

    private function attributeValue(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)?->item(0);

        return $node ? (trim((string) $node->nodeValue) ?: null) : null;
    }
}
