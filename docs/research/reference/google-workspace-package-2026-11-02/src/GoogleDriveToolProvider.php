<?php

namespace OpenCompany\Integrations\Google;

use Illuminate\Support\Facades\Http;
use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\Integrations\Google\Services\GoogleDriveService;
use OpenCompany\Integrations\Google\Tools\GoogleDriveCopy;
use OpenCompany\Integrations\Google\Tools\GoogleDriveCreateFile;
use OpenCompany\Integrations\Google\Tools\GoogleDriveCreateFolder;
use OpenCompany\Integrations\Google\Tools\GoogleDriveDelete;
use OpenCompany\Integrations\Google\Tools\GoogleDriveMove;
use OpenCompany\Integrations\Google\Tools\GoogleDriveRename;
use OpenCompany\Integrations\Google\Tools\GoogleDriveGetFile;
use OpenCompany\Integrations\Google\Tools\GoogleDriveListPermissions;
use OpenCompany\Integrations\Google\Tools\GoogleDriveSearchFiles;
use OpenCompany\Integrations\Google\Tools\GoogleDriveShareFile;
use OpenCompany\Integrations\Google\Tools\GoogleDriveUnshareFile;
use OpenCompany\Integrations\Google\Tools\GoogleDriveStar;
use OpenCompany\Integrations\Google\Tools\GoogleDriveTrash;
use OpenCompany\Integrations\Google\Tools\GoogleDriveUnstar;
use OpenCompany\Integrations\Google\Tools\GoogleDriveUntrash;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

use OpenCompany\IntegrationCore\Contracts\HasIntegrationCapabilities;
class GoogleDriveToolProvider implements ToolProvider, ConfigurableIntegration, HasIntegrationCapabilities
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
        return 'google-drive';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'Google Drive',
            'description' => 'File storage and management',
            'icon' => 'ph:google-drive-logo',
            'logo' => 'simple-icons:googledrive',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Drive',
            'description' => 'File search, management, and sharing',
            'icon' => 'ph:google-drive-logo',
            'logo' => 'simple-icons:googledrive',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/drive.googleapis.com',
            'catalog_visibility' => 'hidden',
            'replaced_by' => 'google-drive',
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_drive',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Drive" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://www.googleapis.com/drive/v3/about', [
                'fields' => 'user(displayName,emailAddress),storageQuota(usage,limit)',
            ]);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $email = $data['user']['emailAddress'] ?? $connectedEmail ?? 'unknown';
                $usage = (int) ($data['storageQuota']['usage'] ?? 0);
                $limit = (int) ($data['storageQuota']['limit'] ?? 0);

                $usageFormatted = GoogleDriveService::formatSize($usage);
                $message = "Connected as {$email}. {$usageFormatted} used.";
                if ($limit > 0) {
                    $limitFormatted = GoogleDriveService::formatSize($limit);
                    $message = "Connected as {$email}. {$usageFormatted} of {$limitFormatted} used.";
                }

                return ['success' => true, 'message' => $message];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Drive API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
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
            'google_drive_copy' => [
                'class' => GoogleDriveCopy::class,
                'type' => 'read',
                'name' => 'Google Drive Copy',
                'description' => 'Duplicate a file in Google Drive.',
                'icon' => 'ph:wrench',
            ],
            'google_drive_create_file' => [
                'class' => GoogleDriveCreateFile::class,
                'type' => 'write',
                'name' => 'Google Drive Create File',
                'description' => 'Create an empty Google Doc, Sheet, or Presentation in Google Drive.',
                'icon' => 'ph:wrench',
            ],
            'google_drive_create_folder' => [
                'class' => GoogleDriveCreateFolder::class,
                'type' => 'write',
                'name' => 'Google Drive Create Folder',
                'description' => 'Create a folder in Google Drive.',
                'icon' => 'ph:wrench',
            ],
            'google_drive_delete' => [
                'class' => GoogleDriveDelete::class,
                'type' => 'read',
                'name' => 'Google Drive Delete',
                'description' => 'Permanently delete a file from Google Drive (irreversible).',
                'icon' => 'ph:wrench',
            ],
            'google_drive_move' => [
                'class' => GoogleDriveMove::class,
                'type' => 'read',
                'name' => 'Google Drive Move',
                'description' => 'Move a file to a different folder in Google Drive.',
                'icon' => 'ph:wrench',
            ],
            'google_drive_rename' => [
                'class' => GoogleDriveRename::class,
                'type' => 'read',
                'name' => 'Google Drive Rename',
                'description' => 'Rename a file or folder in Google Drive.',
                'icon' => 'ph:wrench',
            ],
            'google_drive_get_file' => [
                'class' => GoogleDriveGetFile::class,
                'type' => 'read',
                'name' => 'Google Drive Get File',
                'description' => 'Get file metadata by ID from Google Drive. For Google Docs/Sheets/Slides, use `export_as` to get content as text, csv, or markdown.',
                'icon' => 'ph:wrench',
            ],
            'google_drive_list_permissions' => [
                'class' => GoogleDriveListPermissions::class,
                'type' => 'read',
                'name' => 'Google Drive List Permissions',
                'description' => 'List all permissions (sharing settings) on a Google Drive file or folder.',
                'icon' => 'ph:wrench',
            ],
            'google_drive_search_files' => [
                'class' => GoogleDriveSearchFiles::class,
                'type' => 'read',
                'name' => 'Google Drive Search Files',
                'description' => 'Search for files in Google Drive using Drive query syntax (default: 20 results, max: 100). Trashed files are excluded by default. Drive query syntax examples: - By name: `name contains \'budget\'` or `name = \'Q1 Report\'` - By type: `mimeType = \'application/vnd.google-apps.spreadsheet\'` (also: document, presentation, folder) - In folder: `\'FOLDER_ID\' in parents` - Recent: `modifiedTime > \'2026-01-01\'` - Shared with me: `sharedWithMe = true` - Starred: `starred = true` - By owner: `\'user@example.com\' in owners` - Combine: `name contains \'report\' and mimeType = \'application/vnd.google-apps.spreadsheet\'`',
                'icon' => 'ph:wrench',
            ],
            'google_drive_share_file' => [
                'class' => GoogleDriveShareFile::class,
                'type' => 'read',
                'name' => 'Google Drive Share File',
                'description' => 'Share a Google Drive file or folder. Provide `fileId`, `role` ("reader", "writer", "commenter"), and one of: - `email`: share with a specific user (e.g., "alice@example.com") - `domain`: share with an entire domain (e.g., "example.com") - `type` set to `"anyone"`: make accessible to anyone with the link (no email/domain needed) - `notify` (optional, default true): send email notification (only for email shares)',
                'icon' => 'ph:wrench',
            ],
            'google_drive_unshare_file' => [
                'class' => GoogleDriveUnshareFile::class,
                'type' => 'read',
                'name' => 'Google Drive Unshare File',
                'description' => 'Remove a permission from a Google Drive file or folder. Use google_drive_list_permissions first to find the permission ID.',
                'icon' => 'ph:wrench',
            ],
            'google_drive_star' => [
                'class' => GoogleDriveStar::class,
                'type' => 'read',
                'name' => 'Google Drive Star',
                'description' => 'Mark a file as starred/favorite in Google Drive.',
                'icon' => 'ph:wrench',
            ],
            'google_drive_trash' => [
                'class' => GoogleDriveTrash::class,
                'type' => 'read',
                'name' => 'Google Drive Trash',
                'description' => 'Move a file to trash in Google Drive (reversible).',
                'icon' => 'ph:wrench',
            ],
            'google_drive_unstar' => [
                'class' => GoogleDriveUnstar::class,
                'type' => 'read',
                'name' => 'Google Drive Unstar',
                'description' => 'Remove star from a file in Google Drive.',
                'icon' => 'ph:wrench',
            ],
            'google_drive_untrash' => [
                'class' => GoogleDriveUntrash::class,
                'type' => 'read',
                'name' => 'Google Drive Untrash',
                'description' => 'Restore a file from trash in Google Drive.',
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
            ? new GoogleDriveService(GoogleServiceProvider::makeClient(app(), $this->appName(), (string) $account))
            : app(GoogleDriveService::class);

        return new $class($service);
    }
}
