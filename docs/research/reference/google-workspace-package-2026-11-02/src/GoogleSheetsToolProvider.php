<?php

namespace OpenCompany\Integrations\Google;

use Illuminate\Support\Facades\Http;
use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsAddFilter;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsAddSheet;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsAppend;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsBatchRead;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsBatchWrite;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsClear;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsCreate;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsDeleteColumns;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsDeleteRows;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsDeleteSheet;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsDuplicateSheet;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsFind;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsGetMetadata;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsInsertColumns;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsInsertRows;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsReadRange;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsRemoveFilter;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsRenameSheet;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsSortRange;
use OpenCompany\Integrations\Google\Tools\GoogleSheetsWriteRange;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

use OpenCompany\IntegrationCore\Contracts\HasIntegrationCapabilities;
class GoogleSheetsToolProvider implements ToolProvider, ConfigurableIntegration, HasIntegrationCapabilities
{

/**
     * Describe host and authentication capabilities for catalog and setup flows.
     *
     * @return array<string, mixed>
     */
    public function integrationCapabilities(): array
    {
        return [
          'auth' => [
            'strategy' => 'oauth2_authorization_code',
            'legacy_auth_type' => 'oauth',
            'credential_mode' => 'stored_token',
            'setup_flows' =>
            [
              0 => 'web_redirect',
              1 => 'local_redirect',
              2 => 'device_code',
            ],
            'requires_browser_for_setup' => true,
            'refreshable' => true,
            'token_keys' =>
            [
              0 => 'access_token',
              1 => 'refresh_token',
              2 => 'expires_at',
            ],
            'notes' =>
            [
              0 => 'Web hosts use the registered OAuth redirect callback.',
              1 => 'CLI hosts can support Google OAuth with a desktop loopback redirect; device-code setup is possible where scopes allow it.',
              2 => 'CLI runtime works with stored access and refresh tokens.',
            ],
          ],
          'host_availability' => [
            'web' =>
            [
              'setup_supported' => true,
              'runtime_supported' => true,
              'setup_mode' => 'web_redirect',
            ],
            'cli' =>
            [
              'setup_supported' => true,
              'runtime_supported' => true,
              'setup_mode' => 'local_redirect_or_device_code',
              'runtime_mode' => 'normal',
            ],
          ],
          'runtime_requirements' => [
          ],
          'compatibility' => [
            'web_setup_supported' => true,
            'web_runtime_supported' => true,
            'cli_setup_supported' => true,
            'cli_runtime_supported' => true,
          ],
          'shared_credentials' => [
            'group' => 'google-workspace-oauth-client',
            'keys' => ['client_id', 'client_secret'],
          ],
        ];
    }

    public function appName(): string
    {
        return 'google-sheets';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'Google Sheets',
            'description' => 'Spreadsheet data management',
            'icon' => 'ph:table',
            'logo' => 'simple-icons:googlesheets',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Sheets',
            'description' => 'Read, write, and manage spreadsheet data',
            'icon' => 'ph:table',
            'logo' => 'simple-icons:googlesheets',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/sheets.googleapis.com',
        ];
    }    public function configSchema(): array
    {
        return [
            [
                'key' => 'client_id',
                'type' => 'text',
                'label' => 'Client ID',
                'placeholder' => 'Your Google Cloud OAuth Client ID',
                'hint' => 'From <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> &rarr; Credentials &rarr; OAuth 2.0 Client IDs. Shared across all Google integrations &mdash; only needs to be entered once.',
                'required' => true,
            ],
            [
                'key' => 'client_secret',
                'type' => 'secret',
                'label' => 'Client Secret',
                'placeholder' => 'Your Google Cloud OAuth Client Secret',
                'required' => true,
            ],
            [
                'key' => 'access_token',
                'type' => 'oauth_connect',
                'label' => 'Google Account',
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_sheets',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Sheets" to authorize.'];
        }

        try {
            // Try to access a non-existent spreadsheet to verify auth
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://sheets.googleapis.com/v4/spreadsheets/__test_connection__', [
                'fields' => 'spreadsheetId',
            ]);

            // 404 = auth works, spreadsheet not found (expected)
            // 401/403 = auth failed
            if ($response->status() === 404) {
                $emailInfo = $connectedEmail ? " ({$connectedEmail})" : '';

                return ['success' => true, 'message' => "Google Sheets connected{$emailInfo}."];
            }

            if ($response->successful()) {
                // Shouldn't happen with a fake ID, but handle it gracefully
                $emailInfo = $connectedEmail ? " ({$connectedEmail})" : '';

                return ['success' => true, 'message' => "Google Sheets connected{$emailInfo}."];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Sheets API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public function validationRules(): array
    {
        return [
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'access_token' => 'nullable|string',
        ];
    }

    public function tools(): array
    {
        return [
            'google_sheets_add_filter' => [
                'class' => GoogleSheetsAddFilter::class,
                'type' => 'write',
                'name' => 'Google Sheets Add Filter',
                'description' => 'Apply filter dropdowns to a range in a Google Sheets sheet/tab.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_add_sheet' => [
                'class' => GoogleSheetsAddSheet::class,
                'type' => 'write',
                'name' => 'Google Sheets Add Sheet',
                'description' => 'Add a new sheet/tab to a Google Spreadsheet.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_append' => [
                'class' => GoogleSheetsAppend::class,
                'type' => 'read',
                'name' => 'Google Sheets Append',
                'description' => 'Append rows after the last data row in a Google Spreadsheet. Auto-detects the table boundary. Provide the range (e.g., "Sheet1" or "Sheet1!A:D") and a 2D array of rows to append.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_batch_read' => [
                'class' => GoogleSheetsBatchRead::class,
                'type' => 'read',
                'name' => 'Google Sheets Batch Read',
                'description' => 'Read multiple ranges from a Google Spreadsheet in one call. Provide an array of A1 notation ranges (e.g., ["Sheet1!A1:B5", "Sheet2!C1:D10"]). Returns results keyed by range.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_batch_write' => [
                'class' => GoogleSheetsBatchWrite::class,
                'type' => 'read',
                'name' => 'Google Sheets Batch Write',
                'description' => 'Write to multiple ranges in a Google Spreadsheet in one call. Provide an array of {range, values} objects to update several areas at once.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_clear' => [
                'class' => GoogleSheetsClear::class,
                'type' => 'read',
                'name' => 'Google Sheets Clear',
                'description' => 'Clear all values from a Google Sheets range (keeps formatting intact). Specify the range in A1 notation.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_create' => [
                'class' => GoogleSheetsCreate::class,
                'type' => 'read',
                'name' => 'Google Sheets Create',
                'description' => 'Create a new empty Google Spreadsheet with a given title. Returns the new spreadsheet ID and URL.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_delete_columns' => [
                'class' => GoogleSheetsDeleteColumns::class,
                'type' => 'write',
                'name' => 'Google Sheets Delete Columns',
                'description' => 'Delete columns from a Google Sheets sheet/tab. Uses 0-based indexing.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_delete_rows' => [
                'class' => GoogleSheetsDeleteRows::class,
                'type' => 'write',
                'name' => 'Google Sheets Delete Rows',
                'description' => 'Delete rows from a Google Sheets sheet/tab. Uses 0-based indexing.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_delete_sheet' => [
                'class' => GoogleSheetsDeleteSheet::class,
                'type' => 'write',
                'name' => 'Google Sheets Delete Sheet',
                'description' => 'Delete a sheet/tab from a Google Spreadsheet.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_duplicate_sheet' => [
                'class' => GoogleSheetsDuplicateSheet::class,
                'type' => 'write',
                'name' => 'Google Sheets Duplicate Sheet',
                'description' => 'Copy a sheet/tab within the same Google Spreadsheet.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_find' => [
                'class' => GoogleSheetsFind::class,
                'type' => 'read',
                'name' => 'Google Sheets Find',
                'description' => 'Search for text within a Google Spreadsheet. Searches all sheets by default, or specify a sheet name to narrow the search. Returns match count and number of sheets containing matches.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_get_metadata' => [
                'class' => GoogleSheetsGetMetadata::class,
                'type' => 'read',
                'name' => 'Google Sheets Get Metadata',
                'description' => 'Get spreadsheet title and list of sheets/tabs with their names, IDs, and dimensions. Use this first to discover sheet names and structure before reading or writing data.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_insert_columns' => [
                'class' => GoogleSheetsInsertColumns::class,
                'type' => 'read',
                'name' => 'Google Sheets Insert Columns',
                'description' => 'Insert blank columns into a Google Sheets sheet/tab. Uses 0-based indexing.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_insert_rows' => [
                'class' => GoogleSheetsInsertRows::class,
                'type' => 'read',
                'name' => 'Google Sheets Insert Rows',
                'description' => 'Insert blank rows into a Google Sheets sheet/tab. Uses 0-based indexing.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_read_range' => [
                'class' => GoogleSheetsReadRange::class,
                'type' => 'read',
                'name' => 'Google Sheets Read Range',
                'description' => 'Read cell values from a Google Sheets range using A1 notation. A1 notation examples: `Sheet1!A1:D10` (range), `Sheet1!A:A` (whole column), `Sheet1` (entire sheet). Sheet names with spaces need quotes: `\'My Sheet\'!A1:B2`.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_remove_filter' => [
                'class' => GoogleSheetsRemoveFilter::class,
                'type' => 'write',
                'name' => 'Google Sheets Remove Filter',
                'description' => 'Remove the filter from a Google Sheets sheet/tab.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_rename_sheet' => [
                'class' => GoogleSheetsRenameSheet::class,
                'type' => 'read',
                'name' => 'Google Sheets Rename Sheet',
                'description' => 'Rename a sheet/tab in a Google Spreadsheet.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_sort_range' => [
                'class' => GoogleSheetsSortRange::class,
                'type' => 'read',
                'name' => 'Google Sheets Sort Range',
                'description' => 'Sort data by column(s) in a Google Sheets range.',
                'icon' => 'ph:wrench',
            ],
            'google_sheets_write_range' => [
                'class' => GoogleSheetsWriteRange::class,
                'type' => 'read',
                'name' => 'Google Sheets Write Range',
                'description' => 'Write values to a Google Sheets range. Values format: `[["Name", "Age"], ["Alice", 30]]` — each inner array is one row. Formulas work with user_entered input mode (default): `[["=SUM(A1:A10)"]]`.',
                'icon' => 'ph:wrench',
            ],
        ];
    }


    public function scriptDocsPath(): ?string
    {
        return dirname(__DIR__) . '/script-docs/google.md';
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'access_token', 'type' => 'oauth', 'label' => 'Google Account', 'required' => true],
        ];
    }

    public function isIntegration(): bool
    {
        return true;
    }

    /** @param  array<string, mixed>  $context */
    public function createTool(string $class, array $context = []): Tool
    {
        $account = $context['account'] ?? null;
        $service = $account !== null
            ? new GoogleSheetsService(GoogleServiceProvider::makeClient(app(), $this->appName(), (string) $account))
            : app(GoogleSheetsService::class);

        return new $class($service);
    }
}
