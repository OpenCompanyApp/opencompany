<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;

class GoogleDocsCreate implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function name(): string
    {
        return 'google_docs_create';
    }

    public function description(): string
    {
        return 'Create a new blank Google Docs document. Returns the document ID and URL.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Docs integration is not configured.');
            }

            $title = $args['title'] ?? '';
            if (empty($title)) {
                return ToolResult::error('title is required.');
            }

            $result = $this->service->createDocument((string) $title);
            $docId = $result['documentId'] ?? '';
            $url = "https://docs.google.com/document/d/{$docId}/edit";

            return ToolResult::success("Document created.\nTitle: \"$title\"\nID: $docId\nURL: $url");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'title' => ['type' => 'string', 'required' => true, 'description' => 'Title for the new document.'],
        ];
    }
}
