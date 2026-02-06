<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ForwardMessageToTelegram implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        // Echo loop prevention: skip if message originated from Telegram
        if ($message->source === 'telegram') {
            return;
        }

        // Check if channel is a Telegram external channel
        $channel = $message->channel;
        if (!$channel || $channel->type !== 'external' || $channel->external_provider !== 'telegram') {
            return;
        }

        $chatId = $channel->external_id;
        if (!$chatId) {
            return;
        }

        $telegram = app(TelegramService::class);
        if (!$telegram->isConfigured()) {
            return;
        }

        // Format message with author name, converting markdown to Telegram HTML
        $authorName = htmlspecialchars($message->author?->name ?? 'System', ENT_QUOTES, 'UTF-8');
        $content = TelegramService::markdownToTelegramHtml($message->content);
        $text = "<b>{$authorName}</b>\n{$content}";

        try {
            $telegram->sendMessage($chatId, $text);
        } catch (\Throwable $e) {
            Log::error('Failed to forward message to Telegram', [
                'channel_id' => $channel->id,
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
