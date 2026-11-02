<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;

class GoogleDocsGet implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function name(): string
    {
        return 'google_docs_get';
    }

    public function description(): string
    {
        return 'Get the content of a Google Docs document. Returns plain text by default, or a structured outline with character indexes when format is "structured". The document ID is the long string in the URL: docs.google.com/document/d/{documentId}/edit';
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

            $format = $args['format'] ?? 'text';
            $document = $this->service->getDocument((string) $documentId);

            $title = $document['title'] ?? 'Untitled';
            $docId = $document['documentId'] ?? $documentId;

            if ($format === 'structured') {
                return $this->formatStructuredOutput($document, $title, (string) $docId);
            }

            // Default: plain text
            $text = $this->service->extractText($document);

            if (trim($text) === '') {
                return ToolResult::success("Document: \"$title\" (id: $docId)\n\nThis document is empty.");
            }

            return ToolResult::success("Document: \"$title\" (id: $docId)\n\n$text");
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
            return ToolResult::success(['title' => $title, 'documentId' => $docId, 'structure' => []]);
        }

        $maxIndex = 0;
        $elements = [];
        foreach ($structure as $item) {
            $endIndex = (int) $item['endIndex'];
            if ($endIndex > $maxIndex) {
                $maxIndex = $endIndex;
            }

            $element = [
                'startIndex' => (int) $item['startIndex'],
                'endIndex' => $endIndex,
                'type' => (string) $item['type'],
            ];

            if ($item['type'] === 'TABLE') {
                $element['rows'] = (int) ($item['rows'] ?? 0);
                $element['columns'] = (int) ($item['columns'] ?? 0);
            } else {
                $text = (string) $item['text'];
                $element['text'] = mb_strlen($text) > 80 ? mb_substr($text, 0, 77) . '...' : $text;
            }

            $elements[] = $element;
        }

        return ToolResult::success([
            'title' => $title,
            'documentId' => $docId,
            'totalCharacters' => $maxIndex,
            'structure' => $elements,
        ]);
    }

    public function parameters(): array
    {
        return [
            'document_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Docs document ID (from the URL).'],
            'format' => ['type' => 'string', 'description' => '"text" (default, plain text) or "structured" (outline with character indexes for editing).'],
        ];
    }
}