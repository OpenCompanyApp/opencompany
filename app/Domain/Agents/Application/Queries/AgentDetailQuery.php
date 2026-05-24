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
            'capabilities' => $this->toolRegistry->getAllToolsMeta($agent),
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
