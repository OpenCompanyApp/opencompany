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

        // Skip delegation results (parent agent will respond naturally)
        if ($message->source === 'delegation_result') {
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

        $authorName = htmlspecialchars($message->author->name ?? 'System', ENT_QUOTES, 'UTF-8');

        try {
            // Extract and send chart images as photos (from inline markdown)
            $sentPaths = $this->sendChartImages($telegram, $chatId, $message->content);

            // Send image attachments (charts saved as structured data)
            $this->sendAttachmentImages($telegram, $chatId, $message, $sentPaths);

            // Strip image markdown from content before sending as text
            $textContent = preg_replace('/!\[[^\]]*\]\([^)]+\)\s*/', '', $message->content);

            if (trim($textContent) !== '') {
                $content = TelegramService::markdownToTelegramHtml($textContent);
                $text = "<b>{$authorName}</b>\n{$content}";
                $telegram->sendMessage($chatId, $text);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to forward message to Telegram', [
                'channel_id' => $channel->id,
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
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

            // Only handle local storage URLs (chart images)
            if (!str_starts_with($url, '/storage/')) {
                continue;
            }

            // Convert URL path to disk path
            $relativePath = str_replace('/storage/', '', $url);
            $filePath = storage_path('app/public/' . $relativePath);

            if (!file_exists($filePath)) {
                Log::warning('Chart image not found for Telegram forwarding', ['path' => $filePath]);
                continue;
            }

            try {
                // Send diagram images as documents to preserve resolution
                $isDocument = str_contains($url, '/storage/mermaid/') || str_contains($url, '/storage/plantuml/') || str_contains($url, '/storage/typst/');

                if ($isDocument) {
                    $telegram->sendDocument($chatId, $filePath, $alt ?: null);
                } else {
                    $telegram->sendPhoto($chatId, $filePath, $alt ?: null);
                }
                $sentPaths[] = $filePath;
            } catch (\Throwable $e) {
                // Fall back to sendDocument for oversized images (PHOTO_INVALID_DIMENSIONS)
                $isDocumentFallback = str_contains($url, '/storage/mermaid/') || str_contains($url, '/storage/plantuml/') || str_contains($url, '/storage/typst/');
                if (!$isDocumentFallback && str_contains($e->getMessage(), 'PHOTO_INVALID_DIMENSIONS')) {
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
     */
    private function sendAttachmentImages(TelegramService $telegram, string $chatId, $message, array $alreadySentPaths): void
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

            // Skip if already sent from inline markdown parsing
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
                $isDocumentFallback = str_contains($url, '/storage/mermaid/') || str_contains($url, '/storage/plantuml/') || str_contains($url, '/storage/typst/');
                if (!$isDocumentFallback && str_contains($e->getMessage(), 'PHOTO_INVALID_DIMENSIONS')) {
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
