<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private const BASE_URL = 'https://api.telegram.org/bot';
    private const MAX_MESSAGE_LENGTH = 4096;

    private ?string $botToken;

    public function __construct()
    {
        $setting = IntegrationSetting::where('integration_id', 'telegram')->first();
        $this->botToken = $setting?->getConfigValue('api_key');
    }

    /**
     * Send a text message to a Telegram chat.
     */
    public function sendMessage(string $chatId, string $text, ?array $replyMarkup = null): array
    {
        // Split long messages
        if (strlen($text) > self::MAX_MESSAGE_LENGTH && $replyMarkup === null) {
            return $this->sendLongMessage($chatId, $text);
        }

        $params = [
            'chat_id' => $chatId,
            'text' => substr($text, 0, self::MAX_MESSAGE_LENGTH),
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        try {
            return $this->request('sendMessage', $params);
        } catch (\RuntimeException $e) {
            if (!$replyMarkup && str_contains($e->getMessage(), "can't parse entities")) {
                $params['text'] = strip_tags($params['text']);
                unset($params['parse_mode']);
                return $this->request('sendMessage', $params);
            }
            throw $e;
        }
    }

    /**
     * Edit an existing message's text.
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

        return $this->request('editMessageText', $params);
    }

    /**
     * Answer a callback query (acknowledge button press).
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
     */
    public function setWebhook(string $url, string $secretToken): array
    {
        return $this->request('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ]);
    }

    /**
     * Remove the webhook.
     */
    public function deleteWebhook(): array
    {
        return $this->request('deleteWebhook');
    }

    /**
     * Test the bot token by calling getMe.
     */
    public function getMe(): array
    {
        return $this->request('getMe');
    }

    /**
     * Send a chat action (e.g. "typing") to show the bot is working.
     */
    public function sendChatAction(string $chatId, string $action = 'typing'): array
    {
        return $this->request('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    /**
     * Send a photo to a Telegram chat by uploading a file from disk.
     */
    public function sendPhoto(string $chatId, string $filePath, ?string $caption = null): array
    {
        if (!$this->botToken) {
            throw new \RuntimeException('Telegram bot token is not configured.');
        }

        if (!file_exists($filePath)) {
            throw new \RuntimeException("Photo file not found: {$filePath}");
        }

        $url = self::BASE_URL . $this->botToken . '/sendPhoto';

        try {
            $request = Http::timeout(30)
                ->attach('photo', file_get_contents($filePath), basename($filePath));

            $params = ['chat_id' => $chatId];
            if ($caption) {
                $params['caption'] = substr($caption, 0, 1024);
            }

            $response = $request->post($url, $params);
            $data = $response->json();

            if (!$response->successful() || !($data['ok'] ?? false)) {
                $errorDescription = $data['description'] ?? 'Unknown Telegram API error';
                Log::error('Telegram sendPhoto error', ['error' => $errorDescription]);
                throw new \RuntimeException("Telegram API error: {$errorDescription}");
            }

            return $data['result'] ?? [];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Telegram sendPhoto connection error', ['error' => $e->getMessage()]);
            throw new \RuntimeException("Failed to connect to Telegram API: {$e->getMessage()}");
        }
    }

    /**
     * Send a document to a Telegram chat by uploading a file from disk.
     * Used as fallback when sendPhoto fails (e.g. oversized images).
     */
    public function sendDocument(string $chatId, string $filePath, ?string $caption = null): array
    {
        if (!$this->botToken) {
            throw new \RuntimeException('Telegram bot token is not configured.');
        }

        if (!file_exists($filePath)) {
            throw new \RuntimeException("Document file not found: {$filePath}");
        }

        $url = self::BASE_URL . $this->botToken . '/sendDocument';

        try {
            $request = Http::timeout(30)
                ->attach('document', file_get_contents($filePath), basename($filePath));

            $params = ['chat_id' => $chatId];
            if ($caption) {
                $params['caption'] = substr($caption, 0, 1024);
            }

            $response = $request->post($url, $params);
            $data = $response->json();

            if (!$response->successful() || !($data['ok'] ?? false)) {
                $errorDescription = $data['description'] ?? 'Unknown Telegram API error';
                Log::error('Telegram sendDocument error', ['error' => $errorDescription]);
                throw new \RuntimeException("Telegram API error: {$errorDescription}");
            }

            return $data['result'] ?? [];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Telegram sendDocument connection error', ['error' => $e->getMessage()]);
            throw new \RuntimeException("Failed to connect to Telegram API: {$e->getMessage()}");
        }
    }

    /**
     * Check if the service has a valid bot token configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->botToken);
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
            $id = "\x00BLK" . count($protected) . "\x00";
            $protected[$id] = '<pre>' . htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8') . '</pre>';
            return $id;
        }, $markdown);

        // 2. Convert horizontal rules (---, ***, ___) before table detection
        $text = preg_replace('/^[-*_]{3,}\s*$/m', '───', $text);

        // 3. Convert markdown tables to pre-formatted text
        $text = preg_replace_callback('/^(\|.+\|)\n(\|[-| :]+\|)\n((?:\|.+\|\n?)+)/m', function ($m) use (&$protected) {
            $id = "\x00BLK" . count($protected) . "\x00";
            $protected[$id] = '<pre>' . htmlspecialchars(self::formatTable($m[0]), ENT_QUOTES, 'UTF-8') . '</pre>';
            return $id;
        }, $text);

        // 4. Extract inline code
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$protected) {
            $id = "\x00BLK" . count($protected) . "\x00";
            $protected[$id] = '<code>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>';
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

        return $padding > 0 ? $text . str_repeat(' ', $padding) : $text;
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
     */
    private function sendLongMessage(string $chatId, string $text): array
    {
        $chunks = $this->splitAtLineBoundaries($text, self::MAX_MESSAGE_LENGTH);
        $lastResult = [];

        foreach ($chunks as $chunk) {
            $lastResult = $this->sendChunk($chatId, $chunk);
        }

        return $lastResult;
    }

    /**
     * Send a single chunk, falling back to plain text if HTML parsing fails.
     */
    private function sendChunk(string $chatId, string $text): array
    {
        try {
            return $this->request('sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), "can't parse entities")) {
                return $this->request('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => strip_tags($text),
                ]);
            }
            throw $e;
        }
    }

    /**
     * Split text at safe boundaries, never inside <pre> blocks.
     *
     * Strategy: split the text into "segments" (pre blocks and regular text),
     * then accumulate segments into chunks that fit within maxLength.
     * A <pre> block is never split — if it doesn't fit, it gets its own chunk.
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
                if (strlen($current . $segment) <= $maxLength) {
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
                    $candidate = $current === '' ? $line : $current . "\n" . $line;

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
     */
    private function request(string $method, array $params = []): array
    {
        if (!$this->botToken) {
            throw new \RuntimeException('Telegram bot token is not configured.');
        }

        $url = self::BASE_URL . $this->botToken . '/' . $method;

        try {
            $response = Http::timeout(10)->post($url, $params);

            $data = $response->json();

            if (!$response->successful() || !($data['ok'] ?? false)) {
                $errorDescription = $data['description'] ?? 'Unknown Telegram API error';
                Log::error("Telegram API error: {$method}", [
                    'error' => $errorDescription,
                    'params' => array_diff_key($params, ['text' => true]),
                ]);
                throw new \RuntimeException("Telegram API error: {$errorDescription}");
            }

            $result = $data['result'] ?? [];
            return is_array($result) ? $result : ['ok' => $result];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("Telegram API connection error: {$method}", [
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException("Failed to connect to Telegram API: {$e->getMessage()}");
        }
    }
}
