<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;

class GoogleDocsInsertTable implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function name(): string
    {
        return 'google_docs_insert_table';
    }

    public function description(): string
    {
        return 'Insert a table into a Google Docs document. Specify rows and columns. Omit index or set to -1 to insert at end.';
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

            $rows = $args['rows'] ?? null;
            $columns = $args['columns'] ?? null;
            if ($rows === null || $columns === null) {
                return ToolResult::error('rows and columns are required.');
            }

            $index = $args['index'] ?? -1;
            $atEnd = $index === -1;

            $insertTable = [
                'rows' => (int) $rows,
                'columns' => (int) $columns,
            ];

            if ($atEnd) {
                $insertTable['endOfSegmentLocation'] = ['segmentId' => ''];
            } else {
                $insertTable['location'] = ['index' => (int) $index];
            }

            $requests = [['insertTable' => $insertTable]];

            $this->service->batchUpdate((string) $documentId, $requests);

            $location = $atEnd ? 'end of document' : "index {$index}";

            return ToolResult::success("Table ({$rows} rows x {$columns} columns) inserted at {$location}.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'document_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Docs document ID (from the URL).'],
            'rows' => ['type' => 'integer', 'required' => true, 'description' => 'Number of rows.'],
            'columns' => ['type' => 'integer', 'required' => true, 'description' => 'Number of columns.'],
            'index' => ['type' => 'integer', 'description' => 'Insert position (1-based). Omit or -1 for end of document.'],
        ];
    }
}
