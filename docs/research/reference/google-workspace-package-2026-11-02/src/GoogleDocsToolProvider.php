<?php

namespace OpenCompany\Integrations\Google;

use Illuminate\Support\Facades\Http;
use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;
use OpenCompany\Integrations\Google\Tools\GoogleDocsAddBullets;
use OpenCompany\Integrations\Google\Tools\GoogleDocsCreate;
use OpenCompany\Integrations\Google\Tools\GoogleDocsDeleteRange;
use OpenCompany\Integrations\Google\Tools\GoogleDocsFormatText;
use OpenCompany\Integrations\Google\Tools\GoogleDocsGet;
use OpenCompany\Integrations\Google\Tools\GoogleDocsGetStructure;
use OpenCompany\Integrations\Google\Tools\GoogleDocsInsertImage;
use OpenCompany\Integrations\Google\Tools\GoogleDocsInsertPageBreak;
use OpenCompany\Integrations\Google\Tools\GoogleDocsInsertTable;
use OpenCompany\Integrations\Google\Tools\GoogleDocsInsertText;
use OpenCompany\Integrations\Google\Tools\GoogleDocsRemoveBullets;
use OpenCompany\Integrations\Google\Tools\GoogleDocsReplaceAll;
use OpenCompany\Integrations\Google\Tools\GoogleDocsSearchText;
use OpenCompany\Integrations\Google\Tools\GoogleDocsSetHeading;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

use OpenCompany\IntegrationCore\Contracts\HasIntegrationCapabilities;
class GoogleDocsToolProvider implements ToolProvider, ConfigurableIntegration, HasIntegrationCapabilities
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
        return 'google-docs';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'Google Docs',
            'description' => 'Create, read, and edit Google Docs',
            'icon' => 'ph:file-doc',
            'logo' => 'simple-icons:googledocs',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Docs',
            'description' => 'Create, read, and edit documents with formatting, tables, and images',
            'icon' => 'ph:file-doc',
            'logo' => 'simple-icons:googledocs',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/docs.googleapis.com',
            'catalog_visibility' => 'hidden',
            'replaced_by' => 'google-docs',
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_docs',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Docs" to authorize.'];
        }

        try {
            // Verify the token works by fetching user info
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://www.googleapis.com/oauth2/v2/userinfo');

            if ($response->successful()) {
                $email = $response->json('email') ?? $connectedEmail;
                $emailInfo = $email ? " ({$email})" : '';

                return [
                    'success' => true,
                    'message' => "Google Docs connected{$emailInfo}.",
                ];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Google API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
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
            'google_docs_add_bullets' => [
                'class' => GoogleDocsAddBullets::class,
                'type' => 'write',
                'name' => 'Google Docs Add Bullets',
                'description' => 'Add bullet or numbered list formatting to a range in a Google Docs document. Default preset is BULLET_DISC_CIRCLE_SQUARE. Use NUMBERED_DECIMAL_ALPHA_ROMAN for numbered lists.',
                'icon' => 'ph:wrench',
            ],
            'google_docs_create' => [
                'class' => GoogleDocsCreate::class,
                'type' => 'read',
                'name' => 'Google Docs Create',
                'description' => 'Create a new blank Google Docs document. Returns the document ID and URL.',
                'icon' => 'ph:wrench',
            ],
            'google_docs_delete_range' => [
                'class' => GoogleDocsDeleteRange::class,
                'type' => 'write',
                'name' => 'Google Docs Delete Range',
                'description' => 'Delete content in a Google Docs document by index range. Use google_docs_get_structure first to find the correct indexes.',
                'icon' => 'ph:wrench',
            ],
            'google_docs_format_text' => [
                'class' => GoogleDocsFormatText::class,
                'type' => 'read',
                'name' => 'Google Docs Format Text',
                'description' => 'Apply formatting to a text range in a Google Docs document. Supports bold, italic, underline, strikethrough, fontSize (points), fontFamily, foregroundColor (hex like "#FF0000"), and link (URL).',
                'icon' => 'ph:wrench',
            ],
            'google_docs_get' => [
                'class' => GoogleDocsGet::class,
                'type' => 'read',
                'name' => 'Google Docs Get',
                'description' => 'Get the content of a Google Docs document. Returns plain text by default, or a structured outline with character indexes when format is "structured". The document ID is the long string in the URL: docs.google.com/document/d/{documentId}/edit',
                'icon' => 'ph:wrench',
            ],
            'google_docs_get_structure' => [
                'class' => GoogleDocsGetStructure::class,
                'type' => 'read',
                'name' => 'Google Docs Get Structure',
                'description' => 'Get a simplified structure of a Google Docs document showing heading hierarchy, paragraph indexes, and table positions. Essential before performing index-based editing operations. The document ID is the long string in the URL: docs.google.com/document/d/{documentId}/edit',
                'icon' => 'ph:wrench',
            ],
            'google_docs_insert_image' => [
                'class' => GoogleDocsInsertImage::class,
                'type' => 'read',
                'name' => 'Google Docs Insert Image',
                'description' => 'Insert an image from a URL into a Google Docs document. Supports PNG, JPEG, and GIF. Optionally specify width and height in points.',
                'icon' => 'ph:wrench',
            ],
            'google_docs_insert_page_break' => [
                'class' => GoogleDocsInsertPageBreak::class,
                'type' => 'read',
                'name' => 'Google Docs Insert Page Break',
                'description' => 'Insert a page break into a Google Docs document. Omit index or set to -1 to insert at end.',
                'icon' => 'ph:wrench',
            ],
            'google_docs_insert_table' => [
                'class' => GoogleDocsInsertTable::class,
                'type' => 'read',
                'name' => 'Google Docs Insert Table',
                'description' => 'Insert a table into a Google Docs document. Specify rows and columns. Omit index or set to -1 to insert at end.',
                'icon' => 'ph:wrench',
            ],
            'google_docs_insert_text' => [
                'class' => GoogleDocsInsertText::class,
                'type' => 'read',
                'name' => 'Google Docs Insert Text',
                'description' => 'Insert text into a Google Docs document at a specific position or at the end. Omit index or set to -1 to append at end.',
                'icon' => 'ph:wrench',
            ],
            'google_docs_remove_bullets' => [
                'class' => GoogleDocsRemoveBullets::class,
                'type' => 'write',
                'name' => 'Google Docs Remove Bullets',
                'description' => 'Remove bullet or numbered list formatting from a range in a Google Docs document.',
                'icon' => 'ph:wrench',
            ],
            'google_docs_replace_all' => [
                'class' => GoogleDocsReplaceAll::class,
                'type' => 'read',
                'name' => 'Google Docs Replace All',
                'description' => 'Find and replace all occurrences of text in a Google Docs document. No indexes needed — this is the simplest way to edit text.',
                'icon' => 'ph:wrench',
            ],
            'google_docs_search_text' => [
                'class' => GoogleDocsSearchText::class,
                'type' => 'read',
                'name' => 'Google Docs Search Text',
                'description' => 'Find all occurrences of text in a Google Docs document with their start/end indexes. Useful before format_text or delete_range operations. The document ID is the long string in the URL: docs.google.com/document/d/{documentId}/edit',
                'icon' => 'ph:wrench',
            ],
            'google_docs_set_heading' => [
                'class' => GoogleDocsSetHeading::class,
                'type' => 'read',
                'name' => 'Google Docs Set Heading',
                'description' => 'Set paragraph style (heading level) for a range in a Google Docs document. Valid styles: HEADING_1 through HEADING_6, TITLE, SUBTITLE, NORMAL_TEXT.',
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
            ? new GoogleDocsService(GoogleServiceProvider::makeClient(app(), $this->appName(), (string) $account))
            : app(GoogleDocsService::class);

        return new $class($service);
    }
}
