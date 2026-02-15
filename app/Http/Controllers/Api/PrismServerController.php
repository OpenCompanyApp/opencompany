<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Models\PrismApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PrismServerController extends Controller
{
    /**
     * Get Prism Server configuration.
     */
    public function config(): JsonResponse
    {
        $setting = IntegrationSetting::forWorkspace()->where('integration_id', 'prism-server')->first();

        return response()->json([
            'enabled' => $setting->enabled ?? false,
            'enabled_models' => $setting?->getConfigValue('enabled_models', []) ?? [],
        ]);
    }

    /**
     * Update Prism Server configuration.
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'enabled_models' => 'required|array',
            'enabled_models.*' => 'string',
        ]);

        $setting = IntegrationSetting::forWorkspace()->where('integration_id', 'prism-server')->first();

        if (! $setting) {
            $setting = IntegrationSetting::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => workspace()->id,
                'integration_id' => 'prism-server',
                'enabled' => $validated['enabled'],
                'config' => ['enabled_models' => $validated['enabled_models']],
            ]);
        } else {
            $setting->update([
                'enabled' => $validated['enabled'],
                'config' => array_merge($setting->config ?? [], [
                    'enabled_models' => $validated['enabled_models'],
                ]),
            ]);
        }

        return response()->json([
            'enabled' => $setting->enabled,
            'enabled_models' => $setting->getConfigValue('enabled_models', []),
        ]);
    }

    /**
     * List all API keys (masked).
     */
    public function apiKeys(): JsonResponse
    {
        $keys = PrismApiKey::forWorkspace()->orderByDesc('created_at')->get();

        return response()->json($keys->map(fn (PrismApiKey $key) => [
            'id' => $key->id,
            'name' => $key->name,
            'masked_key' => $key->masked_key,
            'last_used_at' => $key->last_used_at?->toISOString(),
            'expires_at' => $key->expires_at?->toISOString(),
            'created_at' => $key->created_at?->toISOString(),
        ]));
    }

    /**
     * Generate a new API key.
     */
    public function createApiKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $result = PrismApiKey::generateKey($validated['name']);

        return response()->json([
            'id' => $result['key']->id,
            'name' => $result['key']->name,
            'key' => $result['plainTextKey'],
            'masked_key' => $result['key']->masked_key,
            'created_at' => $result['key']->created_at?->toISOString(),
        ], 201);
    }

    /**
     * Revoke (delete) an API key.
     */
    public function deleteApiKey(string $id): JsonResponse
    {
        $key = PrismApiKey::forWorkspace()->findOrFail($id);
        $key->delete();

        return response()->json(['message' => 'API key revoked.']);
    }
}
