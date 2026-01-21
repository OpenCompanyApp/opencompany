<?php

namespace App\Events;

use App\Models\ApprovalRequest;
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
        return [
            new Channel('approvals'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'approval' => $this->approval->load('requester'),
        ];
    }
}
