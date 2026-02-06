<?php

namespace App\Console\Commands;

use App\Models\IntegrationSetting;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramSendMessage extends Command
{
    protected $signature = 'telegram:send {chat_id} {message}';
    protected $description = 'Send a message to a Telegram chat';

    public function handle(): int
    {
        $telegram = app(TelegramService::class);

        if (!$telegram->isConfigured()) {
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
