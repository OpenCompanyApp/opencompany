<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Domain event emitted when a chat message is deleted.
 *
 * Listeners can use the full Message model to sync external chat bridges or
 * update local UI state without re-querying by ID.
 */
class MessageDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Message $message
    ) {}
}
