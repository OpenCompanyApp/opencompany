<?php

namespace App\Domain\Chat\Telegram\Application;

use Illuminate\Support\Str;

/**
 * Extracts downloadable media descriptors from an inbound Telegram message.
 *
 * Owns: deciding which Telegram file IDs in a message are worth capturing
 * (largest photo size, document/voice/audio/video/video note/animation/sticker,
 * live photos, and paid media), their fallback MIME types, the sanitized
 * Workspace File name for each capture, and the optional AI enrichment
 * metadata contract (`telegram_enrichment`) stored alongside the file.
 *
 * Does NOT own: calling Telegram getFile/downloadFile, writing Workspace Files,
 * creating message attachments, dispatching EnrichTelegramMediaJob, or any
 * workspace scoping. Those side effects stay in TelegramWebhookPipeline's
 * captureMedia(), which remains best-effort. This class performs no I/O; the
 * only ambient input is the `telegram.media_enrichment_enabled` config flag.
 *
 * Extracted verbatim from TelegramWebhookPipeline. Agent breadcrumb: the
 * descriptor `kind` values are persisted as `telegram_kind` metadata and read
 * back by the enrichment worker, so renaming kinds is a data contract change.
 */
class TelegramMediaItemExtractor
{
    /**
     * Describe optional AI enrichment work for captured Telegram media.
     *
     * The capture path is deliberately synchronous and bounded to file download
     * plus durable metadata. Transcription and visual description can be picked
     * up by a later worker from this metadata without re-reading Telegram update
     * payloads, and `file_unique_id` gives image/sticker descriptions a stable
     * cache key across chats and repeated sends.
     *
     * @param  array{kind: string, file_id: string, file_unique_id: ?string, file_name: ?string, mime_type: ?string}  $item
     * @return array{status: string, type: string|null, cache_key: string|null, reason?: string}
     */
    public function telegramMediaEnrichmentMetadata(array $item): array
    {
        if (! (bool) config('telegram.media_enrichment_enabled', true)) {
            return [
                'status' => 'disabled',
                'type' => null,
                'cache_key' => null,
                'reason' => 'telegram_media_enrichment_disabled',
            ];
        }

        $kind = $item['kind'];
        $type = match (true) {
            in_array($kind, ['voice', 'audio'], true) => 'transcription',
            in_array($kind, ['photo', 'sticker', 'live_photo', 'paid_media_photo', 'paid_media_live_photo'], true) => 'description',
            default => null,
        };

        if ($type === null) {
            return [
                'status' => 'not_applicable',
                'type' => null,
                'cache_key' => null,
            ];
        }

        $uniqueId = $item['file_unique_id'] ?: $item['file_id'];

        return [
            'status' => 'queued',
            'type' => $type,
            'cache_key' => "telegram_media_{$type}:{$uniqueId}",
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return list<array{kind: string, file_id: string, file_unique_id: ?string, file_name: ?string, mime_type: ?string}>
     */
    public function telegramMediaItems(array $message): array
    {
        $items = [];

        if (isset($message['photo']) && is_array($message['photo']) && $message['photo'] !== []) {
            $photo = collect($message['photo'])->sortBy('file_size')->last();
            if (is_array($photo) && isset($photo['file_id'])) {
                $items[] = [
                    'kind' => 'photo',
                    'file_id' => (string) $photo['file_id'],
                    'file_unique_id' => isset($photo['file_unique_id']) ? (string) $photo['file_unique_id'] : null,
                    'file_name' => null,
                    'mime_type' => 'image/jpeg',
                ];
            }
        }

        foreach ([
            'document' => 'application/octet-stream',
            'voice' => 'audio/ogg',
            'audio' => 'audio/mpeg',
            'video' => 'video/mp4',
            'video_note' => 'video/mp4',
            'animation' => 'video/mp4',
            'sticker' => 'image/webp',
        ] as $key => $fallbackMimeType) {
            $media = is_array($message[$key] ?? null) ? $message[$key] : null;
            if (! $media || ! isset($media['file_id'])) {
                continue;
            }

            $items[] = [
                'kind' => $key,
                'file_id' => (string) $media['file_id'],
                'file_unique_id' => isset($media['file_unique_id']) ? (string) $media['file_unique_id'] : null,
                'file_name' => isset($media['file_name']) ? (string) $media['file_name'] : null,
                'mime_type' => isset($media['mime_type']) ? (string) $media['mime_type'] : $fallbackMimeType,
            ];
        }

        if (isset($message['live_photo']) && is_array($message['live_photo'])) {
            foreach ($this->livePhotoMediaItems($message['live_photo']) as $item) {
                $items[] = $item;
            }
        }

        if (isset($message['paid_media']) && is_array($message['paid_media'])) {
            foreach ($this->paidMediaItems($message['paid_media']) as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $livePhoto
     * @return list<array{kind: string, file_id: string, file_unique_id: ?string, file_name: ?string, mime_type: ?string}>
     */
    private function livePhotoMediaItems(array $livePhoto): array
    {
        $items = [];

        $photo = is_array($livePhoto['photo'] ?? null) ? collect($livePhoto['photo'])->sortBy('file_size')->last() : null;
        if (is_array($photo) && isset($photo['file_id'])) {
            $items[] = [
                'kind' => 'live_photo',
                'file_id' => (string) $photo['file_id'],
                'file_unique_id' => isset($photo['file_unique_id']) ? (string) $photo['file_unique_id'] : null,
                'file_name' => null,
                'mime_type' => 'image/jpeg',
            ];
        }

        $video = is_array($livePhoto['video'] ?? null) ? $livePhoto['video'] : null;
        if ($video && isset($video['file_id'])) {
            $items[] = [
                'kind' => 'live_photo_video',
                'file_id' => (string) $video['file_id'],
                'file_unique_id' => isset($video['file_unique_id']) ? (string) $video['file_unique_id'] : null,
                'file_name' => isset($video['file_name']) ? (string) $video['file_name'] : null,
                'mime_type' => isset($video['mime_type']) ? (string) $video['mime_type'] : 'video/mp4',
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $paidMedia
     * @return list<array{kind: string, file_id: string, file_unique_id: ?string, file_name: ?string, mime_type: ?string}>
     */
    private function paidMediaItems(array $paidMedia): array
    {
        $items = [];
        $mediaItems = is_array($paidMedia['paid_media'] ?? null) ? $paidMedia['paid_media'] : [];

        foreach ($mediaItems as $index => $media) {
            if (! is_array($media)) {
                continue;
            }

            if (($media['type'] ?? null) === 'photo' && isset($media['photo']) && is_array($media['photo'])) {
                $photo = collect($media['photo'])->sortBy('file_size')->last();
                if (is_array($photo) && isset($photo['file_id'])) {
                    $items[] = [
                        'kind' => 'paid_media_photo',
                        'file_id' => (string) $photo['file_id'],
                        'file_unique_id' => isset($photo['file_unique_id']) ? (string) $photo['file_unique_id'] : null,
                        'file_name' => 'paid-media-'.$index.'.jpg',
                        'mime_type' => 'image/jpeg',
                    ];
                }
            }

            if (($media['type'] ?? null) === 'video' && isset($media['video']) && is_array($media['video']) && isset($media['video']['file_id'])) {
                $video = $media['video'];
                $items[] = [
                    'kind' => 'paid_media_video',
                    'file_id' => (string) $video['file_id'],
                    'file_unique_id' => isset($video['file_unique_id']) ? (string) $video['file_unique_id'] : null,
                    'file_name' => isset($video['file_name']) ? (string) $video['file_name'] : 'paid-media-'.$index.'.mp4',
                    'mime_type' => isset($video['mime_type']) ? (string) $video['mime_type'] : 'video/mp4',
                ];
            }

            if (($media['type'] ?? null) === 'live_photo' && isset($media['live_photo']) && is_array($media['live_photo'])) {
                foreach ($this->livePhotoMediaItems($media['live_photo']) as $item) {
                    $items[] = [
                        ...$item,
                        'kind' => 'paid_media_'.$item['kind'],
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array{kind: string, file_id: string, file_unique_id: ?string, file_name: ?string, mime_type: ?string}  $item
     */
    public function telegramMediaFilename(array $message, array $item): string
    {
        $messageId = (string) ($message['message_id'] ?? Str::random(8));
        $name = $item['file_name'] ?: match ($item['kind']) {
            'photo' => 'photo.jpg',
            'voice' => 'voice.ogg',
            'audio' => 'audio.mp3',
            'video' => 'video.mp4',
            'video_note' => 'video-note.mp4',
            'animation' => 'animation.mp4',
            'sticker' => 'sticker.webp',
            'live_photo' => 'live-photo.jpg',
            'live_photo_video' => 'live-photo-video.mp4',
            'paid_media_photo' => 'paid-media.jpg',
            'paid_media_video' => 'paid-media.mp4',
            'paid_media_live_photo' => 'paid-media-live-photo.jpg',
            'paid_media_live_photo_video' => 'paid-media-live-photo-video.mp4',
            default => 'file.bin',
        };

        $name = trim((string) preg_replace('/[^\w.\- ]+/', '_', basename($name)));

        return "telegram-{$messageId}-".($name !== '' ? $name : 'file.bin');
    }
}
