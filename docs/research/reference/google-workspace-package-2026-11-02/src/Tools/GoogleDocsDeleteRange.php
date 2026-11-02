<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;

class GoogleDocsDeleteRange implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function name(): string
    {
        return 'google_docs_delete_range';
    }

    public function description(): string
    {
        return 'Delete content in a Google Docs document by index range. Use google_docs_get_structure first to find the correct indexes.';
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
                return ToolResult::error('startIndex and endIndex are required. Use get_structure to find indexes.');
            }

            $requests = [
                ['deleteContentRange' => [
                    'range' => [
                        'startIndex' => (int) $startIndex,
                        'endIndex' => (int) $endIndex,
                    ],
                ]],
            ];

            $this->service->batchUpdate((string) $documentId, $requests);

            return ToolResult::success("Deleted content from index {$startIndex} to {$endIndex}.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'document_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Docs document ID (from the URL).'],
            'start_index' => ['type' => 'integer', 'required' => true, 'description' => 'Start index of the range to delete.'],
            'end_index' => ['type' => 'integer', 'required' => true, 'description' => 'End index of the range to delete.'],
        ];
    }
}
