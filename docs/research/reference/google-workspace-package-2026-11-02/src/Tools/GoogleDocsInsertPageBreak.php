<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;

class GoogleDocsInsertPageBreak implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function name(): string
    {
        return 'google_docs_insert_page_break';
    }

    public function description(): string
    {
        return 'Insert a page break into a Google Docs document. Omit index or set to -1 to insert at end.';
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

            $index = $args['index'] ?? -1;
            $atEnd = $index === -1;

            if ($atEnd) {
                $requests = [
                    ['insertPageBreak' => [
                        'endOfSegmentLocation' => ['segmentId' => ''],
                    ]],
                ];
            } else {
                $requests = [
                    ['insertPageBreak' => [
                        'location' => ['index' => (int) $index],
                    ]],
                ];
            }

            $this->service->batchUpdate((string) $documentId, $requests);

            $location = $atEnd ? 'end of document' : "index {$index}";

            return ToolResult::success("Page break inserted at {$location}.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'document_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Docs document ID (from the URL).'],
            'index' => ['type' => 'integer', 'description' => 'Insert position (1-based). Omit or -1 for end of document.'],
        ];
    }
}
