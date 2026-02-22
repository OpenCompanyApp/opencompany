<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListItem;
use App\Models\ListStatus;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteListStatus implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Delete a kanban board status. Items using this status will be moved to a replacement status. Cannot delete the default status.';
    }

    public function handle(Request $request): string
    {
        try {
            $statusId = $request['statusId'] ?? null;
            if (!$statusId) {
                return "Error: 'statusId' is required.";
            }

            $status = ListStatus::forWorkspace()->findOrFail($statusId);

            if ($status->is_default) {
                return "Error: Cannot delete the default status.";
            }

            $remainingNonDone = ListStatus::forWorkspace()->where('id', '!=', $statusId)
                ->where('is_done', false)
                ->count();
            if (!$status->is_done && $remainingNonDone === 0) {
                return "Error: Must have at least one non-done status remaining.";
            }

            $replacementSlug = $request['replacementSlug'] ?? null;
            if (!$replacementSlug) {
                $replacementSlug = ListStatus::forWorkspace()->where('is_default', true)->value('slug') ?? 'backlog';
            }

            $reassignedCount = ListItem::forWorkspace()->where('status', $status->slug)->count();
            ListItem::forWorkspace()->where('status', $status->slug)->update(['status' => $replacementSlug]);

            $name = $status->name;
            $status->delete();

            $msg = "Status '{$name}' deleted.";
            if ($reassignedCount > 0) {
                $msg .= " {$reassignedCount} item(s) moved to '{$replacementSlug}'.";
            }

            return $msg;
        } catch (\Throwable $e) {
            return "Error deleting status: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'statusId' => $schema
                ->string()
                ->description('The UUID of the status to delete.')
                ->required(),
            'replacementSlug' => $schema
                ->string()
                ->description("The slug of the status to move existing items to. Defaults to the default status."),
        ];
    }
}
