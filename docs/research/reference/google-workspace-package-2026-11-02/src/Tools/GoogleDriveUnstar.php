<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDriveService;

class GoogleDriveUnstar implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function name(): string
    {
        return 'google_drive_unstar';
    }

    public function description(): string
    {
        return 'Remove star from a file in Google Drive.';
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

            $this->service->updateFile($fileId, ['starred' => false]);

            return ToolResult::success('Star removed from file.');
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'file_id' => ['type' => 'string', 'required' => true, 'description' => 'File ID to unstar.'],
        ];
    }
}
