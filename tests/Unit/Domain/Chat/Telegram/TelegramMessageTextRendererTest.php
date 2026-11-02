<?php

namespace Tests\Unit\Domain\Chat\Telegram;

use App\Domain\Chat\Telegram\Application\TelegramMessageTextRenderer;
use Tests\TestCase;

/**
 * Protects the agent-facing prompt text produced for inbound Telegram messages.
 *
 * TelegramMessageTextRenderer was extracted verbatim from
 * TelegramWebhookPipeline. These scenarios pin the exact strings (they are the
 * prompt contract agents and operators see) and the match-arm priority so a
 * later "cleanup" cannot silently change what lands in the message body.
 * No database is touched; the Laravel TestCase only provides the app timezone.
 */
class TelegramMessageTextRendererTest extends TestCase
{
    private TelegramMessageTextRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new TelegramMessageTextRenderer;
    }

    // Plain text wins, and modern metadata is appended after a blank line.
    public function test_text_message_appends_metadata_context_after_blank_line(): void
    {
        $text = $this->renderer->messageText([
            'text' => '  Hello agent  ',
            'effect_id' => '5104841245755180586',
            'is_paid_post' => true,
            'paid_star_count' => 25,
            'reply_to_checklist_task_id' => 3,
        ]);

        $this->assertSame(
            "Hello agent  \n\nTelegram effect: 5104841245755180586\nTelegram paid post\nTelegram paid stars: 25\nReply to checklist task: 3",
            $text,
        );
    }

    // Captions are treated like text when there is no text field.
    public function test_caption_is_used_when_text_is_missing(): void
    {
        $this->assertSame('Look at this', $this->renderer->messageText([
            'caption' => 'Look at this',
            'photo' => [['file_id' => 'p1']],
        ]));
    }

    // A media-only message without metadata falls back to a stable label.
    public function test_media_only_messages_get_kind_labels(): void
    {
        $this->assertSame('[Telegram photo]', $this->renderer->messageText(['photo' => [['file_id' => 'p']]]));
        $this->assertSame('[Telegram voice message]', $this->renderer->messageText(['voice' => ['file_id' => 'v']]));
        $this->assertSame('[Telegram video note]', $this->renderer->messageText(['video_note' => ['file_id' => 'n']]));
        $this->assertSame('[Telegram message]', $this->renderer->messageText([]));
    }

    // Match-arm order is a contract: live_photo must win over a co-present photo key.
    public function test_live_photo_takes_priority_over_photo(): void
    {
        $this->assertSame(
            "Telegram live photo\nWidth: 1080\nDuration: 3",
            $this->renderer->messageText([
                'live_photo' => ['width' => 1080, 'duration' => 3],
                'photo' => [['file_id' => 'p']],
            ]),
        );
    }

    // Structured payloads keep metadata context on the next line (no blank line).
    public function test_checklist_renders_bounded_tasks_and_single_newline_context(): void
    {
        $tasks = [];
        for ($i = 1; $i <= 25; $i++) {
            $tasks[] = ['text' => "Task {$i}", 'is_checked' => $i === 1];
        }

        $text = $this->renderer->messageText([
            'checklist' => ['title' => 'Launch', 'tasks' => $tasks],
            'effect_id' => 'fx',
        ]);

        $lines = explode("\n", $text);
        $this->assertSame('Telegram checklist', $lines[0]);
        $this->assertSame('Title: Launch', $lines[1]);
        $this->assertSame('[x] Task 1', $lines[2]);
        $this->assertSame('[ ] Task 20', $lines[21]);
        $this->assertSame('Telegram effect: fx', $lines[22]);
        $this->assertCount(23, $lines);
    }

    public function test_location_venue_contact_and_poll_rendering(): void
    {
        $this->assertSame(
            "Telegram location\nLatitude: 51.05\nLongitude: 3.72\nMap: https://maps.google.com/?q=51.05,3.72",
            $this->renderer->messageText(['location' => ['latitude' => 51.05, 'longitude' => 3.72]]),
        );

        // Invalid coordinates degrade to a placeholder, and a venue then omits the location block.
        $this->assertSame('[Telegram location]', $this->renderer->messageText(['location' => ['latitude' => 'x']]));
        $this->assertSame(
            "Telegram venue\nTitle: Office\nAddress: Main St 1",
            $this->renderer->messageText(['venue' => ['title' => 'Office', 'address' => 'Main St 1', 'location' => []]]),
        );

        $this->assertSame(
            "Telegram contact\nName: Ada Lovelace\nPhone: +321234\nTelegram user ID: 42",
            $this->renderer->messageText(['contact' => ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone_number' => '+321234', 'user_id' => 42]]),
        );

        $this->assertSame(
            "Telegram poll\nQuestion: Lunch?\n- Pizza (3 votes)\n- Option",
            $this->renderer->messageText(['poll' => ['question' => 'Lunch?', 'options' => [['text' => 'Pizza', 'voter_count' => 3], []]]]),
        );
    }

    // Ownership service messages compose the shared Telegram user label format.
    public function test_chat_owner_changed_uses_user_labels(): void
    {
        $this->assertSame(
            "Telegram chat owner changed\nPrevious owner: Old Owner @old (1)\nNew owner: @new (2)",
            $this->renderer->messageText(['chat_owner_changed' => [
                'old_owner' => ['id' => 1, 'first_name' => 'Old', 'last_name' => 'Owner', 'username' => 'old'],
                'new_owner' => ['id' => 2, 'username' => 'new'],
            ]]),
        );
    }

    public function test_paid_media_and_story_reply_rendering(): void
    {
        $this->assertSame(
            "Telegram paid media\nStars: 10\nItems: 2 (photo, video)",
            $this->renderer->messageText(['paid_media' => ['star_count' => 10, 'paid_media' => [['type' => 'photo'], ['type' => 'video']]]]),
        );

        $this->assertSame(
            "Hi\n\nReply to Telegram story 7 from News",
            $this->renderer->messageText(['text' => 'Hi', 'reply_to_story' => ['id' => 7, 'chat' => ['id' => -100, 'title' => 'News']]]),
        );
    }

    // The chat title rule is shared with conversation mapping in the pipeline.
    public function test_chat_title_prefers_title_then_name_then_null(): void
    {
        $this->assertSame('Team', $this->renderer->chatTitle(['title' => 'Team', 'first_name' => 'Ignored']));
        $this->assertSame('Ada Lovelace', $this->renderer->chatTitle(['title' => '', 'first_name' => 'Ada', 'last_name' => 'Lovelace']));
        $this->assertNull($this->renderer->chatTitle(['id' => 5]));
    }
}
