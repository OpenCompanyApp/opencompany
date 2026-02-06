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

        return $this->request('sendMessage', $params);
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
        // Extract code blocks first to protect them from other transformations
        $codeBlocks = [];
        $text = preg_replace_callback('/```(?:\w*)\n(.*?)```/s', function ($m) use (&$codeBlocks) {
            $placeholder = "\x00CODEBLOCK" . count($codeBlocks) . "\x00";
            $codeBlocks[$placeholder] = '<pre>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</pre>';
            return $placeholder;
        }, $markdown);

        // Convert markdown tables to pre-formatted text
        $text = preg_replace_callback('/^(\|.+\|)\n(\|[-| :]+\|)\n((?:\|.+\|\n?)+)/m', function ($m) use (&$codeBlocks) {
            $placeholder = "\x00CODEBLOCK" . count($codeBlocks) . "\x00";
            $codeBlocks[$placeholder] = '<pre>' . htmlspecialchars(self::formatTable($m[0]), ENT_QUOTES, 'UTF-8') . '</pre>';
            return $placeholder;
        }, $text);

        // Extract inline code to protect from other transformations
        $inlineCodes = [];
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$inlineCodes) {
            $placeholder = "\x00INLINE" . count($inlineCodes) . "\x00";
            $inlineCodes[$placeholder] = '<code>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>';
            return $placeholder;
        }, $text);

        // Escape HTML entities in remaining text
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // Convert markdown patterns (order matters)
        // Bold: **text** or __text__
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $text);
        $text = preg_replace('/__(.+?)__/s', '<b>$1</b>', $text);

        // Italic: *text* or _text_ (but not inside words like some_var_name)
        $text = preg_replace('/(?<!\w)\*([^*]+?)\*(?!\w)/', '<i>$1</i>', $text);
        $text = preg_replace('/(?<!\w)_([^_]+?)_(?!\w)/', '<i>$1</i>', $text);

        // Strikethrough: ~~text~~
        $text = preg_replace('/~~(.+?)~~/s', '<s>$1</s>', $text);

        // Links: [text](url)
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text);

        // Headers: # text → bold text
        $text = preg_replace('/^#{1,6}\s+(.+)$/m', '<b>$1</b>', $text);

        // Blockquotes: > text
        $text = preg_replace('/^&gt;\s?(.+)$/m', '<blockquote>$1</blockquote>', $text);

        // List items: - text or * text → bullet
        $text = preg_replace('/^[-*]\s+/m', '• ', $text);

        // Restore code blocks and inline code
        foreach ($codeBlocks as $placeholder => $code) {
            $text = str_replace(htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'), $code, $text);
        }
        foreach ($inlineCodes as $placeholder => $code) {
            $text = str_replace(htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'), $code, $text);
        }

        // Clean up multiple blank lines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
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
            if (!empty($cells) && preg_match('/^[-: ]+$/', $cells[0])) {
                continue;
            }
            $rows[] = $cells;
        }

        if (empty($rows)) {
            return $tableMarkdown;
        }

        // Calculate column widths
        $colWidths = [];
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $colWidths[$i] = max($colWidths[$i] ?? 0, mb_strlen($cell));
            }
        }

        // Build aligned output
        $output = [];
        foreach ($rows as $ri => $row) {
            $parts = [];
            foreach ($row as $i => $cell) {
                $parts[] = mb_str_pad($cell, $colWidths[$i] ?? 0);
            }
            $output[] = implode('  ', $parts);
            // Add separator after header
            if ($ri === 0) {
                $sep = [];
                foreach ($colWidths as $w) {
                    $sep[] = str_repeat('─', $w);
                }
                $output[] = implode('  ', $sep);
            }
        }

        return implode("\n", $output);
    }

    /**
     * Send a long message by splitting into chunks.
     */
    private function sendLongMessage(string $chatId, string $text): array
    {
        $chunks = str_split($text, self::MAX_MESSAGE_LENGTH);
        $lastResult = [];

        foreach ($chunks as $chunk) {
            $lastResult = $this->request('sendMessage', [
                'chat_id' => $chatId,
                'text' => $chunk,
                'parse_mode' => 'HTML',
            ]);
        }

        return $lastResult;
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
