<?php

namespace App\Listeners;

use App\Domain\Chat\Telegram\Application\TelegramOutboundSync;
use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessagePinned;
use App\Events\MessageReactionAdded;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\WorkspaceFile;
use App\Services\Chat\ChatManager;
use App\Services\Chat\ChatProviderCapabilities;
use App\Services\FileSystemService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenCompany\Chatogrator\Contracts\Adapter;
use OpenCompany\Chatogrator\Messages\FileUpload;
use OpenCompany\Chatogrator\Messages\PostableMessage;

/**
 * Mirrors OpenCompany message events back to configured external chat channels.
 *
 * Outbound sync is deliberately conservative: it uses a per-message lock, avoids
 * retries after external calls, and skips messages that originated from external
 * adapters. Those guards prevent duplicate posts when providers accept a message
 * but the local worker crashes before recording the external message ID.
 */
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

        // Prevent duplicate external sends when the same Laravel event is
        // dispatched twice or multiple workers race the same message.
        $lock = Cache::lock("sync_chat:{$message->id}", 300);
        if (! $lock->get()) {
            return;
        }

        // Refresh after the lock to catch a send completed by another worker
        // before this job acquired the lock.
        $message->refresh();
        if ($message->external_message_id) {
            return;
        }

        // Echo prevention: inbound external messages are already visible on the
        // provider, and internal system/delegation prompts should not leak back.
        if ($this->isFromExternal($message)) {
            return;
        }

        $channel = $message->channel;
        if (! $this->isExternalChannel($channel)) {
            return;
        }

        if ($channel->external_provider === 'telegram') {
            app(TelegramOutboundSync::class)->sendMessage($message);

            return;
        }

        if (! app(ChatProviderCapabilities::class)->supports($channel->external_provider, 'send_messages')) {
            return;
        }

        $adapter = $this->getAdapter($channel);
        if (! $adapter) {
            return;
        }

        $threadId = $this->resolveThreadId($channel);
        $authorName = $message->author->name ?? 'System';

        try {
            // Send files before text so generated charts/documents are present
            // in the external thread even if the text body later needs links
            // stripped to avoid duplicate file previews.
            $sentImagePaths = $this->sendInlineImages($adapter, $threadId, $message->content);
            $this->sendAttachmentImages($adapter, $threadId, $message, $sentImagePaths);

            // Strip links for files we already uploaded. External chat clients
            // often preview bare links, which would duplicate the uploaded file.
            $textContent = $message->content;
            foreach ($sentImagePaths as $sentUrl) {
                $escaped = preg_quote($sentUrl, '/');
                // Strip markdown links wrapping the URL
                $textContent = preg_replace('/!?\[[^\]]*\]\('.$escaped.'\)\s*/', '', $textContent);
                // Strip bare URL occurrences
                $textContent = preg_replace('/'.$escaped.'/', '', $textContent);
            }

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
        if ($channel->external_provider === 'telegram') {
            app(TelegramOutboundSync::class)->editMessage($message);

            return;
        }

        if (! app(ChatProviderCapabilities::class)->supports($channel->external_provider, 'edit_messages')) {
            return;
        }

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
        if ($channel->external_provider === 'telegram') {
            app(TelegramOutboundSync::class)->deleteMessage($message);

            return;
        }

        if (! app(ChatProviderCapabilities::class)->supports($channel->external_provider, 'delete_messages')) {
            return;
        }

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
        if ($channel->external_provider === 'telegram') {
            app(TelegramOutboundSync::class)->pinMessage($message);

            return;
        }

        if (! app(ChatProviderCapabilities::class)->supports($channel->external_provider, 'pin_messages')) {
            return;
        }

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
        if ($channel->external_provider === 'telegram') {
            app(TelegramOutboundSync::class)->addReaction($message, $event->emoji);

            return;
        }

        if (! app(ChatProviderCapabilities::class)->supports($channel->external_provider, 'reactions')) {
            return;
        }

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

    private function getAdapter($channel): ?Adapter
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
        // Prefer the stored adapter thread ID. Reconstructed IDs are a fallback
        // for older channels created before thread_id was persisted.
        if (! empty($channel->external_config['thread_id'])) {
            return $channel->external_config['thread_id'];
        }

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

        // source stores the origin adapter for inbound messages. The additional
        // internal sources are model/runtime prompts that should never be posted
        // as user-visible chat replies.
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
     * @return string[] URLs that were sent
     */
    private function sendInlineImages($adapter, string $threadId, string $content): array
    {
        if (! app(ChatProviderCapabilities::class)->supports($adapter->name(), 'files')) {
            return [];
        }

        $sentUrls = [];

        // Workspace-file links require authenticated app storage access, so the
        // listener reads bytes locally and uploads the file to the external
        // adapter instead of sending an inaccessible URL.
        if (preg_match_all('#/api/files/([0-9a-f-]+)/download#', $content, $uuidMatches)) {
            $fileIds = array_unique($uuidMatches[1]);

            foreach ($fileIds as $fileId) {
                $url = "/api/files/{$fileId}/download";

                try {
                    $file = WorkspaceFile::find($fileId);
                    if (! $file) {
                        continue;
                    }

                    $bytes = app(FileSystemService::class)->readFileContents($file);
                    if (! $bytes) {
                        continue;
                    }

                    $tmpPath = sys_get_temp_dir().'/'.$file->name;
                    file_put_contents($tmpPath, $bytes);

                    $isDocument = $file->mime_type === 'application/pdf'
                        || ! str_starts_with($file->mime_type ?? '', 'image/');

                    $adapter->sendFile($threadId, FileUpload::fromPath(
                        $tmpPath,
                        filename: $file->name,
                        forceDocument: $isDocument,
                    ));
                    @unlink($tmpPath);
                    $sentUrls[] = $url;
                } catch (\Throwable $e) {
                    Log::error('SyncToChat: failed to send workspace file', [
                        'url' => $url, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Public storage paths are legacy generated artifacts. They can be sent
        // directly from disk, but keep them separate from workspace-file links
        // because they do not go through FileSystemService permissions.
        if (preg_match_all('/!\[([^\]]*)\]\((\/storage\/[^)]+)\)/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $alt = $match[1];
                $url = $match[2];

                try {
                    $relativePath = str_replace('/storage/', '', $url);
                    $filePath = storage_path('app/public/'.$relativePath);

                    if (! file_exists($filePath)) {
                        continue;
                    }

                    $isDocument = str_contains($url, '/storage/mermaid/')
                        || str_contains($url, '/storage/plantuml/')
                        || str_contains($url, '/storage/typst/');

                    $adapter->sendFile($threadId, FileUpload::fromPath(
                        $filePath,
                        caption: $alt ?: null,
                        forceDocument: $isDocument,
                    ));
                    $sentUrls[] = $url;
                } catch (\Throwable $e) {
                    Log::error('SyncToChat: failed to send storage file', [
                        'url' => $url, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $sentUrls;
    }

    /**
     * Send message attachment images, skipping ones already sent inline.
     *
     * @param  string[]  $alreadySentUrls
     */
    private function sendAttachmentImages($adapter, string $threadId, Message $message, array $alreadySentUrls): void
    {
        if (! app(ChatProviderCapabilities::class)->supports($adapter->name(), 'files')) {
            return;
        }

        foreach ($message->attachments as $attachment) {
            $mime = $attachment->mime_type ?? '';
            if (! str_starts_with($mime, 'image/') && $mime !== 'application/pdf') {
                continue;
            }

            $url = $attachment->url ?? '';

            if (in_array($url, $alreadySentUrls, true)) {
                continue;
            }

            try {
                // Workspace attachments use the same upload path as inline
                // links; skip if an inline reference already uploaded it.
                if (preg_match('#^/api/files/([^/]+)/download#', $url, $fileMatch)) {
                    $file = WorkspaceFile::find($fileMatch[1]);
                    if (! $file) {
                        continue;
                    }

                    $bytes = app(FileSystemService::class)->readFileContents($file);
                    if (! $bytes) {
                        continue;
                    }

                    $tmpPath = sys_get_temp_dir().'/'.$file->name;
                    file_put_contents($tmpPath, $bytes);

                    $isDocument = $file->mime_type === 'application/pdf'
                        || ! str_starts_with($file->mime_type ?? '', 'image/');

                    $adapter->sendFile($threadId, FileUpload::fromPath(
                        $tmpPath,
                        filename: $file->name,
                        forceDocument: $isDocument,
                    ));
                    @unlink($tmpPath);

                    continue;
                }

                // Legacy public storage attachments are sent from disk so
                // external users can view generated artifacts without local app
                // authentication.
                if (str_starts_with($url, '/storage/')) {
                    $relativePath = str_replace('/storage/', '', $url);
                    $filePath = storage_path('app/public/'.$relativePath);

                    if (! file_exists($filePath)) {
                        continue;
                    }

                    $isDocument = str_contains($url, '/storage/mermaid/')
                        || str_contains($url, '/storage/plantuml/')
                        || str_contains($url, '/storage/typst/');

                    $adapter->sendFile($threadId, FileUpload::fromPath(
                        $filePath,
                        filename: $attachment->original_name,
                        forceDocument: $isDocument,
                    ));
                }
            } catch (\Throwable $e) {
                Log::error('SyncToChat: failed to send attachment', [
                    'url' => $url, 'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
