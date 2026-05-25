<?php

namespace App\Http\Controllers\Api;

use App\Domain\Chat\Telegram\Application\TelegramSetupService;
use App\Domain\Integrations\Application\ManageIntegrationSettings;
use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Models\TelegramConversation;
use App\Models\TelegramDelivery;
use App\Models\TelegramIntegrationProfile;
use App\Models\UserExternalIdentity;
use App\Services\Ai\ModelCatalog;
use App\Services\Ai\ModelRuntimeCatalog;
use App\Services\Integrations\IntegrationAccountResolver;
use App\Services\Integrations\IntegrationConfigResolver;
use App\Services\Integrations\IntegrationConnectionTester;
use App\Services\Integrations\IntegrationDirectory;
use App\Services\Integrations\IntegrationIdentity;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function __construct(private ManageIntegrationSettings $settings) {}

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
        $account = app(IntegrationAccountResolver::class)->accountFromRequest($request);
        [$payload, $status] = app(IntegrationConfigResolver::class)->update(
            $request,
            $id,
            $account,
        );

        if ($status === 200 && ($payload['configured'] ?? false)) {
            try {
                // First successful configuration can populate provider models.
                // Failure is non-fatal because the user can refresh models later.
                $setting = app(IntegrationAccountResolver::class)
                    ->findSetting($id, $account);
                $existingModels = $setting?->getConfigValue('models');
                if ($setting && empty($existingModels) && ($models = app(ModelRuntimeCatalog::class)->fetchProviderModels(IntegrationIdentity::rawId($id))) !== []) {
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
     * Get configuration for a static AI model provider.
     */
    public function showAiProviderConfig(Request $request, string $id): JsonResponse
    {
        $config = app(IntegrationConfigResolver::class)->showAiProvider(
            $id,
            app(IntegrationAccountResolver::class)->accountFromRequest($request),
        );

        if ($config === null) {
            return response()->json(['error' => 'AI provider not found'], 404);
        }

        return response()->json($config);
    }

    /**
     * Save configuration for a static AI model provider.
     */
    public function updateAiProviderConfig(Request $request, string $id): JsonResponse
    {
        [$payload, $status] = app(IntegrationConfigResolver::class)->updateAiProvider(
            $request,
            $id,
            app(IntegrationAccountResolver::class)->accountFromRequest($request),
        );

        return response()->json($payload, $status);
    }

    /**
     * Toggle an integration on or off (for integrations that don't need config).
     */
    public function toggle(Request $request, string $id): JsonResponse
    {
        $request->validate(['enabled' => 'required|boolean']);

        $setting = $this->settings->toggle($id, $request->boolean('enabled'));

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
     * Test a static AI model provider without falling through to a same-slug
     * package integration.
     */
    public function testAiProviderConnection(Request $request, string $id): JsonResponse
    {
        $result = app(IntegrationConnectionTester::class)->testAiProvider($request, $id);

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
        $id = IntegrationIdentity::rawId($id);

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

        try {
            return response()->json(app(TelegramSetupService::class)->setupWebhook($setting, $apiKey));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh the Telegram Bot API health snapshot without changing webhook
     * registration. This gives the web admin surface a safe "check now" action
     * that updates the persisted profile while keeping the raw token and webhook
     * secret out of the response.
     */
    public function telegramHealthCheck(): JsonResponse
    {
        $setting = $this->telegramSetting();

        try {
            $result = app(TelegramSetupService::class)->health($setting);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => Str::limit($e->getMessage(), 300),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'status' => $result['status'] ?? null,
            'bot' => $result['bot'] ?? null,
            'webhook' => $result['webhook'] ?? null,
            'profile' => $this->serializeTelegramProfile($result['profile'] ?? null),
        ]);
    }

    /**
     * Republish Telegram-native command scopes, BotFather-visible profile
     * metadata, and the default menu button while leaving the current webhook URL
     * and secret untouched.
     */
    public function syncTelegramBotProfile(): JsonResponse
    {
        $setting = $this->telegramSetting();

        try {
            $result = app(TelegramSetupService::class)->syncBotCommandsAndProfile($setting);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => Str::limit($e->getMessage(), 300),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'commands' => $result['commands'] ?? null,
            'profile_sync' => $result['profile_sync'] ?? null,
            'profile' => $this->serializeTelegramProfile($result['profile'] ?? null),
        ]);
    }

    /**
     * Send a constrained test message to the signed-in admin's linked Telegram
     * account, or to an already-observed private Telegram conversation in this
     * workspace. This intentionally refuses arbitrary chat IDs so the setup UI
     * cannot be abused as a raw Bot API sender.
     */
    public function sendTelegramTestMessage(Request $request): JsonResponse
    {
        $setting = $this->telegramSetting();
        $target = $this->telegramTestTarget($request, $setting);

        if (! $target) {
            return response()->json([
                'success' => false,
                'error' => 'No linked Telegram identity or private Telegram conversation is available for a test send.',
            ], 422);
        }

        $text = 'OpenCompany Telegram test '.now()->format('Y-m-d H:i:s T');
        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $setting->workspace_id,
            'integration_setting_id' => $setting->id,
            'chat_id' => $target['chat_id'],
            'topic_id' => $target['topic_id'],
            'direct_messages_topic_id' => $target['direct_messages_topic_id'],
            'parse_mode' => 'HTML',
            'renderer_version' => 'telegram-admin-test-send:v1',
            'status' => 'pending',
            'request_payload' => [
                'method' => 'sendMessage',
                'text' => $text,
                'target' => $target['kind'],
            ],
        ]);

        try {
            $result = app(TelegramService::class)->sendMessage(
                $target['chat_id'],
                htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
                messageThreadId: TelegramService::messageThreadIdForTopic($target['topic_id']),
                directMessagesTopicId: TelegramService::directMessagesTopicId($target['direct_messages_topic_id']),
            );

            $delivery->update([
                'status' => 'sent',
                'telegram_message_id' => isset($result['message_id']) ? (string) $result['message_id'] : null,
                'response_payload' => $result,
                'attempts' => 1,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => 'failed',
                'provider_error_code' => $e::class,
                'provider_error_message' => Str::limit($e->getMessage(), 2000),
                'attempts' => 1,
            ]);

            return response()->json([
                'success' => false,
                'error' => Str::limit($e->getMessage(), 300),
                'delivery' => $this->serializeTelegramDelivery($delivery->fresh()),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'target' => $target['label'],
            'delivery' => $this->serializeTelegramDelivery($delivery->fresh()),
        ]);
    }

    /**
     * Rotate the webhook proof secret and re-register the live Telegram webhook.
     * The raw secret is deliberately not returned; admins only see the stable
     * fingerprint and current webhook/profile state.
     */
    public function rotateTelegramWebhookSecret(): JsonResponse
    {
        $setting = $this->telegramSetting();

        try {
            $result = app(TelegramSetupService::class)->rotateWebhookSecret($setting);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => Str::limit($e->getMessage(), 300),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'rotated' => true,
            'secretFingerprint' => $result['secretFingerprint'] ?? null,
            'webhookUrl' => $result['webhookUrl'] ?? null,
            'profile' => $this->serializeTelegramProfile($result['profile'] ?? null),
        ]);
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

        $result = $this->settings->linkExternalIdentity([
            'userId' => $request->input('userId'),
            'provider' => $request->input('provider'),
            'externalId' => $request->input('externalId'),
            'displayName' => $request->input('displayName'),
        ]);

        if (isset($result['conflict'])) {
            return response()->json([
                'error' => $result['conflict'],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'identity' => $result['identity'],
            'user' => $result['user'],
        ]);
    }

    /**
     * Unlink an external identity from a user.
     */
    public function unlinkExternalUser(string $identityId): JsonResponse
    {
        $this->settings->unlinkExternalIdentity($identityId);

        return response()->json(['success' => true]);
    }

    /**
     * Get all external identity links (optionally filtered by provider).
     */
    public function externalIdentities(Request $request): JsonResponse
    {
        return response()->json($this->settings->externalIdentities($request->input('provider')));
    }

    private function telegramSetting(): IntegrationSetting
    {
        return IntegrationSetting::forWorkspace()
            ->where('integration_id', 'telegram')
            ->where('enabled', true)
            ->firstOrFail();
    }

    /**
     * @return array{kind: string, label: string, chat_id: string, topic_id: ?string, direct_messages_topic_id: ?string}|null
     */
    private function telegramTestTarget(Request $request, IntegrationSetting $setting): ?array
    {
        $user = $request->user();

        if ($user) {
            $identity = UserExternalIdentity::query()
                ->where('provider', 'telegram')
                ->where('user_id', $user->id)
                ->first();

            if ($identity) {
                $conversation = TelegramConversation::where('workspace_id', $setting->workspace_id)
                    ->where('integration_setting_id', $setting->id)
                    ->where('chat_type', 'private')
                    ->where('chat_id', $identity->external_id)
                    ->first();

                return [
                    'kind' => 'linked_identity',
                    'label' => $identity->display_name ?: 'linked Telegram identity',
                    'chat_id' => $identity->external_id,
                    'topic_id' => $conversation?->topic_id,
                    'direct_messages_topic_id' => $conversation?->direct_messages_topic_id,
                ];
            }
        }

        $conversation = TelegramConversation::where('workspace_id', $setting->workspace_id)
            ->where('integration_setting_id', $setting->id)
            ->where('chat_type', 'private')
            ->latest('last_seen_at')
            ->latest()
            ->first();

        if (! $conversation) {
            return null;
        }

        return [
            'kind' => 'recent_private_conversation',
            'label' => $conversation->title ?: 'recent private conversation',
            'chat_id' => $conversation->chat_id,
            'topic_id' => $conversation->topic_id,
            'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeTelegramDelivery(?TelegramDelivery $delivery): ?array
    {
        if (! $delivery) {
            return null;
        }

        return [
            'id' => $delivery->id,
            'chat_id' => $delivery->chat_id,
            'telegram_message_id' => $delivery->telegram_message_id,
            'renderer_version' => $delivery->renderer_version,
            'status' => $delivery->status,
            'provider_error_code' => $delivery->provider_error_code,
            'provider_error_message' => $delivery->provider_error_message,
            'attempts' => $delivery->attempts,
            'sent_at' => $delivery->sent_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeTelegramProfile(mixed $profile): ?array
    {
        if (! $profile instanceof TelegramIntegrationProfile) {
            return null;
        }

        return [
            'id' => $profile->id,
            'bot_id' => $profile->bot_id,
            'bot_username' => $profile->bot_username,
            'webhook_url' => $profile->webhook_url,
            'allowed_updates' => $profile->allowed_updates,
            'command_sync_status' => $profile->command_sync_status,
            'profile_sync_status' => $profile->profile_sync_status,
            'health_status' => $profile->health_status,
            'last_health_error' => $profile->last_health_error,
            'pending_update_count' => $profile->pending_update_count,
            'last_health_checked_at' => $profile->last_health_checked_at?->toIso8601String(),
            'webhook_secret_fingerprint' => $profile->webhook_secret_fingerprint,
            'capabilities' => $profile->capabilities,
        ];
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
     * Returns both integration-based providers (Z.AI, Codex) and config-backed
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
        $id = IntegrationIdentity::rawId($id);

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
        return response()->json(['accounts' => $this->settings->listAccounts($id)]);
    }

    /**
     * Create a new account for an integration.
     */
    public function createAccount(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'alias' => ['required', 'string'],
            'config' => ['nullable', 'array'],
        ]);

        $alias = app(IntegrationAccountResolver::class)->normalizeAlias($request->input('alias'));

        $exists = IntegrationSetting::forWorkspace()
            ->where('integration_id', IntegrationIdentity::rawId($id))
            ->where('account_alias', $alias)
            ->exists();

        if ($exists) {
            return response()->json(['error' => "Account '{$alias}' already exists."], 422);
        }

        $setting = $this->settings->createAccount($id, $alias, $request->input('config', []));

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
        if (! $this->settings->updateAccountConfig($id, $alias, $request->input('config', []))) {
            return response()->json(['error' => 'Account not found.'], 404);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Delete an account.
     */
    public function deleteAccount(string $id, string $alias): JsonResponse
    {
        $deleted = $this->settings->deleteAccount($id, $alias);

        if ($deleted === null) {
            return response()->json(['error' => 'Cannot delete the default account.'], 422);
        }
        if ($deleted === false) {
            return response()->json(['error' => 'Account not found.'], 404);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Set an account as the default.
     */
    public function setDefaultAccount(string $id, string $alias): JsonResponse
    {
        if (! $this->settings->setDefaultAccount($id, $alias)) {
            return response()->json(['error' => 'Account not found.'], 404);
        }

        return response()->json(['success' => true]);
    }
}
