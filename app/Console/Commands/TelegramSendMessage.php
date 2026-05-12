<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\TelegramService;
use Illuminate\Console\Command;

/**
 * Operator helper for sending a raw Telegram message through configured bot settings.
 *
 * This bypasses OpenCompany chat records and is intended for webhook/setup
 * diagnostics, not normal agent-to-channel delivery.
 */
class TelegramSendMessage extends Command
{
    protected $signature = 'telegram:send {chat_id} {message}';

    protected $description = 'Send a message to a Telegram chat';

    public function handle(): int
    {
        $workspace = Workspace::first();
        if ($workspace) {
            // TelegramService reads integration settings through workspace
            // context even though this command is not tenant-routed by HTTP.
            app()->instance('currentWorkspace', $workspace);
        }

        $telegram = app(TelegramService::class);

        if (! $telegram->isConfigured()) {
            $this->error('Telegram bot token is not configured.');

            return self::FAILURE;
        }

        $chatId = $this->argument('chat_id');
        $message = $this->argument('message');

        $this->info("Sending message to chat {$chatId}...");

        try {
            $telegram->sendMessage($chatId, $message);
            $this->info('Message sent successfully!');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to send message: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
