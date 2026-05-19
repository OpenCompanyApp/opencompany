<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\ChannelMember;
use App\Models\IntegrationSetting;
use App\Models\Message;
use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Services\Ai\ModelCatalog;
use App\Services\Ai\ModelRuntimeCatalog;
use App\Services\Integrations\IntegrationAccountResolver;
use App\Services\Integrations\IntegrationConfigResolver;
use App\Services\Integrations\IntegrationConnectionTester;
use App\Services\Integrations\IntegrationDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API surface for integration, provider, and model configuration.
 *
 * This controller delegates most behavior to catalog/config services. Keep it as
 * request orchestration: account aliases, masked-secret handling, OAuth link
 * management, and model-list refreshes are app concerns, while package schemas
 * remain package-owned.
 */
class IntegrationController extends Controller
{
    /**
     * Get all integrations with their status
     */
    public function index(): JsonResponse
    {
        return response()->json(app(IntegrationDirectory::class)->all());
    }

    /**
     * Get configuration for a specific integration (masked API key)
     */
    public function showConfig(Request $request, string $id): JsonResponse
    {
        $config = app(IntegrationConfigResolver::class)->show(
            $id,
            app(IntegrationAccountResolver::class)->accountFromRequest($request),
        );

        if ($config === null) {
            return response()->json(['error' => 'Integration not found'], 404);
        }

        return response()->json($config);
    }

    /**
     * Save configuration for an integration
     */
    public function updateConfig(Request $request, string $id): JsonResponse
    {
        [$payload, $status] = app(IntegrationConfigResolver::class)->update(
            $request,
            $id,
            app(IntegrationAccountResolver::class)->accountFromRequest($request),
        );

        if ($status === 200 && ($payload['configured'] ?? false)) {
            try {
                // First successful configuration can populate provider models.
                // Failure is non-fatal because the user can refresh models later.
                $setting = app(IntegrationAccountResolver::class)
                    ->findSetting($id, app(IntegrationAccountResolver::class)->accountFromRequest($request));
                $existingModels = $setting?->getConfigValue('models');
                if ($setting && empty($existingModels) && ($models = app(ModelRuntimeCatalog::class)->fetchProviderModels($id)) !== []) {
                    $setting->setConfigValue('models', $models);
                    $setting->save();
                }
            } catch (\Throwable) {
                //
            }
        }

        return response()->json($payload, $status);
    }

    /**
     * Toggle an integration on or off (for integrations that don't need config).
     */
    public function toggle(Request $request, string $id): JsonResponse
    {
        $request->validate(['enabled' => 'required|boolean']);

        $setting = app(IntegrationAccountResolver::class)->findSetting($id);

        if ($setting) {
            $setting->update(['enabled' => $request->boolean('enabled')]);
        } else {
            // No-config integrations still need a setting row so workspace-level
            // enablement can be evaluated by AgentPermissionService.
            $setting = IntegrationSetting::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => workspace()->id,
                'integration_id' => $id,
                'account_alias' => '',
                'config' => [],
                'enabled' => $request->boolean('enabled'),
                'is_default' => true,
            ]);
        }

        return response()->json([
            'enabled' => $setting->enabled,
        ]);
    }

    /**
     * Test connection for an integration
     */
    public function testConnection(Request $request, string $id): JsonResponse
    {
        $result = app(IntegrationConnectionTester::class)->test($request, $id);

        return response()->json($result->toArray(), $result->status);
    }

    /**
     * Disconnect an OAuth-based integration (clear stored tokens).
     */
    public function disconnect(Request $request, string $id): JsonResponse
    {
        $disconnected = app(IntegrationConfigResolver::class)->disconnect(
            $id,
            app(IntegrationAccountResolver::class)->accountFromRequest($request),
        );

        if ($disconnected === null) {
            return response()->json(['error' => 'Integration not found'], 404);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Set up webhook for an integration (currently Telegram only)
     */
    public function setupWebhook(Request $request, string $id): JsonResponse
    {
        if ($id !== 'telegram') {
            return response()->json(['error' => 'Webhooks not supported for this integration'], 400);
        }

        $setting = app(IntegrationAccountResolver::class)->findSetting('telegram');
        $apiKey = $request->input('apiKey');

        if (! $apiKey || str_contains($apiKey, '*')) {
            // A masked token from the UI means "reuse the stored Telegram token"
            // and must never overwrite the encrypted setting.
            $apiKey = $setting?->getConfigValue('api_key');
        }

        if (! $apiKey) {
            return response()->json(['success' => false, 'error' => 'No bot token configured'], 400);
        }

        // Ensure the setting exists before generating the webhook secret so the
        // secret and token are stored atomically on the same integration row.
        if (! $setting) {
            $setting = IntegrationSetting::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => workspace()->id,
                'integration_id' => 'telegram',
                'config' => ['api_key' => $apiKey],
                'enabled' => true,
            ]);
        } elseif (! $setting->getConfigValue('api_key')) {
            $setting->setConfigValue('api_key', $apiKey);
            $setting->save();
        }

        // Telegram sends this secret back in the webhook header. Generate once
        // and reuse it so existing webhook registrations remain valid.
        $webhookSecret = $setting->getConfigValue('webhook_secret');
        if (! $webhookSecret) {
            $webhookSecret = Str::random(64);
            $setting->setConfigValue('webhook_secret', $webhookSecret);
            $setting->save();
        }

        $appUrl = config('app.url');
        $webhookUrl = rtrim($appUrl, '/').'/api/webhooks/chat/telegram';

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$apiKey}/setWebhook", [
                'url' => $webhookUrl,
                'secret_token' => $webhookSecret,
                'allowed_updates' => json_encode(['message', 'callback_query']),
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['ok'] ?? false)) {
                // Persist webhook status for UI diagnostics only. Telegram is
                // still the source of truth for actual webhook registration.
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
    public function linkExternalUser(Request $request): JsonResponse
    {
        $request->validate([
            'userId' => 'required|string|exists:users,id',
            'provider' => 'required|string',
            'externalId' => 'required|string',
            'displayName' => 'nullable|string',
        ]);

        $user = User::findOrFail($request->input('userId'));

        // External provider IDs are globally unique per provider. Prevent one
        // Telegram/Slack user from being linked to multiple local users.
        $existing = UserExternalIdentity::where('provider', $request->input('provider'))
            ->where('external_id', $request->input('externalId'))
            ->first();

        if ($existing && $existing->user_id !== $user->id) {
            /** @var User $existingUser */
            $existingUser = $existing->user;

            return response()->json([
                'error' => "This {$request->input('provider')} ID is already linked to user: {$existingUser->name}",
            ], 409);
        }

        // updateOrCreate allows relinking the same provider identity to the
        // selected user after conflict checks pass.
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

        // Telegram inbound messages can create an ephemeral shadow user before
        // the admin links the real account. Merge that history into the chosen
        // user so old messages/approvals stay attached after linking.
        if ($request->input('provider') === 'telegram') {
            $shadowEmail = "telegram-{$request->input('externalId')}@external.opencompany";
            $shadow = User::where('email', $shadowEmail)
                ->where('id', '!=', $user->id)
                ->first();

            if ($shadow) {
                // Delete duplicate memberships first to avoid unique-key
                // conflicts when moving the shadow user's remaining rows.
                $existingChannelIds = ChannelMember::where('user_id', $user->id)->pluck('channel_id');
                ChannelMember::where('user_id', $shadow->id)
                    ->whereIn('channel_id', $existingChannelIds)
                    ->delete();

                // Reassign durable history before deleting the shadow identity.
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
    public function unlinkExternalUser(string $identityId): JsonResponse
    {
        $identity = UserExternalIdentity::findOrFail($identityId);
        $identity->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get all external identity links (optionally filtered by provider).
     */
    public function externalIdentities(Request $request): JsonResponse
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
    public function enabledModels(): JsonResponse
    {
        return response()->json(app(ModelCatalog::class)->enabledModels(workspace()->id));
    }

    /**
     * Get all available AI providers with their models for settings dropdowns.
     *
     * Returns both integration-based providers (Z.AI, Codex) and prism-config
     * providers (Anthropic, OpenAI, etc.) with configuration status.
     */
    public function allProviders(): JsonResponse
    {
        return response()->json(app(ModelCatalog::class)->providerOptions(workspace()->id));
    }

    /**
     * Get available embedding models with provider configuration status.
     */
    public function embeddingModels(): JsonResponse
    {
        return response()->json(app(ModelRuntimeCatalog::class)->embeddingModels());
    }

    /**
     * Get available reranking models with provider configuration status.
     */
    public function rerankingModels(): JsonResponse
    {
        return response()->json(app(ModelRuntimeCatalog::class)->rerankingModels());
    }

    /**
     * Get Ollama connection status and locally available models.
     */
    public function ollamaModelStatus(): JsonResponse
    {
        return response()->json(app(ModelRuntimeCatalog::class)->ollamaStatus());
    }

    /**
     * Pull (download) an Ollama model.
     */
    public function ollamaPullModel(Request $request): StreamedResponse
    {
        $request->validate(['model' => 'required|string|max:200']);

        return app(ModelRuntimeCatalog::class)->pullOllamaModel((string) $request->input('model'));
    }

    /**
     * Fetch available models from the provider API and store in database.
     */
    public function fetchModels(string $id): JsonResponse
    {
        try {
            $models = app(ModelRuntimeCatalog::class)->fetchProviderModels($id);

            if (empty($models)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No models returned from provider',
                ], 400);
            }

            // Persist fetched model names as a workspace override so dropdowns
            // can show the provider's current model set without refetching on
            // every page load.
            $setting = app(IntegrationAccountResolver::class)->findSetting($id);
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
                'error' => 'Failed to fetch models: '.$e->getMessage(),
            ], 400);
        }
    }

    // ─── Multi-Account Endpoints ────────────────────────────────

    /**
     * List all accounts for an integration.
     */
    public function listAccounts(string $id): JsonResponse
    {
        $settings = IntegrationSetting::forWorkspace()
            ->where('integration_id', $id)
            ->orderByDesc('is_default')
            ->orderBy('account_alias')
            ->get();

        $accounts = $settings->map(fn (IntegrationSetting $s) => [
            'alias' => $s->account_alias,
            'is_default' => $s->is_default,
            'enabled' => $s->enabled,
            'configured' => $s->hasValidConfig(),
        ]);

        return response()->json(['accounts' => $accounts]);
    }

    /**
     * Create a new account for an integration.
     */
    public function createAccount(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'alias' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9_]+$/'],
            'config' => ['nullable', 'array'],
        ]);

        $alias = $request->input('alias');

        $exists = IntegrationSetting::forWorkspace()
            ->where('integration_id', $id)
            ->where('account_alias', $alias)
            ->exists();

        if ($exists) {
            return response()->json(['error' => "Account '{$alias}' already exists."], 422);
        }

        $hasOthers = IntegrationSetting::forWorkspace()
            ->where('integration_id', $id)
            ->exists();

        $setting = IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'integration_id' => $id,
            'account_alias' => $alias,
            'config' => $request->input('config', []),
            'enabled' => true,
            'is_default' => ! $hasOthers,
        ]);

        return response()->json([
            'alias' => $setting->account_alias,
            'is_default' => $setting->is_default,
        ], 201);
    }

    /**
     * Update an account's config.
     */
    public function updateAccount(Request $request, string $id, string $alias): JsonResponse
    {
        $setting = IntegrationSetting::forWorkspace()
            ->where('integration_id', $id)
            ->where('account_alias', $alias)
            ->first();

        if (! $setting) {
            return response()->json(['error' => 'Account not found.'], 404);
        }

        $config = $setting->config ?? [];
        foreach ($request->input('config', []) as $key => $value) {
            if (is_string($value) && str_contains($value, '*')) {
                // Masked secrets from the account modal mean "leave existing
                // encrypted value unchanged."
                continue; // Skip masked values
            }
            $config[$key] = $value;
        }
        $setting->config = $config;
        $setting->save();

        return response()->json(['success' => true]);
    }

    /**
     * Delete an account.
     */
    public function deleteAccount(string $id, string $alias): JsonResponse
    {
        if ($alias === '') {
            return response()->json(['error' => 'Cannot delete the default account.'], 422);
        }

        $setting = IntegrationSetting::forWorkspace()
            ->where('integration_id', $id)
            ->where('account_alias', $alias)
            ->first();

        if (! $setting) {
            return response()->json(['error' => 'Account not found.'], 404);
        }

        $wasDefault = $setting->is_default;
        $setting->delete();

        // If the default account is deleted, prefer the legacy unaliased account
        // as replacement so existing tools without account aliases keep working.
        if ($wasDefault) {
            $replacement = IntegrationSetting::forWorkspace()
                ->where('integration_id', $id)
                ->where('account_alias', '')
                ->first()
                ?: IntegrationSetting::forWorkspace()
                    ->where('integration_id', $id)
                    ->orderBy('account_alias')
                    ->first();

            $replacement?->update(['is_default' => true]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Set an account as the default.
     */
    public function setDefaultAccount(string $id, string $alias): JsonResponse
    {
        $setting = IntegrationSetting::forWorkspace()
            ->where('integration_id', $id)
            ->where('account_alias', $alias)
            ->first();

        if (! $setting) {
            return response()->json(['error' => 'Account not found.'], 404);
        }

        // Exactly one default account is allowed per integration/workspace.
        IntegrationSetting::forWorkspace()
            ->where('integration_id', $id)
            ->update(['is_default' => false]);

        $setting->update(['is_default' => true]);

        return response()->json(['success' => true]);
    }
}
