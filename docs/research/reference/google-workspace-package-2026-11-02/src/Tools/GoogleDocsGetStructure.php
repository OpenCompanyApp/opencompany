<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;

class GoogleDocsGetStructure implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function name(): string
    {
        return 'google_docs_get_structure';
    }

    public function description(): string
    {
        return 'Get a simplified structure of a Google Docs document showing heading hierarchy, paragraph indexes, and table positions. Essential before performing index-based editing operations. The document ID is the long string in the URL: docs.google.com/document/d/{documentId}/edit';
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

            $document = $this->service->getDocument((string) $documentId);
            $title = $document['title'] ?? 'Untitled';
            $docId = $document['documentId'] ?? $documentId;

            return $this->formatStructuredOutput($document, $title, (string) $docId);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function formatStructuredOutput(array $document, string $title, string $docId): ToolResult
    {
        $structure = $this->service->extractStructure($document);

        if (empty($structure)) {
            return ToolResult::success("Document: \"$title\" (id: $docId)\n\nThis document is empty.");
        }

        $lines = ["Document: \"$title\" (id: $docId)", '', 'Structure:'];

        $maxIndex = 0;
        foreach ($structure as $item) {
            $startIndex = (int) $item['startIndex'];
            $endIndex = (int) $item['endIndex'];
            $type = (string) $item['type'];
            $text = (string) $item['text'];

            if ($endIndex > $maxIndex) {
                $maxIndex = $endIndex;
            }

            if ($type === 'TABLE') {
                $rows = (int) ($item['rows'] ?? 0);
                $columns = (int) ($item['columns'] ?? 0);
                $lines[] = "[{$startIndex}-{$endIndex}] TABLE: {$rows} rows x {$columns} columns";
            } else {
                $preview = mb_strlen($text) > 80 ? mb_substr($text, 0, 77) . '...' : $text;
                $lines[] = "[{$startIndex}-{$endIndex}] {$type}: \"$preview\"";
            }
        }

        $lines[] = '';
        $lines[] = "Total: {$maxIndex} characters";

        return ToolResult::success(implode("\n", $lines));
    }

    public function parameters(): array
    {
        return [
            'document_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Docs document ID (from the URL).'],
        ];
    }
}
