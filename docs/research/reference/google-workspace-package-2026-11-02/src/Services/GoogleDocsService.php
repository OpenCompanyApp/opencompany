<?php

namespace OpenCompany\Integrations\Google\Services;

use OpenCompany\Integrations\Google\GoogleClient;

class GoogleDocsService
{
    private const BASE_URL = 'https://docs.googleapis.com/v1';

    public function __construct(private GoogleClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    // ─── API Methods ───

    /**
     * Get a document by ID (full JSON).
     *
     * @return array<string, mixed>
     */
    public function getDocument(string $documentId): array
    {
        return $this->client->get(self::BASE_URL . '/documents/' . $documentId);
    }

    /**
     * Create a new blank document.
     *
     * @return array<string, mixed>
     */
    public function createDocument(string $title): array
    {
        return $this->client->post(self::BASE_URL . '/documents', [
            'title' => $title,
        ]);
    }

    /**
     * Apply batch updates to a document.
     *
     * @param  array<int, array<string, mixed>>  $requests
     * @return array<string, mixed>
     */
    public function batchUpdate(string $documentId, array $requests): array
    {
        return $this->client->post(
            self::BASE_URL . '/documents/' . $documentId . ':batchUpdate',
            ['requests' => $requests]
        );
    }

    // ─── Helper Methods ───

    /**
     * Extract plain text from a document JSON response.
     *
     * Iterates through body content elements and concatenates paragraph text runs.
     * This is the primary read method since the `documents` scope doesn't grant Drive API access.
     *
     * @param  array<string, mixed>  $document
     */
    public function extractText(array $document): string
    {
        $body = $document['body'] ?? [];
        $content = $body['content'] ?? [];
        $text = '';

        foreach ($content as $element) {
            if (isset($element['paragraph'])) {
                $paragraph = $element['paragraph'];
                $elements = $paragraph['elements'] ?? [];

                foreach ($elements as $el) {
                    $textRun = $el['textRun'] ?? null;
                    if ($textRun !== null) {
                        $text .= $textRun['content'] ?? '';
                    }
                }
            } elseif (isset($element['table'])) {
                $text .= $this->extractTableText($element['table']);
            }
        }

        return $text;
    }

    /**
     * Extract text from a table element.
     *
     * @param  array<string, mixed>  $table
     */
    private function extractTableText(array $table): string
    {
        $text = '';
        $rows = $table['tableRows'] ?? [];

        foreach ($rows as $row) {
            $cells = $row['tableCells'] ?? [];
            $cellTexts = [];

            foreach ($cells as $cell) {
                $cellContent = $cell['content'] ?? [];
                $cellText = '';

                foreach ($cellContent as $element) {
                    if (isset($element['paragraph'])) {
                        $elements = $element['paragraph']['elements'] ?? [];
                        foreach ($elements as $el) {
                            $textRun = $el['textRun'] ?? null;
                            if ($textRun !== null) {
                                $cellText .= $textRun['content'] ?? '';
                            }
                        }
                    }
                }

                $cellTexts[] = trim($cellText);
            }

            $text .= implode("\t", $cellTexts) . "\n";
        }

        return $text;
    }

    /**
     * Extract simplified document structure with indexes.
     *
     * Returns an array of structural elements:
     * [{type, text, startIndex, endIndex, level?, rows?, columns?}]
     *
     * @param  array<string, mixed>  $document
     * @return array<int, array<string, mixed>>
     */
    public function extractStructure(array $document): array
    {
        $body = $document['body'] ?? [];
        $content = $body['content'] ?? [];
        $structure = [];

        foreach ($content as $element) {
            $startIndex = $element['startIndex'] ?? 0;
            $endIndex = $element['endIndex'] ?? 0;

            if (isset($element['paragraph'])) {
                $paragraph = $element['paragraph'];
                $style = $paragraph['paragraphStyle']['namedStyleType'] ?? 'NORMAL_TEXT';

                // Extract text preview
                $text = '';
                $elements = $paragraph['elements'] ?? [];
                foreach ($elements as $el) {
                    $textRun = $el['textRun'] ?? null;
                    if ($textRun !== null) {
                        $text .= $textRun['content'] ?? '';
                    }
                }
                $text = trim($text);

                if ($text === '' && $style === 'NORMAL_TEXT') {
                    continue; // Skip empty normal paragraphs
                }

                $entry = [
                    'type' => $style,
                    'text' => $text,
                    'startIndex' => $startIndex,
                    'endIndex' => $endIndex,
                ];

                // Add heading level for heading styles
                if (str_starts_with($style, 'HEADING_')) {
                    $entry['level'] = (int) substr($style, 8);
                }

                $structure[] = $entry;
            } elseif (isset($element['table'])) {
                $table = $element['table'];
                $rows = $table['rows'] ?? 0;
                $columns = $table['columns'] ?? 0;

                $structure[] = [
                    'type' => 'TABLE',
                    'text' => '',
                    'startIndex' => $startIndex,
                    'endIndex' => $endIndex,
                    'rows' => $rows,
                    'columns' => $columns,
                ];
            } elseif (isset($element['sectionBreak'])) {
                // Skip section breaks
            }
        }

        return $structure;
    }

    /**
     * Find all occurrences of text in a document body with their indexes.
     *
     * @param  array<string, mixed>  $document
     * @return array<int, array{text: string, startIndex: int, endIndex: int}>
     */
    public function findText(array $document, string $query, bool $matchCase = false): array
    {
        $fullText = $this->extractText($document);
        $occurrences = [];

        // We need to map text positions back to document indexes
        // Build a position map from the document body
        $body = $document['body'] ?? [];
        $content = $body['content'] ?? [];

        // Build a continuous text with index mapping
        $textWithIndexes = $this->buildTextIndexMap($content);

        $searchText = $matchCase ? $query : mb_strtolower($query);
        $sourceText = $matchCase ? $textWithIndexes['text'] : mb_strtolower($textWithIndexes['text']);

        $offset = 0;
        while (($pos = mb_strpos($sourceText, $searchText, $offset)) !== false) {
            // Map text position to document index
            $docStartIndex = $this->mapTextPositionToIndex($textWithIndexes['map'], $pos);
            $docEndIndex = $this->mapTextPositionToIndex($textWithIndexes['map'], $pos + mb_strlen($query));

            $occurrences[] = [
                'text' => mb_substr($textWithIndexes['text'], $pos, mb_strlen($query)),
                'startIndex' => $docStartIndex,
                'endIndex' => $docEndIndex,
            ];

            $offset = $pos + 1;
        }

        return $occurrences;
    }

    /**
     * Build a text string with a mapping of text positions to document indexes.
     *
     * @param  array<int, mixed>  $content
     * @return array{text: string, map: array<int, int>}
     */
    private function buildTextIndexMap(array $content): array
    {
        $text = '';
        $map = []; // text position => document index

        foreach ($content as $element) {
            if (isset($element['paragraph'])) {
                $elements = $element['paragraph']['elements'] ?? [];

                foreach ($elements as $el) {
                    $textRun = $el['textRun'] ?? null;
                    if ($textRun === null) {
                        continue;
                    }

                    $runText = $textRun['content'] ?? '';
                    $startIdx = $el['startIndex'] ?? 0;

                    for ($i = 0; $i < mb_strlen($runText); $i++) {
                        $map[mb_strlen($text) + $i] = $startIdx + $i;
                    }

                    $text .= $runText;
                }
            } elseif (isset($element['table'])) {
                $text .= $this->buildTableTextIndexMap($element['table'], $map, mb_strlen($text));
            }
        }

        // Add sentinel for end-of-text mapping
        $map[mb_strlen($text)] = ($map[mb_strlen($text) - 1] ?? 0) + 1;

        return ['text' => $text, 'map' => $map];
    }

    /**
     * Build text index map for a table element.
     *
     * @param  array<string, mixed>  $table
     * @param  array<int, int>  $map
     * @param-out array<int, int>  $map
     */
    private function buildTableTextIndexMap(array $table, array &$map, int $textOffset): string
    {
        $text = '';
        $rows = $table['tableRows'] ?? [];

        foreach ($rows as $row) {
            $cells = $row['tableCells'] ?? [];
            foreach ($cells as $cell) {
                $cellContent = $cell['content'] ?? [];
                foreach ($cellContent as $element) {
                    if (! isset($element['paragraph'])) {
                        continue;
                    }
                    $elements = $element['paragraph']['elements'] ?? [];
                    foreach ($elements as $el) {
                        $textRun = $el['textRun'] ?? null;
                        if ($textRun === null) {
                            continue;
                        }
                        $runText = (string) ($textRun['content'] ?? '');
                        $startIdx = (int) ($el['startIndex'] ?? 0);

                        for ($i = 0; $i < mb_strlen($runText); $i++) {
                            $map[$textOffset + mb_strlen($text) + $i] = $startIdx + $i;
                        }

                        $text .= $runText;
                    }
                }
            }
        }

        return $text;
    }

    /**
     * Map a text position to a document index using the position map.
     *
     * @param  array<int, int>  $map
     */
    private function mapTextPositionToIndex(array $map, int $textPosition): int
    {
        return $map[$textPosition] ?? $textPosition;
    }
}
