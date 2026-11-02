<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;

class GoogleDocsSetHeading implements Tool
{
    /** @var array<int, string> Valid heading/paragraph styles */
    private const PARAGRAPH_STYLES = [
        'HEADING_1', 'HEADING_2', 'HEADING_3', 'HEADING_4', 'HEADING_5', 'HEADING_6',
        'TITLE', 'SUBTITLE', 'NORMAL_TEXT',
    ];

    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function name(): string
    {
        return 'google_docs_set_heading';
    }

    public function description(): string
    {
        return 'Set paragraph style (heading level) for a range in a Google Docs document. Valid styles: HEADING_1 through HEADING_6, TITLE, SUBTITLE, NORMAL_TEXT.';
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

            $style = $args['style'] ?? '';
            if (empty($style) || ! in_array((string) $style, self::PARAGRAPH_STYLES, true)) {
                return ToolResult::error('style is required. Valid values: ' . implode(', ', self::PARAGRAPH_STYLES) . '.');
            }

            $requests = [
                ['updateParagraphStyle' => [
                    'range' => [
                        'startIndex' => (int) $startIndex,
                        'endIndex' => (int) $endIndex,
                    ],
                    'paragraphStyle' => [
                        'namedStyleType' => (string) $style,
                    ],
                    'fields' => 'namedStyleType',
                ]],
            ];

            $this->service->batchUpdate((string) $documentId, $requests);

            return ToolResult::success("Paragraph style set to {$style} for range [{$startIndex}-{$endIndex}].");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'document_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Docs document ID (from the URL).'],
            'start_index' => ['type' => 'integer', 'required' => true, 'description' => 'Start index of the paragraph range.'],
            'end_index' => ['type' => 'integer', 'required' => true, 'description' => 'End index of the paragraph range.'],
            'style' => ['type' => 'string', 'required' => true, 'description' => 'Paragraph style: HEADING_1, HEADING_2, HEADING_3, HEADING_4, HEADING_5, HEADING_6, TITLE, SUBTITLE, or NORMAL_TEXT.'],
        ];
    }
}
