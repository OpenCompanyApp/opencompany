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
                return 'No statuses configured.';
            }

            $lines = ["Available statuses ({$statuses->count()}):"];
            foreach ($statuses as $status) {
                $default = $status->is_default ? ' (default)' : '';
                $done = $status->is_done ? ' [marks as done]' : '';
                $lines[] = "- {$status->name} (slug: {$status->slug}) | Color: {$status->color} | Icon: {$status->icon}{$default}{$done}";
                $lines[] = "  ID: {$status->id}";
            }

            return implode("\n", $lines);
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
