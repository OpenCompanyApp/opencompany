<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Notification>
     */
    public function index(Request $request): \Illuminate\Database\Eloquent\Collection
    {
        $query = Notification::with(['user', 'actor']);

        if ($request->has('userId')) {
            $query->where('user_id', $request->input('userId'));
        }

        if ($request->has('unreadOnly') && $request->input('unreadOnly')) {
            $query->where('is_read', false);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function store(Request $request): Notification
    {
        $notification = Notification::create([
            'id' => Str::uuid()->toString(),
            'type' => $request->input('type'),
            'title' => $request->input('title'),
            'message' => $request->input('message'),
            'user_id' => $request->input('userId'),
            'actor_id' => $request->input('actorId'),
            'action_url' => $request->input('actionUrl'),
            'metadata' => $request->input('metadata'),
            'is_read' => false,
        ]);

        return $notification->load(['user', 'actor']);
    }

    public function update(Request $request, string $id): Notification
    {
        $notification = Notification::findOrFail($id);

        $notification->update($request->only(['is_read']));

        return $notification->load(['user', 'actor']);
    }

    public function markAllRead(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Notification::where('is_read', false);

        if ($request->has('userId')) {
            $query->where('user_id', $request->input('userId'));
        }

        $query->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function count(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Notification::where('is_read', false);

        if ($request->has('userId')) {
            $query->where('user_id', $request->input('userId'));
        }

        return response()->json(['count' => $query->count()]);
    }
}
