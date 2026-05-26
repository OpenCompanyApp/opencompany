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
            'toolExecutionContext' => ['nullable', 'array'],
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
            'tool_execution_context' => $data['toolExecutionContext'] ?? null,
        ]);

        return $approval->load(['requester', 'channel']);
    }

    public function update(Request $request, string $id): mixed
    {
        $approval = ApprovalRequest::whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
            ->findOrFail($id);

        if ($approval->status !== 'pending') {
            return response()->json([
                'ok' => false,
                'error' => 'approval_already_resolved',
                'status' => $approval->status,
            ], 422);
        }

        $approval->update([
            'status' => $request->input('status'),
            'responded_by_id' => auth()->id(),
            'responded_at' => now(),
        ]);

        /** @var User|null $agent */
        $agent = $approval->requester;
        $agentIsWaiting = $agent
            && $agent->type === 'agent'
            && $agent->awaiting_approval_id === $approval->id;

        if ($approval->status === 'approved' && $approval->tool_execution_context) {
            if ($approval->type === 'access') {
                $this->approvalService->executeApprovedAccess($approval, $agentIsWaiting);
            } else {
                $this->approvalService->executeApprovedTool($approval, $agentIsWaiting);
            }
        } elseif ($approval->status === 'rejected' && $agentIsWaiting) {
            $this->approvalService->handleRejectedTool($approval);
        }

        return $approval->load(['requester', 'respondedBy']);
    }
}
