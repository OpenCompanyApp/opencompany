<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;

class GoogleDocsSearchText implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function name(): string
    {
        return 'google_docs_search_text';
    }

    public function description(): string
    {
        return 'Find all occurrences of text in a Google Docs document with their start/end indexes. Useful before format_text or delete_range operations. The document ID is the long string in the URL: docs.google.com/document/d/{documentId}/edit';
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

            $query = $args['query'] ?? '';
            if (empty($query)) {
                return ToolResult::error('query is required.');
            }

            $matchCase = (bool) ($args['match_case'] ?? false);

            $document = $this->service->getDocument((string) $documentId);
            $title = $document['title'] ?? 'Untitled';
            $docId = $document['documentId'] ?? $documentId;

            $occurrences = $this->service->findText($document, (string) $query, $matchCase);

            if (empty($occurrences)) {
                return ToolResult::success("No occurrences of \"$query\" found in \"$title\" (id: $docId).");
            }

            $count = count($occurrences);
            $lines = ["{$count} " . ($count === 1 ? 'occurrence' : 'occurrences') . " of \"$query\" in \"$title\" (id: $docId):", ''];

            foreach ($occurrences as $i => $occurrence) {
                $num = $i + 1;
                $start = $occurrence['startIndex'];
                $end = $occurrence['endIndex'];
                $text = $occurrence['text'];
                $lines[] = "{$num}. [{$start}-{$end}] \"$text\"";
            }

            return ToolResult::success(implode("\n", $lines));
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'document_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Docs document ID (from the URL).'],
            'query' => ['type' => 'string', 'required' => true, 'description' => 'Text to search for.'],
            'match_case' => ['type' => 'boolean', 'description' => 'Case-sensitive search (default false).'],
        ];
    }
}
