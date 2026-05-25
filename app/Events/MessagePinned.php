<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Domain event emitted when a message pin state changes.
 *
 * The message carries channel/workspace context for listeners that maintain
 * pinned-message views or external chat sync.
 */
class MessagePinned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Message $message
    ) {}
}
