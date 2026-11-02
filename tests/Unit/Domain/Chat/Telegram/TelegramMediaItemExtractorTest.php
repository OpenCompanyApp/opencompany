<?php

namespace Tests\Unit\Domain\Chat\Telegram;

use App\Domain\Chat\Telegram\Application\TelegramMediaItemExtractor;
use Tests\TestCase;

/**
 * Protects which Telegram files are captured and how they are described.
 *
 * TelegramMediaItemExtractor was extracted verbatim from
 * TelegramWebhookPipeline. The descriptor `kind` values, fallback MIME types,
 * sanitized filenames, and enrichment metadata are persisted on Workspace Files
 * and read back by the enrichment worker, so these scenarios pin that data
 * contract. No database or Telegram API is touched.
 */
class TelegramMediaItemExtractorTest extends TestCase
{
    private TelegramMediaItemExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new TelegramMediaItemExtractor;
    }

    // Only the largest photo size is captured, plus every standalone media key.
    public function test_picks_largest_photo_and_standalone_media_with_fallback_mime(): void
    {
        $items = $this->extractor->telegramMediaItems([
            'photo' => [
                ['file_id' => 'small', 'file_size' => 10],
                ['file_id' => 'large', 'file_unique_id' => 'u-large', 'file_size' => 900],
                ['file_id' => 'mid', 'file_size' => 100],
            ],
            'voice' => ['file_id' => 'voice-1'],
            'document' => ['file_id' => 'doc-1', 'file_name' => 'report.pdf', 'mime_type' => 'application/pdf'],
            'sticker' => ['no_file_id' => true],
        ]);

        $this->assertSame([
            ['kind' => 'photo', 'file_id' => 'large', 'file_unique_id' => 'u-large', 'file_name' => null, 'mime_type' => 'image/jpeg'],
            ['kind' => 'document', 'file_id' => 'doc-1', 'file_unique_id' => null, 'file_name' => 'report.pdf', 'mime_type' => 'application/pdf'],
            ['kind' => 'voice', 'file_id' => 'voice-1', 'file_unique_id' => null, 'file_name' => null, 'mime_type' => 'audio/ogg'],
        ], $items);
    }

    // Paid media prefixes nested kinds and names items by index.
    public function test_paid_media_items_are_prefixed_and_indexed(): void
    {
        $items = $this->extractor->telegramMediaItems([
            'paid_media' => ['paid_media' => [
                ['type' => 'photo', 'photo' => [['file_id' => 'pp', 'file_size' => 1]]],
                ['type' => 'video', 'video' => ['file_id' => 'pv']],
                ['type' => 'live_photo', 'live_photo' => ['photo' => [['file_id' => 'lp']], 'video' => ['file_id' => 'lv']]],
                'not-an-array',
            ]],
        ]);

        $this->assertSame(
            ['paid_media_photo', 'paid_media_video', 'paid_media_live_photo', 'paid_media_live_photo_video'],
            array_column($items, 'kind'),
        );
        $this->assertSame('paid-media-0.jpg', $items[0]['file_name']);
        $this->assertSame('paid-media-1.mp4', $items[1]['file_name']);
    }

    public function test_message_without_media_yields_no_items(): void
    {
        $this->assertSame([], $this->extractor->telegramMediaItems(['text' => 'hi']));
    }

    // Filenames are namespaced by Telegram message id and stripped of unsafe characters.
    public function test_filename_uses_kind_default_and_sanitizes_names(): void
    {
        $item = ['kind' => 'voice', 'file_id' => 'f', 'file_unique_id' => null, 'file_name' => null, 'mime_type' => 'audio/ogg'];
        $this->assertSame('telegram-77-voice.ogg', $this->extractor->telegramMediaFilename(['message_id' => 77], $item));

        $item['file_name'] = '../../etc/pa$$wd?.txt';
        // Path segments are dropped and each run of unsafe characters collapses to one underscore.
        $this->assertSame('telegram-77-pa_wd_.txt', $this->extractor->telegramMediaFilename(['message_id' => 77], $item));
    }

    // Enrichment type depends on kind; the cache key prefers file_unique_id.
    public function test_enrichment_metadata_by_kind_and_config(): void
    {
        config(['telegram.media_enrichment_enabled' => true]);

        $voice = ['kind' => 'voice', 'file_id' => 'f1', 'file_unique_id' => 'u1', 'file_name' => null, 'mime_type' => null];
        $this->assertSame(
            ['status' => 'queued', 'type' => 'transcription', 'cache_key' => 'telegram_media_transcription:u1'],
            $this->extractor->telegramMediaEnrichmentMetadata($voice),
        );

        $sticker = ['kind' => 'sticker', 'file_id' => 'f2', 'file_unique_id' => null, 'file_name' => null, 'mime_type' => null];
        $this->assertSame('telegram_media_description:f2', $this->extractor->telegramMediaEnrichmentMetadata($sticker)['cache_key']);

        $document = ['kind' => 'document', 'file_id' => 'f3', 'file_unique_id' => null, 'file_name' => null, 'mime_type' => null];
        $this->assertSame('not_applicable', $this->extractor->telegramMediaEnrichmentMetadata($document)['status']);

        // The kill switch wins regardless of kind.
        config(['telegram.media_enrichment_enabled' => false]);
        $this->assertSame([
            'status' => 'disabled',
            'type' => null,
            'cache_key' => null,
            'reason' => 'telegram_media_enrichment_disabled',
        ], $this->extractor->telegramMediaEnrichmentMetadata($voice));
    }
}
