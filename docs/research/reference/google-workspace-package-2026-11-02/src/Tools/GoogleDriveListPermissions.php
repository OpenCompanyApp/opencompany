<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDriveService;

class GoogleDriveListPermissions implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function name(): string
    {
        return 'google_drive_list_permissions';
    }

    public function description(): string
    {
        return 'List all permissions (sharing settings) on a Google Drive file or folder.';
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

            $result = $this->service->listPermissions($fileId);
            $permissions = $result['permissions'] ?? [];

            if (empty($permissions)) {
                return ToolResult::success('No permissions found.');
            }

            $formatted = array_map(fn (array $perm) => array_filter([
                'id' => $perm['id'] ?? '',
                'type' => $perm['type'] ?? '',
                'role' => $perm['role'] ?? '',
                'email' => $perm['emailAddress'] ?? '',
                'displayName' => $perm['displayName'] ?? '',
                'domain' => $perm['domain'] ?? '',
            ], fn ($v) => $v !== ''), $permissions);

            return ToolResult::success([
                'count' => count($formatted),
                'permissions' => $formatted,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'file_id' => ['type' => 'string', 'required' => true, 'description' => 'File/folder ID to list permissions for.'],
        ];
    }
}