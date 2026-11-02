<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDriveService;

class GoogleDriveCopy implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function name(): string
    {
        return 'google_drive_copy';
    }

    public function description(): string
    {
        return 'Duplicate a file in Google Drive.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Drive integration is not configured.');
            }

            $fileId = $args['file_id'] ?? '';
            if (empty($fileId)) {
                return ToolResult::error('fileId is required.');
            }

            $metadata = [];

            $name = $args['name'] ?? '';
            if ($name !== '') {
                $metadata['name'] = $name;
            }

            $parentId = $args['parent_id'] ?? '';
            if ($parentId !== '') {
                $metadata['parents'] = [$parentId];
            }

            $result = $this->service->copyFile($fileId, $metadata);
            $newName = $result['name'] ?? $name ?: 'copy';

            return ToolResult::success([
                'message' => "File copied as '{$newName}'.",
                'id' => $result['id'] ?? '',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'file_id' => ['type' => 'string', 'required' => true, 'description' => 'File ID to copy.'],
            'name' => ['type' => 'string', 'description' => 'Name for the copy (optional).'],
            'parent_id' => ['type' => 'string', 'description' => 'Target folder ID for the copy (optional).'],
        ];
    }
}
