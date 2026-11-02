<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsAddSheet implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_add_sheet';
    }

    public function description(): string
    {
        return 'Add a new sheet/tab to a Google Spreadsheet.';
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

            $title = $args['title'] ?? '';
            if (empty($title)) {
                return ToolResult::error('title is required.');
            }

            $result = $this->service->batchUpdate($spreadsheetId, [
                ['addSheet' => ['properties' => ['title' => $title]]],
            ]);

            $newSheet = $result['replies'][0]['addSheet']['properties'] ?? [];

            return ToolResult::success([
                'message' => "Sheet '{$title}' added.",
                'sheetId' => (int) ($newSheet['sheetId'] ?? 0),
                'title' => $newSheet['title'] ?? $title,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'spreadsheet_id' => ['type' => 'string', 'required' => true, 'description' => 'Spreadsheet ID (from the URL).'],
            'title' => ['type' => 'string', 'required' => true, 'description' => 'Name for the new sheet/tab.'],
        ];
    }
}
