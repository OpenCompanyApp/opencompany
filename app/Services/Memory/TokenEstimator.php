<?php

namespace App\Services\Memory;

class TokenEstimator
{
    /**
     * Estimate token count for a string.
     * Uses word count with CJK-awareness.
     */
    public static function estimate(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        $wordCount = str_word_count($text);
        $charCount = mb_strlen($text);

        // CJK-heavy text: str_word_count undercounts dramatically
        if ($wordCount > 0 && $charCount / $wordCount > 10) {
            return (int) ceil($charCount * 1.5);
        }

        // Edge case: no words detected but has characters (pure CJK/symbols)
        if ($wordCount === 0) {
            return (int) ceil($charCount * 1.5);
        }

        return (int) ceil($wordCount * 1.3);
    }
}
