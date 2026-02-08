<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentPermission;
use App\Models\User;
use App\Agents\Tools\ToolRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentPermissionController extends Controller
{
    public function __construct(
        private ToolRegistry $toolRegistry,
    ) {}

    /**
     * Get all permissions for an agent, organized by scope type.
     */
    public function index(string $id)
    {
        $agent = User::where('type', 'agent')->findOrFail($id);

        return response()->json([
            'tools' => $this->toolRegistry->getAllToolsMeta($agent),
            'channelIds' => AgentPermission::forAgent($id)->channels()->allowed()->pluck('scope_key')->values(),
            'folderIds' => AgentPermission::forAgent($id)->folders()->allowed()->pluck('scope_key')->values(),
            'behaviorMode' => $agent->behavior_mode ?? 'autonomous',
        ]);
    }

    /**
     * Bulk update tool permissions for an agent.
     */
    public function updateTools(Request $request, string $id)
    {
        $agent = User::where('type', 'agent')->findOrFail($id);

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

        return response()->json([
            'tools' => $this->toolRegistry->getAllToolsMeta($agent),
        ]);
    }

    /**
     * Set allowed channels for an agent.
     * Empty array = unrestricted (no channel permissions enforced).
     */
    public function updateChannels(Request $request, string $id)
    {
        User::where('type', 'agent')->findOrFail($id);

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

        return response()->json([
            'channelIds' => AgentPermission::forAgent($id)->channels()->allowed()->pluck('scope_key')->values(),
        ]);
    }

    /**
     * Set enabled integrations for an agent.
     * Accepts list of enabled integration app names.
     */
    public function updateIntegrations(Request $request, string $id)
    {
        User::where('type', 'agent')->findOrFail($id);

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

        return response()->json([
            'enabledIntegrations' => $enabled,
        ]);
    }

    /**
     * Set allowed document folders for an agent.
     * Empty array = unrestricted (no folder permissions enforced).
     */
    public function updateFolders(Request $request, string $id)
    {
        User::where('type', 'agent')->findOrFail($id);

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

        return response()->json([
            'folderIds' => AgentPermission::forAgent($id)->folders()->allowed()->pluck('scope_key')->values(),
        ]);
    }
}
