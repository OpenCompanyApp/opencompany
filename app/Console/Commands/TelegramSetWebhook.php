<?php

namespace App\Console\Commands;

use App\Domain\Chat\Telegram\Application\TelegramSetupService;
use App\Models\IntegrationSetting;
use App\Models\Workspace;
use App\Services\TelegramService;
use Illuminate\Console\Command;

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

        if (! $telegram->isConfigured()) {
            $this->error('Telegram bot token is not configured. Set it in Integrations first.');

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            $telegram->deleteWebhook();
            $this->info('Webhook removed successfully.');

            return self::SUCCESS;
        }

        $setting = IntegrationSetting::forWorkspace()->where('integration_id', 'telegram')->first();
        if (! $setting) {
            $this->error('Telegram integration setting not found.');

            return self::FAILURE;
        }

        $webhookUrl = rtrim((string) config('app.url'), '/').'/api/webhooks/chat/telegram';
        $this->info("Setting webhook to: {$webhookUrl}");

        try {
            $result = app(TelegramSetupService::class)->setupWebhook($setting);
            $profile = $result['profile'];

            $this->info('Webhook set successfully!');
            $this->table(['Key', 'Value'], [
                ['URL', $result['webhookUrl']],
                ['Bot', '@'.($result['bot']['username'] ?? 'unknown')],
                ['Health', $profile->health_status],
                ['Commands', $profile->command_sync_status],
                ['Profile', $profile->profile_sync_status],
            ]);

            $this->call('telegram:sync', ['--skip-metadata' => true]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to set webhook: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
