<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Domain event emitted when a reaction is added to a message.
 *
 * The emoji is kept separate from the message so listeners can update reaction
 * counters without diffing the message relation payload.
 */
class MessageReactionAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Message $message,
        public string $emoji
    ) {}
}
