<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;

class GoogleDocsReplaceAll implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function name(): string
    {
        return 'google_docs_replace_all';
    }

    public function description(): string
    {
        return 'Find and replace all occurrences of text in a Google Docs document. No indexes needed — this is the simplest way to edit text.';
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

            $find = $args['find'] ?? '';
            if (empty($find)) {
                return ToolResult::error('find is required.');
            }

            $replace = $args['replace'] ?? '';
            $matchCase = (bool) ($args['match_case'] ?? true);

            $requests = [
                ['replaceAllText' => [
                    'containsText' => [
                        'text' => (string) $find,
                        'matchCase' => $matchCase,
                    ],
                    'replaceText' => (string) $replace,
                ]],
            ];

            $result = $this->service->batchUpdate((string) $documentId, $requests);

            // Extract replacement count from response
            $replies = $result['replies'] ?? [];
            $count = 0;
            foreach ($replies as $reply) {
                $count += (int) ($reply['replaceAllText']['occurrencesChanged'] ?? 0);
            }

            if ($count === 0) {
                return ToolResult::success("No occurrences of \"$find\" found.");
            }

            return ToolResult::success("Replaced {$count} " . ($count === 1 ? 'occurrence' : 'occurrences') . " of \"$find\" with \"$replace\".");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'document_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Docs document ID (from the URL).'],
            'find' => ['type' => 'string', 'required' => true, 'description' => 'Text to find.'],
            'replace' => ['type' => 'string', 'required' => true, 'description' => 'Replacement text.'],
            'match_case' => ['type' => 'boolean', 'description' => 'Case-sensitive match (default true).'],
        ];
    }
}
