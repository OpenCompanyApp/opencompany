<?php

namespace App\Agents\Tools\Workspace;

use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SetupIntegrationWebhook implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Set up a webhook for an integration (currently supports Telegram).';
    }

    public function handle(Request $request): string
    {
        try {
            $integrationId = $request['integrationId'] ?? null;
            if ($integrationId !== 'telegram') {
                return 'Webhooks are only supported for Telegram.';
            }

            $setting = IntegrationSetting::forWorkspace()->where('integration_id', 'telegram')->first();
            $apiKey = $setting?->getConfigValue('api_key');

            if (!$apiKey) {
                return 'No Telegram bot token configured. Set it first with update_integration_config.';
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

            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$apiKey}/setWebhook", [
                'url' => $webhookUrl,
                'secret_token' => $webhookSecret,
                'allowed_updates' => json_encode(['message', 'callback_query']),
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['ok'] ?? false)) {
                return "Telegram webhook set up: {$webhookUrl}";
            }

            return 'Failed to set webhook: ' . ($data['description'] ?? 'unknown error');
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'integrationId' => $schema
                ->string()
                ->description('Integration ID. Currently only "telegram" supports webhooks.')
                ->required(),
        ];
    }
}