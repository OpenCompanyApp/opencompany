<?php

namespace App\Console\Commands;

use App\Models\IntegrationSetting;
use App\Models\Workspace;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {--remove : Remove the webhook instead of setting it}';
    protected $description = 'Register the Telegram webhook URL';

    public function handle(): int
    {
        $workspace = Workspace::first();
        if ($workspace) {
            app()->instance('currentWorkspace', $workspace);
        }

        $telegram = app(TelegramService::class);

        if (!$telegram->isConfigured()) {
            $this->error('Telegram bot token is not configured. Set it in Integrations first.');
            return self::FAILURE;
        }

        if ($this->option('remove')) {
            $result = $telegram->deleteWebhook();
            $this->info('Webhook removed successfully.');
            return self::SUCCESS;
        }

        // Ensure webhook_secret exists
        $setting = IntegrationSetting::forWorkspace()->where('integration_id', 'telegram')->first();
        $webhookSecret = $setting->getConfigValue('webhook_secret');

        if (!$webhookSecret) {
            $webhookSecret = Str::random(64);
            $setting->setConfigValue('webhook_secret', $webhookSecret);
            $setting->save();
            $this->info('Generated new webhook secret.');
        }

        $appUrl = config('app.url');
        $webhookUrl = rtrim($appUrl, '/') . '/api/webhooks/telegram';

        $this->info("Setting webhook to: {$webhookUrl}");

        try {
            $result = $telegram->setWebhook($webhookUrl, $webhookSecret);
            $this->info('Webhook set successfully!');
            $this->table(['Key', 'Value'], [
                ['URL', $webhookUrl],
                ['Secret', substr($webhookSecret, 0, 8) . '...'],
            ]);

            // Sync bot commands + profile photo
            $this->call('telegram:sync');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to set webhook: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
