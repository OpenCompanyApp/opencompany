<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListItem;
use App\Models\ListTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ListTemplateController extends Controller
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ListTemplate>
     */
    public function index(Request $request): \Illuminate\Database\Eloquent\Collection
    {
        $query = ListTemplate::with(['defaultAssignee', 'createdBy']);

        if ($request->input('activeOnly', true)) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')->get();
    }

    public function store(Request $request): ListTemplate
    {
        $template = ListTemplate::create([
            'id' => Str::uuid()->toString(),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'default_title' => $request->input('defaultTitle'),
            'default_description' => $request->input('defaultDescription'),
            'default_priority' => $request->input('defaultPriority', 'medium'),
            'default_assignee_id' => $request->input('defaultAssigneeId'),
            'estimated_cost' => $request->input('estimatedCost'),
            'tags' => $request->input('tags'),
            'created_by_id' => $request->input('createdById'),
            'is_active' => true,
        ]);

        return $template->load(['defaultAssignee', 'createdBy']);
    }

    public function update(Request $request, string $id): ListTemplate
    {
        $template = ListTemplate::findOrFail($id);

        $data = $request->only([
            'name',
            'description',
            'tags',
            'is_active',
        ]);

        if ($request->has('defaultTitle')) {
            $data['default_title'] = $request->input('defaultTitle');
        }
        if ($request->has('defaultDescription')) {
            $data['default_description'] = $request->input('defaultDescription');
        }
        if ($request->has('defaultPriority')) {
            $data['default_priority'] = $request->input('defaultPriority');
        }
        if ($request->has('defaultAssigneeId')) {
            $data['default_assignee_id'] = $request->input('defaultAssigneeId');
        }
        if ($request->has('estimatedCost')) {
            $data['estimated_cost'] = $request->input('estimatedCost');
        }
        if ($request->has('isActive')) {
            $data['is_active'] = $request->input('isActive');
        }

        $template->update($data);

        return $template->load(['defaultAssignee', 'createdBy']);
    }

    public function destroy(string $id): \Illuminate\Http\JsonResponse
    {
        ListTemplate::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    public function createListItem(Request $request, string $templateId): ListItem|\Illuminate\Http\JsonResponse
    {
        $template = ListTemplate::findOrFail($templateId);

        $parentId = $request->input('parentId');
        if (!$parentId) {
            return response()->json(['error' => 'Items must belong to a project (parentId is required).'], 422);
        }

        $maxPosition = ListItem::where('status', 'todo')->max('position') ?? 0;

        $listItem = ListItem::create([
            'id' => Str::uuid()->toString(),
            'parent_id' => $parentId,
            'title' => $request->input('title', $template->default_title),
            'description' => $request->input('description', $template->default_description),
            'status' => 'todo',
            'priority' => $request->input('priority', $template->default_priority),
            'assignee_id' => $request->input('assigneeId', $template->default_assignee_id),
            'channel_id' => $request->input('channelId'),
            'estimated_cost' => $request->input('estimatedCost', $template->estimated_cost),
            'position' => $maxPosition + 1,
        ]);

        // Increment template usage count
        $template->increment('usage_count');

        return $listItem->load(['assignee', 'creator', 'collaborators', 'channel']);
    }
}
