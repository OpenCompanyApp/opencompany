<?php

namespace App\Http\Controllers\Api;

use App\Domain\Authorization\Application\ApprovalResolutionDenied;
use App\Domain\Authorization\Application\ApprovalResolutionPolicy;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\User;
use App\Services\ApprovalExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Web/API surface for human approval requests in the active workspace.
 *
 * Visibility invariant: every lookup is `ApprovalRequest::forWorkspace()`
 * (approvals.workspace_id). Do not scope through `channel.workspace_id`:
 * channel-less approvals (e.g. ContactAgent access requests) would become
 * unreachable, and a foreign id must always be a 404.
 *
 * Resolution authority (admin/approver role, no self-approval, no agents) is
 * NOT decided here; ApprovalExecutionService::resolve() enforces
 * ApprovalResolutionPolicy for every transport. This controller only maps a
 * refusal to a 403 and exposes `can_resolve` so the UI can hide buttons.
 */
class ApprovalController extends Controller
{
    public function __construct(
        private ApprovalExecutionService $approvalService,
        private ApprovalResolutionPolicy $resolutionPolicy,
    ) {}

    public function index(Request $request): mixed
    {
        $query = ApprovalRequest::forWorkspace()->with(['requester', 'respondedBy', 'channel']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (ApprovalRequest $approval): array => $this->present($approval, $request->user()))
            ->values();
    }

    public function show(Request $request, string $id): mixed
    {
        $approval = ApprovalRequest::forWorkspace()
            ->with(['requester', 'respondedBy', 'channel'])
            ->findOrFail($id);

        return $this->present($approval, $request->user());
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
            'workspace_id' => workspace()->id,
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

        $approval = ApprovalRequest::forWorkspace()->findOrFail($id);

        try {
            $resolved = $this->approvalService->resolve($approval, $data['status'], $request->user());
        } catch (ApprovalResolutionDenied $denied) {
            return response()->json([
                'ok' => false,
                'error' => $denied->reason,
                'message' => $denied->getMessage(),
            ], 403);
        }

        if (! $resolved) {
            return response()->json([
                'ok' => false,
                'error' => 'approval_already_resolved',
                'status' => $approval->status,
            ], 422);
        }

        return $this->present($approval->fresh(['requester', 'respondedBy', 'channel']) ?? $approval, $request->user());
    }

    /**
     * Serialize an approval (secret-redacted via ApprovalRequest::toArray())
     * plus `can_resolve`: true only while pending AND the policy allows the
     * viewer. It is a UI hint; resolve() re-checks authoritatively.
     *
     * @return array<string, mixed>
     */
    private function present(ApprovalRequest $approval, ?User $viewer): array
    {
        $payload = $approval->toArray();
        unset($payload['channel']);
        $payload['can_resolve'] = $approval->status === 'pending'
            && $this->resolutionPolicy->canResolve($approval, $viewer);

        return $payload;
    }
}
