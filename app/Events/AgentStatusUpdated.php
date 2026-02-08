<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $agent
    ) {}

    public function broadcastAs(): string
    {
        return 'AgentStatusUpdated';
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('agents'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->agent->id,
            'status' => $this->agent->status,
        ];
    }
}
