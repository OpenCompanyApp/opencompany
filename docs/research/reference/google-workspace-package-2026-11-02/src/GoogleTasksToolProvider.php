<?php

namespace OpenCompany\Integrations\Google;

use Illuminate\Support\Facades\Http;
use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\Integrations\Google\Services\GoogleTasksService;
use OpenCompany\Integrations\Google\Tools\GoogleTasksClearCompleted;
use OpenCompany\Integrations\Google\Tools\GoogleTasksComplete;
use OpenCompany\Integrations\Google\Tools\GoogleTasksCreate;
use OpenCompany\Integrations\Google\Tools\GoogleTasksCreateList;
use OpenCompany\Integrations\Google\Tools\GoogleTasksDelete;
use OpenCompany\Integrations\Google\Tools\GoogleTasksDeleteList;
use OpenCompany\Integrations\Google\Tools\GoogleTasksMove;
use OpenCompany\Integrations\Google\Tools\GoogleTasksGetTask;
use OpenCompany\Integrations\Google\Tools\GoogleTasksListLists;
use OpenCompany\Integrations\Google\Tools\GoogleTasksListTasks;
use OpenCompany\Integrations\Google\Tools\GoogleTasksUpdate;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

use OpenCompany\IntegrationCore\Contracts\HasIntegrationCapabilities;
class GoogleTasksToolProvider implements ToolProvider, ConfigurableIntegration, HasIntegrationCapabilities
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
        return 'google-tasks';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'Google Tasks',
            'description' => 'Task management',
            'icon' => 'ph:check-square',
            'logo' => 'simple-icons:googletasks',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Tasks',
            'description' => 'Task lists, to-dos, and checklist management',
            'icon' => 'ph:check-square',
            'logo' => 'simple-icons:googletasks',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/tasks.googleapis.com',
            'catalog_visibility' => 'hidden',
            'replaced_by' => 'google-tasks',
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_tasks',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Tasks" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://tasks.googleapis.com/tasks/v1/users/@me/lists', [
                'maxResults' => '100',
            ]);

            if ($response->successful()) {
                $items = $response->json('items') ?? [];
                $count = count($items);
                $emailInfo = $connectedEmail ? " ({$connectedEmail})" : '';

                return [
                    'success' => true,
                    'message' => "Connected to Google Tasks{$emailInfo}. {$count} task " . ($count === 1 ? 'list' : 'lists') . '.',
                ];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Tasks API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
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
            'google_tasks_clear_completed' => [
                'class' => GoogleTasksClearCompleted::class,
                'type' => 'read',
                'name' => 'Google Tasks Clear Completed',
                'description' => 'Remove all completed tasks from a Google Tasks list. Warning: permanently deletes completed tasks.',
                'icon' => 'ph:wrench',
            ],
            'google_tasks_complete' => [
                'class' => GoogleTasksComplete::class,
                'type' => 'read',
                'name' => 'Google Tasks Complete',
                'description' => 'Mark a Google Task as completed.',
                'icon' => 'ph:wrench',
            ],
            'google_tasks_create' => [
                'class' => GoogleTasksCreate::class,
                'type' => 'read',
                'name' => 'Google Tasks Create',
                'description' => 'Create a task in Google Tasks. Use "@default" as listId for the primary "My Tasks" list.',
                'icon' => 'ph:wrench',
            ],
            'google_tasks_create_list' => [
                'class' => GoogleTasksCreateList::class,
                'type' => 'write',
                'name' => 'Google Tasks Create List',
                'description' => 'Create a new task list in Google Tasks.',
                'icon' => 'ph:wrench',
            ],
            'google_tasks_delete' => [
                'class' => GoogleTasksDelete::class,
                'type' => 'read',
                'name' => 'Google Tasks Delete',
                'description' => 'Delete a Google Task.',
                'icon' => 'ph:wrench',
            ],
            'google_tasks_delete_list' => [
                'class' => GoogleTasksDeleteList::class,
                'type' => 'write',
                'name' => 'Google Tasks Delete List',
                'description' => 'Delete a task list from Google Tasks.',
                'icon' => 'ph:wrench',
            ],
            'google_tasks_move' => [
                'class' => GoogleTasksMove::class,
                'type' => 'read',
                'name' => 'Google Tasks Move',
                'description' => 'Reorder or reparent a Google Task. Use parent to set a new parent (empty string moves to top level), and previous to position after a sibling.',
                'icon' => 'ph:wrench',
            ],
            'google_tasks_get_task' => [
                'class' => GoogleTasksGetTask::class,
                'type' => 'read',
                'name' => 'Google Tasks Get Task',
                'description' => 'Get full details of a single Google Task by its ID.',
                'icon' => 'ph:wrench',
            ],
            'google_tasks_list_lists' => [
                'class' => GoogleTasksListLists::class,
                'type' => 'read',
                'name' => 'Google Tasks List Lists',
                'description' => 'List all Google Task lists. Returns IDs and titles. Start here to discover available lists.',
                'icon' => 'ph:wrench',
            ],
            'google_tasks_list_tasks' => [
                'class' => GoogleTasksListTasks::class,
                'type' => 'read',
                'name' => 'Google Tasks List Tasks',
                'description' => 'List tasks in a Google Task list. Use "@default" as listId for the primary "My Tasks" list. Supports filtering by completion status and due date range.',
                'icon' => 'ph:wrench',
            ],
            'google_tasks_update' => [
                'class' => GoogleTasksUpdate::class,
                'type' => 'read',
                'name' => 'Google Tasks Update',
                'description' => 'Update task fields in Google Tasks. At least one field to update is required.',
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
            ? new GoogleTasksService(GoogleServiceProvider::makeClient(app(), $this->appName(), (string) $account))
            : app(GoogleTasksService::class);

        return new $class($service);
    }
}
