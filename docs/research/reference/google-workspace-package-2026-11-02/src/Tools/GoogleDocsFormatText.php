<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;

class GoogleDocsFormatText implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function name(): string
    {
        return 'google_docs_format_text';
    }

    public function description(): string
    {
        return 'Apply formatting to a text range in a Google Docs document. Supports bold, italic, underline, strikethrough, fontSize (points), fontFamily, foregroundColor (hex like "#FF0000"), and link (URL).';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Docs integration is not configured.');
            }

            $documentId = $args['document_id'] ?? '';
            if (empty($documentId)) {
                return ToolResult::error('documentId is required.');
            }

            $startIndex = $args['start_index'] ?? null;
            $endIndex = $args['end_index'] ?? null;
            if ($startIndex === null || $endIndex === null) {
                return ToolResult::error('startIndex and endIndex are required.');
            }

            // Build text style and fields mask
            $textStyle = [];
            $fields = [];

            if (isset($args['bold'])) {
                $textStyle['bold'] = (bool) $args['bold'];
                $fields[] = 'bold';
            }
            if (isset($args['italic'])) {
                $textStyle['italic'] = (bool) $args['italic'];
                $fields[] = 'italic';
            }
            if (isset($args['underline'])) {
                $textStyle['underline'] = (bool) $args['underline'];
                $fields[] = 'underline';
            }
            if (isset($args['strikethrough'])) {
                $textStyle['strikethrough'] = (bool) $args['strikethrough'];
                $fields[] = 'strikethrough';
            }
            if (isset($args['font_size'])) {
                $textStyle['fontSize'] = [
                    'magnitude' => (float) $args['font_size'],
                    'unit' => 'PT',
                ];
                $fields[] = 'fontSize';
            }
            if (isset($args['font_family'])) {
                $textStyle['weightedFontFamily'] = [
                    'fontFamily' => (string) $args['font_family'],
                ];
                $fields[] = 'weightedFontFamily';
            }
            if (isset($args['foreground_color'])) {
                $color = $this->parseHexColor((string) $args['foreground_color']);
                if ($color !== null) {
                    $textStyle['foregroundColor'] = ['color' => ['rgbColor' => $color]];
                    $fields[] = 'foregroundColor';
                }
            }
            if (isset($args['link'])) {
                $textStyle['link'] = ['url' => (string) $args['link']];
                $fields[] = 'link';
            }

            if (empty($fields)) {
                return ToolResult::error('At least one formatting option is required (bold, italic, underline, strikethrough, fontSize, fontFamily, foregroundColor, link).');
            }

            $requests = [
                ['updateTextStyle' => [
                    'range' => [
                        'startIndex' => (int) $startIndex,
                        'endIndex' => (int) $endIndex,
                    ],
                    'textStyle' => $textStyle,
                    'fields' => implode(',', $fields),
                ]],
            ];

            $this->service->batchUpdate((string) $documentId, $requests);

            return ToolResult::success('Formatting applied (' . implode(', ', $fields) . ") to range [{$startIndex}-{$endIndex}].");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * Parse a hex color string to RGB color object.
     *
     * @return array{red: float, green: float, blue: float}|null
     */
    private function parseHexColor(string $hex): ?array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return null;
        }

        return [
            'red' => hexdec(substr($hex, 0, 2)) / 255.0,
            'green' => hexdec(substr($hex, 2, 2)) / 255.0,
            'blue' => hexdec(substr($hex, 4, 2)) / 255.0,
        ];
    }

    public function parameters(): array
    {
        return [
            'document_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Docs document ID (from the URL).'],
            'start_index' => ['type' => 'integer', 'required' => true, 'description' => 'Start index of the text range to format.'],
            'end_index' => ['type' => 'integer', 'required' => true, 'description' => 'End index of the text range to format.'],
            'bold' => ['type' => 'boolean', 'description' => 'Apply bold formatting.'],
            'italic' => ['type' => 'boolean', 'description' => 'Apply italic formatting.'],
            'underline' => ['type' => 'boolean', 'description' => 'Apply underline formatting.'],
            'strikethrough' => ['type' => 'boolean', 'description' => 'Apply strikethrough formatting.'],
            'font_size' => ['type' => 'number', 'description' => 'Font size in points (e.g., 12, 14, 18).'],
            'font_family' => ['type' => 'string', 'description' => 'Font name (e.g., "Arial", "Times New Roman").'],
            'foreground_color' => ['type' => 'string', 'description' => 'Hex color (e.g., "#FF0000" for red).'],
            'link' => ['type' => 'string', 'description' => 'URL to link the text to.'],
        ];
    }
}
