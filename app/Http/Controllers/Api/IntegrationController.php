<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
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
                'models' => $info['models'],
                'defaultUrl' => $info['default_url'],
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

        return response()->json([
            'id' => $id,
            'name' => $available[$id]['name'],
            'description' => $available[$id]['description'],
            'models' => $available[$id]['models'],
            'defaultUrl' => $available[$id]['default_url'],
            'enabled' => $setting?->enabled ?? false,
            'config' => [
                'apiKey' => $setting?->getMaskedApiKey(),
                'url' => $setting?->getConfigValue('url') ?? $available[$id]['default_url'],
                'defaultModel' => $setting?->getConfigValue('default_model') ?? array_key_first($available[$id]['models']),
            ],
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
            $config['url'] = $request->input('url') ?: $available[$id]['default_url'];
        }
        if ($request->has('defaultModel')) {
            $config['default_model'] = $request->input('defaultModel');
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

        $url = $request->input('url') ?: $available[$id]['default_url'];
        $model = $request->input('defaultModel') ?: array_key_first($available[$id]['models']);

        // Test the connection based on integration type
        try {
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
