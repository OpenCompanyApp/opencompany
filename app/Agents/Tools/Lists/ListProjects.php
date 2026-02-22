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
                return 'No projects found.' . ($parentId ? '' : ' Use create_list_item with isFolder=true to create one.');
            }

            $lines = ["Projects ({$projects->count()}):"];
            foreach ($projects as $project) {
                $lines[] = "- {$project->title} ({$project->item_count} items)";
                $lines[] = "  ID: {$project->id}";
                if ($project->description) {
                    $lines[] = "  Description: " . Str::limit($project->description, 120);
                }
            }

            return implode("\n", $lines);
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
