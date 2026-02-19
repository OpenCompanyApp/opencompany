<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
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
        $query = ApprovalRequest::with(['requester', 'respondedBy']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function show(string $id): ApprovalRequest
    {
        return ApprovalRequest::with(['requester', 'respondedBy'])
            ->findOrFail($id);
    }

    public function store(Request $request): mixed
    {
        $approval = ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => $request->input('type'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'requester_id' => $request->input('requesterId'),
            'amount' => $request->input('amount'),
            'status' => 'pending',
        ]);

        return $approval->load('requester');
    }

    public function update(Request $request, string $id): mixed
    {
        $approval = ApprovalRequest::findOrFail($id);

        $approval->update([
            'status' => $request->input('status'),
            'responded_by_id' => auth()->id(),
            'responded_at' => now(),
        ]);

        /** @var \App\Models\User|null $agent */
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
