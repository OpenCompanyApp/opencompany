<?php

namespace App\Http\Controllers\Api;

use App\Agents\Tools\ToolRegistry;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\IntegrationSetting;
use App\Models\Task;
use App\Models\User;
use App\Services\AgentDocumentService;
use App\Services\AgentPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    public function __construct(
        private AgentDocumentService $agentDocumentService
    ) {}

    /**
     * List all agents
     */
    public function index()
    {
        return User::where('type', 'agent')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new agent
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'agentType' => 'required|in:general,writer,analyst,creative,researcher,coder,coordinator,workspace-manager',
            'brain' => 'required|string',
            'task' => 'nullable|string',
            'behavior' => 'nullable|in:autonomous,supervised,strict',
            'isEphemeral' => 'nullable|boolean',
            'identity' => 'nullable|array',
            'identity.IDENTITY' => 'nullable|string',
            'identity.SOUL' => 'nullable|string',
            'identity.USER' => 'nullable|string',
            'identity.AGENTS' => 'nullable|string',
            'identity.TOOLS' => 'nullable|string',
            'identity.HEARTBEAT' => 'nullable|string',
            'identity.BOOTSTRAP' => 'nullable|string',
            'identity.MEMORY' => 'nullable|string',
        ]);

        // Validate brain format (provider:model)
        if (!str_contains($validated['brain'], ':')) {
            return response()->json([
                'error' => 'Invalid brain format. Expected "provider:model" (e.g., "glm:glm-4.7")',
            ], 422);
        }

        [$provider] = explode(':', $validated['brain'], 2);

        // Standard providers use .env keys; only check IntegrationSetting for custom providers
        $standardProviders = ['anthropic', 'openai', 'gemini', 'groq', 'xai', 'openrouter', 'deepseek', 'mistral', 'ollama'];

        if (!in_array($provider, $standardProviders)) {
            $integration = IntegrationSetting::where('integration_id', $provider)
                ->where('enabled', true)
                ->first();

            if (!$integration) {
                return response()->json([
                    'error' => "AI model provider '{$provider}' is not configured or enabled. Please configure it in Integrations.",
                ], 422);
            }
        }

        // Create the agent user
        $agent = User::create([
            'id' => Str::uuid()->toString(),
            'name' => $validated['name'],
            'type' => 'agent',
            'agent_type' => $validated['agentType'],
            'brain' => $validated['brain'],
            'status' => 'idle',
            'presence' => 'online',
            'is_ephemeral' => $validated['isEphemeral'] ?? false,
            'current_task' => $validated['task'] ?? null,
        ]);

        // Create the document structure for this agent
        $identityContent = $validated['identity'] ?? [];
        $agentFolder = $this->agentDocumentService->createAgentDocumentStructure($agent, $identityContent);

        // Store the folder reference
        $agent->update(['docs_folder_id' => $agentFolder->id]);

        // Create DM channel between creator and agent
        $creatorId = $request->user()?->id ?? 'h1';
        $creator = User::find($creatorId);
        $dmChannel = Channel::create([
            'id' => Str::uuid()->toString(),
            'name' => 'DM: ' . ($creator?->name ?? 'User') . ' ↔ ' . $agent->name,
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

        // Add agent to #general channel by default
        $generalChannel = Channel::where('name', 'general')->first();
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
    public function show(string $id)
    {
        $agent = User::where('type', 'agent')->findOrFail($id);

        // Load identity files and map by type
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
        $taskStats = Task::where('agent_id', $agent->id)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->first();

        // Recent tasks
        $recentTasks = Task::where('agent_id', $agent->id)
            ->with(['requester', 'steps'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Capabilities from ToolRegistry (permission-aware)
        $toolRegistry = app(ToolRegistry::class);
        $capabilities = $toolRegistry->getAllToolsMeta($agent);

        // Channel and folder permissions
        $channelPermissions = $agent->channelPermissions()->where('permission', 'allow')->pluck('scope_key')->values();
        $folderPermissions = $agent->folderPermissions()->where('permission', 'allow')->pluck('scope_key')->values();

        // Agent's channel memberships (for the UI checklist)
        $agentChannels = $agent->channels()->get(['channels.id', 'channels.name', 'channels.type']);

        // Document folders (for the UI checklist)
        $documentFolders = \App\Models\Document::where('is_folder', true)
            ->whereNull('parent_id')
            ->orderBy('title')
            ->get(['id', 'title']);

        return response()->json([
            'id' => $agent->id,
            'name' => $agent->name,
            'type' => $agent->type,
            'agentType' => $agent->agent_type,
            'status' => $agent->status,
            'presence' => $agent->presence,
            'brain' => $agent->brain,
            'currentTask' => $agent->current_task,
            'behaviorMode' => $agent->behavior_mode ?? 'autonomous',
            'mustWaitForApproval' => $agent->must_wait_for_approval ?? false,
            'awaitingApprovalId' => $agent->awaiting_approval_id,
            'identity' => $identity,
            'personality' => $filesByType['SOUL'] ?? null,
            'instructions' => $filesByType['AGENTS'] ?? null,
            'capabilities' => $capabilities,
            'appGroups' => $toolRegistry->getAppGroupsMeta(),
            'enabledIntegrations' => app(AgentPermissionService::class)->getEnabledIntegrations($agent),
            'channelPermissions' => $channelPermissions,
            'folderPermissions' => $folderPermissions,
            'agentChannels' => $agentChannels,
            'documentFolders' => $documentFolders,
            'toolNotes' => $filesByType['TOOLS']['content'] ?? '',
            'memoryContent' => $filesByType['MEMORY']['content'] ?? '',
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
     */
    private function parseIdentityContent(string $content, User $agent): array
    {
        $identity = [
            'name' => $agent->name,
            'emoji' => '🤖',
            'type' => $agent->agent_type ?? 'coder',
            'description' => '',
        ];

        if (!$content) {
            return $identity;
        }

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            // Match "- **Key**: Value" or "Key: Value"
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
     * Convert PascalCase class name to human-readable string
     */
    private function humanizeClassName(string $className): string
    {
        return trim(preg_replace('/([A-Z])/', ' $1', $className));
    }

    /**
     * Update an agent
     */
    public function update(Request $request, string $id)
    {
        $agent = User::where('type', 'agent')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'brain' => 'sometimes|string',
            'status' => 'sometimes|in:idle,working,offline,awaiting_approval',
            'currentTask' => 'sometimes|nullable|string',
            'behaviorMode' => 'sometimes|nullable|in:autonomous,supervised,strict',
            'mustWaitForApproval' => 'sometimes|nullable|boolean',
        ]);

        // If updating brain, validate the format and integration
        if (isset($validated['brain'])) {
            if (!str_contains($validated['brain'], ':')) {
                return response()->json([
                    'error' => 'Invalid brain format. Expected "provider:model"',
                ], 422);
            }

            [$provider] = explode(':', $validated['brain'], 2);
            $standardProviders = ['anthropic', 'openai', 'gemini', 'groq', 'xai', 'openrouter', 'deepseek', 'mistral', 'ollama'];

            if (!in_array($provider, $standardProviders)) {
                $integration = IntegrationSetting::where('integration_id', $provider)
                    ->where('enabled', true)
                    ->first();

                if (!$integration) {
                    return response()->json([
                        'error' => "AI model provider '{$provider}' is not configured or enabled.",
                    ], 422);
                }
            }
        }

        $agent->update([
            'name' => $validated['name'] ?? $agent->name,
            'brain' => $validated['brain'] ?? $agent->brain,
            'status' => $validated['status'] ?? $agent->status,
            'current_task' => $validated['currentTask'] ?? $agent->current_task,
            'behavior_mode' => $validated['behaviorMode'] ?? $agent->behavior_mode,
            'must_wait_for_approval' => $validated['mustWaitForApproval'] ?? $agent->must_wait_for_approval,
        ]);

        return response()->json($agent);
    }

    /**
     * Delete an agent
     */
    public function destroy(string $id)
    {
        $agent = User::where('type', 'agent')->findOrFail($id);
        $this->agentDocumentService->deleteAgentDocumentStructure($agent);
        $agent->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get identity files for an agent
     */
    public function identityFiles(string $id)
    {
        $agent = User::where('type', 'agent')->findOrFail($id);
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
    public function updateIdentityFile(Request $request, string $id, string $fileType)
    {
        $agent = User::where('type', 'agent')->findOrFail($id);

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $file = $this->agentDocumentService->updateIdentityFile(
            $agent,
            $fileType,
            $validated['content']
        );

        if (!$file) {
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
