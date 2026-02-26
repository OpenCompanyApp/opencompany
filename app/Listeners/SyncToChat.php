<?php

namespace App\Listeners;

use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessagePinned;
use App\Events\MessageReactionAdded;
use App\Events\MessageSent;
use App\Models\IntegrationSetting;
use App\Models\Message;
use App\Services\Chat\ChatManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use OpenCompany\Chatogrator\Messages\PostableMessage;

class SyncToChat implements ShouldQueue
{
    use InteractsWithQueue;

    public function handleMessageSent(MessageSent $event): void
    {
        $message = $event->message;

        // Skip messages originating from external platforms (echo prevention)
        if ($this->isFromExternal($message)) {
            return;
        }

        $channel = $message->channel;
        if (! $this->isExternalChannel($channel)) {
            return;
        }

        $adapter = $this->getAdapter($channel);
        if (! $adapter) {
            return;
        }

        $threadId = $this->resolveThreadId($channel);
        $authorName = $message->author->name ?? 'System';

        try {
            // Send chart/attachment images first
            $sentImagePaths = $this->sendInlineImages($adapter, $threadId, $message->content, $channel);
            $this->sendAttachmentImages($adapter, $threadId, $message, $sentImagePaths, $channel);

            // Strip inline images from text content
            $textContent = preg_replace('/!\[[^\]]*\]\([^)]+\)\s*/', '', $message->content);

            if (trim($textContent) !== '') {
                $content = "**{$authorName}**\n{$textContent}";
                $postable = PostableMessage::markdown($content);

                $sent = $adapter->postMessage($threadId, $postable);

                if ($sent->id) {
                    $message->update(['external_message_id' => $sent->id]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('SyncToChat: failed to send message', [
                'channel_id' => $channel->id,
                'adapter' => $channel->external_provider,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleMessageEdited(MessageEdited $event): void
    {
        $message = $event->message;

        if (! $this->canSync($message)) {
            return;
        }

        $channel = $message->channel;
        $adapter = $this->getAdapter($channel);
        if (! $adapter) {
            return;
        }

        try {
            $adapter->editMessage(
                $this->resolveThreadId($channel),
                $message->external_message_id,
                PostableMessage::markdown($message->content)
            );
        } catch (\Throwable $e) {
            Log::error('SyncToChat: failed to edit message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleMessageDeleted(MessageDeleted $event): void
    {
        $message = $event->message;

        if (! $this->canSync($message)) {
            return;
        }

        $channel = $message->channel;
        $adapter = $this->getAdapter($channel);
        if (! $adapter) {
            return;
        }

        try {
            $adapter->deleteMessage(
                $this->resolveThreadId($channel),
                $message->external_message_id
            );
        } catch (\Throwable $e) {
            Log::error('SyncToChat: failed to delete message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleMessagePinned(MessagePinned $event): void
    {
        $message = $event->message;

        if (! $this->canSync($message)) {
            return;
        }

        $channel = $message->channel;
        $adapter = $this->getAdapter($channel);
        if (! $adapter) {
            return;
        }

        // Chatogrator doesn't have a pinMessage interface method.
        // Use Telegram API directly for pin support.
        try {
            if ($channel->external_provider === 'telegram') {
                $setting = IntegrationSetting::where('integration_id', 'telegram')
                    ->where('workspace_id', $channel->workspace_id)
                    ->first();
                $botToken = $setting?->getConfigValue('api_key');
                if ($botToken) {
                    \Illuminate\Support\Facades\Http::timeout(10)->post(
                        "https://api.telegram.org/bot{$botToken}/pinChatMessage",
                        [
                            'chat_id' => $channel->external_id,
                            'message_id' => (int) $message->external_message_id,
                            'disable_notification' => true,
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::error('SyncToChat: failed to pin message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleReactionAdded(MessageReactionAdded $event): void
    {
        $message = $event->message;

        if (! $this->canSync($message)) {
            return;
        }

        $channel = $message->channel;
        $adapter = $this->getAdapter($channel);
        if (! $adapter) {
            return;
        }

        try {
            $adapter->addReaction(
                $this->resolveThreadId($channel),
                $message->external_message_id,
                $event->emoji
            );
        } catch (\Throwable $e) {
            Log::error('SyncToChat: failed to add reaction', [
                'message_id' => $message->id,
                'emoji' => $event->emoji,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function getAdapter($channel): ?\OpenCompany\Chatogrator\Contracts\Adapter
    {
        try {
            $chat = app(ChatManager::class)->forWorkspace($channel->workspace_id);

            return $chat->getAdapter($channel->external_provider);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveThreadId($channel): string
    {
        // Use stored thread_id from external_config if available
        if (! empty($channel->external_config['thread_id'])) {
            return $channel->external_config['thread_id'];
        }

        // Reconstruct from provider + external_id
        return match ($channel->external_provider) {
            'telegram' => "telegram:{$channel->external_id}",
            'slack' => "slack:{$channel->external_id}:",
            'discord' => "discord:{$channel->external_id}:",
            default => "{$channel->external_provider}:{$channel->external_id}",
        };
    }

    private function isExternalChannel($channel): bool
    {
        return $channel
            && $channel->type === 'external'
            && $channel->external_provider
            && $channel->external_id;
    }

    private function isFromExternal(Message $message): bool
    {
        $channel = $message->channel;

        return $message->source === $channel?->external_provider
            || $message->source === 'delegation_result';
    }

    private function canSync(Message $message): bool
    {
        return $message->external_message_id
            && $this->isExternalChannel($message->channel);
    }

    /**
     * Extract inline image URLs from markdown and send via adapter.
     * Uses Telegram-specific API calls for photo/document uploads since the
     * Chatogrator adapter expects file paths, not content buffers.
     *
     * @return string[] File paths that were sent
     */
    private function sendInlineImages($adapter, string $threadId, string $content, $channel): array
    {
        $sentPaths = [];

        if (! preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $content, $matches, PREG_SET_ORDER)) {
            return $sentPaths;
        }

        $decoded = $adapter->decodeThreadId($threadId);
        $chatId = $this->extractChatId($channel->external_provider, $decoded);

        foreach ($matches as $match) {
            $alt = $match[1];
            $url = $match[2];

            if (! str_starts_with($url, '/storage/')) {
                continue;
            }

            $relativePath = str_replace('/storage/', '', $url);
            $filePath = storage_path('app/public/'.$relativePath);

            if (! file_exists($filePath)) {
                continue;
            }

            try {
                $isDocument = str_contains($url, '/storage/mermaid/')
                    || str_contains($url, '/storage/plantuml/')
                    || str_contains($url, '/storage/typst/');

                $this->sendFileViaAdapter($adapter, $channel->external_provider, $chatId, $filePath, $alt, $isDocument);
                $sentPaths[] = $filePath;
            } catch (\Throwable $e) {
                // Retry as document if photo fails
                if (! $isDocument && str_contains($e->getMessage(), 'PHOTO_INVALID_DIMENSIONS')) {
                    try {
                        $this->sendFileViaAdapter($adapter, $channel->external_provider, $chatId, $filePath, $alt, true);
                        $sentPaths[] = $filePath;
                    } catch (\Throwable $docErr) {
                        Log::error('SyncToChat: failed to send image as document', [
                            'path' => $filePath, 'error' => $docErr->getMessage(),
                        ]);
                    }
                } else {
                    Log::error('SyncToChat: failed to send image', [
                        'path' => $filePath, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $sentPaths;
    }

    /**
     * Send message attachment images, skipping ones already sent inline.
     *
     * @param  string[]  $alreadySentPaths
     */
    private function sendAttachmentImages($adapter, string $threadId, Message $message, array $alreadySentPaths, $channel): void
    {
        $decoded = $adapter->decodeThreadId($threadId);
        $chatId = $this->extractChatId($channel->external_provider, $decoded);

        foreach ($message->attachments as $attachment) {
            $mime = $attachment->mime_type ?? '';
            if (! str_starts_with($mime, 'image/') && $mime !== 'application/pdf') {
                continue;
            }

            $url = $attachment->url ?? '';
            if (! str_starts_with($url, '/storage/')) {
                continue;
            }

            $relativePath = str_replace('/storage/', '', $url);
            $filePath = storage_path('app/public/'.$relativePath);

            if (in_array($filePath, $alreadySentPaths, true) || ! file_exists($filePath)) {
                continue;
            }

            try {
                $isDocument = str_contains($url, '/storage/mermaid/')
                    || str_contains($url, '/storage/plantuml/')
                    || str_contains($url, '/storage/typst/');

                $this->sendFileViaAdapter($adapter, $channel->external_provider, $chatId, $filePath, $attachment->original_name, $isDocument);
            } catch (\Throwable $e) {
                if (! $isDocument && str_contains($e->getMessage(), 'PHOTO_INVALID_DIMENSIONS')) {
                    try {
                        $this->sendFileViaAdapter($adapter, $channel->external_provider, $chatId, $filePath, $attachment->original_name, true);
                    } catch (\Throwable) {
                        // Silently fail on retry
                    }
                } else {
                    Log::error('SyncToChat: failed to send attachment', [
                        'path' => $filePath, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function extractChatId(string $provider, array $decoded): string
    {
        return match ($provider) {
            'telegram' => $decoded['chatId'] ?? '',
            'slack' => $decoded['channel'] ?? '',
            'discord' => $decoded['channelId'] ?? $decoded['channel'] ?? '',
            default => $decoded['chatId'] ?? $decoded['channel'] ?? '',
        };
    }

    /**
     * Send a file using adapter-specific API calls.
     * For Telegram, uses sendPhoto/sendDocument via HTTP. Other adapters are not yet supported.
     */
    private function sendFileViaAdapter(\OpenCompany\Chatogrator\Contracts\Adapter $adapter, string $provider, string $chatId, string $filePath, ?string $caption, bool $asDocument): void
    {
        if ($provider === 'telegram') {
            $setting = IntegrationSetting::where('integration_id', 'telegram')
                ->where('workspace_id', app('currentWorkspace')->id ?? null)
                ->first();

            $botToken = $setting?->getConfigValue('api_key');
            if (! $botToken) {
                return;
            }

            $method = $asDocument ? 'sendDocument' : 'sendPhoto';
            $fileKey = $asDocument ? 'document' : 'photo';
            $url = "https://api.telegram.org/bot{$botToken}/{$method}";

            $params = ['chat_id' => $chatId];
            if ($caption) {
                $params['caption'] = substr($caption, 0, 1024);
            }

            \Illuminate\Support\Facades\Http::timeout(30)
                ->attach($fileKey, file_get_contents($filePath), basename($filePath))
                ->post($url, $params);
        }
    }
}
