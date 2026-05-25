<?php

namespace App\Domain\Chat\Telegram\Application;

use App\Models\IntegrationSetting;
use App\Models\TelegramIntegrationProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Setup and health manager for the Telegram chat app.
 *
 * Credentials remain in IntegrationSetting, but bot identity, capability,
 * webhook, command-sync, and health state are mirrored into
 * TelegramIntegrationProfile so admins and operators can diagnose Telegram
 * without reading logs or shelling into the app.
 */
class TelegramSetupService
{
    private const DEFAULT_BASE_URL = 'https://api.telegram.org';

    /**
     * Register the workspace Telegram webhook, sync bot commands, and persist
     * the current Telegram health/profile snapshot.
     *
     * @return array<string, mixed>
     */
    public function setupWebhook(IntegrationSetting $setting, ?string $apiKey = null): array
    {
        $token = $this->resolveToken($setting, $apiKey);
        $secret = $this->ensureWebhookSecret($setting);
        $webhookUrl = rtrim(config('app.url'), '/').'/api/webhooks/chat/telegram';

        $me = $this->request($token, 'getMe');
        $setWebhook = $this->request($token, 'setWebhook', [
            'url' => $webhookUrl,
            'secret_token' => $secret,
            'allowed_updates' => json_encode($this->allowedUpdates()),
        ]);
        $commands = $this->syncCommands($token);
        $profileSync = $this->syncProfile($token, $setting);
        $webhookInfo = $this->request($token, 'getWebhookInfo');

        $setting->setConfigValue('api_key', $token);
        $setting->setConfigValue('webhook_active', true);
        $setting->setConfigValue('webhook_url', $webhookUrl);
        $setting->setConfigValue('allowed_updates', $this->allowedUpdates());
        $setting->setConfigValue('bot_user_id', isset($me['id']) ? (string) $me['id'] : $setting->getConfigValue('bot_user_id'));
        $setting->setConfigValue('bot_username', $me['username'] ?? $setting->getConfigValue('bot_username'));
        $setting->enabled = true;
        $setting->save();

        $profile = $this->profileFor($setting);
        $diagnostics = $this->diagnosticsFromWebhookInfo($webhookInfo, $webhookUrl, $this->allowedUpdates(), $me);
        $diagnostics = [
            ...$diagnostics,
            ...$this->diagnosticsFromProfileSync($profileSync),
        ];

        $profile->update([
            'bot_id' => isset($me['id']) ? (string) $me['id'] : null,
            'bot_username' => $me['username'] ?? null,
            'capabilities' => [
                ...$this->capabilitiesFromGetMe($me),
                'command_scopes' => $commands['scopes'],
                'diagnostics' => $diagnostics,
            ],
            'webhook_url' => $webhookUrl,
            'webhook_secret_fingerprint' => hash('sha256', $secret),
            'allowed_updates' => $this->allowedUpdates(),
            'command_sync_status' => $commands['status'],
            'profile_sync_status' => $profileSync['status'],
            'health_status' => $this->healthStatusFromDiagnostics($diagnostics),
            'last_health_error' => $webhookInfo['last_error_message'] ?? null,
            'pending_update_count' => $webhookInfo['pending_update_count'] ?? null,
            'last_health_checked_at' => now(),
        ]);

        return [
            'success' => true,
            'webhookUrl' => $webhookUrl,
            'bot' => $me,
            'webhook' => $webhookInfo,
            'profile' => $profile->fresh(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function health(IntegrationSetting $setting): array
    {
        $token = (string) $setting->getConfigValue('api_key', '');
        if ($token === '') {
            return [
                'success' => false,
                'status' => 'missing_token',
                'error' => 'No Telegram bot token configured.',
            ];
        }

        $webhookUrl = (string) $setting->getConfigValue('webhook_url', rtrim(config('app.url'), '/').'/api/webhooks/chat/telegram');
        $me = $this->request($token, 'getMe');
        $webhookInfo = $this->request($token, 'getWebhookInfo');

        $profile = $this->profileFor($setting);
        $allowedUpdates = $setting->getConfigValue('allowed_updates', $this->allowedUpdates());
        $allowedUpdates = is_array($allowedUpdates) ? $allowedUpdates : $this->allowedUpdates();
        $diagnostics = $this->diagnosticsFromWebhookInfo($webhookInfo, $webhookUrl, $allowedUpdates, $me);

        $profile->update([
            'bot_id' => isset($me['id']) ? (string) $me['id'] : null,
            'bot_username' => $me['username'] ?? null,
            'capabilities' => [
                ...$this->capabilitiesFromGetMe($me),
                'diagnostics' => $diagnostics,
            ],
            'webhook_url' => $webhookInfo['url'] ?? $webhookUrl,
            'allowed_updates' => $webhookInfo['allowed_updates'] ?? $allowedUpdates,
            'health_status' => $this->healthStatusFromDiagnostics($diagnostics),
            'last_health_error' => $webhookInfo['last_error_message'] ?? null,
            'pending_update_count' => $webhookInfo['pending_update_count'] ?? null,
            'last_health_checked_at' => now(),
        ]);

        return [
            'success' => true,
            'status' => $profile->health_status,
            'bot' => $me,
            'webhook' => $webhookInfo,
            'profile' => $profile->fresh(),
        ];
    }

    /**
     * Sync Telegram-native bot command menus and optional profile/menu-button
     * metadata without rotating the webhook. This is used by operator commands
     * and admin repair actions that need to republish the bot UX while leaving
     * the current webhook URL and secret untouched.
     *
     * @return array<string, mixed>
     */
    public function syncBotCommandsAndProfile(IntegrationSetting $setting, ?string $apiKey = null): array
    {
        $token = $this->resolveToken($setting, $apiKey);
        $commands = $this->syncCommands($token);
        $profileSync = $this->syncProfile($token, $setting);
        $diagnostics = $this->diagnosticsFromProfileSync($profileSync);
        $profile = $this->profileFor($setting);
        $capabilities = is_array($profile->capabilities) ? $profile->capabilities : [];

        $profile->update([
            'capabilities' => [
                ...$capabilities,
                'command_scopes' => $commands['scopes'],
                'diagnostics' => $diagnostics !== [] ? $diagnostics : ($capabilities['diagnostics'] ?? []),
            ],
            'command_sync_status' => $commands['status'],
            'profile_sync_status' => $profileSync['status'],
            'last_health_checked_at' => now(),
        ]);

        return [
            'success' => true,
            'commands' => $commands,
            'profile_sync' => $profileSync,
            'profile' => $profile->fresh(),
        ];
    }

    /**
     * Rotate the Telegram webhook proof secret and immediately register the new
     * secret with Telegram. If setup fails before Telegram accepts the new
     * webhook, the old local secret is restored so inbound traffic is not left
     * permanently unable to verify.
     *
     * @return array<string, mixed>
     */
    public function rotateWebhookSecret(IntegrationSetting $setting): array
    {
        $previousSecret = $setting->getConfigValue('webhook_secret');
        $nextSecret = Str::random(64);

        $setting->setConfigValue('webhook_secret', $nextSecret);
        $setting->save();

        try {
            return [
                ...$this->setupWebhook($setting),
                'rotated' => true,
                'secretFingerprint' => hash('sha256', $nextSecret),
            ];
        } catch (\Throwable $e) {
            if (is_string($previousSecret) && $previousSecret !== '') {
                $setting->setConfigValue('webhook_secret', $previousSecret);
                $setting->save();
            }

            throw $e;
        }
    }

    /** @return list<string> */
    public function allowedUpdates(): array
    {
        $updates = [
            'message',
            'edited_message',
            'channel_post',
            'edited_channel_post',
            'inline_query',
            'chosen_inline_result',
            'callback_query',
            'message_reaction',
            'message_reaction_count',
            'chat_member',
            'my_chat_member',
            'business_connection',
            'business_message',
            'edited_business_message',
            'deleted_business_messages',
            'purchased_paid_media',
            'poll',
            'poll_answer',
            'chat_join_request',
            'chat_boost',
            'removed_chat_boost',
            'managed_bot',
        ];

        if (config('telegram.guest_mode_enabled')) {
            $updates[] = 'guest_message';
        }

        return $updates;
    }

    /** @return list<array{command: string, description: string}> */
    public function commands(): array
    {
        return [
            ['command' => 'start', 'description' => 'Open OpenCompany'],
            ['command' => 'agents', 'description' => 'Switch agent'],
            ['command' => 'topic', 'description' => 'Change lane behavior'],
            ['command' => 'status', 'description' => 'See current work'],
            ['command' => 'approvals', 'description' => 'Review decisions'],
            ['command' => 'help', 'description' => 'Show help'],
        ];
    }

    /**
     * Sync a complete default command list plus Telegram-native scoped command
     * lists. Scopes keep private-chat operators, group users, and group admins
     * focused on the commands that make sense in that surface without moving
     * command ownership back into Chatogrator.
     *
     * @return array{status: string, scopes: list<string>}
     */
    private function syncCommands(string $token): array
    {
        $scopes = [];

        foreach ($this->commandSyncRequests() as $request) {
            $this->request($token, 'setMyCommands', [
                'commands' => json_encode($request['commands']),
                ...($request['scope'] ? ['scope' => json_encode($request['scope'])] : []),
            ]);

            $scopes[] = $request['name'];
        }

        return [
            'status' => $scopes !== [] ? 'synced' : 'unknown',
            'scopes' => $scopes,
        ];
    }

    /**
     * @return list<array{name: string, scope: array<string, string>|null, commands: list<array{command: string, description: string}>}>
     */
    private function commandSyncRequests(): array
    {
        $commands = $this->commands();
        $privateCommands = [
            ...$commands,
            ['command' => 'link', 'description' => 'Link your account'],
        ];
        $groupCommands = $this->onlyCommands($commands, [
            'start',
            'agents',
            'topic',
            'status',
            'approvals',
            'help',
        ]);
        $adminCommands = [
            ...$privateCommands,
            ['command' => 'settings', 'description' => 'Adjust Telegram behavior'],
            ['command' => 'health', 'description' => 'Show Telegram webhook and delivery health'],
        ];
        $adminCommands = $this->onlyCommands($adminCommands, [
            'start',
            'link',
            'agents',
            'topic',
            'status',
            'approvals',
            'settings',
            'health',
            'help',
        ]);

        return [
            ['name' => 'default', 'scope' => null, 'commands' => $commands],
            ['name' => 'private', 'scope' => ['type' => 'all_private_chats'], 'commands' => $privateCommands],
            ['name' => 'groups', 'scope' => ['type' => 'all_group_chats'], 'commands' => $groupCommands],
            ['name' => 'chat_admins', 'scope' => ['type' => 'all_chat_administrators'], 'commands' => $adminCommands],
        ];
    }

    /**
     * @param  list<array{command: string, description: string}>  $commands
     * @param  list<string>  $allowed
     * @return list<array{command: string, description: string}>
     */
    private function onlyCommands(array $commands, array $allowed): array
    {
        return collect($commands)
            ->filter(fn (array $command) => in_array($command['command'], $allowed, true))
            ->values()
            ->all();
    }

    public function profileFor(IntegrationSetting $setting): TelegramIntegrationProfile
    {
        return TelegramIntegrationProfile::firstOrCreate(
            [
                'workspace_id' => $setting->workspace_id,
                'integration_setting_id' => $setting->id,
            ],
            [
                'id' => Str::uuid()->toString(),
                'default_agent_id' => $setting->getConfigValue('default_agent_id'),
                'default_mode' => (string) $setting->getConfigValue('default_mode', 'command_center'),
                'notification_policy' => $setting->getConfigValue('notification_policy', []),
            ],
        );
    }

    private function resolveToken(IntegrationSetting $setting, ?string $apiKey): string
    {
        $token = $apiKey;
        if (! is_string($token) || $token === '' || str_contains($token, '*')) {
            $token = $setting->getConfigValue('api_key');
        }

        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('No Telegram bot token configured.');
        }

        return $token;
    }

    private function ensureWebhookSecret(IntegrationSetting $setting): string
    {
        $secret = $setting->getConfigValue('webhook_secret');
        if (! is_string($secret) || $secret === '') {
            $secret = Str::random(64);
            $setting->setConfigValue('webhook_secret', $secret);
            $setting->save();
        }

        return $secret;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function request(string $token, string $method, array $params = []): array
    {
        $response = Http::timeout(10)->post($this->botApiUrl($token, $method), $params);
        $data = $response->json();

        if (! $response->successful() || ! is_array($data) || ! ($data['ok'] ?? false)) {
            throw new \RuntimeException((string) ($data['description'] ?? "Telegram {$method} failed."));
        }

        $result = $data['result'] ?? [];

        return is_array($result) ? $result : ['ok' => $result];
    }

    private function botApiUrl(string $token, string $method): string
    {
        $baseUrl = rtrim((string) config('telegram.bot_api_base_url', self::DEFAULT_BASE_URL), '/');

        return "{$baseUrl}/bot{$token}/{$method}";
    }

    /**
     * Sync optional Telegram-native bot profile surfaces during setup.
     *
     * Bot branding and the default menu button are valuable UX polish, but they
     * are not required for a safe webhook. Failures are converted into profile
     * diagnostics so admins can repair BotFather/API restrictions without
     * blocking the core runtime.
     *
     * @return array{status: string, actions: list<string>, errors: list<array{method: string, message: string}>}
     */
    private function syncProfile(string $token, IntegrationSetting $setting): array
    {
        $actions = [];
        $errors = [];

        foreach ($this->profileSyncRequests($setting) as $request) {
            try {
                $this->request($token, $request['method'], $request['params']);
                $actions[] = $request['action'];
            } catch (\Throwable $e) {
                $errors[] = [
                    'method' => $request['method'],
                    'message' => Str::limit($e->getMessage(), 300),
                ];
            }
        }

        $status = match (true) {
            $errors === [] && $actions !== [] => 'synced',
            $errors !== [] && $actions !== [] => 'partial',
            $errors !== [] => 'failed',
            default => 'skipped',
        };

        return [
            'status' => $status,
            'actions' => $actions,
            'errors' => $errors,
        ];
    }

    /**
     * @return list<array{method: string, action: string, params: array<string, mixed>}>
     */
    private function profileSyncRequests(IntegrationSetting $setting): array
    {
        $requests = [];
        $name = $this->limitedConfigString($setting, 'bot_display_name', 64);
        $description = $this->limitedConfigString($setting, 'bot_description', 512);
        $shortDescription = $this->limitedConfigString($setting, 'bot_short_description', 120);

        if ($name !== null) {
            $requests[] = [
                'method' => 'setMyName',
                'action' => 'name',
                'params' => ['name' => $name],
            ];
        }

        if ($description !== null) {
            $requests[] = [
                'method' => 'setMyDescription',
                'action' => 'description',
                'params' => ['description' => $description],
            ];
        }

        if ($shortDescription !== null) {
            $requests[] = [
                'method' => 'setMyShortDescription',
                'action' => 'short_description',
                'params' => ['short_description' => $shortDescription],
            ];
        }

        $requests[] = [
            'method' => 'setChatMenuButton',
            'action' => 'menu_button',
            'params' => [
                'menu_button' => json_encode($this->menuButtonPayload($setting)),
            ],
        ];

        return $requests;
    }

    private function limitedConfigString(IntegrationSetting $setting, string $key, int $limit): ?string
    {
        $value = $setting->getConfigValue($key);
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::limit(trim($value), $limit, '');
    }

    /**
     * @return array<string, mixed>
     */
    private function menuButtonPayload(IntegrationSetting $setting): array
    {
        $miniAppUrl = $setting->getConfigValue('mini_app_url');
        if (config('telegram.mini_app_enabled') && is_string($miniAppUrl) && str_starts_with($miniAppUrl, 'https://')) {
            return [
                'type' => 'web_app',
                'text' => (string) $setting->getConfigValue('menu_button_text', 'OpenCompany'),
                'web_app' => [
                    'url' => $miniAppUrl,
                ],
            ];
        }

        return ['type' => 'commands'];
    }

    /**
     * @param  array{status: string, actions: list<string>, errors: list<array{method: string, message: string}>}  $profileSync
     * @return list<array{code: string, severity: string, message: string}>
     */
    private function diagnosticsFromProfileSync(array $profileSync): array
    {
        if ($profileSync['errors'] === []) {
            return [];
        }

        return collect($profileSync['errors'])
            ->map(fn (array $error) => [
                'code' => 'profile_sync_failed',
                'severity' => $profileSync['status'] === 'failed' ? 'warning' : 'info',
                'message' => "Telegram {$error['method']} failed: {$error['message']}",
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $me
     * @return array<string, mixed>
     */
    private function capabilitiesFromGetMe(array $me): array
    {
        $capabilityKeys = [
            'can_join_groups',
            'can_read_all_group_messages',
            'supports_inline_queries',
            'supports_guest_queries',
            'can_connect_to_business',
            'has_main_web_app',
            'has_private_forwards',
            'has_restricted_voice_and_video_messages',
            'has_topics_enabled',
            'allows_users_to_create_topics',
        ];

        $capabilities = [];
        foreach ($capabilityKeys as $key) {
            if (array_key_exists($key, $me)) {
                $capabilities[$key] = $me[$key];
            }
        }

        return $capabilities;
    }

    /**
     * @param  array<string, mixed>  $webhookInfo
     * @param  list<string>  $requiredAllowedUpdates
     * @param  array<string, mixed>  $me
     * @return list<array{code: string, severity: string, message: string}>
     */
    private function diagnosticsFromWebhookInfo(
        array $webhookInfo,
        string $expectedUrl,
        array $requiredAllowedUpdates,
        array $me
    ): array {
        $diagnostics = [];

        if (($webhookInfo['url'] ?? '') !== $expectedUrl) {
            $diagnostics[] = [
                'code' => 'webhook_mismatch',
                'severity' => 'critical',
                'message' => 'Telegram webhook URL does not match the OpenCompany chat webhook route.',
            ];
        }

        $actualAllowedUpdates = $webhookInfo['allowed_updates'] ?? [];
        if (is_array($actualAllowedUpdates)) {
            $missing = array_values(array_diff($requiredAllowedUpdates, array_map('strval', $actualAllowedUpdates)));
            if ($missing !== []) {
                $diagnostics[] = [
                    'code' => 'missing_allowed_updates',
                    'severity' => 'warning',
                    'message' => 'Telegram webhook is missing allowed updates: '.implode(', ', $missing).'.',
                ];
            }
        }

        $lastError = (string) ($webhookInfo['last_error_message'] ?? '');
        if ($lastError !== '') {
            $diagnostics[] = [
                'code' => str_contains(strtolower($lastError), 'too many requests') ? 'rate_limited' : 'webhook_last_error',
                'severity' => 'critical',
                'message' => $lastError,
            ];
        }

        if (($me['can_read_all_group_messages'] ?? null) === false) {
            $diagnostics[] = [
                'code' => 'privacy_mode_limited',
                'severity' => 'info',
                'message' => 'Bot privacy mode may prevent observed-context group lanes from receiving unmentioned messages.',
            ];
        }

        return $diagnostics;
    }

    /**
     * @param  list<array{code: string, severity: string, message: string}>  $diagnostics
     */
    private function healthStatusFromDiagnostics(array $diagnostics): string
    {
        foreach ($diagnostics as $diagnostic) {
            if (($diagnostic['code'] ?? null) === 'webhook_mismatch') {
                return 'webhook_mismatch';
            }
        }

        return collect($diagnostics)->contains(fn (array $diagnostic) => in_array($diagnostic['severity'], ['critical', 'warning'], true))
            ? 'degraded'
            : 'healthy';
    }
}
