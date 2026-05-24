<?php

namespace App\Http\Controllers\Api;

use App\Agents\Providers\AgentBrainValidator;
use App\Domain\Agents\Application\CreateAgent;
use App\Domain\Agents\Application\CreateAgentInput;
use App\Domain\Agents\Application\DeleteAgent;
use App\Domain\Agents\Application\Queries\AgentDetailQuery;
use App\Domain\Agents\Application\UpdateAgentProfile;
use App\Domain\Knowledge\Application\ReadAgentPromptDocuments;
use App\Domain\Knowledge\Application\UpdateAgentIdentityFile;
use App\Http\Controllers\Controller;
use App\Models\User;
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
 */
class AgentController extends Controller
{
    public function __construct(private AgentBrainValidator $brainValidator) {}

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
            'behavior' => 'nullable|in:autonomous,supervised,strict',
            'isEphemeral' => 'nullable|boolean',
            'managerId' => 'nullable|string|exists:users,id',
            'identity' => 'nullable|array',
            'identity.IDENTITY' => 'nullable|string',
            'identity.INSTRUCTIONS' => 'nullable|string',
            'identity.MEMORY' => 'nullable|string',
        ]);

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
    public function show(string $id, AgentDetailQuery $query): JsonResponse
    {
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
            'behaviorMode' => 'sometimes|nullable|in:autonomous,supervised,strict',
            'mustWaitForApproval' => 'sometimes|nullable|boolean',
            'managerId' => 'sometimes|nullable|string|exists:users,id',
            'sleepingUntil' => 'sometimes|nullable|date',
            'sleepingReason' => 'sometimes|nullable|string|max:500',
        ]);

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
    public function destroy(string $id, DeleteAgent $deleteAgent): JsonResponse
    {
        $agent = User::where('type', 'agent')->where('workspace_id', workspace()->id)->findOrFail($id);
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
}
