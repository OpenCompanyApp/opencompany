<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramTest extends Command
{
    protected $signature = 'telegram:test';
    protected $description = 'Test the Telegram bot connection';

    public function handle(): int
    {
        $telegram = app(TelegramService::class);

        if (!$telegram->isConfigured()) {
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
                ['Username', '@' . ($result['username'] ?? 'N/A')],
                ['Can Join Groups', ($result['can_join_groups'] ?? false) ? 'Yes' : 'No'],
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Connection failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
