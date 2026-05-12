<?php

namespace App\Services\Mcp;

class McpResultNormalizer
{
    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function normalize(array $result): array
    {
        return [
            'success' => empty($result['isError']),
            'text' => $this->extractText($result['content'] ?? []),
            'raw' => $result,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $content
     */
    public function extractText(array $content): string
    {
        $texts = [];
        foreach ($content as $item) {
            if (($item['type'] ?? '') === 'text') {
                $texts[] = (string) $item['text'];
            }
        }

        return implode("\n", $texts) ?: 'No response content';
    }
}
