<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListItem;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ConvertListItemToTask implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Convert a list item into an agent task (case). The task will be assigned to the specified agent or the item\'s current assignee.';
    }

    public function handle(Request $request): string
    {
        try {
            $item = ListItem::forWorkspace()->findOrFail($request['listItemId']);

            $assignAgent = null;
            if (isset($request['agentId'])) {
                $assignAgent = User::where('type', 'agent')->findOrFail($request['agentId']);
            }

            $task = $item->convertToTask($this->agent, $assignAgent);

            return json_encode([
                'taskId' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'agentId' => $task->agent_id,
                'listItemId' => $item->id,
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error converting list item to task: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'listItemId' => $schema
                ->string()
                ->description('The UUID of the list item to convert into a task.')
                ->required(),
            'agentId' => $schema
                ->string()
                ->description('The UUID of the agent to assign the task to. Defaults to the item\'s current assignee.'),
        ];
    }
}
