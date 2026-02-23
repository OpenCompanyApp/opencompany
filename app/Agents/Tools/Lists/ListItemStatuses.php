<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListStatus;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListItemStatuses implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List all available statuses (workflow columns) on the kanban board with their slugs, colors, and icons.';
    }

    public function handle(Request $request): string
    {
        try {
            $statuses = ListStatus::forWorkspace()->orderBy('position')->get();

            if ($statuses->isEmpty()) {
                return json_encode([]);
            }

            return json_encode($statuses->map(fn ($status) => array_filter([
                'id' => $status->id,
                'name' => $status->name,
                'slug' => $status->slug,
                'color' => $status->color,
                'icon' => $status->icon,
                'isDefault' => $status->is_default ?: null,
                'isDone' => $status->is_done ?: null,
            ]))->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error listing statuses: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
