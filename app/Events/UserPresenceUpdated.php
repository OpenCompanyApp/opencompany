<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPresenceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user
    ) {}

    public function broadcastOn(): array
    {
        // Agents belong to one workspace; humans may belong to multiple
        if ($this->user->workspace_id) {
            return [
                new PresenceChannel('workspace.' . $this->user->workspace_id . '.online'),
            ];
        }

        // Human user: broadcast to all their workspaces
        return $this->user->workspaces->map(
            fn ($ws) => new PresenceChannel('workspace.' . $ws->id . '.online')
        )->all();
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'presence' => $this->user->presence,
            ],
        ];
    }
}
