<?php

namespace App\Agents\Tools\Workspace;

use App\Models\IntegrationSetting;
use App\Models\User;
use App\Models\UserExternalIdentity;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ManageIntegration implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Configure integrations: update API keys and settings, test connections, set up webhooks.';
    }

    public function handle(Request $request): string
    {
        try {
            return match ($request['action']) {
                'update_config' => $this->updateConfig($request),
                'test_connection' => $this->testConnection($request),
                'setup_webhook' => $this->setupWebhook($request),
                'link_external_user' => $this->linkExternalUser($request),
                default => "Unknown action: {$request['action']}. Use: update_config, test_connection, setup_webhook, link_external_user",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function updateConfig(Request $request): string
    {
        $integrationId = $request['integrationId'] ?? null;
        if (!$integrationId) {
            return 'integrationId is required.';
        }

        $available = IntegrationSetting::getAvailableIntegrations();
        if (!isset($available[$integrationId])) {
            return "Integration not found: {$integrationId}. Available: " . implode(', ', array_keys($available));
        }

        $setting = IntegrationSetting::firstOrNew(['integration_id' => $integrationId]);
        if (!$setting->id) {
            $setting->id = Str::uuid()->toString();
        }

        $config = $setting->config ?? [];

        // API key — accept raw keys, never return them unmasked
        if (!empty($request['apiKey']) && !str_contains($request['apiKey'], '*')) {
            $config['api_key'] = $request['apiKey'];
        }

        if (isset($request['url'])) {
            $config['url'] = $request['url'] ?: ($available[$integrationId]['default_url'] ?? '');
        }

        if (isset($request['defaultModel'])) {
            $config['default_model'] = $request['defaultModel'];
        }

        // Telegram-specific
        if (isset($request['defaultAgentId'])) {
            $config['default_agent_id'] = $request['defaultAgentId'];
        }
        if (isset($request['notifyChatId'])) {
            $config['notify_chat_id'] = $request['notifyChatId'];
        }
        if (isset($request['allowedTelegramUsers'])) {
            $value = $request['allowedTelegramUsers'];
            if (is_array($value)) {
                $config['allowed_telegram_users'] = $value;
            } elseif (is_string($value)) {
                $decoded = json_decode($value, true);
                $config['allowed_telegram_users'] = is_array($decoded) ? $decoded : [];
            } else {
                $config['allowed_telegram_users'] = [];
            }
        }

        // Plausible-specific
        if (isset($request['sites'])) {
            $value = $request['sites'];
            if (is_array($value)) {
                $config['sites'] = $value;
            } elseif (is_string($value)) {
                $decoded = json_decode($value, true);
                $config['sites'] = is_array($decoded) ? $decoded : [];
            } else {
                $config['sites'] = [];
            }
        }

        $setting->config = $config;

        if (isset($request['enabled'])) {
            $setting->enabled = filter_var($request['enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        $setting->save();

        // Return masked info with Telegram-specific details
        $maskedKey = $setting->getMaskedApiKey();
        $info = "Integration '{$integrationId}' updated. Enabled: " . ($setting->enabled ? 'yes' : 'no') . ". API Key: {$maskedKey}. Configured: " . ($setting->hasValidConfig() ? 'yes' : 'no') . '.';

        if ($integrationId === 'telegram') {
            $allowedUsers = $config['allowed_telegram_users'] ?? [];
            $defaultAgent = $config['default_agent_id'] ?? 'none';
            $notifyChat = $config['notify_chat_id'] ?? 'none';
            $info .= " Default agent: {$defaultAgent}. Notify chat: {$notifyChat}. Allowed users: " . (empty($allowedUsers) ? 'all (unrestricted)' : implode(', ', $allowedUsers)) . '.';
        }

        return $info;
    }

    private function testConnection(Request $request): string
    {
        $integrationId = $request['integrationId'] ?? null;
        if (!$integrationId) {
            return 'integrationId is required.';
        }

        $available = IntegrationSetting::getAvailableIntegrations();
        if (!isset($available[$integrationId])) {
            return "Integration not found: {$integrationId}";
        }

        $setting = IntegrationSetting::where('integration_id', $integrationId)->first();
        $apiKey = $setting?->getConfigValue('api_key');

        if (!$apiKey) {
            return "No API key configured for {$integrationId}. Set it first with update_config.";
        }

        if ($integrationId === 'telegram') {
            return $this->testTelegram($apiKey);
        }

        if ($integrationId === 'plausible') {
            $url = $setting->getConfigValue('url') ?? ($available[$integrationId]['default_url'] ?? 'https://plausible.io');
            return $this->testPlausible($apiKey, $url);
        }

        // GLM-style providers
        $url = $setting->getConfigValue('url') ?? ($available[$integrationId]['default_url'] ?? '');
        $model = $setting->getConfigValue('default_model') ?? array_key_first($available[$integrationId]['models'] ?? []);

        if (!$url || !$model) {
            return "URL or model not configured for {$integrationId}.";
        }

        return $this->testChatCompletion($apiKey, $url, $model);
    }

    private function testTelegram(string $apiKey): string
    {
        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$apiKey}/getMe");
        $data = $response->json();

        if ($response->successful() && ($data['ok'] ?? false)) {
            $result = $data['result'];
            return "Telegram connection OK. Bot: {$result['first_name']} (@{$result['username']})";
        }

        return 'Telegram connection failed: ' . ($data['description'] ?? 'unknown error');
    }

    private function testPlausible(string $apiKey, string $url): string
    {
        $baseUrl = rtrim($url, '/');
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(10)->post($baseUrl . '/api/v2/query', [
            'site_id' => '__connection_test__',
            'metrics' => ['visitors'],
            'date_range' => '7d',
        ]);

        if ($response->json() === null) {
            return "Could not reach Plausible API at {$baseUrl}. Check the URL.";
        }

        return "Plausible connection OK at {$baseUrl}.";
    }

    private function testChatCompletion(string $apiKey, string $url, string $model): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url . '/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => 'Reply with "OK" to confirm the API works.'],
            ],
            'max_tokens' => 10,
        ]);

        if ($response->successful()) {
            return "Connection OK. Model: {$model}";
        }

        $error = $response->json('error.message') ?? $response->body();
        return "Connection failed: {$error}";
    }

    private function setupWebhook(Request $request): string
    {
        $integrationId = $request['integrationId'] ?? null;
        if ($integrationId !== 'telegram') {
            return 'Webhooks are only supported for Telegram.';
        }

        $setting = IntegrationSetting::where('integration_id', 'telegram')->first();
        $apiKey = $setting?->getConfigValue('api_key');

        if (!$apiKey) {
            return 'No Telegram bot token configured. Set it first with update_config.';
        }

        // Generate webhook secret if not set
        $webhookSecret = $setting->getConfigValue('webhook_secret');
        if (!$webhookSecret) {
            $webhookSecret = Str::random(64);
            $setting->setConfigValue('webhook_secret', $webhookSecret);
            $setting->save();
        }

        $appUrl = config('app.url');
        $webhookUrl = rtrim($appUrl, '/') . '/api/webhooks/telegram';

        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$apiKey}/setWebhook", [
            'url' => $webhookUrl,
            'secret_token' => $webhookSecret,
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ]);

        $data = $response->json();

        if ($response->successful() && ($data['ok'] ?? false)) {
            return "Telegram webhook set up: {$webhookUrl}";
        }

        return 'Failed to set webhook: ' . ($data['description'] ?? 'unknown error');
    }

    private function linkExternalUser(Request $request): string
    {
        $userId = $request['userId'] ?? null;
        $provider = $request['provider'] ?? null;
        $externalId = $request['externalId'] ?? null;

        if (!$userId || !$provider || !$externalId) {
            return 'userId, provider, and externalId are all required.';
        }

        $user = User::find($userId);
        if (!$user) {
            return "User not found: {$userId}";
        }

        $existing = UserExternalIdentity::where('provider', $provider)
            ->where('external_id', $externalId)
            ->first();

        if ($existing && $existing->user_id !== $user->id) {
            return "{$provider} ID {$externalId} is already linked to user: {$existing->user->name}";
        }

        UserExternalIdentity::updateOrCreate(
            ['provider' => $provider, 'external_id' => $externalId],
            [
                'id' => $existing->id ?? Str::uuid()->toString(),
                'user_id' => $user->id,
                'display_name' => $request['displayName'] ?? null,
            ]
        );

        return "Linked {$provider} ID {$externalId} to user {$user->name}.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("Action: 'update_config', 'test_connection', 'setup_webhook', 'link_external_user'")
                ->required(),
            'integrationId' => $schema
                ->string()
                ->description('Integration ID (e.g., "telegram", "openai", "glm", "plausible"). Required for all actions.')
                ->required(),
            'apiKey' => $schema
                ->string()
                ->description('Raw API key/token for the integration.'),
            'url' => $schema
                ->string()
                ->description('API base URL (for OpenAI-compatible providers, Plausible, etc.).'),
            'defaultModel' => $schema
                ->string()
                ->description('Default model ID for AI providers.'),
            'enabled' => $schema
                ->string()
                ->description("'true' or 'false' to enable/disable the integration."),
            'defaultAgentId' => $schema
                ->string()
                ->description('Telegram: default agent UUID to handle messages.'),
            'notifyChatId' => $schema
                ->string()
                ->description('Telegram: chat ID for notifications.'),
            'allowedTelegramUsers' => $schema
                ->string()
                ->description('Telegram: JSON array of allowed user IDs, e.g. ["123456","789012"].'),
            'sites' => $schema
                ->string()
                ->description('Plausible: JSON array of site domains, e.g. ["example.com"].'),
            'provider' => $schema
                ->string()
                ->description('External provider name for link_external_user (e.g. "telegram", "whatsapp", "slack").'),
            'externalId' => $schema
                ->string()
                ->description('External user ID for link_external_user (e.g. Telegram user ID).'),
            'displayName' => $schema
                ->string()
                ->description('Display name for the external identity.'),
        ];
    }
}
