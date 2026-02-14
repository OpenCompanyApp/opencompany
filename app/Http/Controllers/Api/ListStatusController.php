<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListItem;
use App\Models\ListStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ListStatusController extends Controller
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ListStatus>
     */
    public function index(): \Illuminate\Database\Eloquent\Collection
    {
        return ListStatus::orderBy('position')->get();
    }

    public function store(Request $request): ListStatus
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'required|string|max:30',
            'icon' => 'required|string|max:50',
            'isDone' => 'boolean',
        ]);

        $slug = Str::slug($request->input('name'), '_');

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
            'name' => $request->input('name'),
            'slug' => $slug,
            'color' => $request->input('color'),
            'icon' => $request->input('icon'),
            'is_done' => $request->input('isDone', false),
            'is_default' => false,
            'position' => $maxPosition + 1,
        ]);

        return $status;
    }

    public function update(Request $request, string $id): ListStatus
    {
        $status = ListStatus::findOrFail($id);

        $data = [];

        if ($request->has('name')) {
            $data['name'] = $request->input('name');
        }
        if ($request->has('color')) {
            $data['color'] = $request->input('color');
        }
        if ($request->has('icon')) {
            $data['icon'] = $request->input('icon');
        }
        if ($request->has('isDone')) {
            $data['is_done'] = $request->input('isDone');
        }
        if ($request->has('isDefault')) {
            // If setting as default, unset the current default
            if ($request->input('isDefault')) {
                ListStatus::where('is_default', true)->update(['is_default' => false]);
            }
            $data['is_default'] = $request->input('isDefault');
        }

        $status->update($data);

        return $status;
    }

    public function reorder(Request $request): \Illuminate\Http\JsonResponse
    {
        $orders = $request->input('orders', []);

        foreach ($orders as $order) {
            ListStatus::where('id', $order['id'])->update(['position' => $order['position']]);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $status = ListStatus::findOrFail($id);

        // Cannot delete the default status
        if ($status->is_default) {
            return response()->json(['error' => 'Cannot delete the default status'], 422);
        }

        // Must have at least one non-done status remaining
        $remainingNonDone = ListStatus::where('id', '!=', $id)->where('is_done', false)->count();
        if (!$status->is_done && $remainingNonDone === 0) {
            return response()->json(['error' => 'Must have at least one non-done status'], 422);
        }

        // Reassign items to the replacement status
        $replacementSlug = $request->input('replacementSlug');
        if (!$replacementSlug) {
            // Default to the default status
            $replacementSlug = ListStatus::where('is_default', true)->value('slug') ?? 'backlog';
        }

        ListItem::where('status', $status->slug)->update(['status' => $replacementSlug]);

        $status->delete();

        return response()->json(['success' => true]);
    }
}
