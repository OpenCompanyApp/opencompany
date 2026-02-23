<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListItem;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListProjects implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List all projects (folders) on the kanban board. Projects are top-level containers that hold list items.';
    }

    public function handle(Request $request): string
    {
        try {
            $parentId = $request['parentId'] ?? null;

            $query = ListItem::forWorkspace()->where('is_folder', true)
                ->withCount(['children as item_count' => function ($q) {
                    $q->where('is_folder', false);
                }])
                ->orderBy('position');

            if ($parentId) {
                $query->where('parent_id', $parentId);
            } else {
                $query->whereNull('parent_id');
            }

            $projects = $query->get();

            if ($projects->isEmpty()) {
                return json_encode([]);
            }

            return json_encode($projects->map(fn ($project) => array_filter([
                'id' => $project->id,
                'title' => $project->title,
                'itemCount' => $project->item_count,
                'description' => $project->description ? Str::limit($project->description, 120) : null,
            ]))->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error listing projects: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'parentId' => $schema
                ->string()
                ->description('Filter by parent project ID to get nested sub-projects.'),
        ];
    }
}
