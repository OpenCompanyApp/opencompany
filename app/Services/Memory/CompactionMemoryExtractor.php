<?php

namespace App\Services\Memory;

class CompactionMemoryExtractor
{
    /**
     * @return string[]
     */
    public function extract(string $summary): array
    {
        $items = array_merge(
            $this->extractSectionBullets($summary, 'Durable Facts'),
            $this->extractSectionBullets($summary, 'Decisions'),
        );

        return array_values(array_unique(array_filter(array_map(
            fn (string $item): string => trim(preg_replace('/\s+/', ' ', $item) ?? ''),
            $items,
        ))));
    }

    /**
     * @return string[]
     */
    private function extractSectionBullets(string $summary, string $heading): array
    {
        $pattern = sprintf(
            '/^##\s+%s\s*$([\s\S]*?)(?=^##\s+|\z)/mi',
            preg_quote($heading, '/'),
        );

        if (! preg_match($pattern, $summary, $matches)) {
            return [];
        }

        preg_match_all('/^\-\s+(.*)$/m', trim($matches[1]), $bullets);

        return $bullets[1] ?? [];
    }
}
