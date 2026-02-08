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
            new PrivateChannel('tasks'),
            new PrivateChannel('task.' . $this->task->id),
        ];
    }

    public function broadcastWith(): array
    {
        $task = $this->task->load(['agent', 'requester', 'steps']);
        $data = $task->toArray();

        // Strip large JSON fields to stay within Reverb payload limit (~10KB)
        unset($data['context']);
        unset($data['result']);

        // Strip step metadata (tool arguments/results) — keep description, status, timing
        if (isset($data['steps'])) {
            $data['steps'] = array_map(function ($step) {
                unset($step['metadata']);
                return $step;
            }, $data['steps']);
        }

        return [
            'action' => $this->action,
            'task' => $data,
        ];
    }
}
