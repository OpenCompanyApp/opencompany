<?php

namespace App\Listeners;

use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessagePinned;
use App\Events\MessageReactionAdded;
use App\Events\MessageSent;
use App\Models\Message;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SyncToTelegram implements ShouldQueue
{
    use InteractsWithQueue;

    public function handleMessageSent(MessageSent $event): void
    {
        $message = $event->message;

        if ($message->source === 'telegram' || $message->source === 'delegation_result') {
            return;
        }

        $channel = $message->channel;
        if (!$this->isTelegramChannel($channel)) {
            return;
        }

        $chatId = $channel->external_id;
        $telegram = app(TelegramService::class);
        if (!$telegram->isConfigured()) {
            return;
        }

        $authorName = htmlspecialchars($message->author->name ?? 'System', ENT_QUOTES, 'UTF-8');

        try {
            $sentPaths = $this->sendChartImages($telegram, $chatId, $message->content);
            $this->sendAttachmentImages($telegram, $chatId, $message, $sentPaths);

            $textContent = preg_replace('/!\[[^\]]*\]\([^)]+\)\s*/', '', $message->content);

            if (trim($textContent) !== '') {
                $content = TelegramService::markdownToTelegramHtml($textContent);
                $text = "<b>{$authorName}</b>\n{$content}";

                // Thread the reply on Telegram if replying to a message with an external ID
                $replyToExternalId = null;
                if ($message->reply_to_id) {
                    $replyToExternalId = Message::where('id', $message->reply_to_id)->value('external_message_id');
                }

                $result = $telegram->sendMessage($chatId, $text, null, $replyToExternalId ? (int) $replyToExternalId : null);

                if (!empty($result['message_id'])) {
                    $message->update(['external_message_id' => (string) $result['message_id']]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('SyncToTelegram: failed to send message', [
                'channel_id' => $channel->id,
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleMessageEdited(MessageEdited $event): void
    {
        $message = $event->message;

        if (!$this->canSync($message)) {
            return;
        }

        $channel = $message->channel;
        $telegram = app(TelegramService::class);

        try {
            $content = TelegramService::markdownToTelegramHtml($message->content);
            $telegram->editMessageText(
                $channel->external_id,
                (int) $message->external_message_id,
                $content
            );
        } catch (\Throwable $e) {
            Log::error('SyncToTelegram: failed to edit message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleMessageDeleted(MessageDeleted $event): void
    {
        $message = $event->message;

        if (!$this->canSync($message)) {
            return;
        }

        $channel = $message->channel;
        $telegram = app(TelegramService::class);

        try {
            $telegram->deleteMessage(
                $channel->external_id,
                (int) $message->external_message_id
            );
        } catch (\Throwable $e) {
            Log::error('SyncToTelegram: failed to delete message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleMessagePinned(MessagePinned $event): void
    {
        $message = $event->message;

        if (!$this->canSync($message)) {
            return;
        }

        $channel = $message->channel;
        $telegram = app(TelegramService::class);

        try {
            $telegram->pinChatMessage(
                $channel->external_id,
                (int) $message->external_message_id
            );
        } catch (\Throwable $e) {
            Log::error('SyncToTelegram: failed to pin message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleReactionAdded(MessageReactionAdded $event): void
    {
        $message = $event->message;

        if (!$this->canSync($message)) {
            return;
        }

        $channel = $message->channel;
        $telegram = app(TelegramService::class);

        try {
            $telegram->setMessageReaction(
                $channel->external_id,
                (int) $message->external_message_id,
                $event->emoji
            );
        } catch (\Throwable $e) {
            Log::error('SyncToTelegram: failed to set reaction', [
                'message_id' => $message->id,
                'emoji' => $event->emoji,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if a message can be synced to Telegram (has external ID and is in a Telegram channel).
     */
    private function canSync(Message $message): bool
    {
        if (!$message->external_message_id) {
            return false;
        }

        $channel = $message->channel;

        return $this->isTelegramChannel($channel) && app(TelegramService::class)->isConfigured();
    }

    private function isTelegramChannel(mixed $channel): bool
    {
        return $channel
            && $channel->type === 'external'
            && $channel->external_provider === 'telegram'
            && $channel->external_id;
    }

    /**
     * Extract chart image URLs from message content and send as Telegram photos.
     *
     * @return string[] File paths that were successfully sent
     */
    private function sendChartImages(TelegramService $telegram, string $chatId, string $content): array
    {
        $sentPaths = [];

        if (!preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $content, $matches, PREG_SET_ORDER)) {
            return $sentPaths;
        }

        foreach ($matches as $match) {
            $alt = $match[1];
            $url = $match[2];

            if (!str_starts_with($url, '/storage/')) {
                continue;
            }

            $relativePath = str_replace('/storage/', '', $url);
            $filePath = storage_path('app/public/' . $relativePath);

            if (!file_exists($filePath)) {
                Log::warning('Chart image not found for Telegram forwarding', ['path' => $filePath]);
                continue;
            }

            try {
                $isDocument = str_contains($url, '/storage/mermaid/') || str_contains($url, '/storage/plantuml/') || str_contains($url, '/storage/typst/');

                if ($isDocument) {
                    $telegram->sendDocument($chatId, $filePath, $alt ?: null);
                } else {
                    $telegram->sendPhoto($chatId, $filePath, $alt ?: null);
                }
                $sentPaths[] = $filePath;
            } catch (\Throwable $e) {
                if (!str_contains($url, '/storage/mermaid/') && !str_contains($url, '/storage/plantuml/') && !str_contains($url, '/storage/typst/') && str_contains($e->getMessage(), 'PHOTO_INVALID_DIMENSIONS')) {
                    try {
                        $telegram->sendDocument($chatId, $filePath, $alt ?: null);
                        $sentPaths[] = $filePath;
                    } catch (\Throwable $docError) {
                        Log::error('Failed to send chart as document to Telegram', [
                            'error' => $docError->getMessage(),
                            'path' => $filePath,
                        ]);
                    }
                } else {
                    Log::error('Failed to send chart image to Telegram', [
                        'error' => $e->getMessage(),
                        'path' => $filePath,
                    ]);
                }
            }
        }

        return $sentPaths;
    }

    /**
     * Send image attachments as Telegram photos, skipping any already sent via inline markdown.
     *
     * @param  array<int, string>  $alreadySentPaths
     */
    private function sendAttachmentImages(TelegramService $telegram, string $chatId, Message $message, array $alreadySentPaths): void
    {
        foreach ($message->attachments as $attachment) {
            $mime = $attachment->mime_type ?? '';
            if (!str_starts_with($mime, 'image/') && $mime !== 'application/pdf') {
                continue;
            }

            $url = $attachment->url ?? '';
            if (!str_starts_with($url, '/storage/')) {
                continue;
            }

            $relativePath = str_replace('/storage/', '', $url);
            $filePath = storage_path('app/public/' . $relativePath);

            if (in_array($filePath, $alreadySentPaths, true)) {
                continue;
            }

            if (!file_exists($filePath)) {
                Log::warning('Attachment image not found for Telegram forwarding', ['path' => $filePath]);
                continue;
            }

            try {
                $isDocument = str_contains($url, '/storage/mermaid/') || str_contains($url, '/storage/plantuml/') || str_contains($url, '/storage/typst/');

                if ($isDocument) {
                    $telegram->sendDocument($chatId, $filePath, $attachment->original_name);
                } else {
                    $telegram->sendPhoto($chatId, $filePath, $attachment->original_name);
                }
            } catch (\Throwable $e) {
                if (!str_contains($url, '/storage/mermaid/') && !str_contains($url, '/storage/plantuml/') && !str_contains($url, '/storage/typst/') && str_contains($e->getMessage(), 'PHOTO_INVALID_DIMENSIONS')) {
                    try {
                        $telegram->sendDocument($chatId, $filePath, $attachment->original_name);
                    } catch (\Throwable $docError) {
                        Log::error('Failed to send attachment as document to Telegram', [
                            'error' => $docError->getMessage(),
                            'path' => $filePath,
                        ]);
                    }
                } else {
                    Log::error('Failed to send attachment image to Telegram', [
                        'error' => $e->getMessage(),
                        'path' => $filePath,
                    ]);
                }
            }
        }
    }
}
