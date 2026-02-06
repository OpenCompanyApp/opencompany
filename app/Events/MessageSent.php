<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->message->channel_id),
        ];
    }

    public function broadcastWith(): array
    {
        $message = $this->message->load(['author', 'reactions.user', 'attachments', 'replyTo.author']);
        $data = $message->toArray();

        // Truncate long content to stay within Reverb/Pusher payload limits (~10KB)
        if (strlen($data['content'] ?? '') > 2000) {
            $data['content'] = mb_substr($data['content'], 0, 2000) . "\n\n…";
            $data['truncated'] = true;
        }

        return ['message' => $data];
    }
}
