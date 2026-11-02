<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDriveService;

class GoogleDriveCreateFolder implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function name(): string
    {
        return 'google_drive_create_folder';
    }

    public function description(): string
    {
        return 'Create a folder in Google Drive.';
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

            $metadata = [
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
            ];

            $parentId = $args['parent_id'] ?? '';
            if ($parentId !== '') {
                $metadata['parents'] = [$parentId];
            }

            $result = $this->service->createFile($metadata);

            return ToolResult::success([
                'message' => "Folder '{$name}' created.",
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
            'name' => ['type' => 'string', 'required' => true, 'description' => 'Folder name.'],
            'parent_id' => ['type' => 'string', 'description' => 'Parent folder ID (defaults to root).'],
        ];
    }
}
