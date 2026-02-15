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

    public function broadcastAs(): string
    {
        return 'TaskUpdated';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workspace.' . $this->task->workspace_id . '.tasks'),
            new PrivateChannel('task.' . $this->task->id),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        // Minimal payload — frontend uses this as a refresh trigger only.
        // Full task data is fetched via API when needed.
        return [
            'action' => $this->action,
            'task' => [
                'id' => $this->task->id,
                'status' => $this->task->status,
                'title' => $this->task->title,
                'parentTaskId' => $this->task->parent_task_id,
            ],
        ];
    }
}
