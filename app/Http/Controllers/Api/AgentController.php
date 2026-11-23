<?php

namespace App\Http\Controllers\Api;

use App\Agents\Providers\AgentBrainValidator;
use App\Domain\Agents\Application\CreateAgent;
use App\Domain\Agents\Application\CreateAgentInput;
use App\Domain\Agents\Application\DeleteAgent;
use App\Domain\Agents\Application\Queries\AgentDetailQuery;
use App\Domain\Agents\Application\UpdateAgentProfile;
use App\Domain\Authorization\Application\AuthorizationService;
use App\Domain\Knowledge\Application\ReadAgentPromptDocuments;
use App\Domain\Knowledge\Application\UpdateAgentIdentityFile;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * API surface for creating and managing workspace agents.
 *
 * Agent records are runtime identities, not just profile rows. Creating one also
 * creates its identity document tree, avatar, default DM channel, and initial
 * channel membership, so this controller keeps those side effects together.
 *
 * Authorization (GIN-1201): explicit capability decisions through
 * AuthorizationService instead of plain workspace membership.
 * - `agent.create`: owner, admin, approver, member (never viewer); only an
 *   admin may name another human as the new agent's manager.
 * - `agent.update`, `agent.update_identity`, `agent.delete`: admins for any
 *   agent; otherwise only the agent's direct manager.
 * - `agent.reassign_manager`: admins only; the new manager must be a member
 *   or agent of this workspace and never the agent itself.
 * Grants live in AgentPermissionController (`agent.update_grants`).
 * Agent ids from another workspace are a 404.
 */
class AgentController extends Controller
{
    public function __construct(
        private AgentBrainValidator $brainValidator,
        private AuthorizationService $authorization,
    ) {}

    /**
     * List all agents
     *
     * @return Collection<int, User>
     */
    public function index(): Collection
    {
        return User::where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new agent
     */
    public function store(Request $request, CreateAgent $createAgent): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'agentType' => 'required|in:general,writer,analyst,creative,researcher,coder,coordinator,workspace-manager',
            'brain' => 'required|string',
            'task' => 'nullable|string',
            'isEphemeral' => 'nullable|boolean',
            'managerId' => 'nullable|string|exists:users,id',
            'identity' => 'nullable|array',
            'identity.IDENTITY' => 'nullable|string',
            'identity.INSTRUCTIONS' => 'nullable|string',
            'identity.MEMORY' => 'nullable|string',
        ]);

        // A manager must be a member or agent of this workspace; naming
        // someone else as manager needs an admin (decided by the engine).
        $managerId = $validated['managerId'] ?? null;
        if ($managerId !== null && ! $this->isWorkspaceParticipant((string) $managerId)) {
            return response()->json(['message' => 'The manager must belong to this workspace.', 'error' => 'target_not_in_workspace'], 422);
        }
        $this->authorization->authorize($request->user(), 'agent.create', workspace(), ['manager_id' => $managerId]);

        try {
            $agent = $createAgent->handle(
                workspace(),
                $request->user(),
                CreateAgentInput::fromValidated($validated),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'example' => $this->brainValidator->example(),
            ], 422);
        }

        return response()->json($agent, 201);
    }

    /**
     * Get a specific agent with enriched detail data
     */
    public function show(Request $request, string $id, AgentDetailQuery $query): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'agent.read', workspace());

        return response()->json($query->handle(workspace(), $id));
    }

    /**
     * Update an agent
     */
    public function update(Request $request, string $id, UpdateAgentProfile $updateAgent): JsonResponse
    {
        $agent = User::where('type', 'agent')->where('workspace_id', workspace()->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'brain' => 'sometimes|string',
            'status' => 'sometimes|in:idle,working,offline,awaiting_approval',
            'currentTask' => 'sometimes|nullable|string',
            'mustWaitForApproval' => 'sometimes|nullable|boolean',
            'managerId' => 'sometimes|nullable|string|exists:users,id',
            'sleepingUntil' => 'sometimes|nullable|date',
            'sleepingReason' => 'sometimes|nullable|string|max:500',
        ]);

        // Invariant: the profile decision first, then a manager change needs
        // its own admin decision and a valid target.
        $this->authorization->authorize($request->user(), 'agent.update', $agent);
        if (array_key_exists('managerId', $validated) && $validated['managerId'] !== $agent->manager_id) {
            $newManager = $validated['managerId'];
            if ($newManager !== null && ($newManager === $agent->id || ! $this->isWorkspaceParticipant((string) $newManager))) {
                return response()->json(['message' => 'The manager must be another member or agent of this workspace.', 'error' => 'target_not_in_workspace'], 422);
            }
            $this->authorization->authorize($request->user(), 'agent.reassign_manager', $agent, ['new_manager_id' => $newManager]);
        }

        try {
            $agent = $updateAgent->handle(workspace(), $agent, $validated);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'example' => $this->brainValidator->example(),
            ], 422);
        }

        return response()->json($agent);
    }

    /**
     * Delete an agent
     */
    public function destroy(Request $request, string $id, DeleteAgent $deleteAgent): JsonResponse
    {
        $agent = User::where('type', 'agent')->where('workspace_id', workspace()->id)->findOrFail($id);
        $this->authorization->authorize($request->user(), 'agent.delete', $agent);
        $deleteAgent->handle($agent);

        return response()->json(['success' => true]);
    }

    /**
     * Get identity files for an agent
     */
    public function identityFiles(string $id, ReadAgentPromptDocuments $promptDocuments): JsonResponse
    {
        $agent = User::where('type', 'agent')->where('workspace_id', workspace()->id)->findOrFail($id);
        $files = $promptDocuments->handle($agent);

        return response()->json($files->map(function ($file) {
            return [
                'id' => $file->id,
                'type' => str_replace('.md', '', $file->title),
                'title' => $file->title,
                'content' => $file->content,
                'updatedAt' => $file->updated_at,
            ];
        }));
    }

    /**
     * Update an identity file for an agent
     */
    public function updateIdentityFile(Request $request, string $id, string $fileType, UpdateAgentIdentityFile $updateIdentityFile): JsonResponse
    {
        $agent = User::where('type', 'agent')->where('workspace_id', workspace()->id)->findOrFail($id);
        $this->authorization->authorize($request->user(), 'agent.update_identity', $agent, ['file' => $fileType]);

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        try {
            $file = $updateIdentityFile->handle(
                $agent,
                $fileType,
                $validated['content']
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }

        if (! $file) {
            return response()->json(['error' => 'Identity file not found'], 404);
        }

        return response()->json([
            'id' => $file->id,
            'type' => str_replace('.md', '', $file->title),
            'title' => $file->title,
            'content' => $file->content,
            'updatedAt' => $file->updated_at,
        ]);
    }

    /** Whether a user id is a member (human) or agent of this workspace. */
    private function isWorkspaceParticipant(string $userId): bool
    {
        return WorkspaceMember::query()->where('workspace_id', workspace()->id)->where('user_id', $userId)->exists()
            || User::query()->where('type', 'agent')->where('workspace_id', workspace()->id)->whereKey($userId)->exists();
    }
}
