<?php

namespace App\Agents\Tools;

use App\Models\ListItem;
use App\Models\ListStatus;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ManageListStatus implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Create, update, or delete list statuses (workflow columns on the kanban board).';
    }

    public function handle(Request $request): string
    {
        try {
            $action = $request['action'];

            return match ($action) {
                'create' => $this->create($request),
                'update' => $this->update($request),
                'delete' => $this->delete($request),
                default => "Unknown action: {$action}. Use 'create', 'update', or 'delete'.",
            };
        } catch (\Throwable $e) {
            return "Error managing list status: {$e->getMessage()}";
        }
    }

    private function create(Request $request): string
    {
        $name = $request['name'] ?? null;
        if (!$name) {
            return "Error: 'name' is required for the 'create' action.";
        }

        $slug = Str::slug($name, '_');

        // Ensure unique slug
        $baseSlug = $slug;
        $counter = 1;
        while (ListStatus::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '_' . $counter;
            $counter++;
        }

        $maxPosition = ListStatus::max('position') ?? -1;

        $status = ListStatus::create([
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'slug' => $slug,
            'color' => $request['color'] ?? 'neutral',
            'icon' => $request['icon'] ?? 'ph:circle',
            'is_done' => $request['isDone'] ?? false,
            'is_default' => false,
            'position' => $maxPosition + 1,
        ]);

        return "Status created: '{$status->name}' (slug: {$status->slug}, ID: {$status->id})";
    }

    private function update(Request $request): string
    {
        $statusId = $request['statusId'] ?? null;
        if (!$statusId) {
            return "Error: 'statusId' is required for the 'update' action.";
        }

        $status = ListStatus::findOrFail($statusId);

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
                ListStatus::where('is_default', true)->update(['is_default' => false]);
            }
            $status->is_default = $request['isDefault'];
        }

        $status->save();

        return "Status '{$status->name}' updated.";
    }

    private function delete(Request $request): string
    {
        $statusId = $request['statusId'] ?? null;
        if (!$statusId) {
            return "Error: 'statusId' is required for the 'delete' action.";
        }

        $status = ListStatus::findOrFail($statusId);

        if ($status->is_default) {
            return "Error: Cannot delete the default status.";
        }

        $remainingNonDone = ListStatus::where('id', '!=', $statusId)
            ->where('is_done', false)
            ->count();
        if (!$status->is_done && $remainingNonDone === 0) {
            return "Error: Must have at least one non-done status remaining.";
        }

        $replacementSlug = $request['replacementSlug'] ?? null;
        if (!$replacementSlug) {
            $replacementSlug = ListStatus::where('is_default', true)->value('slug') ?? 'backlog';
        }

        $reassignedCount = ListItem::where('status', $status->slug)->count();
        ListItem::where('status', $status->slug)->update(['status' => $replacementSlug]);

        $name = $status->name;
        $status->delete();

        $msg = "Status '{$name}' deleted.";
        if ($reassignedCount > 0) {
            $msg .= " {$reassignedCount} item(s) moved to '{$replacementSlug}'.";
        }

        return $msg;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("The action to perform: 'create', 'update', or 'delete'.")
                ->required(),
            'statusId' => $schema
                ->string()
                ->description('The UUID of the status. Required for update and delete.'),
            'name' => $schema
                ->string()
                ->description('The display name for the status. Required for create.'),
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
            'replacementSlug' => $schema
                ->string()
                ->description("When deleting, the slug of the status to move existing items to. Defaults to the default status."),
        ];
    }
}
