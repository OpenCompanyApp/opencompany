<?php

namespace App\Http\Controllers\Api;

use App\Agents\Providers\AgentBrainValidator;
use App\Agents\Tools\ToolRegistry;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\DirectMessage;
use App\Models\Document;
use App\Models\Task;
use App\Models\User;
use App\Services\AgentAvatarService;
use App\Services\AgentDocumentService;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
    public function __construct(
        private AgentDocumentService $agentDocumentService,
        private AgentAvatarService $agentAvatarService,
        private AgentBrainValidator $brainValidator,
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
    public function store(Request $request): JsonResponse
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
            // Brain validation happens before any side effects so a bad provider
            // or model never leaves behind a half-created agent/document tree.
            $this->brainValidator->validate($validated['brain'], workspace()->id);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'example' => $this->brainValidator->example(),
            ], 422);
        }

        // Agents belong directly to the current workspace. The manager defaults
        // to the creator so interagent permissions have an initial hierarchy.
        $agent = User::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'name' => $validated['name'],
            'type' => 'agent',
            'agent_type' => $validated['agentType'],
            'brain' => $validated['brain'],
            'status' => 'idle',
            'presence' => 'online',
            'is_ephemeral' => $validated['isEphemeral'] ?? false,
            'current_task' => $validated['task'] ?? null,
            'manager_id' => $validated['managerId'] ?? $request->user()->id,
        ]);

        // Identity and memory documents are system documents used by prompt
        // assembly. They are created immediately so the agent is runnable.
        $identityContent = $validated['identity'] ?? [];
        $agentFolder = $this->agentDocumentService->createAgentDocumentStructure($agent, $identityContent);

        // Store the folder reference
        $agent->update(['docs_folder_id' => $agentFolder->id]);

        // Generate procedural avatar
        $this->agentAvatarService->generate($agent);

        // Create the default DM thread that the response pipeline expects for
        // first contact and onboarding messages.
        $creatorId = $request->user()->id;
        $creator = User::find($creatorId);
        $dmChannel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'name' => 'DM: '.($creator->name ?? 'User').' ↔ '.$agent->name,
            'type' => 'dm',
            'is_ephemeral' => false,
        ]);

        ChannelMember::create([
            'channel_id' => $dmChannel->id,
            'user_id' => $creatorId,
        ]);
        ChannelMember::create([
            'channel_id' => $dmChannel->id,
            'user_id' => $agent->id,
        ]);

        // Create the DirectMessage record (required for agent response pipeline)
        DirectMessage::create([
            'id' => Str::uuid()->toString(),
            'user1_id' => $creatorId,
            'user2_id' => $agent->id,
            'channel_id' => $dmChannel->id,
        ]);

        // General-channel membership is convenience only. Private memory context
        // still stays out of public channels in OpenCompanyAgent.
        $generalChannel = Channel::forWorkspace()->where('name', 'general')->first();
        if ($generalChannel) {
            ChannelMember::create([
                'channel_id' => $generalChannel->id,
                'user_id' => $agent->id,
            ]);
        }

        return response()->json($agent->fresh(), 201);
    }

    /**
     * Get a specific agent with enriched detail data
     */
    public function show(string $id): JsonResponse
    {
        // All detail endpoints scope by workspace first; an agent UUID from
        // another workspace should look like a missing record.
        $agent = User::where('type', 'agent')->where('workspace_id', workspace()->id)->findOrFail($id);

        // The UI edits identity files by logical type rather than document ID,
        // so map IDENTITY.md, INSTRUCTIONS.md, and MEMORY.md into a stable shape.
        $identityFiles = $this->agentDocumentService->getIdentityFiles($agent);
        $filesByType = [];
        foreach ($identityFiles as $file) {
            $type = strtoupper(str_replace('.md', '', $file->title));
            $filesByType[$type] = [
                'content' => $file->content ?? '',
                'updatedAt' => $file->updated_at,
            ];
        }

        // Parse IDENTITY.md for structured identity info
        $identity = $this->parseIdentityContent(
            $filesByType['IDENTITY']['content'] ?? '',
            $agent
        );

        // Task stats
        /** @var object{total: int, completed: int}|null $taskStats */
        $taskStats = Task::where('agent_id', $agent->id)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->first();

        // Recent tasks
        $recentTasks = Task::where('agent_id', $agent->id)
            ->with(['requester', 'steps'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // ToolRegistry returns permission-aware capabilities for this agent.
        // Do not build tool lists directly here or package/MCP tools drift.
        $toolRegistry = app(ToolRegistry::class);
        $capabilities = $toolRegistry->getAllToolsMeta($agent);

        // Channel and folder permissions
        $channelPermissions = $agent->channelPermissions()->where('permission', 'allow')->pluck('scope_key')->values();
        $folderPermissions = $agent->folderPermissions()->where('permission', 'allow')->pluck('scope_key')->values();
        $fileFolderPermissions = $agent->fileFolderPermissions()->where('permission', 'allow')->pluck('scope_key')->values();

        // Agent's channel memberships (for the UI checklist)
        $agentChannels = $agent->channels()->get(['channels.id', 'channels.name', 'channels.type']);

        // Document folders (for the UI checklist)
        $documentFolders = Document::forWorkspace()
            ->where('is_folder', true)
            ->whereNull('parent_id')
            ->orderBy('title')
            ->get(['id', 'title']);

        // File folder permissions use WorkspaceFile IDs, so the checklist needs
        // the virtual folder tree rather than document folders.
        $fileFolders = app(FileSystemService::class)
            ->getFolderTree(workspace()->id);

        return response()->json([
            'id' => $agent->id,
            'name' => $agent->name,
            'avatar' => $agent->avatar,
            'type' => $agent->type,
            'agentType' => $agent->agent_type,
            'status' => $agent->status,
            'presence' => $agent->presence,
            'brain' => $agent->brain,
            'currentTask' => $agent->current_task,
            'behaviorMode' => $agent->behavior_mode ?? 'autonomous',
            'mustWaitForApproval' => $agent->must_wait_for_approval ?? false,
            'awaitingApprovalId' => $agent->awaiting_approval_id,
            'managerId' => $agent->manager_id,
            'manager' => $agent->manager ? [
                'id' => $agent->manager->id,
                'name' => $agent->manager->name,
                'type' => $agent->manager->type,
                'agentType' => $agent->manager->agent_type,
                'avatar' => $agent->manager->avatar,
            ] : null,
            'directReports' => $agent->directReports()
                ->select('id', 'name', 'type', 'agent_type', 'status', 'avatar')
                ->get(),
            'sleepingUntil' => $agent->sleeping_until,
            'sleepingReason' => $agent->sleeping_reason,
            'awaitingDelegationIds' => $agent->awaiting_delegation_ids,
            'identity' => $identity,
            'capabilities' => $capabilities,
            'appGroups' => $toolRegistry->getAppGroupsMeta(),
            'enabledIntegrations' => app(AgentPermissionService::class)->getEnabledIntegrations($agent),
            'channelPermissions' => $channelPermissions,
            'folderPermissions' => $folderPermissions,
            'fileFolderPermissions' => $fileFolderPermissions,
            'agentChannels' => $agentChannels,
            'documentFolders' => $documentFolders,
            'fileFolders' => $fileFolders,
            'stats' => [
                'tasksCompleted' => (int) ($taskStats->completed ?? 0),
                'totalTasks' => (int) ($taskStats->total ?? 0),
                'efficiency' => $taskStats->total > 0
                    ? (int) round(($taskStats->completed / $taskStats->total) * 100)
                    : 0,
                'totalSessions' => 0,
            ],
            'tasks' => $recentTasks,
        ]);
    }

    /**
     * Parse IDENTITY.md content to extract structured identity data
     *
     * @return array<string, mixed>
     */
    private function parseIdentityContent(string $content, User $agent): array
    {
        $identity = [
            'name' => $agent->name,
            'emoji' => '🤖',
            'type' => $agent->agent_type ?? 'coder',
            'description' => '',
        ];

        if (! $content) {
            return $identity;
        }

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            // Keep parsing permissive because humans and agents both edit this
            // Markdown file. Only a few keys are structured; everything else
            // remains free-form prompt content.
            if (preg_match('/^(?:-\s*\*\*|\*\*|#+\s*)?(\w+)(?:\*\*)?\s*:\s*(.+)$/i', $line, $matches)) {
                $key = strtolower(trim($matches[1]));
                $value = trim($matches[2]);
                match ($key) {
                    'name' => $identity['name'] = $value,
                    'emoji' => $identity['emoji'] = $value,
                    'type' => $identity['type'] = strtolower($value),
                    'description', 'vibe' => $identity['description'] = $value,
                    default => null,
                };
            }
        }

        return $identity;
    }

    /**
     * Update an agent
     */
    public function update(Request $request, string $id): JsonResponse
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

        // Brain changes affect live provider/model routing, so validate before
        // persisting the new value.
        if (isset($validated['brain'])) {
            try {
                $this->brainValidator->validate($validated['brain'], workspace()->id);
            } catch (InvalidArgumentException $e) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'example' => $this->brainValidator->example(),
                ], 422);
            }
        }

        $agent->update([
            'name' => $validated['name'] ?? $agent->name,
            'brain' => $validated['brain'] ?? $agent->brain,
            'status' => $validated['status'] ?? $agent->status,
            'current_task' => $validated['currentTask'] ?? $agent->current_task,
            'behavior_mode' => $validated['behaviorMode'] ?? $agent->behavior_mode,
            'must_wait_for_approval' => $validated['mustWaitForApproval'] ?? $agent->must_wait_for_approval,
            'manager_id' => array_key_exists('managerId', $validated) ? $validated['managerId'] : $agent->manager_id,
            'sleeping_until' => array_key_exists('sleepingUntil', $validated) ? $validated['sleepingUntil'] : $agent->sleeping_until,
            'sleeping_reason' => array_key_exists('sleepingReason', $validated) ? $validated['sleepingReason'] : $agent->sleeping_reason,
        ]);

        return response()->json($agent);
    }

    /**
     * Delete an agent
     */
    public function destroy(string $id): JsonResponse
    {
        $agent = User::where('type', 'agent')->where('workspace_id', workspace()->id)->findOrFail($id);
        $this->agentDocumentService->deleteAgentDocumentStructure($agent);
        $agent->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get identity files for an agent
     */
    public function identityFiles(string $id): JsonResponse
    {
        $agent = User::where('type', 'agent')->where('workspace_id', workspace()->id)->findOrFail($id);
        $files = $this->agentDocumentService->getIdentityFiles($agent);

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
    public function updateIdentityFile(Request $request, string $id, string $fileType): JsonResponse
    {
        $agent = User::where('type', 'agent')->where('workspace_id', workspace()->id)->findOrFail($id);

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $allowedTypes = app(AgentDocumentService::class)->getIdentityFileTypes();
        $normalizedType = strtoupper($fileType);

        if (! in_array($normalizedType, $allowedTypes)) {
            // Restrict writes to known prompt document types. Arbitrary file
            // creation belongs in the document APIs, not the identity endpoint.
            return response()->json([
                'error' => "Invalid file type '{$fileType}'. Allowed: ".implode(', ', $allowedTypes),
            ], 422);
        }

        $file = $this->agentDocumentService->updateIdentityFile(
            $agent,
            $normalizedType,
            $validated['content']
        );

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
