<?php

namespace OpenCompany\Integrations\Google;

use Illuminate\Support\Facades\Http;
use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\Integrations\Google\Services\GmailService;
use OpenCompany\Integrations\Google\Tools\GmailAddLabels;
use OpenCompany\Integrations\Google\Tools\GmailArchive;
use OpenCompany\Integrations\Google\Tools\GmailCreateDraft;
use OpenCompany\Integrations\Google\Tools\GmailMarkRead;
use OpenCompany\Integrations\Google\Tools\GmailMarkUnread;
use OpenCompany\Integrations\Google\Tools\GmailRead;
use OpenCompany\Integrations\Google\Tools\GmailRemoveLabels;
use OpenCompany\Integrations\Google\Tools\GmailReply;
use OpenCompany\Integrations\Google\Tools\GmailCountBySender;
use OpenCompany\Integrations\Google\Tools\GmailListLabels;
use OpenCompany\Integrations\Google\Tools\GmailSaveAttachment;
use OpenCompany\Integrations\Google\Tools\GmailSearchEmails;
use OpenCompany\Integrations\Google\Tools\GmailSendDraft;
use OpenCompany\Integrations\Google\Tools\GmailSendEmail;
use OpenCompany\Integrations\Google\Tools\GmailTrash;
use OpenCompany\Integrations\Google\Tools\GmailUntrash;
use OpenCompany\IntegrationCore\Contracts\AgentFileStorage;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

use OpenCompany\IntegrationCore\Contracts\HasIntegrationCapabilities;
class GmailToolProvider implements ToolProvider, ConfigurableIntegration, HasIntegrationCapabilities
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
        return 'gmail';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'Gmail',
            'description' => 'Email management',
            'icon' => 'ph:envelope',
            'logo' => 'simple-icons:gmail',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Gmail',
            'description' => 'Email search, reading, sending, and management',
            'icon' => 'ph:envelope',
            'logo' => 'simple-icons:gmail',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/gmail.googleapis.com',
        ];
    }

    public function configSchema(): array
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=gmail',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Gmail" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://www.googleapis.com/gmail/v1/users/me/profile');

            if ($response->successful()) {
                $email = $response->json('emailAddress') ?? $connectedEmail ?? 'unknown';
                $total = $response->json('messagesTotal') ?? 0;

                return [
                    'success' => true,
                    'message' => "Connected to Gmail as {$email}. {$total} total messages.",
                ];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Gmail API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
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
            'gmail_add_labels' => [
                'class' => GmailAddLabels::class,
                'type' => 'write',
                'name' => 'Gmail Add Labels',
                'description' => 'Add labels to one or more Gmail messages. Provide messageIds (comma-separated) for batch operations.',
                'icon' => 'ph:wrench',
            ],
            'gmail_archive' => [
                'class' => GmailArchive::class,
                'type' => 'read',
                'name' => 'Gmail Archive',
                'description' => 'Archive one or more Gmail messages (remove from inbox). Provide messageIds (comma-separated) for batch operations.',
                'icon' => 'ph:wrench',
            ],
            'gmail_create_draft' => [
                'class' => GmailCreateDraft::class,
                'type' => 'write',
                'name' => 'Gmail Create Draft',
                'description' => 'Create a draft email in Gmail (not sent).',
                'icon' => 'ph:wrench',
            ],
            'gmail_mark_read' => [
                'class' => GmailMarkRead::class,
                'type' => 'write',
                'name' => 'Gmail Mark Read',
                'description' => 'Mark one or more Gmail messages as read. Provide messageIds (comma-separated) for batch operations.',
                'icon' => 'ph:wrench',
            ],
            'gmail_mark_unread' => [
                'class' => GmailMarkUnread::class,
                'type' => 'write',
                'name' => 'Gmail Mark Unread',
                'description' => 'Mark one or more Gmail messages as unread. Provide messageIds (comma-separated) for batch operations.',
                'icon' => 'ph:wrench',
            ],
            'gmail_read' => [
                'class' => GmailRead::class,
                'type' => 'read',
                'name' => 'Gmail Read',
                'description' => 'Read the full content of a Gmail message by its ID. Returns headers (From, To, Subject, Date), the decoded text body, and a list of attachments. Use gmail_search first to find message IDs, then use this tool to read the full content.',
                'icon' => 'ph:wrench',
            ],
            'gmail_remove_labels' => [
                'class' => GmailRemoveLabels::class,
                'type' => 'write',
                'name' => 'Gmail Remove Labels',
                'description' => 'Remove labels from one or more Gmail messages. Provide messageIds (comma-separated) for batch operations.',
                'icon' => 'ph:wrench',
            ],
            'gmail_reply' => [
                'class' => GmailReply::class,
                'type' => 'read',
                'name' => 'Gmail Reply',
                'description' => 'Reply to an existing Gmail message (maintains the thread).',
                'icon' => 'ph:wrench',
            ],
            'gmail_count_by_sender' => [
                'class' => GmailCountBySender::class,
                'type' => 'read',
                'name' => 'Gmail Count By Sender',
                'description' => 'Count all matching Gmail messages grouped by sender. Automatically paginates through ALL results (handles thousands of messages). Returns top senders sorted by count. Use for questions like "who sends me the most email?" or "count unread by sender".',
                'icon' => 'ph:wrench',
            ],
            'gmail_list_labels' => [
                'class' => GmailListLabels::class,
                'type' => 'read',
                'name' => 'Gmail List Labels',
                'description' => 'List all labels in the Gmail mailbox (INBOX, SENT, custom labels, etc.).',
                'icon' => 'ph:wrench',
            ],
            'gmail_save_attachment' => [
                'class' => GmailSaveAttachment::class,
                'type' => 'read',
                'name' => 'Gmail Save Attachment',
                'description' => 'Download an email attachment and save it to workspace files. Requires a messageId and attachmentId (both returned by gmail_read). The file is saved under the agent\'s folder and can be browsed in the Files page.',
                'icon' => 'ph:wrench',
            ],
            'gmail_search_emails' => [
                'class' => GmailSearchEmails::class,
                'type' => 'read',
                'name' => 'Gmail Search Emails',
                'description' => 'Search Gmail messages using Gmail query syntax (e.g., "from:alice subject:meeting is:unread has:attachment after:2026-02-01"). Returns message summaries with headers. Max 100 per page.',
                'icon' => 'ph:wrench',
            ],
            'gmail_send_draft' => [
                'class' => GmailSendDraft::class,
                'type' => 'write',
                'name' => 'Gmail Send Draft',
                'description' => 'Send a previously created Gmail draft by its ID.',
                'icon' => 'ph:wrench',
            ],
            'gmail_send_email' => [
                'class' => GmailSendEmail::class,
                'type' => 'write',
                'name' => 'Gmail Send Email',
                'description' => 'Send an email directly via Gmail.',
                'icon' => 'ph:wrench',
            ],
            'gmail_trash' => [
                'class' => GmailTrash::class,
                'type' => 'read',
                'name' => 'Gmail Trash',
                'description' => 'Move one or more Gmail messages to trash. Provide messageIds (comma-separated) for batch operations.',
                'icon' => 'ph:wrench',
            ],
            'gmail_untrash' => [
                'class' => GmailUntrash::class,
                'type' => 'read',
                'name' => 'Gmail Untrash',
                'description' => 'Remove one or more Gmail messages from trash. Provide messageIds (comma-separated) for batch operations.',
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
            ? new GmailService(GoogleServiceProvider::makeClient(app(), $this->appName(), (string) $account))
            : app(GmailService::class);

        if ($class === GmailSaveAttachment::class) {
            $fileStorage = app()->bound(AgentFileStorage::class) ? app(AgentFileStorage::class) : null;
            $agent = $context['agent'] ?? null;

            if (! $fileStorage || ! $agent) {
                throw new \RuntimeException('GmailSaveAttachment requires AgentFileStorage and an agent context.');
            }

            return new $class($service, $fileStorage, $agent);
        }

        return new $class($service);
    }
}
