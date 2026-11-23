<?php

namespace App\Http\Controllers\Api;

use App\Domain\Authorization\Application\AuthorizationService;
use App\Domain\Authorization\Engine\PolicyEngine;
use App\Http\Controllers\Controller;
use App\Models\AiGatewayApiKey;
use App\Models\IntegrationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin API for OpenCompany AI Gateway workspace settings.
 *
 * This manages the app-owned AI Gateway settings surface. It owns only
 * OpenCompany-owned gateway exposure and API keys; provider credentials remain
 * owned by each provider integration.
 *
 * Authorization: every action asks AuthorizationService for
 * `ai_gateway.manage` (the `workspace.admin` route middleware stays as
 * defense in depth). Key creation and revocation are recorded in the
 * authorization ledger as security changes, without the plaintext key.
 *
 * Keys (GIN-1203): always expire (default and maximum lifetime from
 * config/authorization.php), carry explicit operation and model scopes, are
 * bound to the creating human, and are revoked rather than deleted.
 */
class AiGatewayController extends Controller
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function config(Request $request): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'ai_gateway.manage', workspace());
        $setting = IntegrationSetting::forWorkspace()->where('integration_id', 'ai-gateway')->first();

        return response()->json([
            'enabled' => $setting->enabled ?? false,
            'enabled_models' => $setting?->getConfigValue('enabled_models', []) ?? [],
        ]);
    }

    public function updateConfig(Request $request): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'ai_gateway.manage', workspace());
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'enabled_models' => 'required|array',
            'enabled_models.*' => 'string',
        ]);

        $setting = IntegrationSetting::forWorkspace()->where('integration_id', 'ai-gateway')->first();

        if (! $setting) {
            $setting = IntegrationSetting::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => workspace()->id,
                'integration_id' => 'ai-gateway',
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

    public function apiKeys(Request $request): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'ai_gateway.manage', workspace());

        $keys = AiGatewayApiKey::forWorkspace()
            ->when(! $request->boolean('include_revoked'), fn ($query) => $query->whereNull('revoked_at'))
            ->orderByDesc('created_at')
            ->get();

        return response()->json($keys->map(fn (AiGatewayApiKey $key) => $this->present($key)));
    }

    /**
     * Create a key. Body: `name`, optional `expires_in_days` (1..max) or
     * `expires_at` (future, within max), optional `scopes.actions` and
     * `scopes.models`. Without an expiry the configured default applies;
     * a key never lives longer than the configured maximum.
     */
    public function createApiKey(Request $request): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'ai_gateway.manage', workspace());

        $maxDays = max(1, (int) config('authorization.ai_gateway.max_key_days', 365));
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'expires_in_days' => "nullable|integer|min:1|max:{$maxDays}",
            'expires_at' => 'nullable|date|after:now',
            'scopes' => 'nullable|array',
            'scopes.actions' => 'nullable|array|min:1',
            'scopes.actions.*' => ['string', Rule::in(PolicyEngine::GATEWAY_OPERATIONS)],
            'scopes.models' => 'nullable|array|min:1',
            'scopes.models.*' => 'string|max:255',
        ]);

        $expiresAt = isset($validated['expires_at'])
            ? \Illuminate\Support\Carbon::parse($validated['expires_at'])
            : now()->addDays((int) ($validated['expires_in_days'] ?? config('authorization.ai_gateway.default_key_days', 90)));
        if ($expiresAt->greaterThan(now()->addDays($maxDays))) {
            return response()->json([
                'message' => "API keys can live at most {$maxDays} days.",
                'errors' => ['expires_at' => ["API keys can live at most {$maxDays} days."]],
            ], 422);
        }

        $full = AiGatewayApiKey::fullScopes();
        $scopes = [
            'actions' => array_values(array_unique($validated['scopes']['actions'] ?? $full['actions'])),
            'models' => array_values(array_unique($validated['scopes']['models'] ?? $full['models'])),
        ];

        $result = AiGatewayApiKey::generateKey(
            $validated['name'],
            expiresAt: $expiresAt,
            scopes: $scopes,
            createdByUserId: (string) $request->user()->id,
        );

        // Ledger: identifiers and scopes only; never the plaintext key.
        $this->authorization->recordChange($request->user(), workspace(), 'ai_gateway.key.create', ['type' => 'ai_gateway_key', 'id' => $result['key']->id], [
            'key_name' => $result['key']->name,
            'key_prefix' => $result['key']->key_prefix,
            'expires_at' => $result['key']->expires_at?->toIso8601String(),
            'scopes' => $scopes,
        ]);

        return response()->json([
            ...$this->present($result['key']),
            'key' => $result['plainTextKey'],
        ], 201);
    }

    /** Revoke (not delete): the row stays for the audit trail. */
    public function deleteApiKey(Request $request, string $id): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'ai_gateway.manage', workspace());

        $key = AiGatewayApiKey::forWorkspace()->whereNull('revoked_at')->findOrFail($id);
        $key->update(['revoked_at' => now()]);

        $this->authorization->recordChange($request->user(), workspace(), 'ai_gateway.key.revoke', ['type' => 'ai_gateway_key', 'id' => $key->id], [
            'key_name' => $key->name,
            'key_prefix' => $key->key_prefix,
        ]);

        return response()->json(['message' => 'API key revoked.']);
    }

    /** @return array<string, mixed> */
    private function present(AiGatewayApiKey $key): array
    {
        return [
            'id' => $key->id,
            'name' => $key->name,
            'masked_key' => $key->masked_key,
            'scopes' => $key->normalizedScopes(),
            'created_by_user_id' => $key->created_by_user_id,
            'last_used_at' => $key->last_used_at?->toISOString(),
            'expires_at' => $key->expires_at?->toISOString(),
            'revoked_at' => $key->revoked_at?->toISOString(),
            'created_at' => $key->created_at?->toISOString(),
        ];
    }
}
