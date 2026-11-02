<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDriveService;

class GoogleDriveRename implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function name(): string
    {
        return 'google_drive_rename';
    }

    public function description(): string
    {
        return 'Rename a file or folder in Google Drive.';
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

            $name = $args['name'] ?? '';
            if (empty($name)) {
                return ToolResult::error('name is required.');
            }

            $this->service->updateFile($fileId, ['name' => $name]);

            return ToolResult::success("File renamed to '{$name}'.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'file_id' => ['type' => 'string', 'required' => true, 'description' => 'File or folder ID to rename.'],
            'name' => ['type' => 'string', 'required' => true, 'description' => 'New name for the file or folder.'],
        ];
    }
}
