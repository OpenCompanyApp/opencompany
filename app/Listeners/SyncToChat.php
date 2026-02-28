<?php

namespace App\Listeners;

use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessagePinned;
use App\Events\MessageReactionAdded;
use App\Events\MessageSent;
use App\Models\Message;
use App\Services\Chat\ChatManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenCompany\Chatogrator\Messages\FileUpload;
use OpenCompany\Chatogrator\Messages\PostableMessage;

class SyncToChat implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Don't retry — external API may have received the message even if the job failed.
     */
    public int $tries = 1;

    /**
     * External API calls should complete well within 30 seconds.
     */
    public int $timeout = 30;

    public function handleMessageSent(MessageSent $event): void
    {
        $message = $event->message;

        // Prevent duplicate external sends (e.g. if event fires twice)
        $lock = Cache::lock("sync_chat:{$message->id}", 300);
        if (! $lock->get()) {
            return;
        }

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
            $sentImagePaths = $this->sendInlineImages($adapter, $threadId, $message->content);
            $this->sendAttachmentImages($adapter, $threadId, $message, $sentImagePaths);

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

        try {
            $adapter->pinMessage(
                $this->resolveThreadId($channel),
                $message->external_message_id
            );
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
            || $message->source === 'delegation_result'
            || $message->source === 'automation_prompt';
    }

    private function canSync(Message $message): bool
    {
        return $message->external_message_id
            && $this->isExternalChannel($message->channel);
    }

    /**
     * Extract inline image URLs from markdown and send via adapter.
     *
     * @return string[] File paths that were sent
     */
    private function sendInlineImages($adapter, string $threadId, string $content): array
    {
        $sentPaths = [];

        if (! preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $content, $matches, PREG_SET_ORDER)) {
            return $sentPaths;
        }

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

                $adapter->sendFile($threadId, FileUpload::fromPath(
                    $filePath,
                    caption: $alt ?: null,
                    forceDocument: $isDocument,
                ));
                $sentPaths[] = $filePath;
            } catch (\Throwable $e) {
                Log::error('SyncToChat: failed to send image', [
                    'path' => $filePath, 'error' => $e->getMessage(),
                ]);
            }
        }

        return $sentPaths;
    }

    /**
     * Send message attachment images, skipping ones already sent inline.
     *
     * @param  string[]  $alreadySentPaths
     */
    private function sendAttachmentImages($adapter, string $threadId, Message $message, array $alreadySentPaths): void
    {
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

                $adapter->sendFile($threadId, FileUpload::fromPath(
                    $filePath,
                    filename: $attachment->original_name,
                    forceDocument: $isDocument,
                ));
            } catch (\Throwable $e) {
                Log::error('SyncToChat: failed to send attachment', [
                    'path' => $filePath, 'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
