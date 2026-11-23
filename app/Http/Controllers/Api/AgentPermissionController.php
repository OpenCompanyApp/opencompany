<?php

namespace App\Http\Controllers\Api;

use App\Domain\Authorization\Application\AuthorizationService;
use App\Http\Controllers\Controller;
use App\Models\AgentPermission;
use App\Models\User;
use App\Agents\Tools\ToolRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Reads and replaces an agent's grants (tools, channels, document folders,
 * file folders, integrations).
 *
 * Authorization (GIN-1201): replacing grants is `agent.update_grants`, an
 * admin capability; an agent's manager cannot widen what it may do. Every
 * replacement is recorded in the authorization ledger with the before and
 * after grant sets (ids and flags only).
 */
class AgentPermissionController extends Controller
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private AuthorizationService $authorization,
    ) {}

    /**
     * Get all permissions for an agent, organized by scope type.
     */
    public function index(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $agent = User::where('type', 'agent')->where('workspace_id', workspace()->id)->findOrFail($id);
        $this->authorization->authorize($request->user(), 'agent.read', $agent);

        return response()->json([
            'tools' => $this->toolRegistry->getAllToolsMeta($agent),
            'channelIds' => AgentPermission::forAgent($id)->channels()->allowed()->pluck('scope_key')->values(),
            'folderIds' => AgentPermission::forAgent($id)->folders()->allowed()->pluck('scope_key')->values(),
        ]);
    }

    /**
     * Bulk update tool permissions for an agent.
     */
    public function updateTools(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $agent = $this->authorizeGrants($request, $id);
        $before = $this->grantSnapshot($id, 'tool');

        $validated = $request->validate([
            'tools' => 'required|array',
            'tools.*.scopeKey' => 'required|string',
            'tools.*.permission' => 'required|in:allow,deny',
            'tools.*.requiresApproval' => 'required|boolean',
        ]);

        // Delete existing tool permissions
        AgentPermission::forAgent($id)->tools()->delete();

        // Insert new ones
        foreach ($validated['tools'] as $tool) {
            AgentPermission::create([
                'id' => Str::uuid()->toString(),
                'agent_id' => $id,
                'scope_type' => 'tool',
                'scope_key' => $tool['scopeKey'],
                'permission' => $tool['permission'],
                'requires_approval' => $tool['requiresApproval'],
            ]);
        }

        $this->recordGrantChange($request, $agent, 'tool', $before);

        return response()->json([
            'tools' => $this->toolRegistry->getAllToolsMeta($agent),
        ]);
    }

    /**
     * Set allowed channels for an agent.
     * Empty array = unrestricted (no channel permissions enforced).
     */
    public function updateChannels(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $agent = $this->authorizeGrants($request, $id);
        $before = $this->grantSnapshot($id, 'channel');

        $validated = $request->validate([
            'channels' => 'present|array',
            'channels.*' => 'string',
        ]);

        // Delete existing channel permissions
        AgentPermission::forAgent($id)->channels()->delete();

        // Insert new ones (empty array = unrestricted)
        foreach ($validated['channels'] as $channelId) {
            AgentPermission::create([
                'id' => Str::uuid()->toString(),
                'agent_id' => $id,
                'scope_type' => 'channel',
                'scope_key' => $channelId,
                'permission' => 'allow',
                'requires_approval' => false,
            ]);
        }

        $this->recordGrantChange($request, $agent, 'channel', $before);

        return response()->json([
            'channelIds' => AgentPermission::forAgent($id)->channels()->allowed()->pluck('scope_key')->values(),
        ]);
    }

    /**
     * Set enabled integrations for an agent.
     * Accepts list of enabled integration app names.
     */
    public function updateIntegrations(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $agent = $this->authorizeGrants($request, $id);
        $before = $this->grantSnapshot($id, 'integration');

        $validated = $request->validate([
            'integrations' => 'present|array',
            'integrations.*' => 'string|in:' . implode(',', $this->toolRegistry->getEffectiveIntegrationApps()),
        ]);

        $enabled = $validated['integrations'];

        // Delete existing integration permissions
        AgentPermission::forAgent($id)->where('scope_type', 'integration')->delete();

        // Create records for all integration apps
        foreach ($this->toolRegistry->getEffectiveIntegrationApps() as $app) {
            AgentPermission::create([
                'id' => Str::uuid()->toString(),
                'agent_id' => $id,
                'scope_type' => 'integration',
                'scope_key' => $app,
                'permission' => in_array($app, $enabled) ? 'allow' : 'deny',
                'requires_approval' => false,
            ]);
        }

        $this->recordGrantChange($request, $agent, 'integration', $before);

        return response()->json([
            'enabledIntegrations' => $enabled,
        ]);
    }

    /**
     * Set allowed file system folders for an agent.
     * Empty array = home folder only (default). ['*'] = unrestricted.
     */
    public function updateFileFolders(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $agent = $this->authorizeGrants($request, $id);
        $before = $this->grantSnapshot($id, 'file_folder');

        $validated = $request->validate([
            'folders' => 'present|array',
            'folders.*' => 'string',
        ]);

        // Delete existing file folder permissions
        AgentPermission::forAgent($id)->where('scope_type', 'file_folder')->delete();

        // Insert new ones
        foreach ($validated['folders'] as $folderId) {
            AgentPermission::create([
                'id' => Str::uuid()->toString(),
                'agent_id' => $id,
                'scope_type' => 'file_folder',
                'scope_key' => $folderId,
                'permission' => 'allow',
                'requires_approval' => false,
            ]);
        }

        $this->recordGrantChange($request, $agent, 'file_folder', $before);

        return response()->json([
            'fileFolderIds' => AgentPermission::forAgent($id)
                ->where('scope_type', 'file_folder')->where('permission', 'allow')
                ->pluck('scope_key')->values(),
        ]);
    }

    /**
     * Set allowed document folders for an agent.
     * Empty array = unrestricted (no folder permissions enforced).
     */
    public function updateFolders(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $agent = $this->authorizeGrants($request, $id);
        $before = $this->grantSnapshot($id, 'folder');

        $validated = $request->validate([
            'folders' => 'present|array',
            'folders.*' => 'string',
        ]);

        // Delete existing folder permissions
        AgentPermission::forAgent($id)->folders()->delete();

        // Insert new ones
        foreach ($validated['folders'] as $folderId) {
            AgentPermission::create([
                'id' => Str::uuid()->toString(),
                'agent_id' => $id,
                'scope_type' => 'folder',
                'scope_key' => $folderId,
                'permission' => 'allow',
                'requires_approval' => false,
            ]);
        }

        $this->recordGrantChange($request, $agent, 'folder', $before);

        return response()->json([
            'folderIds' => AgentPermission::forAgent($id)->folders()->allowed()->pluck('scope_key')->values(),
        ]);
    }

    /** The agent in this workspace, after `agent.update_grants` is allowed. */
    private function authorizeGrants(Request $request, string $id): User
    {
        $agent = User::where('type', 'agent')->where('workspace_id', workspace()->id)->findOrFail($id);
        $this->authorization->authorize($request->user(), 'agent.update_grants', $agent);

        return $agent;
    }

    /** @return list<string> one "key:permission[:approval]" entry per row, sorted */
    private function grantSnapshot(string $agentId, string $scopeType): array
    {
        $rows = AgentPermission::forAgent($agentId)->where('scope_type', $scopeType)->get(['scope_key', 'permission', 'requires_approval'])
            ->map(fn (AgentPermission $row): string => $row->scope_key.':'.$row->permission.($row->requires_approval ? ':approval' : ''))
            ->sort()->values()->all();

        return $rows;
    }

    /** @param  list<string>  $before */
    private function recordGrantChange(Request $request, User $agent, string $scopeType, array $before): void
    {
        $this->authorization->recordChange($request->user(), workspace(), 'agent.grants.replace', ['type' => 'agent', 'id' => (string) $agent->id], [
            'scope_type' => $scopeType,
            'before' => $before,
            'after' => $this->grantSnapshot((string) $agent->id, $scopeType),
        ]);
    }
}
