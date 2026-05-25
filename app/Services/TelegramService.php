<?php

namespace App\Services;

use App\Domain\Chat\Telegram\Application\TelegramRateLimitException;
use App\Domain\Chat\Telegram\Application\TelegramSetupService;
use App\Models\IntegrationSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Low-level Telegram Bot API transport for OpenCompany-owned chat features.
 *
 * This service owns HTTP calls, configured Bot API base URLs, upload/download
 * helpers, and transport-level fallbacks. It deliberately does not decide
 * workspace authorization, command routing, callback policy, or renderer shape;
 * those belong to the app-owned Telegram domain under `Domain\Chat\Telegram`.
 */
class TelegramService
{
    private const DEFAULT_BASE_URL = 'https://api.telegram.org';

    private const MAX_MESSAGE_LENGTH = 4096;

    private ?string $botToken;

    public function __construct()
    {
        $setting = app()->bound('currentWorkspace')
            ? IntegrationSetting::forWorkspace()->where('integration_id', 'telegram')->first()
            : IntegrationSetting::where('integration_id', 'telegram')->first();

        $this->botToken = $setting?->getConfigValue('api_key');
    }

    public static function messageThreadIdForTopic(?string $topicId): ?int
    {
        if ($topicId === null || $topicId === '' || $topicId === '1' || ! ctype_digit($topicId)) {
            return null;
        }

        return (int) $topicId;
    }

    public static function directMessagesTopicId(?string $topicId): ?int
    {
        if ($topicId === null || $topicId === '' || ! ctype_digit($topicId)) {
            return null;
        }

        return (int) $topicId;
    }

    /**
     * Send a text message to a Telegram chat.
     *
     * @param  array<string, mixed>|null  $replyMarkup
     * @return array<string, mixed>
     */
    public function sendMessage(
        string $chatId,
        string $text,
        ?array $replyMarkup = null,
        ?int $replyToMessageId = null,
        ?int $messageThreadId = null,
        ?int $directMessagesTopicId = null,
        bool $disableNotification = false,
        bool $disableLinkPreview = true,
    ): array {
        // Split long messages
        if (strlen($text) > self::MAX_MESSAGE_LENGTH && $replyMarkup === null) {
            return $this->sendLongMessage($chatId, $text, $replyToMessageId, $messageThreadId, $directMessagesTopicId, $disableNotification, $disableLinkPreview);
        }

        $params = [
            'chat_id' => $chatId,
            'text' => substr($text, 0, self::MAX_MESSAGE_LENGTH),
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        if ($replyToMessageId) {
            $params['reply_parameters'] = json_encode([
                'message_id' => $replyToMessageId,
                'allow_sending_without_reply' => true,
            ]);
        }

        if ($messageThreadId) {
            $params['message_thread_id'] = $messageThreadId;
        }

        if ($directMessagesTopicId) {
            $params['direct_messages_topic_id'] = $directMessagesTopicId;
        }

        if ($disableNotification) {
            $params['disable_notification'] = true;
        }

        $this->applyTextMessageOptions($params, $disableLinkPreview);

        try {
            return $this->request('sendMessage', $params);
        } catch (\RuntimeException $e) {
            if ($this->isParseModeFailure($e)) {
                return $this->plainTextFallback('sendMessage', $params);
            }

            throw $e;
        }
    }

    /**
     * Stream an ephemeral partial message in a private Telegram chat.
     *
     * Telegram drafts are temporary previews and are not chat history. Callers
     * must still send or edit a durable final message after the agent response is
     * ready. An empty text intentionally renders Telegram's native "Thinking..."
     * placeholder on Bot API versions that support it.
     *
     * @return array<string, mixed>
     */
    public function sendMessageDraft(
        string $chatId,
        int $draftId,
        string $text = '',
        ?int $messageThreadId = null,
    ): array {
        $params = [
            'chat_id' => $chatId,
            'draft_id' => $draftId,
            'text' => substr($text, 0, self::MAX_MESSAGE_LENGTH),
            'parse_mode' => 'HTML',
        ];

        if ($messageThreadId) {
            $params['message_thread_id'] = $messageThreadId;
        }

        return $this->request('sendMessageDraft', $params);
    }

    /**
     * Reply to a Telegram guest-mode query with a prepared inline result.
     *
     * Guest messages may originate from chats where the bot is not a member, so
     * they are not normal workspace lanes and must not use sendMessage. The Bot
     * API expects an InlineQueryResult payload, which OpenCompany constrains to a
     * plain text article response until guest workflows have explicit identity and
     * permission contracts.
     *
     * @return array<string, mixed>
     */
    public function answerGuestQuery(string $guestQueryId, string $text, ?string $title = null): array
    {
        return $this->request('answerGuestQuery', [
            'guest_query_id' => $guestQueryId,
            'result' => json_encode([
                'type' => 'article',
                'id' => 'opencompany-guest-reply',
                'title' => $title ?: 'OpenCompany',
                'input_message_content' => [
                    'message_text' => substr($text, 0, self::MAX_MESSAGE_LENGTH),
                    'parse_mode' => 'HTML',
                ],
            ]),
        ]);
    }

    /**
     * Answer a Telegram inline-mode query with OpenCompany launcher results.
     *
     * Inline mode is an external launcher surface rather than a normal chat lane:
     * the user invokes the bot from another chat, Telegram expects an immediate
     * result list, and no workspace mutation should happen until the user chooses
     * a result or follows a deep link back into an authenticated OpenCompany flow.
     *
     * @param  list<array<string, mixed>>  $results
     * @return array<string, mixed>
     */
    public function answerInlineQuery(string $inlineQueryId, array $results, int $cacheTime = 0, bool $isPersonal = true): array
    {
        return $this->request('answerInlineQuery', [
            'inline_query_id' => $inlineQueryId,
            'results' => json_encode($results),
            'cache_time' => $cacheTime,
            'is_personal' => $isPersonal,
        ]);
    }

    /**
     * Edit an existing message's text.
     *
     * @param  array<string, mixed>|null  $replyMarkup
     * @return array<string, mixed>
     */
    public function editMessageText(string $chatId, int $messageId, string $text, ?array $replyMarkup = null): array
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => substr($text, 0, self::MAX_MESSAGE_LENGTH),
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        $this->applyTextMessageOptions($params);

        try {
            return $this->request('editMessageText', $params);
        } catch (\RuntimeException $e) {
            if ($this->isMessageNotModified($e)) {
                return [
                    'message_id' => $messageId,
                    '_opencompany_noop' => true,
                    '_opencompany_noop_reason' => 'message_not_modified',
                ];
            }

            if ($this->isParseModeFailure($e)) {
                return $this->plainTextFallback('editMessageText', $params);
            }

            throw $e;
        }
    }

    /**
     * Answer a callback query (acknowledge button press).
     *
     * @return array<string, mixed>
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): array
    {
        $params = [
            'callback_query_id' => $callbackQueryId,
        ];

        if ($text) {
            $params['text'] = $text;
        }

        return $this->request('answerCallbackQuery', $params);
    }

    /**
     * Register a webhook URL with Telegram.
     *
     * @return array<string, mixed>
     */
    public function setWebhook(string $url, string $secretToken): array
    {
        return $this->request('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => json_encode(app(TelegramSetupService::class)->allowedUpdates()),
        ]);
    }

    /**
     * Remove the webhook.
     *
     * @return array<string, mixed>
     */
    public function deleteWebhook(): array
    {
        return $this->request('deleteWebhook');
    }

    /**
     * Test the bot token by calling getMe.
     *
     * @return array<string, mixed>
     */
    public function getMe(): array
    {
        return $this->request('getMe');
    }

    /**
     * Get the current Telegram webhook registration.
     *
     * @return array<string, mixed>
     */
    public function getWebhookInfo(): array
    {
        return $this->request('getWebhookInfo');
    }

    /**
     * Resolve Telegram file metadata before downloading the actual bytes.
     *
     * Telegram update payloads contain file IDs, not stable download URLs. The
     * Bot API requires a getFile call for each file so OpenCompany can capture
     * the provider path, size, and unique file identity in its own file metadata.
     *
     * @return array<string, mixed>
     */
    public function getFile(string $fileId): array
    {
        return $this->request('getFile', [
            'file_id' => $fileId,
        ]);
    }

    /**
     * Download raw bytes for a file_path returned by getFile.
     */
    public function downloadFile(string $filePath): string
    {
        if (! $this->botToken) {
            throw new \RuntimeException('Telegram bot token is not configured.');
        }

        $response = Http::timeout(30)->get($this->botApiFileUrl($filePath));

        if (! $response->successful()) {
            throw new \RuntimeException('Telegram file download failed with HTTP '.$response->status());
        }

        return $response->body();
    }

    /**
     * Send a chat action (e.g. "typing") to show the bot is working.
     *
     * @return array<string, mixed>
     */
    public function sendChatAction(string $chatId, string $action = 'typing', ?int $messageThreadId = null): array
    {
        $params = [
            'chat_id' => $chatId,
            'action' => $action,
        ];

        if ($messageThreadId) {
            $params['message_thread_id'] = $messageThreadId;
        }

        return $this->request('sendChatAction', $params);
    }

    /**
     * Send a photo to a Telegram chat by uploading a file from disk.
     *
     * @return array<string, mixed>
     */
    public function sendPhoto(string $chatId, string $filePath, ?string $caption = null, ?int $directMessagesTopicId = null): array
    {
        if (! $this->botToken) {
            throw new \RuntimeException('Telegram bot token is not configured.');
        }

        if (! file_exists($filePath)) {
            throw new \RuntimeException("Photo file not found: {$filePath}");
        }

        $url = $this->botApiUrl('sendPhoto');

        try {
            $request = Http::timeout(30)
                ->attach('photo', file_get_contents($filePath), basename($filePath));

            $params = ['chat_id' => $chatId];
            if ($caption) {
                $params['caption'] = substr($caption, 0, 1024);
            }
            if ($directMessagesTopicId) {
                $params['direct_messages_topic_id'] = $directMessagesTopicId;
            }

            $response = $request->post($url, $params);
            $data = $response->json();

            if (! $response->successful() || ! ($data['ok'] ?? false)) {
                $this->throwTelegramApiException('sendPhoto', is_array($data) ? $data : null, $response->status(), $params);
            }

            return $data['result'] ?? [];
        } catch (ConnectionException $e) {
            Log::error('Telegram sendPhoto connection error', ['error' => $e->getMessage()]);
            throw new \RuntimeException("Failed to connect to Telegram API: {$e->getMessage()}");
        }
    }

    /**
     * Send a document to a Telegram chat by uploading a file from disk.
     * Used as fallback when sendPhoto fails (e.g. oversized images).
     *
     * @return array<string, mixed>
     */
    public function sendDocument(string $chatId, string $filePath, ?string $caption = null, ?int $directMessagesTopicId = null): array
    {
        if (! $this->botToken) {
            throw new \RuntimeException('Telegram bot token is not configured.');
        }

        if (! file_exists($filePath)) {
            throw new \RuntimeException("Document file not found: {$filePath}");
        }

        $url = $this->botApiUrl('sendDocument');

        try {
            $request = Http::timeout(30)
                ->attach('document', file_get_contents($filePath), basename($filePath));

            $params = ['chat_id' => $chatId];
            if ($caption) {
                $params['caption'] = substr($caption, 0, 1024);
            }
            if ($directMessagesTopicId) {
                $params['direct_messages_topic_id'] = $directMessagesTopicId;
            }

            $response = $request->post($url, $params);
            $data = $response->json();

            if (! $response->successful() || ! ($data['ok'] ?? false)) {
                $this->throwTelegramApiException('sendDocument', is_array($data) ? $data : null, $response->status(), $params);
            }

            return $data['result'] ?? [];
        } catch (ConnectionException $e) {
            Log::error('Telegram sendDocument connection error', ['error' => $e->getMessage()]);
            throw new \RuntimeException("Failed to connect to Telegram API: {$e->getMessage()}");
        }
    }

    /**
     * Delete a message from a Telegram chat.
     *
     * @return array<string, mixed>
     */
    public function deleteMessage(string $chatId, int $messageId): array
    {
        return $this->request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Pin a message in a Telegram chat.
     *
     * @return array<string, mixed>
     */
    public function pinChatMessage(string $chatId, int $messageId, bool $disableNotification = true): array
    {
        return $this->request('pinChatMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'disable_notification' => $disableNotification,
        ]);
    }

    /**
     * Set a reaction on a message in a Telegram chat.
     *
     * @return array<string, mixed>
     */
    public function setMessageReaction(string $chatId, int $messageId, string $emoji): array
    {
        return $this->request('setMessageReaction', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reaction' => json_encode([['type' => 'emoji', 'emoji' => $emoji]]),
        ]);
    }

    /**
     * Register the bot's command menu with Telegram.
     *
     * @return array<string, mixed>
     */
    public function setMyCommands(): array
    {
        return $this->request('setMyCommands', [
            'commands' => json_encode(app(TelegramSetupService::class)->commands()),
        ]);
    }

    /**
     * Set the bot's profile photo. Requires a JPEG file.
     * Uses Bot API 9.4 InputProfilePhotoStatic format.
     *
     * @return array<string, mixed>
     */
    public function setMyProfilePhoto(string $filePath): array
    {
        if (! $this->botToken) {
            throw new \RuntimeException('Telegram bot token is not configured.');
        }

        if (! file_exists($filePath)) {
            throw new \RuntimeException("Photo file not found: {$filePath}");
        }

        $url = $this->botApiUrl('setMyProfilePhoto');

        try {
            $response = Http::timeout(30)
                ->attach('file', file_get_contents($filePath), 'avatar.jpg')
                ->post($url, [
                    'photo' => json_encode([
                        'type' => 'static',
                        'photo' => 'attach://file',
                    ]),
                ]);

            $data = $response->json();

            if (! $response->successful() || ! ($data['ok'] ?? false)) {
                $this->throwTelegramApiException('setMyProfilePhoto', is_array($data) ? $data : null, $response->status());
            }

            $result = $data['result'] ?? true;

            return is_array($result) ? $result : ['ok' => $result];
        } catch (ConnectionException $e) {
            Log::error('Telegram setMyProfilePhoto connection error', ['error' => $e->getMessage()]);
            throw new \RuntimeException("Failed to connect to Telegram API: {$e->getMessage()}");
        }
    }

    /**
     * Check if the service has a valid bot token configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->botToken);
    }

    /**
     * Convert Markdown text to Telegram-safe HTML.
     *
     * Telegram HTML mode supports: <b>, <i>, <u>, <s>, <code>, <pre>, <a>, <blockquote>
     */
    public static function markdownToTelegramHtml(string $markdown): string
    {
        $protected = [];

        // 1. Extract fenced code blocks (with optional language tag, newline optional)
        $text = preg_replace_callback('/```(?:\w*)\n?(.*?)```/s', function ($m) use (&$protected) {
            $id = "\x00BLK".count($protected)."\x00";
            $protected[$id] = '<pre>'.htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8').'</pre>';

            return $id;
        }, $markdown);

        // 2. Convert horizontal rules (---, ***, ___) before table detection
        $text = preg_replace('/^[-*_]{3,}\s*$/m', '───', $text);

        // 3. Convert markdown tables to pre-formatted text
        $text = preg_replace_callback('/^(\|.+\|)\n(\|[-| :]+\|)\n((?:\|.+\|\n?)+)/m', function ($m) use (&$protected) {
            $id = "\x00BLK".count($protected)."\x00";
            $protected[$id] = '<pre>'.htmlspecialchars(self::formatTable($m[0]), ENT_QUOTES, 'UTF-8').'</pre>';

            return $id;
        }, $text);

        // 4. Extract inline code
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$protected) {
            $id = "\x00BLK".count($protected)."\x00";
            $protected[$id] = '<code>'.htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8').'</code>';

            return $id;
        }, $text);

        // 5. Escape HTML entities in remaining text
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // 6. Convert markdown patterns (order matters)
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $text);
        $text = preg_replace('/__(.+?)__/s', '<b>$1</b>', $text);
        $text = preg_replace('/(?<!\w)\*([^*]+?)\*(?!\w)/', '<i>$1</i>', $text);
        $text = preg_replace('/(?<!\w)_([^_]+?)_(?!\w)/', '<i>$1</i>', $text);
        $text = preg_replace('/~~(.+?)~~/s', '<s>$1</s>', $text);
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text);
        $text = preg_replace('/^#{1,6}\s+(.+)$/m', '<b>$1</b>', $text);
        $text = preg_replace('/^&gt;\s?(.+)$/m', '<blockquote>$1</blockquote>', $text);
        $text = preg_replace('/^[-*]\s+/m', '• ', $text);

        // 7. Restore protected blocks
        foreach ($protected as $id => $html) {
            $text = str_replace(htmlspecialchars($id, ENT_QUOTES, 'UTF-8'), $html, $text);
        }

        // 8. Clean up multiple blank lines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Strip markdown formatting markers from text.
     */
    private static function stripMarkdown(string $text): string
    {
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        $text = preg_replace('/__(.+?)__/', '$1', $text);
        $text = preg_replace('/(?<!\w)\*([^*]+?)\*(?!\w)/', '$1', $text);
        $text = preg_replace('/(?<!\w)_([^_]+?)_(?!\w)/', '$1', $text);
        $text = preg_replace('/~~(.+?)~~/', '$1', $text);

        return $text;
    }

    /**
     * Calculate visual width of a string, accounting for double-width characters (emoji, CJK).
     */
    private static function visualWidth(string $text): int
    {
        // mb_strwidth treats East Asian characters as width 2
        // but doesn't handle all emoji. Use it as base, then adjust for emoji.
        $width = mb_strwidth($text, 'UTF-8');

        // Count emoji (most common ranges) — each emoji takes ~2 columns in monospace
        // mb_strwidth already counts some as 2, but many emoji are missed
        preg_match_all('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}\x{200D}]/u', $text, $emoji);
        // Each emoji is counted as 1 by mb_strwidth but renders as 2; add 1 per emoji
        $width += count($emoji[0]);

        return $width;
    }

    /**
     * Pad a string to a visual width, accounting for double-width characters.
     */
    private static function visualPad(string $text, int $targetWidth): string
    {
        $currentWidth = self::visualWidth($text);
        $padding = $targetWidth - $currentWidth;

        return $padding > 0 ? $text.str_repeat(' ', $padding) : $text;
    }

    /**
     * Format a markdown table into aligned plain-text columns.
     */
    private static function formatTable(string $tableMarkdown): string
    {
        $lines = array_filter(explode("\n", trim($tableMarkdown)), fn ($l) => trim($l) !== '');
        $rows = [];

        foreach ($lines as $line) {
            $cells = array_map('trim', explode('|', trim($line, '|')));
            // Skip separator rows (----, :---:, etc.)
            if (preg_match('/^[-: ]+$/', $cells[0])) {
                continue;
            }
            // Clean markdown formatting from cell content
            $cells = array_map([self::class, 'stripMarkdown'], $cells);
            $rows[] = $cells;
        }

        if (empty($rows)) {
            return $tableMarkdown;
        }

        // Calculate column widths using visual width (handles emoji/CJK)
        $colWidths = [];
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $colWidths[$i] = max($colWidths[$i] ?? 0, self::visualWidth($cell));
            }
        }

        // Build aligned output
        $output = [];
        foreach ($rows as $ri => $row) {
            $parts = [];
            foreach ($row as $i => $cell) {
                $parts[] = self::visualPad($cell, $colWidths[$i] ?? 0);
            }
            $output[] = implode('  ', $parts);
            // Add separator after header
            if ($ri === 0) {
                $sep = [];
                foreach ($colWidths as $w) {
                    $sep[] = str_repeat('-', $w);
                }
                $output[] = implode('  ', $sep);
            }
        }

        return implode("\n", $output);
    }

    /**
     * Send a long message by splitting into chunks at line boundaries.
     *
     * @return array<string, mixed>
     */
    private function sendLongMessage(
        string $chatId,
        string $text,
        ?int $replyToMessageId = null,
        ?int $messageThreadId = null,
        ?int $directMessagesTopicId = null,
        bool $disableNotification = false,
        bool $disableLinkPreview = true,
    ): array {
        $chunks = $this->splitAtLineBoundaries($text, self::MAX_MESSAGE_LENGTH);
        $lastResult = [];

        foreach ($chunks as $i => $chunk) {
            // Only reply-thread the first chunk
            $replyId = $i === 0 ? $replyToMessageId : null;
            $lastResult = $this->sendChunk($chatId, $chunk, $replyId, $messageThreadId, $directMessagesTopicId, $disableNotification, $disableLinkPreview);
        }

        return $lastResult;
    }

    /**
     * Send a single chunk, falling back to plain text if HTML parsing fails.
     *
     * @return array<string, mixed>
     */
    private function sendChunk(
        string $chatId,
        string $text,
        ?int $replyToMessageId = null,
        ?int $messageThreadId = null,
        ?int $directMessagesTopicId = null,
        bool $disableNotification = false,
        bool $disableLinkPreview = true,
    ): array {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyToMessageId) {
            $params['reply_parameters'] = json_encode([
                'message_id' => $replyToMessageId,
                'allow_sending_without_reply' => true,
            ]);
        }

        if ($messageThreadId) {
            $params['message_thread_id'] = $messageThreadId;
        }

        if ($directMessagesTopicId) {
            $params['direct_messages_topic_id'] = $directMessagesTopicId;
        }

        if ($disableNotification) {
            $params['disable_notification'] = true;
        }

        $this->applyTextMessageOptions($params, $disableLinkPreview);

        try {
            return $this->request('sendMessage', $params);
        } catch (\RuntimeException $e) {
            if ($this->isParseModeFailure($e)) {
                return $this->plainTextFallback('sendMessage', $params);
            }

            throw $e;
        }
    }

    private function isParseModeFailure(\RuntimeException $e): bool
    {
        return str_contains($e->getMessage(), "can't parse entities");
    }

    private function isMessageNotModified(\RuntimeException $e): bool
    {
        return str_contains(strtolower($e->getMessage()), 'message is not modified');
    }

    /**
     * Apply shared Bot API text-message options.
     *
     * Operational OpenCompany cards include web URLs for fallback access, but
     * Telegram's automatic preview cards make task/run bubbles noisy and push
     * action buttons off screen. LinkPreviewOptions is the current Bot API
     * surface for suppressing those previews while still keeping links tappable.
     *
     * @param  array<string, mixed>  $params
     */
    private function applyTextMessageOptions(array &$params, bool $disableLinkPreview = true): void
    {
        if (! $disableLinkPreview) {
            return;
        }

        $params['link_preview_options'] = json_encode(['is_disabled' => true]);
    }

    /**
     * Retry a text delivery without parse mode while preserving transport hints.
     *
     * Telegram formatting is fragile: one malformed entity can reject the whole
     * message. Buttons, reply targets, topics, silent mode, and direct-message
     * topic routing are independent of parse mode, so the fallback strips only
     * formatting tags and keeps the rest of the send parameters intact.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function plainTextFallback(string $method, array $params): array
    {
        $params['text'] = html_entity_decode(
            strip_tags((string) ($params['text'] ?? '')),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
        unset($params['parse_mode']);

        return [
            ...$this->request($method, $params),
            '_opencompany_parse_mode_fallback' => true,
        ];
    }

    /**
     * Split text at safe boundaries, never inside <pre> blocks.
     *
     * Strategy: split the text into "segments" (pre blocks and regular text),
     * then accumulate segments into chunks that fit within maxLength.
     * A <pre> block is never split — if it doesn't fit, it gets its own chunk.
     *
     * @return array<int, string>
     */
    private function splitAtLineBoundaries(string $text, int $maxLength): array
    {
        if (strlen($text) <= $maxLength) {
            return [$text];
        }

        // Split into segments: alternating text and <pre>...</pre> blocks
        $segments = preg_split('/(<pre>.*?<\/pre>)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $chunks = [];
        $current = '';

        foreach ($segments as $segment) {
            $isPre = str_starts_with($segment, '<pre>');

            if ($isPre) {
                // Never split a <pre> block — flush current and add as its own chunk if needed
                if (strlen($current.$segment) <= $maxLength) {
                    $current .= $segment;
                } else {
                    if ($current !== '') {
                        $chunks[] = trim($current);
                        $current = '';
                    }
                    // Pre block alone (may exceed max, but we can't split it safely)
                    $chunks[] = trim($segment);
                }
            } else {
                // Regular text — split at line boundaries
                $lines = explode("\n", $segment);
                foreach ($lines as $line) {
                    $candidate = $current === '' ? $line : $current."\n".$line;

                    if (strlen($candidate) > $maxLength) {
                        if ($current !== '') {
                            $chunks[] = trim($current);
                            $current = $line;
                        } else {
                            // Single line exceeds max
                            $chunks[] = substr($line, 0, $maxLength);
                            $current = substr($line, $maxLength);
                        }
                    } else {
                        $current = $candidate;
                    }
                }
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return array_filter($chunks, fn ($c) => $c !== '');
    }

    /**
     * Make an HTTP request to the Telegram Bot API.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function request(string $method, array $params = []): array
    {
        if (! $this->botToken) {
            throw new \RuntimeException('Telegram bot token is not configured.');
        }

        $url = $this->botApiUrl($method);

        try {
            $response = Http::timeout(10)->post($url, $params);

            $data = $response->json();

            if (! $response->successful() || ! ($data['ok'] ?? false)) {
                $this->throwTelegramApiException($method, is_array($data) ? $data : null, $response->status(), $params);
            }

            $result = $data['result'] ?? [];

            return is_array($result) ? $result : ['ok' => $result];
        } catch (ConnectionException $e) {
            Log::error("Telegram API connection error: {$method}", [
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException("Failed to connect to Telegram API: {$e->getMessage()}");
        }
    }

    /**
     * Convert Bot API error payloads into typed exceptions with operator-safe
     * metadata. Multipart upload methods cannot reuse request(), so they share
     * this classifier with normal JSON-form Bot API methods.
     *
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>  $params
     */
    private function throwTelegramApiException(string $method, ?array $data, int $status, array $params = []): never
    {
        $errorDescription = is_array($data) ? (string) ($data['description'] ?? 'Unknown Telegram API error') : 'Unknown Telegram API error';
        $errorCode = is_array($data) ? (int) ($data['error_code'] ?? $status) : $status;
        $parameters = is_array($data['parameters'] ?? null) ? $data['parameters'] : [];
        $retryAfter = (int) ($parameters['retry_after'] ?? 0);

        if ($method === 'editMessageText' && str_contains($errorDescription, 'message is not modified')) {
            throw new \RuntimeException("Telegram API error: {$errorDescription}");
        }

        Log::error("Telegram API error: {$method}", [
            'error' => $errorDescription,
            'error_code' => $errorCode,
            'retry_after' => $retryAfter ?: null,
            'params' => array_diff_key($params, ['text' => true, 'caption' => true]),
        ]);

        if ($errorCode === 429 || $retryAfter > 0) {
            throw new TelegramRateLimitException(
                max(1, $retryAfter),
                "Telegram API rate limit: {$errorDescription}",
            );
        }

        throw new \RuntimeException("Telegram API error: {$errorDescription}");
    }

    /**
     * Build the JSON/multipart Bot API endpoint for the configured transport.
     *
     * OpenCompany defaults to Telegram's hosted Bot API, but local Bot API
     * servers use the same "/bot{token}/{method}" path on a different origin.
     * Keeping URL construction centralized prevents upload, webhook, and normal
     * form requests from accidentally drifting apart.
     */
    private function botApiUrl(string $method): string
    {
        return $this->botApiBaseUrl().'/bot'.$this->botToken.'/'.ltrim($method, '/');
    }

    /**
     * Build the file download endpoint for a getFile file_path.
     *
     * Telegram file downloads are a separate URL namespace from method calls.
     * Operators may split that namespace onto a different host; otherwise it
     * follows the method base URL with "/file" appended.
     */
    private function botApiFileUrl(string $filePath): string
    {
        $baseUrl = config('telegram.bot_api_file_base_url');

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            $baseUrl = $this->botApiBaseUrl().'/file';
        }

        return rtrim($baseUrl, '/').'/bot'.$this->botToken.'/'.ltrim($filePath, '/');
    }

    /**
     * Resolve and normalize the configured Bot API origin.
     */
    private function botApiBaseUrl(): string
    {
        $baseUrl = config('telegram.bot_api_base_url', self::DEFAULT_BASE_URL);

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            $baseUrl = self::DEFAULT_BASE_URL;
        }

        return rtrim($baseUrl, '/');
    }
}
