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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class IntegrationController extends Controller
{
    /**
     * Get all integrations with their status
     */
    public function index()
    {
        $settings = IntegrationSetting::all()->keyBy('integration_id');
        $available = IntegrationSetting::getAvailableIntegrations();
        $registry = app(ToolProviderRegistry::class);

        $integrations = [];

        // Static integrations (GLM, Telegram — no ToolProvider package)
        foreach ($available as $id => $info) {
            $setting = $settings->get($id);
            $integrations[] = [
                'id' => $id,
                'name' => $info['name'],
                'description' => $info['description'],
                'icon' => $info['icon'],
                'models' => $info['models'] ?? null,
                'defaultUrl' => $info['default_url'] ?? null,
                'enabled' => $setting?->enabled ?? false,
                'configured' => $setting?->hasValidConfig() ?? false,
                'configurable' => false,
            ];
        }

        // Dynamic integrations from ToolProviderRegistry
        foreach ($registry->all() as $provider) {
            if (!($provider instanceof ConfigurableIntegration)) {
                continue;
            }
            $meta = $provider->integrationMeta();
            $setting = $settings->get($provider->appName());
            $integrations[] = [
                'id' => $provider->appName(),
                'name' => $meta['name'],
                'description' => $meta['description'],
                'icon' => $meta['icon'],
                'logo' => $meta['logo'] ?? null,
                'category' => $meta['category'] ?? null,
                'badge' => $meta['badge'] ?? null,
                'docsUrl' => $meta['docs_url'] ?? null,
                'enabled' => $setting?->enabled ?? false,
                'configured' => $setting?->hasValidConfig() ?? false,
                'configurable' => true,
                'configSchema' => $provider->configSchema(),
            ];
        }

        // MCP servers (remote tool providers)
        $mcpServers = McpServer::where('enabled', true)->get();
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
    public function showConfig(string $id)
    {
        // Check dynamic providers first
        $provider = $this->findConfigurableProvider($id);
        if ($provider) {
            $setting = IntegrationSetting::where('integration_id', $id)->first();
            $schema = $provider->configSchema();
            $meta = $provider->integrationMeta();

            $config = [];
            foreach ($schema as $field) {
                $key = $field['key'];
                if ($field['type'] === 'secret') {
                    $config[$key] = $setting?->getMaskedValue($key);
                } else {
                    $config[$key] = $setting?->getConfigValue($key, $field['default'] ?? null)
                        ?? ($field['default'] ?? null);
                }
            }

            return response()->json([
                'id' => $id,
                'name' => $meta['name'],
                'description' => $meta['description'],
                'enabled' => $setting?->enabled ?? false,
                'config' => $config,
                'configSchema' => $schema,
            ]);
        }

        // Static integrations (GLM, Telegram)
        $available = IntegrationSetting::getAvailableIntegrations();
        if (!isset($available[$id])) {
            return response()->json(['error' => 'Integration not found'], 404);
        }

        $setting = IntegrationSetting::where('integration_id', $id)->first();

        $config = [
            'apiKey' => $setting?->getMaskedApiKey(),
        ];

        if ($id === 'telegram') {
            $config['defaultAgentId'] = $setting?->getConfigValue('default_agent_id') ?? '';
            $config['notifyChatId'] = $setting?->getConfigValue('notify_chat_id') ?? '';
            $config['allowedTelegramUsers'] = $setting?->getConfigValue('allowed_telegram_users', []) ?? [];
        } else {
            $config['url'] = $setting?->getConfigValue('url') ?? ($available[$id]['default_url'] ?? '');
            $config['defaultModel'] = $setting?->getConfigValue('default_model') ?? array_key_first($available[$id]['models'] ?? []);
        }

        return response()->json([
            'id' => $id,
            'name' => $available[$id]['name'],
            'description' => $available[$id]['description'],
            'models' => $available[$id]['models'] ?? null,
            'defaultUrl' => $available[$id]['default_url'] ?? null,
            'enabled' => $setting?->enabled ?? false,
            'config' => $config,
        ]);
    }

    /**
     * Save configuration for an integration
     */
    public function updateConfig(Request $request, string $id)
    {
        // Check dynamic providers first
        $provider = $this->findConfigurableProvider($id);
        if ($provider) {
            $request->validate(array_merge(
                $provider->validationRules(),
                ['enabled' => 'nullable|boolean'],
            ));

            $setting = IntegrationSetting::firstOrNew(['integration_id' => $id]);
            if (!$setting->id) {
                $setting->id = Str::uuid()->toString();
            }

            $config = $setting->config ?? [];
            foreach ($provider->configSchema() as $field) {
                $key = $field['key'];
                if (!$request->has($key)) {
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

            $setting->config = $config;
            if ($request->has('enabled')) {
                $setting->enabled = $request->input('enabled');
            }
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

        $setting = IntegrationSetting::firstOrNew(['integration_id' => $id]);

        if (!$setting->id) {
            $setting->id = Str::uuid()->toString();
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

        return response()->json([
            'success' => true,
            'enabled' => $setting->enabled,
            'configured' => $setting->hasValidConfig(),
        ]);
    }

    /**
     * Test connection for an integration
     */
    public function testConnection(Request $request, string $id)
    {
        // Check dynamic providers first
        $provider = $this->findConfigurableProvider($id);
        if ($provider) {
            $config = $request->all();

            // Substitute masked secret fields with stored values
            $setting = IntegrationSetting::where('integration_id', $id)->first();
            foreach ($provider->configSchema() as $field) {
                if ($field['type'] === 'secret') {
                    $key = $field['key'];
                    $value = $config[$key] ?? '';
                    if (!$value || str_contains($value, '*')) {
                        $config[$key] = $setting?->getConfigValue($key);
                    }
                }
            }

            try {
                $result = $provider->testConnection($config);
                $status = ($result['success'] ?? false) ? 200 : 400;

                return response()->json($result, $status);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        // Static integrations (GLM, Telegram)
        $available = IntegrationSetting::getAvailableIntegrations();
        if (!isset($available[$id])) {
            return response()->json(['error' => 'Integration not found'], 404);
        }

        $apiKey = $request->input('apiKey');
        if (!$apiKey || str_contains($apiKey, '*')) {
            $setting = IntegrationSetting::where('integration_id', $id)->first();
            $apiKey = $setting?->getConfigValue('api_key');
        }

        if (!$apiKey) {
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

            if ($id === 'glm' || $id === 'glm-coding') {
                return $this->testGlmConnection($apiKey, $url, $model);
            }

            return response()->json([
                'success' => false,
                'error' => 'Test not implemented for this integration',
            ], 501);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test GLM/Zhipu AI connection
     */
    private function testGlmConnection(string $apiKey, string $url, string $model)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url . '/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => 'Hello, please respond with "Connection successful" to confirm the API is working.'],
            ],
            'max_tokens' => 50,
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
    private function testTelegramConnection(string $apiKey)
    {
        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$apiKey}/getMe");

        $data = $response->json();

        if ($response->successful() && ($data['ok'] ?? false)) {
            $result = $data['result'];
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
    public function setupWebhook(Request $request, string $id)
    {
        if ($id !== 'telegram') {
            return response()->json(['error' => 'Webhooks not supported for this integration'], 400);
        }

        $setting = IntegrationSetting::where('integration_id', 'telegram')->first();
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
    public function linkExternalUser(Request $request)
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
            return response()->json([
                'error' => "This {$request->input('provider')} ID is already linked to user: {$existing->user->name}",
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
    public function unlinkExternalUser(string $identityId)
    {
        $identity = UserExternalIdentity::findOrFail($identityId);
        $identity->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get all external identity links (optionally filtered by provider).
     */
    public function externalIdentities(Request $request)
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
    public function enabledModels()
    {
        $settings = IntegrationSetting::where('enabled', true)->get();
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

        return response()->json($models);
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
