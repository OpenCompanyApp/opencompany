<?php

namespace App\Agents\Tools\Workspace;

use App\Models\ListTemplate;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateItemTemplate implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Create a new list item template with default values and tags.';
    }

    public function handle(Request $request): string
    {
        try {
            $name = $request['name'] ?? null;
            if (!$name) {
                return 'name is required.';
            }

            $tags = [];
            if (!empty($request['tags'])) {
                $decoded = json_decode($request['tags'], true);
                if (is_array($decoded)) {
                    $tags = $decoded;
                }
            }

            $template = ListTemplate::create([
                'id' => Str::uuid()->toString(),
                'name' => $name,
                'default_title' => $request['defaultTitle'] ?? null,
                'default_description' => $request['defaultDescription'] ?? null,
                'default_priority' => $request['defaultPriority'] ?? 'medium',
                'default_assignee_id' => $request['defaultAssigneeId'] ?? null,
                'estimated_cost' => $request['estimatedCost'] ?? null,
                'tags' => $tags,
                'created_by_id' => $this->agent->id,
                'is_active' => true,
                'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
            ]);

            return "Template created: {$template->name} (ID: {$template->id})";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema
                ->string()
                ->description('Template name.')
                ->required(),
            'defaultTitle' => $schema
                ->string()
                ->description('Default title for template items.'),
            'defaultDescription' => $schema
                ->string()
                ->description('Default description for template items.'),
            'defaultPriority' => $schema
                ->string()
                ->description("Default priority: 'low', 'medium', 'high', 'urgent'."),
            'defaultAssigneeId' => $schema
                ->string()
                ->description('Default assignee UUID.'),
            'estimatedCost' => $schema
                ->string()
                ->description('Estimated cost (decimal).'),
            'tags' => $schema
                ->string()
                ->description('JSON array of tags, e.g. ["support","bug"].'),
        ];
    }
}
