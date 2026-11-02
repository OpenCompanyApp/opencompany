<?php

namespace OpenCompany\Integrations\Google;

use Illuminate\Support\Facades\Http;
use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\Integrations\Google\Services\GoogleFormsService;
use OpenCompany\Integrations\Google\Tools\GoogleFormsAddQuestion;
use OpenCompany\Integrations\Google\Tools\GoogleFormsAddSection;
use OpenCompany\Integrations\Google\Tools\GoogleFormsAddTextItem;
use OpenCompany\Integrations\Google\Tools\GoogleFormsCreate;
use OpenCompany\Integrations\Google\Tools\GoogleFormsDeleteItem;
use OpenCompany\Integrations\Google\Tools\GoogleFormsGet;
use OpenCompany\Integrations\Google\Tools\GoogleFormsGetResponse;
use OpenCompany\Integrations\Google\Tools\GoogleFormsListResponses;
use OpenCompany\Integrations\Google\Tools\GoogleFormsMoveItem;
use OpenCompany\Integrations\Google\Tools\GoogleFormsPublish;
use OpenCompany\Integrations\Google\Tools\GoogleFormsUpdateInfo;
use OpenCompany\Integrations\Google\Tools\GoogleFormsUpdateQuestion;
use OpenCompany\Integrations\Google\Tools\GoogleFormsUpdateSettings;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

use OpenCompany\IntegrationCore\Contracts\HasIntegrationCapabilities;
class GoogleFormsToolProvider implements ToolProvider, ConfigurableIntegration, HasIntegrationCapabilities
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
        return 'google-forms';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'Google Forms',
            'description' => 'Create, manage, and read responses from Google Forms',
            'icon' => 'ph:list-checks',
            'logo' => 'simple-icons:googleforms',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Forms',
            'description' => 'Create surveys, quizzes, and forms with questions of any type, and read responses',
            'icon' => 'ph:list-checks',
            'logo' => 'simple-icons:googleforms',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/forms.googleapis.com',
            'catalog_visibility' => 'hidden',
            'replaced_by' => 'google-forms',
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_forms',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Forms" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://www.googleapis.com/oauth2/v2/userinfo');

            if ($response->successful()) {
                $email = $response->json('email') ?? $connectedEmail;
                $emailInfo = $email ? " ({$email})" : '';

                return [
                    'success' => true,
                    'message' => "Google Forms connected{$emailInfo}.",
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
            'google_forms_add_question' => [
                'class' => GoogleFormsAddQuestion::class,
                'type' => 'write',
                'name' => 'Google Forms Add Question',
                'description' => 'Add a question to a Google Form. Supports types: text, paragraph, multiple_choice, checkbox, dropdown, scale, date, time, rating. Use options for choice types. Use low/high/lowLabel/highLabel for scale. Use ratingScale/ratingIcon for rating. Use includeTime/includeYear for date. Use duration for time. Omit index to add at end. Use google_forms_get to see current form structure before editing.',
                'icon' => 'ph:wrench',
            ],
            'google_forms_add_section' => [
                'class' => GoogleFormsAddSection::class,
                'type' => 'write',
                'name' => 'Google Forms Add Section',
                'description' => 'Add a page break / section to a Google Form. Omit index to add at end. Use google_forms_get to see current form structure.',
                'icon' => 'ph:wrench',
            ],
            'google_forms_add_text_item' => [
                'class' => GoogleFormsAddTextItem::class,
                'type' => 'write',
                'name' => 'Google Forms Add Text Item',
                'description' => 'Add a static text block to a Google Form. Omit index to add at end. Use google_forms_get to see current form structure.',
                'icon' => 'ph:wrench',
            ],
            'google_forms_create' => [
                'class' => GoogleFormsCreate::class,
                'type' => 'read',
                'name' => 'Google Forms Create',
                'description' => 'Create a new Google Form with a title, optional description, and optional quiz mode. Auto-publishes. Returns form ID, edit URL, and responder URL.',
                'icon' => 'ph:wrench',
            ],
            'google_forms_delete_item' => [
                'class' => GoogleFormsDeleteItem::class,
                'type' => 'write',
                'name' => 'Google Forms Delete Item',
                'description' => 'Delete an item from a Google Form by its 0-based index. Use google_forms_get to see current form structure.',
                'icon' => 'ph:wrench',
            ],
            'google_forms_get' => [
                'class' => GoogleFormsGet::class,
                'type' => 'read',
                'name' => 'Google Forms Get',
                'description' => 'Get a Google Form\'s structure: title, description, settings, all questions with types/options/IDs, and responder URL. The form ID is the long string in the Google Forms URL: docs.google.com/forms/d/{formId}/edit To list all forms, use google_drive_search with file type "application/vnd.google-apps.form".',
                'icon' => 'ph:wrench',
            ],
            'google_forms_get_response' => [
                'class' => GoogleFormsGetResponse::class,
                'type' => 'read',
                'name' => 'Google Forms Get Response',
                'description' => 'Get a single response to a Google Form by response ID, with question labels. The form ID is the long string in the Google Forms URL: docs.google.com/forms/d/{formId}/edit',
                'icon' => 'ph:wrench',
            ],
            'google_forms_list_responses' => [
                'class' => GoogleFormsListResponses::class,
                'type' => 'read',
                'name' => 'Google Forms List Responses',
                'description' => 'List responses to a Google Form with question labels. The form ID is the long string in the Google Forms URL: docs.google.com/forms/d/{formId}/edit',
                'icon' => 'ph:wrench',
            ],
            'google_forms_move_item' => [
                'class' => GoogleFormsMoveItem::class,
                'type' => 'write',
                'name' => 'Google Forms Move Item',
                'description' => 'Move an item in a Google Form from one 0-based index to another. Use google_forms_get to see current form structure.',
                'icon' => 'ph:wrench',
            ],
            'google_forms_publish' => [
                'class' => GoogleFormsPublish::class,
                'type' => 'read',
                'name' => 'Google Forms Publish',
                'description' => 'Set publish settings for a Google Form: publish/unpublish and accept/stop accepting responses. At least one of published or acceptingResponses must be provided.',
                'icon' => 'ph:wrench',
            ],
            'google_forms_update_info' => [
                'class' => GoogleFormsUpdateInfo::class,
                'type' => 'write',
                'name' => 'Google Forms Update Info',
                'description' => 'Update a Google Form title and/or description. At least one of title or description must be provided.',
                'icon' => 'ph:wrench',
            ],
            'google_forms_update_question' => [
                'class' => GoogleFormsUpdateQuestion::class,
                'type' => 'write',
                'name' => 'Google Forms Update Question',
                'description' => 'Update a question in a Google Form by its 0-based index. Can update title, description, required status, and options (for choice questions). Use google_forms_get to see current form structure.',
                'icon' => 'ph:wrench',
            ],
            'google_forms_update_settings' => [
                'class' => GoogleFormsUpdateSettings::class,
                'type' => 'write',
                'name' => 'Google Forms Update Settings',
                'description' => 'Update Google Form settings such as quiz mode and email collection. At least one setting must be provided.',
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
            ? new GoogleFormsService(GoogleServiceProvider::makeClient(app(), $this->appName(), (string) $account))
            : app(GoogleFormsService::class);

        return new $class($service);
    }
}
