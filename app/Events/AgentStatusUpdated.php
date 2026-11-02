<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
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
            // Invariant: private — routes/channels.php admits workspace members
            // only. A public channel would leak agent status to anyone holding
            // the Reverb key and a workspace id.
            new PrivateChannel('workspace.' . $this->agent->workspace_id . '.agents'),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->agent->id,
            'status' => $this->agent->status,
            'currentTask' => $this->agent->current_task,
        ];
    }
}
