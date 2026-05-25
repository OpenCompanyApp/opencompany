<?php

namespace App\Domain\Chat\Telegram\Application;

use RuntimeException;

/**
 * Telegram Bot API rate-limit response with structured retry metadata.
 *
 * Delivery records can store retry_after separately from the human-readable
 * provider error so operators can distinguish transient flood control from
 * malformed payloads, blocked chats, or credential failures.
 */
class TelegramRateLimitException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfter,
        string $message = 'Telegram Bot API rate limit exceeded.',
    ) {
        parent::__construct($message);
    }
}
