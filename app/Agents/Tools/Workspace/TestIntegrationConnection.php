<?php

namespace App\Agents\Tools\Workspace;

use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class TestIntegrationConnection implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Test the connection for a configured integration to verify API keys and endpoints work.';
    }

    public function handle(Request $request): string
    {
        try {
            $integrationId = $request['integrationId'] ?? null;
            if (!$integrationId) {
                return 'integrationId is required.';
            }

            // Check dynamic providers first
            $provider = $this->findConfigurableProvider($integrationId);

            if ($provider) {
                $setting = IntegrationSetting::forWorkspace()->where('integration_id', $integrationId)->first();
                $config = $setting->config ?? [];

                $result = $provider->testConnection($config);

                return $result['success']
                    ? ($result['message'] ?? 'Connection OK.')
                    : 'Connection failed: ' . ($result['error'] ?? 'unknown error');
            }

            // Static providers
            $available = IntegrationSetting::getAvailableIntegrations();
            if (!isset($available[$integrationId])) {
                return "Integration not found: {$integrationId}";
            }

            $setting = IntegrationSetting::forWorkspace()->where('integration_id', $integrationId)->first();
            $apiKey = $setting?->getConfigValue('api_key');

            if (!$apiKey) {
                return "No API key configured for {$integrationId}. Set it first with update_integration_config.";
            }

            if ($integrationId === 'telegram') {
                return $this->testTelegram($apiKey);
            }

            // GLM-style providers
            $url = $setting->getConfigValue('url') ?? ($available[$integrationId]['default_url'] ?? '');
            $model = $setting->getConfigValue('default_model') ?? array_key_first($available[$integrationId]['models'] ?? []);

            if (!$url || !$model) {
                return "URL or model not configured for {$integrationId}.";
            }

            return $this->testChatCompletion($apiKey, $url, $model);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function findConfigurableProvider(string $id): ?ConfigurableIntegration
    {
        $provider = app(ToolProviderRegistry::class)->get($id);

        return $provider instanceof ConfigurableIntegration ? $provider : null;
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

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'integrationId' => $schema
                ->string()
                ->description('Integration ID (e.g., "telegram", "glm", "plausible"). Includes both static and dynamic package-provided integrations.')
                ->required(),
        ];
    }
}