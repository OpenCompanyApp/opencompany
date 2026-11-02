<?php

namespace App\Http\Controllers\Api;

use App\Domain\Governance\RunReverter;
use App\Http\Controllers\Controller;
use App\Models\AgentRunRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Revert this run" API (governance-model.md "Change sets").
 *
 * Owns: resolving the run inside the active workspace and handing it to
 * RunReverter, attributed to the requesting human. Access is workspace-level
 * until Spaces exist; any human member may revert an agent run in their
 * workspace (reverting only restores earlier state and never overwrites a
 * value changed since). Agents have no API session, so they cannot call this.
 */
class AgentRunRevertController extends Controller
{
    public function __invoke(Request $request, string $runId, RunReverter $reverter): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->type === 'agent', 403);

        $run = AgentRunRecord::query()
            ->where('workspace_id', workspace()->id)
            ->findOrFail($runId);

        return response()->json($reverter->revert($run, $user));
    }
}
