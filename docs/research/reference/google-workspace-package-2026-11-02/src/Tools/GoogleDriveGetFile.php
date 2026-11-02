<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDriveService;

class GoogleDriveGetFile implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function name(): string
    {
        return 'google_drive_get_file';
    }

    public function description(): string
    {
        return 'Get file metadata by ID from Google Drive. For Google Docs/Sheets/Slides, use `export_as` to get content as text, csv, or markdown.';
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

            $file = $this->service->getFile($fileId);
            $output = $this->formatFile($file);

            // Handle export for Google Workspace files
            $exportAs = $args['export_as'] ?? '';
            if ($exportAs !== '') {
                $mimeType = $file['mimeType'] ?? '';

                if (! GoogleDriveService::isGoogleWorkspaceType($mimeType)) {
                    $output['export_error'] = 'Export is only available for Google Docs, Sheets, and Slides.';
                } else {
                    $exportMimeType = GoogleDriveService::getExportMimeType($mimeType, $exportAs);
                    if ($exportMimeType === null) {
                        $output['export_error'] = "Format '{$exportAs}' is not supported for this file type.";
                    } else {
                        $content = $this->service->exportFile($fileId, $exportMimeType);

                        // For markdown export of Docs, strip HTML tags
                        if ($exportAs === 'markdown' && $exportMimeType === 'text/html') {
                            $content = strip_tags($content);
                        }

                        $output['content'] = $content;
                        $output['export_format'] = $exportAs;
                    }
                }
            }

            return ToolResult::success($output);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * Format a file array for output.
     *
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    private function formatFile(array $file): array
    {
        $formatted = [
            'id' => $file['id'] ?? '',
            'name' => $file['name'] ?? '',
            'mimeType' => $file['mimeType'] ?? '',
            'createdTime' => $file['createdTime'] ?? '',
            'modifiedTime' => $file['modifiedTime'] ?? '',
            'webViewLink' => $file['webViewLink'] ?? '',
        ];

        if (isset($file['size'])) {
            $formatted['size'] = GoogleDriveService::formatSize($file['size']);
        }

        if (isset($file['shared']) && $file['shared']) {
            $formatted['shared'] = true;
        }

        if (isset($file['starred']) && $file['starred']) {
            $formatted['starred'] = true;
        }

        if (isset($file['parents'])) {
            $formatted['parents'] = $file['parents'];
        }

        if (isset($file['owners']) && is_array($file['owners'])) {
            $formatted['owner'] = $file['owners'][0]['emailAddress'] ?? $file['owners'][0]['displayName'] ?? '';
        }

        return array_filter($formatted, fn ($v) => $v !== '');
    }

    public function parameters(): array
    {
        return [
            'file_id' => ['type' => 'string', 'required' => true, 'description' => 'File ID to retrieve.'],
            'export_as' => ['type' => 'string', 'description' => 'Export format for Google Docs/Sheets/Slides: "text", "csv", or "markdown".'],
        ];
    }
}