<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\ChannelMember;
use App\Models\IntegrationSetting;
use App\Models\Message;
use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IntegrationController extends Controller
{
    /**
     * Get all integrations with their status
     */
    public function index()
    {
        $settings = IntegrationSetting::all()->keyBy('integration_id');
        $available = IntegrationSetting::getAvailableIntegrations();

        $integrations = [];
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
            ];
        }

        return response()->json($integrations);
    }

    /**
     * Get configuration for a specific integration (masked API key)
     */
    public function showConfig(string $id)
    {
        $available = IntegrationSetting::getAvailableIntegrations();
        if (!isset($available[$id])) {
            return response()->json(['error' => 'Integration not found'], 404);
        }

        $setting = IntegrationSetting::where('integration_id', $id)->first();

        $config = [
            'apiKey' => $setting?->getMaskedApiKey(),
        ];

        // Integration-specific config
        if ($id === 'telegram') {
            $config['defaultAgentId'] = $setting?->getConfigValue('default_agent_id') ?? '';
            $config['notifyChatId'] = $setting?->getConfigValue('notify_chat_id') ?? '';
            $config['allowedTelegramUsers'] = $setting?->getConfigValue('allowed_telegram_users', []) ?? [];
        } elseif ($id === 'plausible') {
            $config['url'] = $setting?->getConfigValue('url') ?? ($available[$id]['default_url'] ?? '');
            $config['sites'] = $setting?->getConfigValue('sites', []) ?? [];
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

        // Only update API key if a new one is provided (not masked)
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
        // Plausible-specific fields
        if ($request->has('sites')) {
            $config['sites'] = $request->input('sites', []);
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
        $available = IntegrationSetting::getAvailableIntegrations();
        if (!isset($available[$id])) {
            return response()->json(['error' => 'Integration not found'], 404);
        }

        // Get API key from request or existing config
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

        // Test the connection based on integration type
        try {
            if ($id === 'telegram') {
                return $this->testTelegramConnection($apiKey);
            }

            $url = $request->input('url') ?: ($available[$id]['default_url'] ?? '');
            $model = $request->input('defaultModel') ?: array_key_first($available[$id]['models'] ?? []);

            if ($id === 'glm' || $id === 'glm-coding') {
                return $this->testGlmConnection($apiKey, $url, $model);
            }

            if ($id === 'plausible') {
                $url = $request->input('url') ?: ($available[$id]['default_url'] ?? 'https://plausible.io');
                return $this->testPlausibleConnection($apiKey, $url);
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
     * Test Plausible Analytics connection
     */
    private function testPlausibleConnection(string $apiKey, string $url)
    {
        $baseUrl = rtrim($url, '/');

        // Try the v2 query endpoint. Plausible returns JSON for API requests
        // (even errors) but HTML for invalid URLs. A JSON response confirms
        // the API is reachable and the key format is accepted.
        // Note: Plausible returns 401 for both "bad key" and "key lacks site access",
        // so we also accept a siteId param to do a real data test.
        $siteId = request()->input('siteId');
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(10)->post($baseUrl . '/api/v2/query', [
            'site_id' => $siteId ?: '__connection_test__',
            'metrics' => ['visitors'],
            'date_range' => '7d',
        ]);

        $json = $response->json();

        // Got HTML back — wrong URL or API isn't available
        if ($json === null) {
            return response()->json([
                'success' => false,
                'error' => "Could not reach Plausible API at {$baseUrl}. Check the URL.",
            ], 400);
        }

        // If we provided a real siteId and got data back, full success
        if ($response->successful() && $siteId) {
            return response()->json([
                'success' => true,
                'message' => "Connected to Plausible at {$baseUrl} — site '{$siteId}' accessible.",
            ]);
        }

        // Got JSON error but no siteId was provided — API is reachable
        if (!$siteId) {
            return response()->json([
                'success' => true,
                'message' => "Connected to Plausible API at {$baseUrl}.",
            ]);
        }

        // Got JSON error with a real siteId — likely bad key or no access to site
        return response()->json([
            'success' => false,
            'error' => $json['error'] ?? 'API returned an error',
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
}
