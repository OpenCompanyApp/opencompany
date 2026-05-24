<?php

namespace App\Domain\Web\Extraction;

use App\Domain\Web\ValueObjects\ExtractedPage;

/**
 * Builds outline and section maps from markdown-like provider output.
 */
class MarkdownPageExtractor
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function extract(string $content, ?string $title = null, array $metadata = []): ExtractedPage
    {
        $content = trim($this->normalizeWhitespace($content));
        $outline = [];
        $sections = [];
        $currentId = 'full';
        $buffer = [];
        $seen = [];

        foreach (preg_split("/\r\n|\n|\r/", $content) ?: [] as $line) {
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $matches) === 1) {
                if ($buffer !== []) {
                    $sections[$currentId] = trim(implode("\n", $buffer));
                }

                $heading = trim($matches[2]);
                $id = $this->uniqueSlug($heading, $seen);
                $outline[] = ['id' => $id, 'title' => $heading, 'level' => strlen($matches[1])];
                $currentId = $id;
                $buffer = [$line];

                continue;
            }

            $buffer[] = $line;
        }

        if ($buffer !== []) {
            $sections[$currentId] = trim(implode("\n", $buffer));
        }

        if ($sections === []) {
            $sections['full'] = $content;
        }

        return new ExtractedPage(
            title: $title,
            metadata: $metadata,
            outline: $outline,
            sections: $sections,
            fullContent: $content,
        );
    }

    public function markdownToPlainText(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/```[^\n]*\n(.*?)```/s', "$1\n", $content) ?? $content;
        $content = preg_replace('/`([^`]+)`/', '$1', $content) ?? $content;
        $content = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '$1', $content) ?? $content;
        $content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$1', $content) ?? $content;
        $content = preg_replace('/^(#{1,6})\s*/m', '', $content) ?? $content;
        $content = preg_replace('/^\s*>\s?/m', '', $content) ?? $content;
        $content = strip_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($this->normalizeWhitespace($content));
    }

    private function normalizeWhitespace(string $content): string
    {
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        $content = preg_replace("/[ \t]+\n/", "\n", $content) ?? $content;
        $content = preg_replace("/\n{3,}/", "\n\n", $content) ?? $content;

        return $content;
    }

    /**
     * @param  array<string, int>  $seen
     */
    private function uniqueSlug(string $heading, array &$seen): string
    {
        $slug = mb_strtolower(trim($heading));
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? $slug;
        $slug = trim($slug, '-') ?: 'section';

        $count = $seen[$slug] ?? 0;
        $seen[$slug] = $count + 1;

        return $count === 0 ? $slug : "{$slug}-{$count}";
    }
}
