<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\ChannelMember;
use App\Models\IntegrationSetting;
use App\Models\McpServer;
use App\Models\Message;
use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use OpenCompany\PrismCodex\CodexTokenStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class IntegrationController extends Controller
{
    /**
     * Get all integrations with their status
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $settings = IntegrationSetting::forWorkspace()->get()->keyBy('integration_id');
        $available = IntegrationSetting::getAvailableIntegrations();
        $registry = app(ToolProviderRegistry::class);

        $integrations = [];

        // Static integrations (GLM, Telegram, Codex — no ToolProvider package)
        foreach ($available as $id => $info) {
            // Codex uses OAuth tokens, not API keys
            if ($id === 'codex') {
                $codexToken = CodexTokenStore::current();
                $integrations[] = [
                    'id' => $id,
                    'name' => $info['name'],
                    'description' => $info['description'],
                    'icon' => $info['icon'],
                    'category' => $info['category'] ?? null,
                    'models' => $info['models'] ?? null,
                    'enabled' => $codexToken !== null && !$codexToken->isExpired(),
                    'configured' => $codexToken !== null,
                    'configurable' => false,
                    'authType' => 'oauth',
                ];
                continue;
            }

            /** @var IntegrationSetting|null $setting */
            $setting = $settings->get($id);
            $integrations[] = [
                'id' => $id,
                'name' => $info['name'],
                'description' => $info['description'],
                'icon' => $info['icon'],
                'category' => $info['category'] ?? null,
                'models' => $info['models'] ?? null,
                'defaultUrl' => $info['default_url'] ?? null,
                'enabled' => $setting ? $setting->enabled : false,
                'configured' => $setting ? $setting->hasValidConfig() : false,
                'configurable' => false,
            ];
        }

        // Dynamic integrations from ToolProviderRegistry
        foreach ($registry->all() as $provider) {
            if (!($provider instanceof ConfigurableIntegration)) {
                continue;
            }
            $meta = $provider->integrationMeta();
            /** @var IntegrationSetting|null $setting */
            $setting = $settings->get($provider->appName());
            $integrations[] = [
                'id' => $provider->appName(),
                'name' => $meta['name'],
                'description' => $meta['description'],
                'icon' => $meta['icon'],
                'logo' => $meta['logo'] ?? null,
                'category' => $meta['category'],
                'badge' => $meta['badge'] ?? null,
                'docsUrl' => $meta['docs_url'] ?? null,
                'enabled' => $setting ? $setting->enabled : false,
                'configured' => $setting ? $setting->hasValidConfig() : false,
                'configurable' => true,
                'configSchema' => $provider->configSchema(),
            ];
        }

        // MCP servers (remote tool providers)
        $mcpServers = McpServer::forWorkspace()->where('enabled', true)->get();
        foreach ($mcpServers as $server) {
            $integrations[] = [
                'id' => 'mcp_' . $server->slug,
                'name' => $server->name,
                'description' => $server->description ?? 'Remote MCP server',
                'icon' => $server->icon,
                'enabled' => true,
                'configured' => true,
                'configurable' => false,
                'type' => 'mcp',
                'badge' => 'mcp',
                'mcpServerId' => $server->id,
                'toolCount' => count($server->discovered_tools ?? []),
                'url' => $server->url,
            ];
        }

        return response()->json($integrations);
    }

    /**
     * Get configuration for a specific integration (masked API key)
     */
    public function showConfig(string $id): \Illuminate\Http\JsonResponse
    {
        // Check dynamic providers first
        $provider = $this->findConfigurableProvider($id);
        if ($provider) {
            $setting = IntegrationSetting::forWorkspace()->where('integration_id', $id)->first();
            $schema = $provider->configSchema();
            $meta = $provider->integrationMeta();

            $config = [];
            /** @var array{key: string, type: string, label: string, default?: mixed} $field */
            foreach ($schema as $field) {
                $key = $field['key'];
                if ($field['type'] === 'secret' || $field['type'] === 'oauth_connect') {
                    $config[$key] = $setting?->getMaskedValue($key);
                } else {
                    $config[$key] = $setting?->getConfigValue($key, $field['default'] ?? null)
                        ?? ($field['default'] ?? null);
                }
            }

            // Pre-populate shared Google Cloud credentials from sibling integrations
            $googleIntegrations = [
                'google_calendar', 'gmail', 'google_drive',
                'google_contacts', 'google_sheets', 'google_search_console', 'google_tasks', 'google_analytics', 'google_docs', 'google_forms',
            ];
            if (in_array($id, $googleIntegrations, true)) {
                foreach (['client_id', 'client_secret'] as $sharedKey) {
                    if (empty($config[$sharedKey])) {
                        foreach ($googleIntegrations as $sibling) {
                            if ($sibling === $id) {
                                continue;
                            }
                            $siblingSetting = IntegrationSetting::forWorkspace()->where('integration_id', $sibling)->first();
                            $siblingVal = $siblingSetting?->getConfigValue($sharedKey);
                            if (! empty($siblingVal) && is_string($siblingVal)) {
                                $config[$sharedKey] = $sharedKey === 'client_secret'
                                    ? $siblingSetting->getMaskedValue($sharedKey)
                                    : $siblingVal;
                                break;
                            }
                        }
                    }
                }
            }

            return response()->json([
                'id' => $id,
                'name' => $meta['name'],
                'description' => $meta['description'],
                'enabled' => $setting ? $setting->enabled : false,
                'config' => $config,
                'configSchema' => $schema,
            ]);
        }

        // Static integrations (GLM, Telegram)
        $available = IntegrationSetting::getAvailableIntegrations();
        if (!isset($available[$id])) {
            return response()->json(['error' => 'Integration not found'], 404);
        }

        $setting = IntegrationSetting::forWorkspace()->where('integration_id', $id)->first();

        $config = [
            'apiKey' => $setting?->getMaskedApiKey(),
        ];

        if ($id === 'telegram') {
            $config['defaultAgentId'] = $setting?->getConfigValue('default_agent_id') ?? '';
            $config['notifyChatId'] = $setting?->getConfigValue('notify_chat_id') ?? '';
            $config['allowedTelegramUsers'] = $setting?->getConfigValue('allowed_telegram_users', []) ?? [];
            $config['botUsername'] = $setting?->getConfigValue('bot_username') ?? '';
            $config['webhookActive'] = (bool) ($setting?->getConfigValue('webhook_active') ?? false);
        } else {
            $config['url'] = $setting?->getConfigValue('url') ?? ($available[$id]['default_url'] ?? '');
            $config['defaultModel'] = $setting?->getConfigValue('default_model') ?? array_key_first($available[$id]['models'] ?? []);
        }

        return response()->json([
            'id' => $id,
            'name' => $available[$id]['name'],
            'description' => $available[$id]['description'],
            'icon' => $available[$id]['icon'] ?? 'ph:gear',
            'models' => $available[$id]['models'] ?? null,
            'defaultUrl' => $available[$id]['default_url'] ?? null,
            'apiFormat' => $available[$id]['api_format'] ?? null,
            'apiKeyUrl' => $available[$id]['api_key_url'] ?? null,
            'enabled' => $setting ? $setting->enabled : false,
            'config' => $config,
        ]);
    }

    /**
     * Save configuration for an integration
     */
    public function updateConfig(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        // Check dynamic providers first
        $provider = $this->findConfigurableProvider($id);
        if ($provider) {
            $request->validate(array_merge(
                $provider->validationRules(),
                ['enabled' => 'nullable|boolean'],
            ));

            $setting = IntegrationSetting::forWorkspace()->firstOrNew(['integration_id' => $id]);
            if (!$setting->exists) {
                $setting->id = Str::uuid()->toString();
                $setting->workspace_id = workspace()->id;
            }

            $config = $setting->config ?? [];
            /** @var array{key: string, type: string, label: string, default?: mixed} $field */
            foreach ($provider->configSchema() as $field) {
                $key = $field['key'];
                if (!$request->has($key)) {
                    continue;
                }

                // Skip OAuth-managed fields (set by OAuth callback, not user input)
                if ($field['type'] === 'oauth_connect') {
                    continue;
                }

                // Skip masked secret values (user didn't change them)
                if ($field['type'] === 'secret') {
                    $value = $request->input($key);
                    if (!$value || str_contains($value, '*')) {
                        continue;
                    }
                }

                $config[$key] = $request->input($key, $field['default'] ?? null);
            }

            // Copy shared Google credentials from sibling integrations
            $googleIntegrations = [
                'google_calendar', 'gmail', 'google_drive',
                'google_contacts', 'google_sheets', 'google_search_console', 'google_tasks', 'google_analytics', 'google_docs', 'google_forms',
            ];
            if (in_array($id, $googleIntegrations, true)) {
                foreach (['client_id', 'client_secret'] as $sharedKey) {
                    if (empty($config[$sharedKey])) {
                        foreach ($googleIntegrations as $sibling) {
                            if ($sibling === $id) {
                                continue;
                            }
                            $siblingVal = IntegrationSetting::forWorkspace()->where('integration_id', $sibling)
                                ->first()?->getConfigValue($sharedKey);
                            if (! empty($siblingVal) && is_string($siblingVal)) {
                                $config[$sharedKey] = $siblingVal;
                                break;
                            }
                        }
                    }
                }
            }

            $setting->config = $config;
            $setting->enabled = $request->input('enabled', true);
            $setting->save();

            return response()->json([
                'success' => true,
                'enabled' => $setting->enabled,
                'configured' => $setting->hasValidConfig(),
            ]);
        }

        // Static integrations (GLM, Telegram)
        $available = IntegrationSetting::getAvailableIntegrations();
        if (!isset($available[$id])) {
            return response()->json(['error' => 'Integration not found'], 404);
        }

        $request->validate([
            'apiKey' => 'nullable|string',
            'url' => 'nullable|string|url',
            'defaultModel' => 'nullable|string',
            'enabled' => 'nullable|boolean',
        ]);

        $setting = IntegrationSetting::forWorkspace()->firstOrNew(['integration_id' => $id]);

        if (!$setting->exists) {
            $setting->id = Str::uuid()->toString();
            $setting->workspace_id = workspace()->id;
        }

        $config = $setting->config ?? [];
        if ($request->has('apiKey') && $request->input('apiKey') && !str_contains($request->input('apiKey'), '*')) {
            $config['api_key'] = $request->input('apiKey');
        }
        if ($request->has('url')) {
            $config['url'] = $request->input('url') ?: ($available[$id]['default_url'] ?? '');
        }
        if ($request->has('defaultModel')) {
            $config['default_model'] = $request->input('defaultModel');
        }
        // Telegram-specific fields
        if ($request->has('defaultAgentId')) {
            $config['default_agent_id'] = $request->input('defaultAgentId');
        }
        if ($request->has('notifyChatId')) {
            $config['notify_chat_id'] = $request->input('notifyChatId');
        }
        if ($request->has('allowedTelegramUsers')) {
            $config['allowed_telegram_users'] = $request->input('allowedTelegramUsers', []);
        }

        $setting->config = $config;

        if ($request->has('enabled')) {
            $setting->enabled = $request->input('enabled');
        }

        $setting->save();

        // Auto-fetch models if this integration has a valid API key and no models yet
        if ($setting->hasValidConfig() && $id !== 'telegram') {
            $existingModels = $setting->getConfigValue('models');
            if (empty($existingModels)) {
                try {
                    $models = $this->fetchModelsFromProvider($id);
                    if (!empty($models)) {
                        $setting->setConfigValue('models', $models);
                        $setting->save();
                    }
                } catch (\Throwable) {
                    // Silently ignore — user can manually fetch later
                }
            }
        }

        return response()->json([
            'success' => true,
            'enabled' => $setting->enabled,
            'configured' => $setting->hasValidConfig(),
        ]);
    }

    /**
     * Test connection for an integration
     */
    public function testConnection(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        // Check dynamic providers first
        $provider = $this->findConfigurableProvider($id);
        if ($provider) {
            $config = $request->all();

            // Substitute masked secret fields with stored values
            $setting = IntegrationSetting::forWorkspace()->where('integration_id', $id)->first();
            foreach ($provider->configSchema() as $field) {
                if ($field['type'] === 'secret' || $field['type'] === 'oauth_connect') {
                    $key = $field['key'];
                    $value = $config[$key] ?? '';
                    if (!$value || str_contains($value, '*')) {
                        $config[$key] = $setting?->getConfigValue($key);
                    }
                }
            }

            try {
                $result = $provider->testConnection($config);
                $status = $result['success'] ? 200 : 400;

                return response()->json($result, $status);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        // Static integrations
        $available = IntegrationSetting::getAvailableIntegrations();
        if (!isset($available[$id])) {
            return response()->json(['error' => 'Integration not found'], 404);
        }

        $apiKey = $request->input('apiKey');
        if (!$apiKey || str_contains($apiKey, '*')) {
            $setting = IntegrationSetting::forWorkspace()->where('integration_id', $id)->first();
            $apiKey = $setting?->getConfigValue('api_key');
        }

        $format = $available[$id]['api_format'] ?? null;

        // Ollama doesn't need an API key
        if (!$apiKey && $format !== 'ollama') {
            return response()->json([
                'success' => false,
                'error' => 'No API key provided',
            ], 400);
        }

        try {
            if ($id === 'telegram') {
                return $this->testTelegramConnection($apiKey);
            }

            $url = $request->input('url') ?: ($available[$id]['default_url'] ?? '');
            $model = $request->input('defaultModel') ?: array_key_first($available[$id]['models'] ?? []);

            return match ($format) {
                'anthropic' => $this->testAnthropicConnection($apiKey, $url, $model),
                'gemini' => $this->testGeminiConnection($apiKey, $url, $model),
                'ollama' => $this->testOpenAiCompatConnection(null, $url, $model),
                'openai', 'openai_compat' => $this->testOpenAiCompatConnection($apiKey, $url, $model),
                default => response()->json([
                    'success' => false,
                    'error' => 'Test not implemented for this integration',
                ], 501),
            };
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disconnect an OAuth-based integration (clear stored tokens).
     */
    public function disconnect(string $id): \Illuminate\Http\JsonResponse
    {
        $provider = $this->findConfigurableProvider($id);
        if (!$provider) {
            return response()->json(['error' => 'Integration not found'], 404);
        }

        $setting = IntegrationSetting::forWorkspace()->where('integration_id', $id)->first();
        if ($setting) {
            $config = $setting->config ?? [];
            foreach ($provider->configSchema() as $field) {
                if ($field['type'] === 'oauth_connect') {
                    unset($config[$field['key']]);
                }
            }
            $setting->config = $config;
            $setting->enabled = false;
            $setting->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Test GLM/Zhipu AI connection
     */
    /**
     * Test connection for OpenAI-compatible providers (OpenAI, DeepSeek, Groq, Mistral, xAI, OpenRouter, GLM, Ollama).
     */
    private function testOpenAiCompatConnection(?string $apiKey, string $url, ?string $model): \Illuminate\Http\JsonResponse
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($apiKey) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->post($url . '/chat/completions', [
                'model' => $model ?? 'default',
                'messages' => [
                    ['role' => 'user', 'content' => 'Respond with "ok".'],
                ],
                'max_tokens' => 10,
            ]);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'model' => $model,
            ]);
        }

        $error = $response->json('error.message') ?? $response->body();
        return response()->json([
            'success' => false,
            'error' => 'API returned error: ' . $error,
        ], 400);
    }

    /**
     * Test connection for Anthropic (Claude).
     */
    private function testAnthropicConnection(string $apiKey, string $url, ?string $model): \Illuminate\Http\JsonResponse
    {
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url . '/messages', [
            'model' => $model ?? 'claude-sonnet-4-5-20250929',
            'max_tokens' => 10,
            'messages' => [
                ['role' => 'user', 'content' => 'Respond with "ok".'],
            ],
        ]);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'model' => $model,
            ]);
        }

        $error = $response->json('error.message') ?? $response->body();
        return response()->json([
            'success' => false,
            'error' => 'API returned error: ' . $error,
        ], 400);
    }

    /**
     * Test connection for Google Gemini.
     */
    private function testGeminiConnection(string $apiKey, string $url, ?string $model): \Illuminate\Http\JsonResponse
    {
        $model = $model ?? 'gemini-2.0-flash';

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url . '/models/' . $model . ':generateContent?key=' . $apiKey, [
            'contents' => [
                ['parts' => [['text' => 'Respond with "ok".']]],
            ],
            'generationConfig' => ['maxOutputTokens' => 10],
        ]);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'model' => $model,
            ]);
        }

        $error = $response->json('error.message') ?? $response->body();
        return response()->json([
            'success' => false,
            'error' => 'API returned error: ' . $error,
        ], 400);
    }

    /**
     * Test Telegram bot connection
     */
    private function testTelegramConnection(string $apiKey): \Illuminate\Http\JsonResponse
    {
        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$apiKey}/getMe");

        $data = $response->json();

        if ($response->successful() && ($data['ok'] ?? false)) {
            $result = $data['result'];

            // Persist bot username in config
            $setting = IntegrationSetting::forWorkspace()->where('integration_id', 'telegram')->first();
            if ($setting) {
                $setting->setConfigValue('bot_username', $result['username'] ?? '');
                $setting->save();
            }

            return response()->json([
                'success' => true,
                'botName' => $result['first_name'] ?? 'Unknown',
                'username' => $result['username'] ?? 'Unknown',
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $data['description'] ?? 'Failed to connect to Telegram',
        ], 400);
    }

    /**
     * Set up webhook for an integration (currently Telegram only)
     */
    public function setupWebhook(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        if ($id !== 'telegram') {
            return response()->json(['error' => 'Webhooks not supported for this integration'], 400);
        }

        $setting = IntegrationSetting::forWorkspace()->where('integration_id', 'telegram')->first();
        $apiKey = $request->input('apiKey');

        if (!$apiKey || str_contains($apiKey, '*')) {
            $apiKey = $setting?->getConfigValue('api_key');
        }

        if (!$apiKey) {
            return response()->json(['success' => false, 'error' => 'No bot token configured'], 400);
        }

        // Ensure setting exists and save the API key
        if (!$setting) {
            $setting = IntegrationSetting::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => workspace()->id,
                'integration_id' => 'telegram',
                'config' => ['api_key' => $apiKey],
                'enabled' => true,
            ]);
        } elseif (!$setting->getConfigValue('api_key')) {
            $setting->setConfigValue('api_key', $apiKey);
            $setting->save();
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

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$apiKey}/setWebhook", [
                'url' => $webhookUrl,
                'secret_token' => $webhookSecret,
                'allowed_updates' => json_encode(['message', 'callback_query']),
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['ok'] ?? false)) {
                // Persist webhook status in config
                $setting->setConfigValue('webhook_active', true);
                $setting->save();

                return response()->json([
                    'success' => true,
                    'webhookUrl' => $webhookUrl,
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $data['description'] ?? 'Failed to set webhook',
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Link an external identity to a system user.
     */
    public function linkExternalUser(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'userId' => 'required|string|exists:users,id',
            'provider' => 'required|string',
            'externalId' => 'required|string',
            'displayName' => 'nullable|string',
        ]);

        $user = User::findOrFail($request->input('userId'));

        // Check if this external identity is already linked to another user
        $existing = UserExternalIdentity::where('provider', $request->input('provider'))
            ->where('external_id', $request->input('externalId'))
            ->first();

        if ($existing && $existing->user_id !== $user->id) {
            /** @var \App\Models\User $existingUser */
            $existingUser = $existing->user;
            return response()->json([
                'error' => "This {$request->input('provider')} ID is already linked to user: {$existingUser->name}",
            ], 409);
        }

        // Create or update the identity link
        $identity = UserExternalIdentity::updateOrCreate(
            [
                'provider' => $request->input('provider'),
                'external_id' => $request->input('externalId'),
            ],
            [
                'id' => $existing->id ?? Str::uuid()->toString(),
                'user_id' => $user->id,
                'display_name' => $request->input('displayName'),
            ]
        );

        // Clean up shadow user if one exists for this provider/external ID
        if ($request->input('provider') === 'telegram') {
            $shadowEmail = "telegram-{$request->input('externalId')}@external.opencompany";
            $shadow = User::where('email', $shadowEmail)
                ->where('id', '!=', $user->id)
                ->first();

            if ($shadow) {
                // Deduplicate channel memberships before reassigning
                $existingChannelIds = ChannelMember::where('user_id', $user->id)->pluck('channel_id');
                ChannelMember::where('user_id', $shadow->id)
                    ->whereIn('channel_id', $existingChannelIds)
                    ->delete();

                // Reassign remaining memberships, messages, and approvals
                ChannelMember::where('user_id', $shadow->id)->update(['user_id' => $user->id]);
                Message::where('author_id', $shadow->id)->update(['author_id' => $user->id]);
                ApprovalRequest::where('responded_by_id', $shadow->id)
                    ->update(['responded_by_id' => $user->id]);

                $shadow->delete();
            }
        }

        return response()->json([
            'success' => true,
            'identity' => $identity,
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Unlink an external identity from a user.
     */
    public function unlinkExternalUser(string $identityId): \Illuminate\Http\JsonResponse
    {
        $identity = UserExternalIdentity::findOrFail($identityId);
        $identity->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get all external identity links (optionally filtered by provider).
     */
    public function externalIdentities(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = UserExternalIdentity::with('user');

        if ($request->has('provider')) {
            $query->where('provider', $request->input('provider'));
        }

        return response()->json($query->get());
    }

    /**
     * Get enabled AI models for agent brain selection
     */
    public function enabledModels(): \Illuminate\Http\JsonResponse
    {
        $settings = IntegrationSetting::forWorkspace()->where('enabled', true)->get();
        $available = IntegrationSetting::getAvailableIntegrations();

        $models = [];
        foreach ($settings as $setting) {
            if (!isset($available[$setting->integration_id])) {
                continue;
            }

            $info = $available[$setting->integration_id];
            if (empty($info['models'])) {
                continue;
            }
            foreach ($info['models'] as $modelId => $modelName) {
                $models[] = [
                    'id' => $setting->integration_id . ':' . $modelId,
                    'provider' => $setting->integration_id,
                    'providerName' => $info['name'],
                    'model' => $modelId,
                    'name' => $modelName,
                    'icon' => $info['icon'],
                ];
            }
        }

        // Codex uses OAuth, not IntegrationSetting — check separately
        $codexToken = CodexTokenStore::current();
        if ($codexToken && !$codexToken->isExpired()) {
            $codexInfo = $available['codex'] ?? null;
            if ($codexInfo && !empty($codexInfo['models'])) {
                foreach ($codexInfo['models'] as $modelId => $modelName) {
                    $models[] = [
                        'id' => 'codex:' . $modelId,
                        'provider' => 'codex',
                        'providerName' => $codexInfo['name'],
                        'model' => $modelId,
                        'name' => $modelName,
                        'icon' => $codexInfo['icon'],
                    ];
                }
            }
        }

        return response()->json($models);
    }

    /**
     * Get all available AI providers with their models for settings dropdowns.
     *
     * Returns both integration-based providers (GLM, Codex) and prism-config
     * providers (Anthropic, OpenAI, etc.) with configuration status.
     */
    public function allProviders(): \Illuminate\Http\JsonResponse
    {
        $providers = [];
        $available = IntegrationSetting::getAvailableIntegrations();
        $settings = IntegrationSetting::forWorkspace()->get()->keyBy('integration_id');

        // AI providers from config/integrations.php (those with api_format set)
        foreach ($available as $id => $info) {
            if (!isset($info['api_format'])) {
                continue;
            }

            /** @var IntegrationSetting|null $setting */
            $setting = $settings->get($id);
            /** @var array<string, string> $models */
            $models = $info['models'] ?? [];

            // Determine if configured: DB-stored key OR .env key
            $configured = $setting?->hasValidConfig()
                || !empty(config("prism.providers.{$id}.api_key", ''));

            // Ollama is configured if URL is reachable (no key needed)
            if ($info['api_format'] === 'ollama') {
                $configured = $setting?->enabled || !empty(config('prism.providers.ollama.url'));
            }

            $providers[] = [
                'id' => $id,
                'name' => $info['name'],
                'icon' => $info['icon'],
                'configured' => (bool) $configured,
                'source' => $setting?->hasValidConfig() ? 'integration' : 'prism',
                'models' => collect($models)->map(fn (string $name, string $id) => [
                    'id' => $id,
                    'name' => $name,
                ])->values()->all(),
            ];
        }

        // Codex (OAuth-based)
        $codexToken = CodexTokenStore::current();
        $codexInfo = $available['codex'] ?? null;
        if ($codexToken && !$codexToken->isExpired() && $codexInfo && !empty($codexInfo['models'])) {
            /** @var array<string, string> $codexModels */
            $codexModels = $codexInfo['models'];
            $providers[] = [
                'id' => 'codex',
                'name' => $codexInfo['name'],
                'icon' => $codexInfo['icon'],
                'configured' => true,
                'source' => 'oauth',
                'models' => collect($codexModels)->map(fn (string $name, string $id) => [
                    'id' => $id,
                    'name' => $name,
                ])->values()->all(),
            ];
        }

        return response()->json($providers);
    }

    /**
     * Get available embedding models with provider configuration status.
     */
    public function embeddingModels(): \Illuminate\Http\JsonResponse
    {
        $embeddingProviders = [
            'openai' => [
                'name' => 'OpenAI',
                'models' => [
                    'text-embedding-3-small' => 'text-embedding-3-small (1536d)',
                    'text-embedding-3-large' => 'text-embedding-3-large (3072d)',
                    'text-embedding-ada-002' => 'text-embedding-ada-002 (1536d)',
                ],
            ],
            'voyageai' => [
                'name' => 'VoyageAI',
                'models' => [
                    'voyage-3' => 'voyage-3',
                    'voyage-3-lite' => 'voyage-3-lite',
                    'voyage-code-3' => 'voyage-code-3',
                ],
            ],
            'gemini' => [
                'name' => 'Gemini',
                'models' => [
                    'text-embedding-004' => 'text-embedding-004 (768d)',
                ],
            ],
        ];

        $result = [];

        // Cloud providers
        foreach ($embeddingProviders as $providerId => $provider) {
            $apiKey = config("prism.providers.{$providerId}.api_key", '');
            $configured = !empty($apiKey);

            foreach ($provider['models'] as $modelId => $modelName) {
                $result[] = [
                    'id' => "{$providerId}:{$modelId}",
                    'provider' => $providerId,
                    'providerName' => $provider['name'],
                    'model' => $modelId,
                    'name' => $modelName,
                    'configured' => $configured,
                    'type' => 'cloud',
                ];
            }
        }

        // Ollama (self-hosted) embedding models
        $ollamaModels = [
            'snowflake-arctic-embed2' => [
                'name' => 'Snowflake Arctic Embed 2',
                'size' => '568M',
                'parameters' => '568M',
                'memory' => '~1.2 GB',
                'dimensions' => 1024,
                'context' => 8192,
                'description' => 'State-of-the-art multilingual embedding model with 8K context. Best quality for retrieval across multiple languages.',
            ],
            'nomic-embed-text' => [
                'name' => 'Nomic Embed Text v1.5',
                'size' => '137M',
                'parameters' => '137M',
                'memory' => '~0.5 GB',
                'dimensions' => 768,
                'context' => 8192,
                'description' => 'Efficient general-purpose model with Matryoshka dimension support (64–768d). Good balance of quality and speed.',
            ],
            'snowflake-arctic-embed:s' => [
                'name' => 'Snowflake Arctic Embed S',
                'size' => '33M',
                'parameters' => '33M',
                'memory' => '~0.2 GB',
                'dimensions' => 384,
                'context' => 512,
                'description' => 'Ultra-lightweight model for large datasets. Smallest footprint with competitive accuracy. Best for constrained environments.',
            ],
            'mxbai-embed-large' => [
                'name' => 'MxBAI Embed Large',
                'size' => '335M',
                'parameters' => '335M',
                'memory' => '~1.2 GB',
                'dimensions' => 1024,
                'context' => 512,
                'description' => 'High-quality model from Mixedbread AI. Strong benchmark performance, outperforms OpenAI ada-002.',
            ],
        ];

        $ollamaStatus = $this->getOllamaStatus();
        $downloadedModels = $ollamaStatus['models'];

        foreach ($ollamaModels as $modelId => $info) {
            $baseName = explode(':', $modelId)[0];
            $downloaded = in_array($baseName, $downloadedModels) || in_array($modelId, $downloadedModels);

            $result[] = [
                'id' => "ollama:{$modelId}",
                'provider' => 'ollama',
                'providerName' => 'Ollama (self-hosted)',
                'model' => $modelId,
                'name' => $info['name'],
                'configured' => $ollamaStatus['online'],
                'type' => 'local',
                'downloaded' => $downloaded,
                'size' => $info['size'],
                'parameters' => $info['parameters'],
                'memory' => $info['memory'],
                'dimensions' => $info['dimensions'],
                'context' => $info['context'],
                'description' => $info['description'],
            ];
        }

        return response()->json($result);
    }

    /**
     * Get available reranking models with provider configuration status.
     */
    public function rerankingModels(): \Illuminate\Http\JsonResponse
    {
        $result = [];

        // Ollama (self-hosted) reranking models
        $ollamaModels = [
            'dengcao/Qwen3-Reranker-0.6B:Q8_0' => [
                'name' => 'Qwen3 Reranker 0.6B',
                'size' => '639 MB',
                'parameters' => '0.6B',
                'memory' => '~1 GB',
                'description' => 'Lightweight reranker based on Qwen3 (Q8_0 quantization). Fast inference on CPU, good accuracy for most use cases.',
            ],
            'dengcao/Qwen3-Reranker-4B:Q4_K_M' => [
                'name' => 'Qwen3 Reranker 4B',
                'size' => '2.5 GB',
                'parameters' => '4B',
                'memory' => '~4 GB',
                'description' => 'Mid-size reranker with stronger relevance judgments (Q4_K_M quantization). Good balance of quality and speed.',
            ],
            'dengcao/Qwen3-Reranker-8B:Q4_K_M' => [
                'name' => 'Qwen3 Reranker 8B',
                'size' => '5.0 GB',
                'parameters' => '8B',
                'memory' => '~7 GB',
                'description' => 'Largest Qwen3 Reranker variant (Q4_K_M quantization). State-of-the-art accuracy, best for high-stakes retrieval.',
            ],
        ];

        $ollamaStatus = $this->getOllamaStatus();
        $downloadedModels = $ollamaStatus['models'];

        foreach ($ollamaModels as $modelId => $info) {
            $baseName = explode(':', $modelId)[0];
            $downloaded = in_array($baseName, $downloadedModels) || in_array($modelId, $downloadedModels);

            $result[] = [
                'id' => "ollama:{$modelId}",
                'provider' => 'ollama',
                'providerName' => 'Ollama (self-hosted)',
                'model' => $modelId,
                'name' => $info['name'],
                'configured' => $ollamaStatus['online'],
                'type' => 'local',
                'downloaded' => $downloaded,
                'size' => $info['size'],
                'parameters' => $info['parameters'],
                'memory' => $info['memory'],
                'description' => $info['description'],
            ];
        }

        // Cloud providers (Cohere, Jina)
        $cloudProviders = [
            'cohere' => [
                'name' => 'Cohere',
                'models' => [
                    'rerank-v3.5' => 'Rerank v3.5',
                    'rerank-english-v3.0' => 'Rerank English v3.0',
                    'rerank-multilingual-v3.0' => 'Rerank Multilingual v3.0',
                ],
            ],
            'jina' => [
                'name' => 'Jina AI',
                'models' => [
                    'jina-reranker-v2-base-multilingual' => 'Jina Reranker v2 Multilingual',
                ],
            ],
        ];

        foreach ($cloudProviders as $providerId => $provider) {
            $apiKey = config("ai.providers.{$providerId}.key", '');
            $configured = ! empty($apiKey);

            foreach ($provider['models'] as $modelId => $modelName) {
                $result[] = [
                    'id' => "{$providerId}:{$modelId}",
                    'provider' => $providerId,
                    'providerName' => $provider['name'],
                    'model' => $modelId,
                    'name' => $modelName,
                    'configured' => $configured,
                    'type' => 'cloud',
                ];
            }
        }

        return response()->json($result);
    }

    /**
     * Get Ollama connection status and locally available models.
     */
    public function ollamaModelStatus(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->getOllamaStatus());
    }

    /**
     * Pull (download) an Ollama model.
     */
    public function ollamaPullModel(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate(['model' => 'required|string|max:200']);

        $model = $request->input('model');
        $url = config('prism.providers.ollama.url', 'http://localhost:11434');

        return response()->stream(function () use ($model, $url) {
            try {
                $client = new \GuzzleHttp\Client();
                $response = $client->post($url . '/api/pull', [
                    'json' => ['model' => $model, 'stream' => true],
                    'stream' => true,
                    'timeout' => 600,
                    'connect_timeout' => 10,
                ]);

                $body = $response->getBody();
                $buffer = '';

                while (! $body->eof()) {
                    $buffer .= $body->read(8192);

                    while (($newlinePos = strpos($buffer, "\n")) !== false) {
                        $line = substr($buffer, 0, $newlinePos);
                        $buffer = substr($buffer, $newlinePos + 1);

                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }

                        $data = json_decode($line, true);
                        if (! is_array($data)) {
                            continue;
                        }

                        $event = [
                            'status' => $data['status'] ?? '',
                        ];

                        if (isset($data['completed'], $data['total']) && $data['total'] > 0) {
                            $event['completed'] = $data['completed'];
                            $event['total'] = $data['total'];
                            $event['percent'] = min(100, (int) round($data['completed'] / $data['total'] * 100));
                        }

                        if (($data['status'] ?? '') === 'success') {
                            $event['done'] = true;
                            $event['success'] = true;
                        }

                        echo 'data: '.json_encode($event)."\n\n";
                        ob_flush();
                        flush();
                    }
                }

                // Final event if not already sent
                echo 'data: '.json_encode(['done' => true, 'success' => true])."\n\n";
                ob_flush();
                flush();
            } catch (\Throwable $e) {
                echo 'data: '.json_encode(['done' => true, 'success' => false, 'error' => $e->getMessage()])."\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Check Ollama availability and list downloaded models.
     *
     * @return array{online: bool, models: array<int, mixed>, url: mixed}
     */
    private function getOllamaStatus(): array
    {
        $url = config('prism.providers.ollama.url', 'http://localhost:11434');

        try {
            $response = Http::timeout(3)->get($url . '/api/tags');

            if (!$response->successful()) {
                return ['online' => false, 'models' => [], 'url' => $url];
            }

            /** @var array<int, array{name: string}> $rawModels */
            $rawModels = $response->json('models', []);
            $models = collect($rawModels)
                ->pluck('name')
                ->map(fn (string $n) => explode(':', $n)[0])
                ->unique()
                ->values()
                ->toArray();

            return ['online' => true, 'models' => $models, 'url' => $url];
        } catch (\Throwable) {
            return ['online' => false, 'models' => [], 'url' => $url];
        }
    }

    /**
     * Fetch available models from the provider API and store in database.
     */
    public function fetchModels(string $id): \Illuminate\Http\JsonResponse
    {
        try {
            $models = $this->fetchModelsFromProvider($id);

            if (empty($models)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No models returned from provider',
                ], 400);
            }

            // Store in integration settings
            $setting = IntegrationSetting::forWorkspace()->where('integration_id', $id)->first();
            if ($setting) {
                $setting->setConfigValue('models', $models);
                $setting->save();
            }

            return response()->json([
                'success' => true,
                'models' => $models,
                'count' => count($models),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch models: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Fetch models from a provider's API.
     *
     * @return array<string, string> Model ID => display name
     */
    private function fetchModelsFromProvider(string $id): array
    {
        if ($id === 'codex') {
            return $this->fetchCodexModels();
        }

        $available = config('integrations', []);
        $format = $available[$id]['api_format'] ?? null;

        return match ($format) {
            'anthropic' => $this->fetchAnthropicModels($id),
            'gemini' => $this->fetchGeminiModels($id),
            'ollama' => $this->fetchOllamaModels($id),
            'openai', 'openai_compat' => $this->fetchOpenAiCompatModels($id),
            default => throw new \InvalidArgumentException("Model fetching not supported for: {$id}"),
        };
    }

    /**
     * Fetch models from an OpenAI-compatible /models endpoint.
     * Works for: OpenAI, DeepSeek, Groq, Mistral, xAI, OpenRouter, GLM.
     *
     * @return array<string, string>
     */
    private function fetchOpenAiCompatModels(string $id): array
    {
        [$apiKey, $baseUrl] = $this->getProviderCredentials($id);

        if (!$apiKey) {
            throw new \RuntimeException('API key not configured. Save your API key first.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->timeout(15)->get($baseUrl . '/models');

        if (!$response->successful()) {
            throw new \RuntimeException('API returned ' . $response->status() . ': ' . $response->body());
        }

        $data = $response->json('data', []);
        $models = [];

        foreach ($data as $model) {
            $modelId = $model['id'] ?? null;
            if (!$modelId) {
                continue;
            }
            $models[$modelId] = $this->formatModelName($modelId);
        }

        // GLM: probe flash/plus variants not listed by /models
        if ($id === 'glm' || $id === 'glm-coding') {
            $models = $this->probeGlmVariants($models, $apiKey, $baseUrl);
        }

        ksort($models);

        return $models;
    }

    /**
     * Fetch models from Anthropic API.
     *
     * @return array<string, string>
     */
    private function fetchAnthropicModels(string $id): array
    {
        [$apiKey, $baseUrl] = $this->getProviderCredentials($id);

        if (!$apiKey) {
            throw new \RuntimeException('API key not configured. Save your API key first.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(15)->get($baseUrl . '/models');

        if (!$response->successful()) {
            throw new \RuntimeException('API returned ' . $response->status() . ': ' . $response->body());
        }

        $data = $response->json('data', []);
        $models = [];

        foreach ($data as $model) {
            $modelId = $model['id'] ?? null;
            if (!$modelId) {
                continue;
            }
            $models[$modelId] = $model['display_name'] ?? $this->formatModelName($modelId);
        }

        ksort($models);

        return $models;
    }

    /**
     * Fetch models from Google Gemini API.
     *
     * @return array<string, string>
     */
    private function fetchGeminiModels(string $id): array
    {
        [$apiKey, $baseUrl] = $this->getProviderCredentials($id);

        if (!$apiKey) {
            throw new \RuntimeException('API key not configured. Save your API key first.');
        }

        $response = Http::timeout(15)->get($baseUrl . '/models', [
            'key' => $apiKey,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('API returned ' . $response->status() . ': ' . $response->body());
        }

        $data = $response->json('models', []);
        $models = [];

        foreach ($data as $model) {
            $name = $model['name'] ?? null;
            if (!$name) {
                continue;
            }
            // Gemini returns "models/gemini-2.0-flash" — strip "models/" prefix
            $modelId = str_replace('models/', '', $name);
            // Only include generateContent-capable models
            $methods = $model['supportedGenerationMethods'] ?? [];
            if (!in_array('generateContent', $methods)) {
                continue;
            }
            $models[$modelId] = $model['displayName'] ?? $this->formatModelName($modelId);
        }

        ksort($models);

        return $models;
    }

    /**
     * Fetch models from Ollama (OpenAI compatibility layer, no auth).
     *
     * @return array<string, string>
     */
    private function fetchOllamaModels(string $id): array
    {
        $setting = IntegrationSetting::forWorkspace()->where('integration_id', $id)->first();
        $available = config('integrations', []);
        $baseUrl = $setting?->getConfigValue('url') ?: ($available[$id]['default_url'] ?? 'http://localhost:11434/v1');

        $response = Http::timeout(10)->get($baseUrl . '/models');

        if (!$response->successful()) {
            throw new \RuntimeException('Could not connect to Ollama at ' . $baseUrl);
        }

        $data = $response->json('data', $response->json('models', []));
        $models = [];

        foreach ($data as $model) {
            $modelId = $model['id'] ?? $model['name'] ?? null;
            if (!$modelId) {
                continue;
            }
            $models[$modelId] = $this->formatModelName($modelId);
        }

        ksort($models);

        return $models;
    }

    /**
     * Get API key and base URL for a provider from IntegrationSetting or config.
     *
     * @return array{0: ?string, 1: string}
     */
    private function getProviderCredentials(string $id): array
    {
        $setting = IntegrationSetting::forWorkspace()->where('integration_id', $id)->first();
        $available = config('integrations', []);

        $apiKey = $setting?->getConfigValue('api_key')
            ?: config("prism.providers.{$id}.api_key", '');
        $baseUrl = $setting?->getConfigValue('url')
            ?: config("prism.providers.{$id}.url")
            ?: ($available[$id]['default_url'] ?? '');

        return [$apiKey ?: null, $baseUrl];
    }

    /**
     * GLM-specific: probe flash/plus variants not listed by /models.
     *
     * @param  array<string, string>  $models
     * @return array<string, string>
     */
    private function probeGlmVariants(array $models, string $apiKey, string $baseUrl): array
    {
        $variants = [];
        foreach (array_keys($models) as $modelId) {
            $variants[] = $modelId . '-flash';
            $variants[] = $modelId . '-plus';
        }

        foreach ($variants as $variant) {
            if (isset($models[$variant])) {
                continue;
            }
            try {
                $probe = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])->timeout(8)->post($baseUrl . '/chat/completions', [
                    'model' => $variant,
                    'messages' => [['role' => 'user', 'content' => 'hi']],
                    'max_tokens' => 1,
                ]);
                if ($probe->status() === 200 || $probe->status() === 429) {
                    $models[$variant] = $this->formatModelName($variant);
                }
            } catch (\Throwable) {
                // Skip on timeout/error
            }
        }

        return $models;
    }

    /**
     * Format a model ID into a readable display name.
     */
    private function formatModelName(string $modelId): string
    {
        // Strip date suffixes like -20250929
        $name = preg_replace('/-\d{8}$/', '', $modelId);
        // Replace separators with spaces
        $name = str_replace(['-', '_', '/'], [' ', ' ', ' / '], $name);
        return ucwords($name);
    }

    /**
     * Fetch models from the Codex API.
     *
     * @return array<string, string>
     */
    private function fetchCodexModels(): array
    {
        $oauthService = app(\OpenCompany\PrismCodex\CodexOAuthService::class);
        $token = $oauthService->getAccessToken();

        if (!$token) {
            throw new \RuntimeException('Not authenticated. Please connect your Codex account first.');
        }

        $response = Http::withToken($token)
            ->withHeaders(array_filter([
                'ChatGPT-Account-Id' => $oauthService->getAccountId(),
            ]))
            ->timeout(15)
            ->get(config('codex.url', 'https://chatgpt.com/backend-api/codex') . '/models');

        if (!$response->successful()) {
            throw new \RuntimeException('API returned ' . $response->status() . ': ' . $response->body());
        }

        $data = $response->json('models', $response->json('data', []));
        $models = [];

        foreach ($data as $model) {
            $modelId = is_string($model) ? $model : ($model['id'] ?? $model['slug'] ?? null);
            if (!$modelId) {
                continue;
            }
            $displayName = strtoupper(str_replace(['-', '_'], [' ', ' '], $modelId));
            $displayName = ucwords(strtolower($displayName));
            $models[$modelId] = $displayName;
        }

        return $models;
    }

    /**
     * Find a ToolProvider that implements ConfigurableIntegration for the given ID.
     */
    private function findConfigurableProvider(string $id): ?ConfigurableIntegration
    {
        $registry = app(ToolProviderRegistry::class);
        $provider = $registry->get($id);

        if ($provider instanceof ConfigurableIntegration) {
            return $provider;
        }

        return null;
    }
}
