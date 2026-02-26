<?php

namespace App\Http\Controllers\Api;

use App\Models\IntegrationSetting;
use App\Models\Workspace;
use App\Services\Chat\ChatManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ChatWebhookController
{
    public function __invoke(Request $request, string $adapter): Response
    {
        $workspace = $this->resolveWorkspace($adapter, $request);

        if (! $workspace) {
            return new Response('Unauthorized', 401);
        }

        app()->instance('currentWorkspace', $workspace);

        try {
            $chat = app(ChatManager::class)->forWorkspace($workspace);

            return $chat->handleWebhook($adapter, $request);
        } catch (\Throwable $e) {
            Log::error("Chat webhook error [{$adapter}]", [
                'error' => $e->getMessage(),
                'workspace' => $workspace->id,
            ]);

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

        $setting = IntegrationSetting::where('integration_id', 'telegram')
            ->where('enabled', true)
            ->get()
            ->first(fn ($s) => $s->getConfigValue('webhook_secret') === $secret);

        return $setting ? Workspace::find($setting->workspace_id) : null;
    }

    /**
     * Slack sends team_id in the JSON body or form-encoded payload.
     */
    private function resolveFromSlackSignature(Request $request): ?Workspace
    {
        $body = json_decode($request->getContent(), true);
        $teamId = $body['team_id'] ?? null;

        // Form-encoded interactive payloads
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
            ->first(fn ($s) => $s->getConfigValue('team_id') === $teamId);

        return $setting ? Workspace::find($setting->workspace_id) : null;
    }

    /**
     * Discord identifies by application_id in the payload.
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
            ->first(fn ($s) => $s->getConfigValue('application_id') === $applicationId);

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
            ->first(fn ($s) => $s->getConfigValue('app_id') === $recipientId);

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

        $setting = IntegrationSetting::where('integration_id', $adapter)
            ->where('enabled', true)
            ->get()
            ->first(fn ($s) => $s->getConfigValue('webhook_secret') === $secret);

        return $setting ? Workspace::find($setting->workspace_id) : null;
    }
}
