<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListStatus;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateListStatus implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Update an existing kanban board status (name, color, icon, done state, or set as default).';
    }

    public function handle(Request $request): string
    {
        try {
            $statusId = $request['statusId'] ?? null;
            if (!$statusId) {
                return "Error: 'statusId' is required.";
            }

            $status = ListStatus::forWorkspace()->findOrFail($statusId);

            if (isset($request['name'])) {
                $status->name = $request['name'];
            }
            if (isset($request['color'])) {
                $status->color = $request['color'];
            }
            if (isset($request['icon'])) {
                $status->icon = $request['icon'];
            }
            if (isset($request['isDone'])) {
                $status->is_done = $request['isDone'];
            }
            if (isset($request['isDefault'])) {
                if ($request['isDefault']) {
                    ListStatus::forWorkspace()->where('is_default', true)->update(['is_default' => false]);
                }
                $status->is_default = $request['isDefault'];
            }

            $status->save();

            return json_encode(array_filter([
                'id' => $status->id,
                'name' => $status->name,
                'slug' => $status->slug,
                'color' => $status->color,
                'icon' => $status->icon,
                'isDone' => $status->is_done ?: null,
                'isDefault' => $status->is_default ?: null,
            ], fn ($v) => $v !== null), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error updating status: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'statusId' => $schema
                ->string()
                ->description('The UUID of the status to update.')
                ->required(),
            'name' => $schema
                ->string()
                ->description('The new display name for the status.'),
            'color' => $schema
                ->string()
                ->description("The status color: 'neutral', 'blue', 'green', 'yellow', 'orange', 'red', 'purple', or 'pink'."),
            'icon' => $schema
                ->string()
                ->description("The status icon: 'ph:circle-dashed', 'ph:circle-half', 'ph:check-circle', 'ph:circle', 'ph:star', 'ph:flag', 'ph:clock', 'ph:eye', 'ph:lightning', or 'ph:hourglass'."),
            'isDone' => $schema
                ->boolean()
                ->description('Whether items in this status are considered completed.'),
            'isDefault' => $schema
                ->boolean()
                ->description('Whether this status should be the default for new items.'),
        ];
    }
}
