<?php

namespace App\Domain\Agents\Application\Queries;

use App\Agents\Tools\ToolRegistry;
use App\Domain\Knowledge\Application\ReadAgentPromptDocuments;
use App\Models\Document;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;

/**
 * Builds the enriched agent-detail payload consumed by the agent settings UI.
 *
 * This is a read model, not an aggregate load. It intentionally gathers data
 * from several contexts so controllers do not accumulate presentation queries
 * and parsing rules.
 */
class AgentDetailQuery
{
    public function __construct(
        private ReadAgentPromptDocuments $promptDocuments,
        private ToolRegistry $toolRegistry,
        private AgentPermissionService $permissions,
        private FileSystemService $files,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Workspace $workspace, string $agentId): array
    {
        $agent = User::query()
            ->where('type', 'agent')
            ->where('workspace_id', $workspace->id)
            ->findOrFail($agentId);

        $filesByType = $this->identityFilesByType($agent);
        $identity = $this->parseIdentityContent(
            $filesByType['IDENTITY']['content'] ?? '',
            $agent
        );

        /** @var object{total: int, completed: int}|null $taskStats */
        $taskStats = Task::where('agent_id', $agent->id)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->first();

        $recentTasks = Task::where('agent_id', $agent->id)
            ->with(['requester', 'steps'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $channelPermissions = $agent->channelPermissions()->where('permission', 'allow')->pluck('scope_key')->values();
        $folderPermissions = $agent->folderPermissions()->where('permission', 'allow')->pluck('scope_key')->values();
        $fileFolderPermissions = $agent->fileFolderPermissions()->where('permission', 'allow')->pluck('scope_key')->values();

        $documentFolders = Document::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_folder', true)
            ->whereNull('parent_id')
            ->orderBy('title')
            ->get(['id', 'title']);

        $totalTasks = (int) ($taskStats->total ?? 0);
        $completedTasks = (int) ($taskStats->completed ?? 0);

        $capabilities = $this->toolRegistry->getAllToolsMeta($agent);

        return [
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
            'vfsPolicy' => $this->effectiveVfsPolicy(
                $agent,
                $capabilities,
                $channelPermissions->all(),
                $folderPermissions->all(),
                $fileFolderPermissions->all(),
            ),
            'appGroups' => $this->toolRegistry->getAppGroupsMeta(),
            'enabledIntegrations' => $this->permissions->getEnabledIntegrations($agent),
            'channelPermissions' => $channelPermissions,
            'folderPermissions' => $folderPermissions,
            'fileFolderPermissions' => $fileFolderPermissions,
            'agentChannels' => $agent->channels()->get(['channels.id', 'channels.name', 'channels.type']),
            'documentFolders' => $documentFolders,
            'fileFolders' => $this->files->getFolderTree($workspace->id),
            'stats' => [
                'tasksCompleted' => $completedTasks,
                'totalTasks' => $totalTasks,
                'efficiency' => $totalTasks > 0
                    ? (int) round(($completedTasks / $totalTasks) * 100)
                    : 0,
                'totalSessions' => 0,
            ],
            'tasks' => $recentTasks,
        ];
    }

    /**
     * Build the effective VFS policy summary consumed by the capabilities UI.
     *
     * This is deliberately a projection over existing permission sources rather
     * than a new policy store. Runtime VFS checks still evaluate the primitive
     * tool permissions plus domain resource scopes; the UI summary explains
     * that same state in mount/scope/action terms.
     *
     * @param  list<array<string, mixed>>  $capabilities
     * @param  list<string>  $channelPermissions
     * @param  list<string>  $folderPermissions
     * @param  list<string>  $fileFolderPermissions
     * @return array<string, mixed>
     */
    private function effectiveVfsPolicy(User $agent, array $capabilities, array $channelPermissions, array $folderPermissions, array $fileFolderPermissions): array
    {
        $tools = collect($capabilities)->keyBy('id');
        $exec = $tools->get('vfs_exec', []);
        $patch = $tools->get('vfs_patch', []);
        $write = $tools->get('vfs_write', []);

        $execEnabled = (bool) ($exec['enabled'] ?? false);
        $patchEnabled = (bool) ($patch['enabled'] ?? false);
        $writeEnabled = (bool) ($write['enabled'] ?? false);

        $writeApproval = ((bool) ($patch['requiresApproval'] ?? false) || (bool) ($write['requiresApproval'] ?? false))
            ? 'Writes'
            : $this->approvalLabelForMode((string) ($agent->behavior_mode ?? 'autonomous'));

        $rows = [
            [
                'mount' => 'Documents',
                'path' => '/docs',
                'scope' => $folderPermissions === [] ? 'All document folders' : count($folderPermissions).' selected folder(s)',
                'actions' => array_values(array_filter(['browse', 'read/search', $patchEnabled ? 'patch' : null])),
                'approval' => $patchEnabled ? $writeApproval : 'Inherit',
                'status' => $execEnabled ? 'enabled' : 'blocked',
                'reason' => $execEnabled ? null : 'vfs_exec is disabled',
            ],
            [
                'mount' => 'Files',
                'path' => '/files',
                'scope' => in_array('*', $fileFolderPermissions, true)
                    ? 'All file folders'
                    : ($fileFolderPermissions === [] ? 'Home folder only' : 'Home folder + '.count($fileFolderPermissions).' selected folder(s)'),
                'actions' => array_values(array_filter(['browse', 'read/search', $writeEnabled ? 'write/manage' : null])),
                'approval' => $writeEnabled ? $writeApproval : 'Inherit',
                'status' => $execEnabled ? 'enabled' : 'blocked',
                'reason' => $execEnabled ? null : 'vfs_exec is disabled',
            ],
            [
                'mount' => 'Agent memory',
                'path' => '/agents',
                'scope' => 'Own, manager, and direct-report agent documents',
                'actions' => array_values(array_filter(['browse', 'read/search', $patchEnabled ? 'patch' : null])),
                'approval' => $patchEnabled ? $writeApproval : 'Inherit',
                'status' => $execEnabled ? 'limited' : 'blocked',
                'reason' => $execEnabled ? 'Private memory remains scoped by agent relationship' : 'vfs_exec is disabled',
            ],
            [
                'mount' => 'Tasks',
                'path' => '/tasks',
                'scope' => 'Workspace task projections, windowed',
                'actions' => array_values(array_filter(['browse', 'read/search', $patchEnabled ? 'patch status/fields' : null])),
                'approval' => $patchEnabled ? $writeApproval : 'Inherit',
                'status' => $execEnabled ? 'enabled' : 'blocked',
                'reason' => $execEnabled ? null : 'vfs_exec is disabled',
            ],
            [
                'mount' => 'Lists',
                'path' => '/lists',
                'scope' => 'Workspace list projections, windowed',
                'actions' => array_values(array_filter(['browse', 'read/search', $patchEnabled ? 'patch status/fields' : null])),
                'approval' => $patchEnabled ? $writeApproval : 'Inherit',
                'status' => $execEnabled ? 'enabled' : 'blocked',
                'reason' => $execEnabled ? null : 'vfs_exec is disabled',
            ],
            [
                'mount' => 'Channels',
                'path' => '/channels',
                'scope' => $channelPermissions === [] ? 'All joined channels' : count($channelPermissions).' selected channel(s)',
                'actions' => ['browse', 'read/search'],
                'approval' => 'Inherit',
                'status' => $execEnabled ? 'enabled' : 'blocked',
                'reason' => $execEnabled ? null : 'vfs_exec is disabled',
            ],
            [
                'mount' => 'Tables',
                'path' => '/tables',
                'scope' => 'Workspace tables',
                'actions' => ['browse', 'read/search'],
                'approval' => 'Inherit',
                'status' => $execEnabled ? 'enabled' : 'blocked',
                'reason' => $execEnabled ? null : 'vfs_exec is disabled',
            ],
            [
                'mount' => 'Approvals',
                'path' => '/approvals',
                'scope' => 'Own requests and pending approvals',
                'actions' => ['browse', 'read'],
                'approval' => 'Inherit',
                'status' => $execEnabled ? 'limited' : 'blocked',
                'reason' => $execEnabled ? 'Decisions stay in approval tools' : 'vfs_exec is disabled',
            ],
            [
                'mount' => 'Automations',
                'path' => '/automations',
                'scope' => 'Workspace automation records',
                'actions' => ['browse', 'read'],
                'approval' => 'Inherit',
                'status' => $execEnabled ? 'limited' : 'blocked',
                'reason' => $execEnabled ? 'Runs and edits stay in automation tools' : 'vfs_exec is disabled',
            ],
        ];

        return [
            'interfaceEnabled' => $execEnabled || $patchEnabled || $writeEnabled,
            'tools' => [
                'exec' => ['enabled' => $execEnabled, 'approval' => (bool) ($exec['requiresApproval'] ?? false)],
                'patch' => ['enabled' => $patchEnabled, 'approval' => (bool) ($patch['requiresApproval'] ?? false)],
                'write' => ['enabled' => $writeEnabled, 'approval' => (bool) ($write['requiresApproval'] ?? false)],
            ],
            'summary' => [
                'Read/search: '.($execEnabled ? 'enabled across allowed mounts' : 'disabled'),
                'Writes: '.($patchEnabled || $writeEnabled ? strtolower($writeApproval) : 'disabled'),
                'Scopes: documents/files/channels reuse existing pickers',
            ],
            'rows' => $rows,
        ];
    }

    private function approvalLabelForMode(string $mode): string
    {
        return match ($mode) {
            'strict' => 'All',
            'supervised' => 'Writes',
            default => 'Inherit',
        };
    }

    /**
     * @return array<string, array{content: string, updatedAt: mixed}>
     */
    private function identityFilesByType(User $agent): array
    {
        $filesByType = [];

        foreach ($this->promptDocuments->handle($agent) as $file) {
            $type = strtoupper(str_replace('.md', '', $file->title));
            $filesByType[$type] = [
                'content' => $file->content ?? '',
                'updatedAt' => $file->updated_at,
            ];
        }

        return $filesByType;
    }

    /**
     * @return array{name: string, emoji: string, type: string, description: string}
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
}
