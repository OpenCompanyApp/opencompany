<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDriveService;

class GoogleDriveCreateFile implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function name(): string
    {
        return 'google_drive_create_file';
    }

    public function description(): string
    {
        return 'Create an empty Google Doc, Sheet, or Presentation in Google Drive.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Drive integration is not configured.');
            }

            $name = $args['name'] ?? '';
            if (empty($name)) {
                return ToolResult::error('name is required.');
            }

            $type = $args['type'] ?? '';
            $mimeType = GoogleDriveService::resolveMimeType($type);
            if ($mimeType === null) {
                return ToolResult::error('type must be "document", "spreadsheet", or "presentation".');
            }

            $metadata = [
                'name' => $name,
                'mimeType' => $mimeType,
            ];

            $parentId = $args['parent_id'] ?? '';
            if ($parentId !== '') {
                $metadata['parents'] = [$parentId];
            }

            $result = $this->service->createFile($metadata);
            $typeLabel = ucfirst($type);

            return ToolResult::success([
                'message' => "{$typeLabel} '{$name}' created.",
                'id' => $result['id'] ?? '',
                'webViewLink' => $result['webViewLink'] ?? '',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'name' => ['type' => 'string', 'required' => true, 'description' => 'File name.'],
            'type' => ['type' => 'string', 'required' => true, 'description' => 'File type: "document", "spreadsheet", or "presentation".'],
            'parent_id' => ['type' => 'string', 'description' => 'Parent folder ID (defaults to root).'],
        ];
    }
}
