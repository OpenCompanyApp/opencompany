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
use Illuminate\Support\Collection;

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
        $tableRowUpdate = $tools->get('update_table_row', []);
        $identityUpdate = $tools->get('update_agent_identity_file', []);

        $execEnabled = (bool) ($exec['enabled'] ?? false);
        $patchEnabled = (bool) ($patch['enabled'] ?? false);
        $writeEnabled = (bool) ($write['enabled'] ?? false);
        $patchState = $this->vfsComposedState($tools, ['vfs_patch']);
        $writeState = $this->vfsComposedState($tools, ['vfs_write']);
        $execWriteState = $this->vfsComposedState($tools, ['vfs_exec', 'vfs_write']);
        $tableRowState = $this->vfsComposedState($tools, ['vfs_patch', 'update_table_row']);
        $identityState = $this->vfsComposedState($tools, ['vfs_patch', 'update_agent_identity_file']);

        $rows = [
            [
                'mount' => 'Documents',
                'path' => '/docs',
                'scope' => $folderPermissions === [] ? 'All document folders' : count($folderPermissions).' selected folder(s)',
                'actions' => $this->vfsActions(['browse', 'read/search', $patchState['enabled'] ? 'patch' : null]),
                'approval' => $patchState['approval'],
                'status' => $execEnabled ? 'enabled' : 'blocked',
                'reason' => $execEnabled ? $patchState['reason'] : 'vfs_exec is disabled',
            ],
            [
                'mount' => 'Files',
                'path' => '/files',
                'scope' => in_array('*', $fileFolderPermissions, true)
                    ? 'All file folders'
                    : ($fileFolderPermissions === [] ? 'Home folder only' : 'Home folder + '.count($fileFolderPermissions).' selected folder(s)'),
                'actions' => $this->vfsActions(['browse', 'read/search', $writeState['enabled'] ? 'write/manage' : null]),
                'approval' => $writeState['approval'],
                'status' => $execEnabled ? 'enabled' : 'blocked',
                'reason' => $execEnabled ? $execWriteState['reason'] : 'vfs_exec is disabled',
            ],
            [
                'mount' => 'Agent identity and memory',
                'path' => '/agents',
                'scope' => 'Own docs plus manager/direct-report read; managers can edit direct-report identity and MEMORY.md',
                'actions' => $this->vfsActions(['browse', 'read/search', $identityState['enabled'] ? 'patch identity' : null, $patchState['enabled'] ? 'patch scoped memory' : null]),
                'approval' => $identityState['enabled'] || $patchState['enabled'] ? $this->vfsMostRestrictiveApproval([$identityState, $patchState]) : 'Denied',
                'status' => $execEnabled ? 'limited' : 'blocked',
                'reason' => $execEnabled ? 'Identity uses VFS Patch + Update Agent Identity File; memory uses VFS Patch plus relationship rules' : 'vfs_exec is disabled',
            ],
            [
                'mount' => 'Tasks',
                'path' => '/tasks',
                'scope' => 'Workspace task projections, windowed',
                'actions' => $this->vfsActions(['browse', 'read/search', $patchState['enabled'] ? 'patch status/fields' : null]),
                'approval' => $patchState['approval'],
                'status' => $execEnabled ? 'enabled' : 'blocked',
                'reason' => $execEnabled ? $patchState['reason'] : 'vfs_exec is disabled',
            ],
            [
                'mount' => 'Lists',
                'path' => '/lists',
                'scope' => 'Workspace list projections, windowed',
                'actions' => $this->vfsActions(['browse', 'read/search', $patchState['enabled'] ? 'patch status/fields' : null]),
                'approval' => $patchState['approval'],
                'status' => $execEnabled ? 'enabled' : 'blocked',
                'reason' => $execEnabled ? $patchState['reason'] : 'vfs_exec is disabled',
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
                'actions' => $this->vfsActions(['browse', 'read/search', $tableRowState['enabled'] ? 'patch rows' : null]),
                'approval' => $tableRowState['approval'],
                'status' => $execEnabled ? 'enabled' : 'blocked',
                'reason' => $execEnabled ? $tableRowState['reason'] : 'vfs_exec is disabled',
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
                'mount' => 'Routines',
                'path' => '/routines',
                'scope' => 'Workspace routine records',
                'actions' => ['browse', 'read'],
                'approval' => 'Inherit',
                'status' => $execEnabled ? 'limited' : 'blocked',
                'reason' => $execEnabled ? 'Runs and edits stay in routine tools' : 'vfs_exec is disabled',
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
                'Patch: '.strtolower($patchState['approval']),
                'Files: '.($writeEnabled ? strtolower($execWriteState['approval']) : 'disabled'),
                'Domain edits reuse normal tool permissions',
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $tools
     * @param  list<string>  $required
     * @return array{enabled: bool, approval: string, reason: string|null}
     */
    private function vfsComposedState(Collection $tools, array $required): array
    {
        foreach ($required as $tool) {
            $meta = $tools->get($tool, []);
            if (! (bool) ($meta['enabled'] ?? false)) {
                return [
                    'enabled' => false,
                    'approval' => 'Denied',
                    'reason' => "Denied because {$this->toolDisplayName($meta, $tool)} is disabled",
                ];
            }
        }

        foreach ($required as $tool) {
            $meta = $tools->get($tool, []);
            if ((bool) ($meta['requiresApproval'] ?? false)) {
                return [
                    'enabled' => true,
                    'approval' => 'Requires approval',
                    'reason' => "Requires approval because {$this->toolDisplayName($meta, $tool)} requires approval",
                ];
            }
        }

        return [
            'enabled' => true,
            'approval' => 'Allowed',
            'reason' => count($required) > 1 ? 'Requires '.implode(' + ', array_map(fn (string $tool): string => $this->toolDisplayName($tools->get($tool, []), $tool), $required)) : null,
        ];
    }

    /**
     * @param  list<array{enabled: bool, approval: string, reason: string|null}>  $states
     */
    private function vfsMostRestrictiveApproval(array $states): string
    {
        if (collect($states)->contains(fn (array $state): bool => $state['approval'] === 'Requires approval')) {
            return 'Requires approval';
        }

        return collect($states)->contains(fn (array $state): bool => $state['approval'] === 'Allowed') ? 'Allowed' : 'Denied';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function toolDisplayName(array $meta, string $fallback): string
    {
        return (string) ($meta['name'] ?? str_replace('_', ' ', $fallback));
    }

    /**
     * @param  list<string|null>  $actions
     * @return list<string>
     */
    private function vfsActions(array $actions): array
    {
        return array_values(array_filter($actions, fn (?string $action): bool => $action !== null));
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
