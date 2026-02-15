<?php

namespace App\Events;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApprovalCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ApprovalRequest $approval
    ) {}

    public function broadcastOn(): array
    {
        $workspaceId = User::where('id', $this->approval->requester_id)->value('workspace_id');

        return [
            new Channel('workspace.' . $workspaceId . '.approvals'),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'approval' => $this->approval->load('requester'),
        ];
    }
}
