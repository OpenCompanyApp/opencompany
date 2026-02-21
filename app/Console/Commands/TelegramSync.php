<?php

namespace App\Console\Commands;

use App\Models\IntegrationSetting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AgentAvatarService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramSync extends Command
{
    protected $signature = 'telegram:sync';
    protected $description = 'Sync Telegram bot commands and profile photo with the configured agent avatar';

    public function handle(TelegramService $telegram, AgentAvatarService $avatarService): int
    {
        if (!app()->bound('currentWorkspace')) {
            $workspace = Workspace::first();
            if ($workspace) {
                app()->instance('currentWorkspace', $workspace);
            }
        }

        if (!$telegram->isConfigured()) {
            $this->components->warn('Telegram not configured, skipping.');
            return self::SUCCESS;
        }

        // 1. Register bot commands
        try {
            $telegram->setMyCommands();
            $this->components->info('Bot commands registered.');
        } catch (\Throwable $e) {
            $this->components->error("Failed to register commands: {$e->getMessage()}");
        }

        // 2. Sync profile photo from agent avatar
        $setting = IntegrationSetting::forWorkspace()->where('integration_id', 'telegram')->first();
        $agentId = $setting?->getConfigValue('default_agent_id');

        if (!$agentId) {
            $this->components->warn('No default agent configured, skipping avatar sync.');
            return self::SUCCESS;
        }

        $agent = User::find($agentId);
        if (!$agent) {
            $this->components->warn("Agent {$agentId} not found, skipping avatar sync.");
            return self::SUCCESS;
        }

        $jpegPath = $avatarService->toJpeg($agent);
        if (!$jpegPath) {
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
