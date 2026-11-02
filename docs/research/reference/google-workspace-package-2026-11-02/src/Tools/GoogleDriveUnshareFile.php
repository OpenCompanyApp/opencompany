<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDriveService;

class GoogleDriveUnshareFile implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function name(): string
    {
        return 'google_drive_unshare_file';
    }

    public function description(): string
    {
        return 'Remove a permission from a Google Drive file or folder. Use google_drive_list_permissions first to find the permission ID.';
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

            $permissionId = $args['permission_id'] ?? '';
            if (empty($permissionId)) {
                return ToolResult::error('permissionId is required. Use google_drive_list_permissions to find it.');
            }

            $this->service->deletePermission($fileId, $permissionId);

            return ToolResult::success('Permission removed.');
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'file_id' => ['type' => 'string', 'required' => true, 'description' => 'File/folder ID to remove permission from.'],
            'permission_id' => ['type' => 'string', 'required' => true, 'description' => 'Permission ID to remove.'],
        ];
    }
}
