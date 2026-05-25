<?php

namespace App\Agents\Tools\Workspace;

use App\Domain\Chat\Telegram\Application\TelegramSetupService;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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

            if (! $apiKey) {
                return 'No Telegram bot token configured. Set it first with update_integration_config.';
            }

            $result = app(TelegramSetupService::class)->setupWebhook($setting, $apiKey);

            return 'Telegram webhook set up: '.$result['webhookUrl'];
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
