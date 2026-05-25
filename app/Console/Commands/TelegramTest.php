<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\TelegramService;
use Illuminate\Console\Command;

/**
 * Operator helper for validating Telegram bot credentials.
 *
 * The command calls Telegram getMe with the first workspace context so setup
 * failures can be diagnosed without sending a chat message.
 */
class TelegramTest extends Command
{
    protected $signature = 'telegram:test';

    protected $description = 'Test the Telegram bot connection';

    public function handle(): int
    {
        $workspace = Workspace::first();
        if ($workspace) {
            // TelegramService resolves its token from workspace integration
            // settings; bind the first workspace for this CLI-only check.
            app()->instance('currentWorkspace', $workspace);
        }

        $telegram = app(TelegramService::class);

        if (! $telegram->isConfigured()) {
            $this->error('Telegram bot token is not configured. Set it in Integrations first.');

            return self::FAILURE;
        }

        $this->info('Testing Telegram bot connection...');

        try {
            $result = $telegram->getMe();

            $this->info('Connection successful!');
            $this->table(['Key', 'Value'], [
                ['Bot ID', $result['id'] ?? 'N/A'],
                ['Bot Name', $result['first_name'] ?? 'N/A'],
                ['Username', '@'.($result['username'] ?? 'N/A')],
                ['Can Join Groups', ($result['can_join_groups'] ?? false) ? 'Yes' : 'No'],
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Connection failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
