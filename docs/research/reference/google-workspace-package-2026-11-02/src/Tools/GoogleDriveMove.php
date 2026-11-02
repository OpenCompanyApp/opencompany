<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDriveService;

class GoogleDriveMove implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function name(): string
    {
        return 'google_drive_move';
    }

    public function description(): string
    {
        return 'Move a file to a different folder in Google Drive.';
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

            $targetFolderId = $args['target_folder_id'] ?? '';
            if (empty($targetFolderId)) {
                return ToolResult::error('targetFolderId is required.');
            }

            // Get current parents to remove
            $file = $this->service->getFile($fileId, ['fields' => 'parents']);
            $currentParents = $file['parents'] ?? [];
            $removeParents = implode(',', $currentParents);

            $this->service->updateFile($fileId, [], [
                'addParents' => $targetFolderId,
                'removeParents' => $removeParents,
            ]);

            return ToolResult::success("File moved to folder '{$targetFolderId}'.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'file_id' => ['type' => 'string', 'required' => true, 'description' => 'File ID to move.'],
            'target_folder_id' => ['type' => 'string', 'required' => true, 'description' => 'Destination folder ID.'],
        ];
    }
}
