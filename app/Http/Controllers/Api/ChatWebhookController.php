<?php

namespace App\Http\Controllers\Api;

use App\Domain\Chat\Telegram\Application\TelegramWebhookPipeline;
use App\Models\IntegrationSetting;
use App\Models\Workspace;
use App\Services\Chat\ChatManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Generic Chatogrator webhook entrypoint.
 *
 * Webhooks arrive before normal workspace middleware can know the tenant, so
 * this controller authenticates the adapter payload/header first, resolves the
 * workspace from integration settings, binds currentWorkspace, then hands off to
 * Chatogrator's adapter-specific verification and dispatch.
 */
class ChatWebhookController
{
    public function __invoke(Request $request, string $adapter): Response
    {
        $workspace = $this->resolveWorkspace($adapter, $request);

        if (! $workspace) {
            // Do not reveal which adapter or secret failed. External providers
            // only need a non-success response for unauthorized payloads.
            return new Response('Unauthorized', 401);
        }

        app()->instance('currentWorkspace', $workspace);

        try {
            if ($adapter === 'telegram') {
                return app(TelegramWebhookPipeline::class)->handle($request, $workspace);
            }

            $chat = app(ChatManager::class)->forWorkspace($workspace);

            return $chat->handleWebhook($adapter, $request);
        } catch (\Throwable $e) {
            Log::error("Chat webhook error [{$adapter}]", [
                'error' => $e->getMessage(),
                'workspace' => $workspace->id,
            ]);

            // Return 200 after logging so providers do not retry indefinitely on
            // app-side processing errors that are already recorded locally.
            return new Response('', 200);
        }
    }

    private function resolveWorkspace(string $adapter, Request $request): ?Workspace
    {
        return match ($adapter) {
            'telegram' => $this->resolveFromTelegramSecret($request),
            'slack' => $this->resolveFromSlackSignature($request),
            'discord' => $this->resolveFromDiscordAppId($request),
            'teams' => $this->resolveFromTeamsAppId($request),
            'google_chat', 'github_chat', 'linear_chat' => $this->resolveFromGenericSecret($adapter, $request),
            default => $this->resolveFromGenericSecret($adapter, $request),
        };
    }

    /**
     * Telegram sends X-Telegram-Bot-Api-Secret-Token header.
     * Match against all workspace telegram configs.
     */
    private function resolveFromTelegramSecret(Request $request): ?Workspace
    {
        $secret = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if (! $secret) {
            return null;
        }

        // Secrets are encrypted inside config, so compare after loading enabled
        // settings rather than querying the raw encrypted column.
        $setting = IntegrationSetting::where('integration_id', 'telegram')
            ->where('enabled', true)
            ->get()
            ->first(fn ($s) => $s->getConfigValue('webhook_secret') === $secret);

        return $setting ? Workspace::find($setting->workspace_id) : null;
    }

    /**
     * Slack workspace binding requires both the declared team_id and a valid
     * request signature. A team_id alone is tenant-controlled request content
     * and must never decide the OpenCompany workspace by itself.
     */
    private function resolveFromSlackSignature(Request $request): ?Workspace
    {
        $body = json_decode($request->getContent(), true);
        $teamId = $body['team_id'] ?? null;

        // Slack interactive callbacks can arrive as form-encoded payload JSON
        // instead of raw JSON.
        if (! $teamId) {
            $payloadStr = $request->input('payload');
            if ($payloadStr) {
                $payload = json_decode($payloadStr, true);
                $teamId = $payload['team']['id'] ?? $payload['team_id'] ?? null;
            }
        }

        if (! $teamId) {
            return null;
        }

        $setting = IntegrationSetting::where('integration_id', 'slack')
            ->where('enabled', true)
            ->get()
            ->first(fn ($s) => $s->getConfigValue('team_id') === $teamId && $this->validSlackSignature($request, $s));

        return $setting ? Workspace::find($setting->workspace_id) : null;
    }

    /**
     * Discord payloads must match the configured application_id and carry a
     * provider proof. Native interactions use Ed25519 request signatures; relay
     * events may instead use the configured gateway/webhook secret header.
     */
    private function resolveFromDiscordAppId(Request $request): ?Workspace
    {
        $body = json_decode($request->getContent(), true);
        $applicationId = $body['application_id'] ?? null;

        if (! $applicationId) {
            return null;
        }

        $setting = IntegrationSetting::where('integration_id', 'discord')
            ->where('enabled', true)
            ->get()
            ->first(fn ($s) => $s->getConfigValue('application_id') === $applicationId && $this->validDiscordProof($request, $s));

        return $setting ? Workspace::find($setting->workspace_id) : null;
    }

    /**
     * Teams sends the bot's app_id as recipient.id in the activity payload.
     */
    private function resolveFromTeamsAppId(Request $request): ?Workspace
    {
        $body = json_decode($request->getContent(), true);
        $recipientId = $body['recipient']['id'] ?? null;

        if (! $recipientId) {
            return null;
        }

        $setting = IntegrationSetting::where('integration_id', 'teams')
            ->where('enabled', true)
            ->get()
            ->first(fn ($s) => $s->getConfigValue('app_id') === $recipientId && $this->validSecretHeader($request, $s, ['webhook_secret', 'app_password']));

        return $setting ? Workspace::find($setting->workspace_id) : null;
    }

    /**
     * Generic fallback: match a webhook secret header or query param.
     */
    private function resolveFromGenericSecret(string $adapter, Request $request): ?Workspace
    {
        $secret = $request->header('X-Webhook-Secret')
            ?? $request->query('secret');

        if (! $secret) {
            return null;
        }

        // Generic adapters use a shared webhook_secret convention until a
        // provider needs stronger first-class signature verification.
        $setting = IntegrationSetting::where('integration_id', $adapter)
            ->where('enabled', true)
            ->get()
            ->first(fn ($s) => $s->getConfigValue('webhook_secret') === $secret);

        return $setting ? Workspace::find($setting->workspace_id) : null;
    }

    private function validSlackSignature(Request $request, IntegrationSetting $setting): bool
    {
        $secret = $setting->getConfigValue('signing_secret');
        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (! is_string($secret) || $secret === '' || ! is_string($timestamp) || ! is_string($signature)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $base = 'v0:'.$timestamp.':'.$request->getContent();
        $expected = 'v0='.hash_hmac('sha256', $base, $secret);

        return hash_equals($expected, $signature);
    }

    private function validDiscordProof(Request $request, IntegrationSetting $setting): bool
    {
        if ($this->validSecretHeader($request, $setting, ['gateway_secret', 'webhook_secret'])) {
            return true;
        }

        $publicKey = $setting->getConfigValue('public_key');
        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');

        if (! function_exists('sodium_crypto_sign_verify_detached')
            || ! is_string($publicKey)
            || ! is_string($signature)
            || ! is_string($timestamp)
        ) {
            return false;
        }

        $publicKeyBytes = @hex2bin($publicKey);
        $signatureBytes = @hex2bin($signature);
        if ($publicKeyBytes === false || $signatureBytes === false) {
            return false;
        }

        return sodium_crypto_sign_verify_detached(
            $signatureBytes,
            $timestamp.$request->getContent(),
            $publicKeyBytes,
        );
    }

    /**
     * @param  list<string>  $configKeys
     */
    private function validSecretHeader(Request $request, IntegrationSetting $setting, array $configKeys): bool
    {
        $secret = $request->header('X-Webhook-Secret') ?? $request->query('secret');
        if (! is_string($secret) || $secret === '') {
            return false;
        }

        foreach ($configKeys as $key) {
            $candidate = $setting->getConfigValue($key);
            if (is_string($candidate) && $candidate !== '' && hash_equals($candidate, $secret)) {
                return true;
            }
        }

        return false;
    }
}
