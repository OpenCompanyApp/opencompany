<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Domain event emitted after a message body changes.
 *
 * The event stays transport-agnostic; listeners decide whether to broadcast,
 * sync to chat adapters, or ignore the edit.
 */
class MessageEdited
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Message $message
    ) {}
}
