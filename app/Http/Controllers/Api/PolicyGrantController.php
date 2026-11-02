<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PolicyGrant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin API for scoped policy grants (Product Direction pillar 5).
 *
 * Owns: listing, creating and revoking exact-match grants that let one agent
 * run one tool without approval in a given scope. Routes sit behind
 * `workspace.admin`; agents can never call this. There is no UI yet (Inertia
 * is frozen; the messenger client will surface grants on approval cards).
 *
 * Workspace boundary: the agent must belong to the active workspace, and
 * grants are always created in and read from that workspace.
 */
class PolicyGrantController extends Controller
{
    public function index(string $agentId): JsonResponse
    {
        $agent = $this->agent($agentId);

        return response()->json(PolicyGrant::forWorkspace()
            ->where('agent_id', $agent->id)
            ->orderByDesc('created_at')
            ->get());
    }

    public function store(Request $request, string $agentId): JsonResponse
    {
        $agent = $this->agent($agentId);
        $data = $request->validate([
            'toolSlug' => ['required', 'string', 'max:200'],
            'scope' => ['required', Rule::in(PolicyGrant::SCOPES)],
            'runId' => ['required_if:scope,run', 'nullable', 'uuid'],
            'taskId' => ['required_if:scope,task', 'nullable', 'uuid'],
            'expiresAt' => ['required_if:scope,until', 'nullable', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $grant = PolicyGrant::create([
            'workspace_id' => workspace()->id,
            'agent_id' => $agent->id,
            'tool_slug' => $data['toolSlug'],
            'scope' => $data['scope'],
            'run_id' => $data['scope'] === 'run' ? $data['runId'] : null,
            'task_id' => $data['scope'] === 'task' ? $data['taskId'] : null,
            'expires_at' => $data['scope'] === 'until' ? $data['expiresAt'] : null,
            'granted_by_id' => $request->user()->id,
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json($grant, 201);
    }

    public function destroy(string $agentId, string $grantId): JsonResponse
    {
        $agent = $this->agent($agentId);
        $grant = PolicyGrant::forWorkspace()->where('agent_id', $agent->id)->findOrFail($grantId);
        $grant->update(['revoked_at' => now()]);

        return response()->json(['revoked' => true]);
    }

    private function agent(string $agentId): User
    {
        return User::query()
            ->where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->findOrFail($agentId);
    }
}
