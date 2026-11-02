<?php

namespace OpenCompany\Integrations\Google;

use Illuminate\Support\Facades\Http;
use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\Integrations\Google\Services\GoogleContactsService;
use OpenCompany\Integrations\Google\Tools\GoogleContactsCreate;
use OpenCompany\Integrations\Google\Tools\GoogleContactsDelete;
use OpenCompany\Integrations\Google\Tools\GoogleContactsGet;
use OpenCompany\Integrations\Google\Tools\GoogleContactsList;
use OpenCompany\Integrations\Google\Tools\GoogleContactsListGroups;
use OpenCompany\Integrations\Google\Tools\GoogleContactsSearchContacts;
use OpenCompany\Integrations\Google\Tools\GoogleContactsUpdate;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

use OpenCompany\IntegrationCore\Contracts\HasIntegrationCapabilities;
class GoogleContactsToolProvider implements ToolProvider, ConfigurableIntegration, HasIntegrationCapabilities
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
        return 'google-contacts';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'Google Contacts',
            'description' => 'Contact management',
            'icon' => 'ph:address-book',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Contacts',
            'description' => 'Contact search, lookup, and management',
            'icon' => 'ph:address-book',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/people.googleapis.com',
            'catalog_visibility' => 'hidden',
            'replaced_by' => 'google-contacts',
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_contacts',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Contacts" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://people.googleapis.com/v1/people/me/connections', [
                'personFields' => 'names',
                'pageSize' => '1',
            ]);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $total = $data['totalPeople'] ?? $data['totalItems'] ?? 0;
                $email = $connectedEmail ?? 'your account';

                return [
                    'success' => true,
                    'message' => "Connected to Google Contacts ({$email}). {$total} contacts.",
                ];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'People API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
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
            'google_contacts_create' => [
                'class' => GoogleContactsCreate::class,
                'type' => 'read',
                'name' => 'Google Contacts Create',
                'description' => 'Create a new Google Contact with name, email, phone, company, title, address, and notes.',
                'icon' => 'ph:wrench',
            ],
            'google_contacts_delete' => [
                'class' => GoogleContactsDelete::class,
                'type' => 'read',
                'name' => 'Google Contacts Delete',
                'description' => 'Permanently delete a Google Contact by resource name.',
                'icon' => 'ph:wrench',
            ],
            'google_contacts_get' => [
                'class' => GoogleContactsGet::class,
                'type' => 'read',
                'name' => 'Google Contacts Get',
                'description' => 'Get full details of a single Google Contact including notes, websites, and group memberships.',
                'icon' => 'ph:wrench',
            ],
            'google_contacts_list' => [
                'class' => GoogleContactsList::class,
                'type' => 'read',
                'name' => 'Google Contacts List',
                'description' => 'List all Google Contacts sorted by first name with pagination.',
                'icon' => 'ph:wrench',
            ],
            'google_contacts_list_groups' => [
                'class' => GoogleContactsListGroups::class,
                'type' => 'read',
                'name' => 'Google Contacts List Groups',
                'description' => 'List all Google Contact groups/labels (e.g., Friends, Family, custom groups) with member counts.',
                'icon' => 'ph:wrench',
            ],
            'google_contacts_search_contacts' => [
                'class' => GoogleContactsSearchContacts::class,
                'type' => 'read',
                'name' => 'Google Contacts Search Contacts',
                'description' => 'Fuzzy search Google Contacts by name, email, or phone. Matches partial strings (e.g., "john", "acme.com", "555"). Use this to look up contacts before sending emails with gmail_send.',
                'icon' => 'ph:wrench',
            ],
            'google_contacts_update' => [
                'class' => GoogleContactsUpdate::class,
                'type' => 'read',
                'name' => 'Google Contacts Update',
                'description' => 'Update an existing Google Contact. Unspecified fields are preserved. Email, phone, and address are added alongside existing values; name, company, title, and notes are replaced.',
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
            ? new GoogleContactsService(GoogleServiceProvider::makeClient(app(), $this->appName(), (string) $account))
            : app(GoogleContactsService::class);

        return new $class($service);
    }
}
