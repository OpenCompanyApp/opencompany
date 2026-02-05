<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Task $task,
        public string $action = 'updated'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tasks'),
            new PrivateChannel('task.' . $this->task->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'task' => $this->task->load(['agent', 'requester', 'steps']),
        ];
    }
}
