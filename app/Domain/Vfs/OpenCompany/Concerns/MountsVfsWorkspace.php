<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * Workspace metadata mount.
 */
trait MountsVfsWorkspace
{
    /**
     * @return list<VfsEntry>
     */
    private function listWorkspace(string $path): array
    {
        if ($path !== '/workspace') {
            throw VfsError::notReadable($path);
        }

        return [
            new VfsEntry('members.json', '/workspace/members.json', 'file', capabilities: ['read'], metadata: [
                'paged_collection' => true,
                ...$this->readOnlyMetadata(
                    'Workspace members JSON is a cursor-paged generated snapshot.',
                    'workspace/admin tools',
                    'Use vfs_read with next_cursor to continue; use workspace/admin tools to change membership.',
                ),
            ]),
            new VfsEntry('settings.json', '/workspace/settings.json', 'file', capabilities: ['read'], metadata: $this->readOnlyMetadata(
                'Workspace settings JSON is a generated snapshot.',
                'workspace/admin tools',
                'Use workspace/admin tools to update workspace settings.',
            )),
        ];
    }

    /**
     * @param  list<string>  $segments
     */
    private function readWorkspace(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1) {
            return VfsText::truncate($this->entriesToText($this->listWorkspace($path)), $budget->maxBytes);
        }
        if (count($segments) !== 2) {
            throw VfsError::notFound($path);
        }

        $content = match ($segments[1]) {
            'members.json' => $this->pageWorkspaceMembersExport($budget, [])['content'],
            'settings.json' => json_encode(['workspace_id' => $agent->workspace_id], JSON_PRETTY_PRINT),
            default => throw VfsError::notFound($path),
        };

        return VfsText::truncate($content ?: '', $budget->maxBytes);
    }

    /**
     * Page human members and agents as one stable principal index. The join is
     * constrained to the current workspace before the OR predicate is applied,
     * so a human membership from another tenant cannot satisfy this query.
     * Every chunk is complete JSON even when more principals remain.
     *
     * @param  array<string, mixed>  $state
     * @return array{content: string, returned: int, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function pageWorkspaceMembersExport(VfsBudget $budget, array $state): array
    {
        $path = '/workspace/members.json';
        $emptyContent = $this->workspaceMembersExportContent([]);
        $usedBytes = strlen($emptyContent);
        $this->vfsRecordFitsByteBudget($path, $budget->maxBytes, 0, $usedBytes, 0);

        $memberCount = 0;
        $agentCount = 0;
        $workspaceId = (string) workspace()->id;
        $query = User::query()
            ->leftJoin('workspace_members as vfs_memberships', function (JoinClause $join) use ($workspaceId): void {
                $join->on('vfs_memberships.user_id', '=', 'users.id')
                    ->where('vfs_memberships.workspace_id', '=', $workspaceId);
            })
            ->where(function (Builder $query) use ($workspaceId): void {
                $query->where(function (Builder $agents) use ($workspaceId): void {
                    $agents->where('users.type', 'agent')->where('users.workspace_id', $workspaceId);
                })->orWhereNotNull('vfs_memberships.id');
            })
            ->select([
                'users.*',
                'vfs_memberships.id as vfs_membership_id',
                'vfs_memberships.role as vfs_role',
                'vfs_memberships.created_at as vfs_joined_at',
            ]);

        $page = $this->paginateVfsLabelQuery(
            $query,
            'name',
            $budget->maxEntries,
            $state,
            fn (User $principal): VfsEntry => new VfsEntry(
                name: $principal->name,
                path: $principal->type === 'agent' ? "/agents/by-id/{$principal->id}" : '/workspace/members.json',
                type: 'file',
                backendType: 'workspace_principal',
                backendId: $principal->id,
                metadata: ['principal' => $this->workspacePrincipalPayload($principal)],
            ),
            acceptEntry: function (array $entries, VfsEntry $candidate) use (
                $path,
                $budget,
                &$usedBytes,
                &$memberCount,
                &$agentCount,
            ): bool {
                $principal = $candidate->metadata['principal'] ?? [];
                $isAgent = ($principal['type'] ?? null) === 'agent';
                $groupCount = $isAgent ? $agentCount : $memberCount;
                $additionalBytes = $this->workspacePrincipalPrettyBytes($principal) + ($groupCount === 0 ? 6 : 2);
                if (! $this->vfsRecordFitsByteBudget(
                    $path,
                    $budget->maxBytes,
                    $usedBytes,
                    $additionalBytes,
                    count($entries),
                    $candidate->backendId,
                )) {
                    return false;
                }

                $usedBytes += $additionalBytes;
                if ($isAgent) {
                    $agentCount++;
                } else {
                    $memberCount++;
                }

                return true;
            },
        );

        return [
            'content' => $this->workspaceMembersExportContent($page['entries']),
            'returned' => count($page['entries']),
            'has_more' => $page['has_more'],
            'next_state' => $page['next_state'],
            'scanned' => $page['scanned'],
            'skipped' => $page['skipped'],
        ];
    }

    /**
     * Render one independently valid workspace-roster JSON chunk.
     *
     * @param  list<VfsEntry>  $entries
     */
    private function workspaceMembersExportContent(array $entries): string
    {
        $members = [];
        $agents = [];
        foreach ($entries as $entry) {
            $principal = $entry->metadata['principal'] ?? [];
            if (($principal['type'] ?? null) === 'agent') {
                $agents[] = $principal;
            } else {
                $members[] = $principal;
            }
        }

        return json_encode([
            'members' => $members,
            'agents' => $agents,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    /**
     * Return the exact bytes a principal adds to a pretty-printed JSON array,
     * excluding the comma/newline or first-entry array expansion bytes.
     *
     * @param  array<string, mixed>  $principal
     */
    private function workspacePrincipalPrettyBytes(array $principal): int
    {
        $encoded = json_encode(
            $principal,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );
        $indented = preg_replace('/^/m', '        ', $encoded) ?? $encoded;

        return strlen($indented);
    }

    /** @return array<string, mixed> */
    private function workspacePrincipalPayload(User $principal): array
    {
        if ($principal->type === 'agent') {
            return [
                'id' => $principal->id,
                'name' => $principal->name,
                'email' => $principal->email,
                'type' => $principal->type,
                'status' => $principal->status,
                'presence' => $principal->presence ?? 'offline',
                'manager_id' => $principal->manager_id,
                'created_at' => $principal->created_at?->toIso8601String(),
                'vfs_path' => "/agents/by-id/{$principal->id}",
            ];
        }

        $joinedAt = $principal->getAttribute('vfs_joined_at');

        return [
            'id' => $principal->getAttribute('vfs_membership_id'),
            'membership_id' => $principal->getAttribute('vfs_membership_id'),
            'user_id' => $principal->id,
            'name' => $principal->name,
            'email' => $principal->email,
            'avatar' => $principal->avatar,
            'type' => $principal->type,
            'presence' => $principal->presence ?? 'offline',
            'role' => $principal->getAttribute('vfs_role'),
            'joined_at' => $joinedAt instanceof CarbonInterface ? $joinedAt->toIso8601String() : (is_string($joinedAt) ? $joinedAt : null),
            'vfs_path' => null,
        ];
    }
}
