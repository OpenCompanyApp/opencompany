<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListItem;
use App\Models\ListItemCollaborator;
use App\Models\ListStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ListItemController extends Controller
{
    /** @return Collection<int, ListItem> */
    public function index(): Collection
    {
        return ListItem::forWorkspace()->with(['assignee', 'creator', 'collaborators', 'channel'])
            ->orderBy('position')
            ->get();
    }

    public function show(string $id): ListItem
    {
        return ListItem::forWorkspace()->with(['assignee', 'creator', 'collaborators', 'channel', 'comments.author'])
            ->findOrFail($id);
    }

    public function store(Request $request): ListItem|JsonResponse
    {
        $isFolder = $request->input('isFolder', false);
        $parentId = $request->input('parentId');

        if (!$isFolder && !$parentId) {
            return response()->json(['error' => 'Items must belong to a project (parentId is required).'], 422);
        }

        // For folders, calculate max position among siblings
        // For items, calculate max position within parent and status
        if ($isFolder) {
            $maxPosition = ListItem::forWorkspace()->where('parent_id', $parentId)
                ->where('is_folder', true)
                ->max('position') ?? 0;
        } else {
            $maxPosition = ListItem::forWorkspace()->where('parent_id', $parentId)
                ->where('is_folder', false)
                ->where('status', $request->input('status', 'backlog'))
                ->max('position') ?? 0;
        }

        $listItem = ListItem::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'parent_id' => $parentId,
            'is_folder' => $isFolder,
            'title' => $request->input('title'),
            'description' => $request->input('description', ''),
            'status' => $isFolder
                ? (ListStatus::forWorkspace()->where('is_default', true)->value('slug') ?? 'backlog')
                : $request->input('status', ListStatus::forWorkspace()->where('is_default', true)->value('slug') ?? 'backlog'),
            'priority' => $isFolder ? 'medium' : $request->input('priority', 'medium'),
            'assignee_id' => $request->input('assigneeId'),
            'creator_id' => $request->input('creatorId'),
            'channel_id' => $request->input('channelId'),
            'due_date' => $request->input('dueDate'),
            'position' => $maxPosition + 1,
        ]);

        // Add collaborators
        if ($request->input('collaboratorIds')) {
            foreach ($request->input('collaboratorIds') as $collaboratorId) {
                ListItemCollaborator::create([
                    'id' => Str::uuid()->toString(),
                    'list_item_id' => $listItem->id,
                    'user_id' => $collaboratorId,
                ]);
            }
        }

        return $listItem->load(['assignee', 'creator', 'collaborators', 'channel']);
    }

    public function update(Request $request, string $id): ListItem
    {
        $listItem = ListItem::forWorkspace()->findOrFail($id);

        $data = $request->only([
            'title',
            'description',
            'status',
            'priority',
            'position',
            'progress',
        ]);

        // Handle snake_case conversion for camelCase inputs
        if ($request->has('parentId')) {
            $data['parent_id'] = $request->input('parentId');
        }
        if ($request->has('assigneeId')) {
            $data['assignee_id'] = $request->input('assigneeId');
        }
        if ($request->has('channelId')) {
            $data['channel_id'] = $request->input('channelId');
        }
        if ($request->has('dueDate')) {
            $data['due_date'] = $request->input('dueDate');
        }

        // Handle status changes
        if (isset($data['status'])) {
            $newStatus = ListStatus::forWorkspace()->where('slug', $data['status'])->first();
            if ($newStatus) {
                if ($newStatus->is_done && !$listItem->completed_at) {
                    $data['completed_at'] = now();
                }
                if (!$newStatus->is_done && $listItem->completed_at) {
                    $data['completed_at'] = null;
                }
            }
        }

        $listItem->update($data);

        // Update collaborators if provided
        if ($request->has('collaboratorIds')) {
            ListItemCollaborator::where('list_item_id', $id)->delete();
            foreach ($request->input('collaboratorIds') as $collaboratorId) {
                ListItemCollaborator::create([
                    'id' => Str::uuid()->toString(),
                    'list_item_id' => $id,
                    'user_id' => $collaboratorId,
                ]);
            }
        }

        return $listItem->load(['assignee', 'creator', 'collaborators', 'channel']);
    }

    public function destroy(string $id): JsonResponse
    {
        ListItem::forWorkspace()->findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $itemOrders = $request->input('itemOrders') ?? $request->input('taskOrders');

        foreach ($itemOrders as $order) {
            $data = ['position' => $order['position']];
            if (isset($order['status'])) {
                $data['status'] = $order['status'];
            }
            ListItem::where('id', $order['id'])->update($data);
        }

        return response()->json(['success' => true]);
    }
}
