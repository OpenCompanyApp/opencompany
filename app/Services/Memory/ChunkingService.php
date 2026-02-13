<?php

namespace App\Services\Memory;

class ChunkingService
{
    /**
     * Split text into overlapping chunks.
     *
     * @return array<int, string>  Ordered list of chunk strings
     */
    public function chunk(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $maxSize = config('memory.chunking.max_chunk_size', 512);
        $overlap = config('memory.chunking.chunk_overlap', 64);
        $separator = config('memory.chunking.separator', "\n\n");

        $paragraphs = array_filter(explode($separator, $text), fn ($p) => trim($p) !== '');

        if (empty($paragraphs)) {
            return [];
        }

        $chunks = [];
        $current = '';

        foreach ($paragraphs as $para) {
            $candidate = $current === '' ? $para : $current . $separator . $para;

            if ($this->estimateTokens($candidate) > $maxSize && $current !== '') {
                $chunks[] = trim($current);
                $current = $this->takeTrailing($current, $overlap) . $separator . $para;
            } else {
                $current = $candidate;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(str_word_count($text) * 1.3);
    }

    private function takeTrailing(string $text, int $tokenCount): string
    {
        $words = explode(' ', $text);
        $wordCount = (int) ceil($tokenCount / 1.3);

        return implode(' ', array_slice($words, -$wordCount));
    }
}
