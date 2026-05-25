<?php

namespace App\Console\Commands;

use App\Domain\Chat\Telegram\Application\TelegramSetupService;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AgentAvatarService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramSync extends Command
{
    protected $signature = 'telegram:sync {--skip-metadata : Skip command/menu/profile metadata sync and only refresh the profile photo}';

    protected $description = 'Sync Telegram bot commands and profile photo with the configured agent avatar';

    public function handle(TelegramService $telegram, TelegramSetupService $setup, AgentAvatarService $avatarService): int
    {
        if (! app()->bound('currentWorkspace')) {
            $workspace = Workspace::first();
            if ($workspace) {
                app()->instance('currentWorkspace', $workspace);
            }
        }

        if (! $telegram->isConfigured()) {
            $this->components->warn('Telegram not configured, skipping.');

            return self::SUCCESS;
        }

        $setting = IntegrationSetting::forWorkspace()->where('integration_id', 'telegram')->first();
        if (! $setting) {
            $this->components->warn('Telegram integration setting not found, skipping.');

            return self::SUCCESS;
        }

        if (! $this->option('skip-metadata')) {
            // Keep command/profile metadata in the app-owned Telegram setup
            // layer so CLI repair uses the same scoped command menu as web
            // setup.
            try {
                $result = $setup->syncBotCommandsAndProfile($setting);
                $this->components->info('Bot commands and profile metadata synced: '.implode(', ', $result['commands']['scopes'] ?? []));
            } catch (\Throwable $e) {
                $this->components->error("Failed to sync commands/profile metadata: {$e->getMessage()}");
            }
        }

        $agentId = $setting?->getConfigValue('default_agent_id');

        if (! $agentId) {
            $this->components->warn('No default agent configured, skipping avatar sync.');

            return self::SUCCESS;
        }

        $agent = User::find($agentId);
        if (! $agent) {
            $this->components->warn("Agent {$agentId} not found, skipping avatar sync.");

            return self::SUCCESS;
        }

        $jpegPath = $avatarService->toJpeg($agent);
        if (! $jpegPath) {
            $this->components->warn("Could not convert avatar for {$agent->name} to JPEG.");

            return self::SUCCESS;
        }

        try {
            $telegram->setMyProfilePhoto($jpegPath);
            $this->components->info("Bot profile photo synced with {$agent->name}'s avatar.");
        } catch (\Throwable $e) {
            $this->components->error("Failed to set profile photo: {$e->getMessage()}");
        } finally {
            @unlink($jpegPath);
        }

        return self::SUCCESS;
    }
}
