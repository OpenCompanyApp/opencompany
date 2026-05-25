<?php

namespace App\Domain\Web\Support;

use App\Domain\Web\ValueObjects\WebFetchResponse;
use App\Domain\Web\ValueObjects\WebSearchResponse;

class WebToolFormatter
{
    public function search(WebSearchResponse $response): string
    {
        $lines = [
            "Provider: {$response->provider}",
            "Query: {$response->query}",
            'Results: '.count($response->results),
        ];

        if ($response->answer !== null && trim($response->answer) !== '') {
            $lines[] = '';
            $lines[] = 'Answer:';
            $lines[] = trim($response->answer);
        }

        if ($response->results !== []) {
            $lines[] = '';
            $lines[] = 'Top results:';
            foreach ($response->results as $index => $result) {
                $lines[] = sprintf('%d. %s', $index + 1, $result->title);
                if ($result->url !== '') {
                    $lines[] = "   URL: {$result->url}";
                }
                if ($result->snippet !== '') {
                    $lines[] = '   Snippet: '.$this->oneLine($result->snippet, 280);
                }
            }
        }

        $lines[] = '';
        $lines[] = 'Use web_fetch in metadata, outline, or section mode to inspect only the relevant parts of a result page.';
        $lines[] = '';
        $lines[] = 'Structured data:';
        $lines[] = json_encode($response->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return implode("\n", $lines);
    }

    public function fetch(WebFetchResponse $response, string $mode, bool $includeMetadata, bool $includeOutline): string
    {
        $lines = [
            "Provider: {$response->provider}",
            "Mode: {$mode}",
            "URL: {$response->url}",
        ];

        if ($response->finalUrl !== null && $response->finalUrl !== $response->url) {
            $lines[] = "Final URL: {$response->finalUrl}";
        }

        if ($response->title !== null && $response->title !== '') {
            $lines[] = "Title: {$response->title}";
        }

        if ($includeMetadata && $response->metadata !== []) {
            $lines[] = '';
            $lines[] = 'Metadata:';
            foreach ($response->metadata as $key => $value) {
                if (is_scalar($value)) {
                    $lines[] = "- {$key}: {$value}";
                }
            }
        }

        if ($includeOutline && $response->outline !== []) {
            $lines[] = '';
            $lines[] = 'Outline:';
            foreach ($response->outline as $entry) {
                $indent = str_repeat('  ', max(0, ((int) $entry['level']) - 1));
                $lines[] = sprintf('%s- %s [id: %s]', $indent, $entry['title'], $entry['id']);
            }
        }

        if (! in_array($mode, ['metadata', 'outline'], true)) {
            $lines[] = '';
            $lines[] = 'Content:';
            $lines[] = $response->content !== '' ? $response->content : '[No content extracted]';
        }

        if ($response->truncated && $response->nextChunkToken !== null) {
            $lines[] = '';
            $lines[] = 'More content is available.';
            $lines[] = 'Next chunk token: '.$response->nextChunkToken;
            $lines[] = 'Use web_fetch with mode="chunk" and this chunk_token to continue from the current position.';
        }

        $lines[] = '';
        $lines[] = 'Structured data:';
        $lines[] = json_encode($response->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return implode("\n", $lines);
    }

    private function oneLine(string $value, int $limit): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 3).'...' : $value;
    }
}
