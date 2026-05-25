<?php

namespace App\Domain\Web\Extraction;

use App\Domain\Web\ValueObjects\ExtractedPage;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Extracts LLM-friendly markdown, metadata, outline, and sections from HTML.
 *
 * This intentionally avoids browser execution. JavaScript-heavy pages can use a
 * future browser-backed fetch adapter, while this extractor keeps regular web
 * fetch cheap, deterministic, and safe.
 */
class HtmlPageExtractor
{
    public function __construct(private MarkdownPageExtractor $markdown) {}

    public function extract(string $html, string $url): ExtractedPage
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $this->removeNoise($xpath);

        $title = $this->text($xpath->query('//title')->item(0));
        $metadata = $this->metadata($xpath, $url, $title);
        $root = $this->contentRoot($xpath, $dom);
        $markdown = trim($this->nodeToMarkdown($root));
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown) ?? $markdown;
        $page = $this->markdown->extract($markdown, $title ?: null, $metadata);

        return new ExtractedPage(
            title: $page->title,
            metadata: $page->metadata,
            outline: $page->outline,
            sections: $page->sections,
            fullContent: $page->fullContent,
        );
    }

    private function removeNoise(DOMXPath $xpath): void
    {
        $query = '//script|//style|//noscript|//svg|//canvas|//iframe|//nav|//footer|//aside|//form|//button|//template|//*[@hidden]|//*[@aria-hidden="true"]';
        foreach (iterator_to_array($xpath->query($query) ?: []) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function contentRoot(DOMXPath $xpath, DOMDocument $dom): DOMNode
    {
        $queries = [
            '//main',
            '//*[@role="main"]',
            '//article',
            '//*[contains(concat(" ", normalize-space(@class), " "), " content ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " post ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " entry ")]',
            '//body',
        ];

        foreach ($queries as $query) {
            $node = $xpath->query($query)->item(0);
            if ($node instanceof DOMNode) {
                return $node;
            }
        }

        return $dom->documentElement;
    }

    /**
     * @return array<string, string>
     */
    private function metadata(DOMXPath $xpath, string $url, string $title): array
    {
        $metadata = [
            'canonical_url' => $url,
        ];

        if ($title !== '') {
            $metadata['title'] = $title;
        }

        foreach ($xpath->query('//meta') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $name = strtolower($node->getAttribute('name') ?: $node->getAttribute('property'));
            $content = trim($node->getAttribute('content'));
            if ($name !== '' && $content !== '') {
                $metadata[$name] = $content;
            }
        }

        $canonical = $xpath->query('//link[translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="canonical"]')->item(0);
        if ($canonical instanceof DOMElement && trim($canonical->getAttribute('href')) !== '') {
            $metadata['canonical_url'] = trim($canonical->getAttribute('href'));
        }

        return $metadata;
    }

    private function nodeToMarkdown(?DOMNode $node): string
    {
        if (! $node) {
            return '';
        }

        if ($node->nodeType === XML_TEXT_NODE) {
            return preg_replace('/\s+/', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';
        }

        if (! $node instanceof DOMElement) {
            return $this->childrenToMarkdown($node);
        }

        $children = trim($this->childrenToMarkdown($node));
        $tag = strtolower($node->tagName);

        return match ($tag) {
            'h1' => "\n# {$children}\n\n",
            'h2' => "\n## {$children}\n\n",
            'h3' => "\n### {$children}\n\n",
            'h4' => "\n#### {$children}\n\n",
            'h5' => "\n##### {$children}\n\n",
            'h6' => "\n###### {$children}\n\n",
            'p', 'div', 'section', 'article', 'header' => $children !== '' ? "\n{$children}\n\n" : '',
            'br' => "\n",
            'li' => $children !== '' ? "- {$children}\n" : '',
            'ul', 'ol' => "\n{$children}\n",
            'a' => $this->linkMarkdown($node, $children),
            'strong', 'b' => $children !== '' ? "**{$children}**" : '',
            'em', 'i' => $children !== '' ? "_{$children}_" : '',
            'code' => $children !== '' ? "`{$children}`" : '',
            'pre' => $children !== '' ? "\n```\n{$node->textContent}\n```\n\n" : '',
            default => $children,
        };
    }

    private function childrenToMarkdown(DOMNode $node): string
    {
        $parts = [];
        foreach ($node->childNodes as $child) {
            $parts[] = $this->nodeToMarkdown($child);
        }

        return implode('', $parts);
    }

    private function linkMarkdown(DOMElement $node, string $text): string
    {
        $href = trim($node->getAttribute('href'));
        if ($href === '' || $text === '') {
            return $text;
        }

        return "[{$text}]({$href})";
    }

    private function text(?DOMNode $node): string
    {
        return $node ? trim(preg_replace('/\s+/', ' ', $node->textContent) ?? $node->textContent) : '';
    }
}
