<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\User;
use App\Services\ApprovalExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApprovalController extends Controller
{
    public function __construct(
        private ApprovalExecutionService $approvalService,
    ) {}

    public function index(Request $request): mixed
    {
        $query = ApprovalRequest::with(['requester', 'respondedBy'])
            ->whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id));

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function show(string $id): ApprovalRequest
    {
        return ApprovalRequest::with(['requester', 'respondedBy'])
            ->whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
            ->findOrFail($id);
    }

    public function store(Request $request): mixed
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:budget,action,spawn,access'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'requesterId' => ['required', 'string'],
            'channelId' => ['required', 'string'],
            'amount' => ['nullable', 'numeric'],
            // Executable context is created only by trusted agent/runtime code.
            // Accepting it from the browser would let a member fabricate an
            // approval and execute allowed tools as another agent.
            'toolExecutionContext' => ['prohibited'],
        ]);

        $requester = User::query()
            ->where('id', $data['requesterId'])
            ->where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->firstOrFail();

        $channel = Channel::query()
            ->where('id', $data['channelId'])
            ->where('workspace_id', workspace()->id)
            ->firstOrFail();

        $approval = ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'requester_id' => $requester->id,
            'channel_id' => $channel->id,
            'amount' => $data['amount'] ?? null,
            'status' => 'pending',
            'tool_execution_context' => null,
        ]);

        return $approval->load(['requester', 'channel']);
    }

    public function update(Request $request, string $id): mixed
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected'],
        ]);

        $approval = ApprovalRequest::whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
            ->findOrFail($id);

        if (! $this->approvalService->resolve($approval, $data['status'], $request->user())) {
            return response()->json([
                'ok' => false,
                'error' => 'approval_already_resolved',
                'status' => $approval->status,
            ], 422);
        }

        return $approval->fresh(['requester', 'respondedBy']);
    }
}
