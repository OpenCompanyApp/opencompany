<?php

namespace OpenCompany\Integrations\Google;

use Illuminate\Support\Facades\Http;
use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\Integrations\Google\Services\GoogleCalendarService;
use OpenCompany\Integrations\Google\Tools\GoogleCalendarCreateEvent;
use OpenCompany\Integrations\Google\Tools\GoogleCalendarDeleteEvent;
use OpenCompany\Integrations\Google\Tools\GoogleCalendarFreeBusy;
use OpenCompany\Integrations\Google\Tools\GoogleCalendarGetEvent;
use OpenCompany\Integrations\Google\Tools\GoogleCalendarListCalendars;
use OpenCompany\Integrations\Google\Tools\GoogleCalendarListEvents;
use OpenCompany\Integrations\Google\Tools\GoogleCalendarQuickAdd;
use OpenCompany\Integrations\Google\Tools\GoogleCalendarUpdateEvent;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

use OpenCompany\IntegrationCore\Contracts\HasIntegrationCapabilities;
class GoogleCalendarToolProvider implements ToolProvider, ConfigurableIntegration, HasIntegrationCapabilities
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
        return 'google-calendar';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'Google Calendar',
            'description' => 'Calendar management',
            'icon' => 'ph:calendar',
            'logo' => 'simple-icons:googlecalendar',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Calendar',
            'description' => 'Calendar events, scheduling, and availability',
            'icon' => 'ph:calendar',
            'logo' => 'simple-icons:googlecalendar',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/calendar-json.googleapis.com',
            'catalog_visibility' => 'hidden',
            'replaced_by' => 'google-calendar',
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_calendar',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Calendar" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://www.googleapis.com/calendar/v3/users/me/calendarList', [
                'maxResults' => 10,
            ]);

            if ($response->successful()) {
                $items = $response->json('items') ?? [];
                $count = count($items);
                $emailInfo = $connectedEmail ? " as {$connectedEmail}" : '';

                return [
                    'success' => true,
                    'message' => "Connected to Google Calendar{$emailInfo}. Found {$count} calendar(s).",
                ];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Google Calendar API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
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
            'google_calendar_create_event' => [
                'class' => GoogleCalendarCreateEvent::class,
                'type' => 'write',
                'name' => 'Google Calendar Create Event',
                'description' => 'Create a Google Calendar event. Use startDateTime/endDateTime for timed events, or startDate/endDate for all-day events.',
                'icon' => 'ph:wrench',
            ],
            'google_calendar_delete_event' => [
                'class' => GoogleCalendarDeleteEvent::class,
                'type' => 'write',
                'name' => 'Google Calendar Delete Event',
                'description' => 'Delete a Google Calendar event by its ID.',
                'icon' => 'ph:wrench',
            ],
            'google_calendar_freebusy' => [
                'class' => GoogleCalendarFreeBusy::class,
                'type' => 'read',
                'name' => 'Google Calendar Freebusy',
                'description' => 'Check free/busy availability across one or more Google Calendars. Returns busy time slots within the specified time range. Useful for finding open slots for scheduling meetings.',
                'icon' => 'ph:wrench',
            ],
            'google_calendar_get_event' => [
                'class' => GoogleCalendarGetEvent::class,
                'type' => 'read',
                'name' => 'Google Calendar Get Event',
                'description' => 'Get a single Google Calendar event by its ID.',
                'icon' => 'ph:wrench',
            ],
            'google_calendar_list_calendars' => [
                'class' => GoogleCalendarListCalendars::class,
                'type' => 'read',
                'name' => 'Google Calendar List Calendars',
                'description' => 'List all Google Calendars the user has access to.',
                'icon' => 'ph:wrench',
            ],
            'google_calendar_list_events' => [
                'class' => GoogleCalendarListEvents::class,
                'type' => 'read',
                'name' => 'Google Calendar List Events',
                'description' => 'List or search events in a Google Calendar. Supports date range filtering and text search.',
                'icon' => 'ph:wrench',
            ],
            'google_calendar_quick_add' => [
                'class' => GoogleCalendarQuickAdd::class,
                'type' => 'read',
                'name' => 'Google Calendar Quick Add',
                'description' => 'Create a Google Calendar event from natural language text (e.g., "Lunch with Alice tomorrow at noon").',
                'icon' => 'ph:wrench',
            ],
            'google_calendar_update_event' => [
                'class' => GoogleCalendarUpdateEvent::class,
                'type' => 'write',
                'name' => 'Google Calendar Update Event',
                'description' => 'Update an existing Google Calendar event (partial update). Only specified fields are changed.',
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
            ? new GoogleCalendarService(GoogleServiceProvider::makeClient(app(), $this->appName(), (string) $account))
            : app(GoogleCalendarService::class);

        return new $class($service);
    }
}
