<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $query = Message::with(['author', 'reactions.user', 'attachments', 'replyTo.author']);

        if ($request->has('channelId')) {
            $query->where('channel_id', $request->input('channelId'));
        }

        $limit = $request->input('limit', 50);

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function store(Request $request)
    {
        $message = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => $request->input('content'),
            'channel_id' => $request->input('channelId'),
            'author_id' => $request->input('authorId'),
            'reply_to_id' => $request->input('replyToId'),
        ]);

        // Attach any pre-uploaded attachments
        if ($request->input('attachmentIds')) {
            MessageAttachment::whereIn('id', $request->input('attachmentIds'))
                ->update(['message_id' => $message->id]);
        }

        // Update channel's last_message_at
        Channel::where('id', $request->input('channelId'))
            ->update(['last_message_at' => now()]);

        return $message->load(['author', 'reactions.user', 'attachments', 'replyTo.author']);
    }

    public function destroy(string $id)
    {
        Message::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    public function addReaction(Request $request, string $messageId)
    {
        $reaction = MessageReaction::create([
            'id' => Str::uuid()->toString(),
            'message_id' => $messageId,
            'user_id' => $request->input('userId'),
            'emoji' => $request->input('emoji'),
        ]);

        return $reaction->load('user');
    }

    public function removeReaction(string $messageId, string $reactionId)
    {
        MessageReaction::where('id', $reactionId)
            ->where('message_id', $messageId)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function thread(string $messageId)
    {
        $parentMessage = Message::with(['author', 'reactions.user', 'attachments'])
            ->findOrFail($messageId);

        $replies = Message::with(['author', 'reactions.user', 'attachments'])
            ->where('reply_to_id', $messageId)
            ->orderBy('created_at', 'asc')
            ->get();

        return [
            'parent' => $parentMessage,
            'replies' => $replies,
        ];
    }

    public function pin(Request $request, string $messageId)
    {
        $message = Message::findOrFail($messageId);
        $message->update([
            'is_pinned' => true,
            'pinned_at' => now(),
            'pinned_by_id' => $request->input('userId'),
        ]);

        return $message->load(['author', 'reactions.user', 'attachments']);
    }

    public function uploadAttachment(Request $request)
    {
        $file = $request->file('file');
        $path = $file->store('message-attachments', 'public');

        $attachment = MessageAttachment::create([
            'id' => Str::uuid()->toString(),
            'channel_id' => $request->input('channelId'),
            'uploader_id' => $request->input('uploaderId'),
            'name' => $file->getClientOriginalName(),
            'type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'url' => '/storage/' . $path,
        ]);

        return $attachment;
    }
}
