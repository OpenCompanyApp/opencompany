<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListStatus;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateListStatus implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Create a new status (workflow column) on the kanban board.';
    }

    public function handle(Request $request): string
    {
        try {
            $name = $request['name'] ?? null;
            if (!$name) {
                return "Error: 'name' is required.";
            }

            $slug = Str::slug($name, '_');

            // Ensure unique slug
            $baseSlug = $slug;
            $counter = 1;
            while (ListStatus::forWorkspace()->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '_' . $counter;
                $counter++;
            }

            $maxPosition = ListStatus::forWorkspace()->max('position') ?? -1;

            $status = ListStatus::create([
                'id' => Str::uuid()->toString(),
                'name' => $name,
                'slug' => $slug,
                'color' => $request['color'] ?? 'neutral',
                'icon' => $request['icon'] ?? 'ph:circle',
                'is_done' => $request['isDone'] ?? false,
                'is_default' => false,
                'position' => $maxPosition + 1,
                'workspace_id' => workspace()->id,
            ]);

            return json_encode(array_filter([
                'id' => $status->id,
                'name' => $status->name,
                'slug' => $status->slug,
                'color' => $status->color,
                'icon' => $status->icon,
                'isDone' => $status->is_done ?: null,
            ], fn ($v) => $v !== null), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error creating status: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema
                ->string()
                ->description('The display name for the status.')
                ->required(),
            'color' => $schema
                ->string()
                ->description("The status color: 'neutral', 'blue', 'green', 'yellow', 'orange', 'red', 'purple', or 'pink'."),
            'icon' => $schema
                ->string()
                ->description("The status icon: 'ph:circle-dashed', 'ph:circle-half', 'ph:check-circle', 'ph:circle', 'ph:star', 'ph:flag', 'ph:clock', 'ph:eye', 'ph:lightning', or 'ph:hourglass'."),
            'isDone' => $schema
                ->boolean()
                ->description('Whether items in this status are considered completed.'),
        ];
    }
}
