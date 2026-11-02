<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;

class GoogleDocsInsertText implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function name(): string
    {
        return 'google_docs_insert_text';
    }

    public function description(): string
    {
        return 'Insert text into a Google Docs document at a specific position or at the end. Omit index or set to -1 to append at end.';
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

            $text = $args['text'] ?? '';
            if ($text === '') {
                return ToolResult::error('text is required.');
            }

            $index = $args['index'] ?? -1;
            $atEnd = $index === -1;

            if ($atEnd) {
                $requests = [
                    ['insertText' => [
                        'endOfSegmentLocation' => ['segmentId' => ''],
                        'text' => (string) $text,
                    ]],
                ];
            } else {
                $requests = [
                    ['insertText' => [
                        'location' => ['index' => (int) $index],
                        'text' => (string) $text,
                    ]],
                ];
            }

            $this->service->batchUpdate((string) $documentId, $requests);

            $location = $atEnd ? 'end of document' : "index {$index}";

            return ToolResult::success("Text inserted at {$location}.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'document_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Docs document ID (from the URL).'],
            'text' => ['type' => 'string', 'required' => true, 'description' => 'Text to insert.'],
            'index' => ['type' => 'integer', 'description' => 'Insert position (1-based). Omit or -1 for end of document.'],
        ];
    }
}
