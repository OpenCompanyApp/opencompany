<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsCreate implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_create';
    }

    public function description(): string
    {
        return 'Create a new empty Google Spreadsheet with a given title. Returns the new spreadsheet ID and URL.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Sheets integration is not configured.');
            }

            $title = $args['title'] ?? '';
            if (empty($title)) {
                return ToolResult::error('title is required for create.');
            }

            $result = $this->service->createSpreadsheet($title);

            return ToolResult::success([
                'message' => "Spreadsheet '{$title}' created.",
                'spreadsheetId' => $result['spreadsheetId'] ?? '',
                'url' => $result['spreadsheetUrl'] ?? '',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'title' => ['type' => 'string', 'required' => true, 'description' => 'Title for the new spreadsheet.'],
        ];
    }
}
