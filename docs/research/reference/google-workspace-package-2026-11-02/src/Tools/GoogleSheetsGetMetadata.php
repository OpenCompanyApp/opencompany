<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsGetMetadata implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_get_metadata';
    }

    public function description(): string
    {
        return 'Get spreadsheet title and list of sheets/tabs with their names, IDs, and dimensions. Use this first to discover sheet names and structure before reading or writing data.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Sheets integration is not configured.');
            }

            $spreadsheetId = $args['spreadsheet_id'] ?? '';
            if (empty($spreadsheetId)) {
                return ToolResult::error('spreadsheetId is required.');
            }

            $metadata = $this->service->getMetadata($spreadsheetId);
            $formatted = GoogleSheetsService::formatMetadata($metadata);

            return ToolResult::success($formatted);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'spreadsheet_id' => ['type' => 'string', 'required' => true, 'description' => 'Spreadsheet ID (from the URL).'],
        ];
    }
}
