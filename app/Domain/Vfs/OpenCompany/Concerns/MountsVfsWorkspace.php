<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Domain\Vfs\Events\VfsOperationContext;
use App\Models\AgentPermission;
use App\Models\User;
use App\Models\VfsOperationEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * Workspace metadata mount.
 *
 * - members.json: human members and agents. Human email addresses are
 *   personal data: they are only served to agents with an explicit `allow`
 *   row for the `list_members` tool (the members/contacts permission an admin
 *   sets in Capabilities) that does not require approval; otherwise `email`
 *   is null and `email_redacted` is true; when list_members is allowed only
 *   with approval, the top-level `email_redaction` points at that gated
 *   tool. Agent
 *   principals keep their (non-personal) addresses.
 * - changes.ndjson: the change feed (see pageWorkspaceChanges()).
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
            new VfsEntry('changes.ndjson', '/workspace/changes.ndjson', 'file', capabilities: ['read'], metadata: [
                'paged_collection' => true,
                'description' => 'Committed VFS changes you can read, oldest first; keep next_cursor to poll for newer changes.',
                ...$this->readOnlyMetadata(
                    'The change feed is generated from the VFS audit log.',
                    'vfs_read',
                    'Use vfs_read with next_cursor to continue and to resume polling later.',
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
            return VfsText::fit($this->entriesToText($this->listWorkspace($path)), $budget, $path);
        }
        if (count($segments) !== 2) {
            throw VfsError::notFound($path);
        }

        $content = match ($segments[1]) {
            'members.json' => $this->pageWorkspaceMembersExport($agent, $budget, [])['content'],
            'changes.ndjson' => $this->pageWorkspaceChanges($agent, $budget, [])['content'],
            'settings.json' => json_encode(['workspace_id' => $agent->workspace_id], JSON_PRETTY_PRINT),
            default => throw VfsError::notFound($path),
        };

        return VfsText::fit($content ?: '', $budget, $path);
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
    private function pageWorkspaceMembersExport(User $agent, VfsBudget $budget, array $state): array
    {
        $path = '/workspace/members.json';
        $showEmails = $this->agentMaySeeMemberEmails($agent);
        $emailNote = $showEmails ? null : $this->memberEmailRedactionNote($agent);
        $emptyContent = $this->workspaceMembersExportContent([], $emailNote);
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
                metadata: ['principal' => $this->workspacePrincipalPayload($principal, $showEmails)],
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
            'content' => $this->workspaceMembersExportContent($page['entries'], $emailNote),
            'returned' => count($page['entries']),
            'has_more' => $page['has_more'],
            'next_state' => $page['next_state'],
            'scanned' => $page['scanned'],
            'skipped' => $page['skipped'],
        ];
    }

    /**
     * `/workspace/changes.ndjson`: committed VFS writes in this workspace that
     * the agent may see, oldest first, so computer agents and VFS-only agents
     * can refresh copies they pulled earlier without rescanning.
     *
     * - Source: committed write events (`vfs_operation_events.committed`),
     *   keyset-paged on (occurred_at, id); `id` is monotonic, so a saved
     *   next_cursor resumes exactly after the last delivered change and can be
     *   polled again later for newer ones (cursors are actor-bound).
     * - Permission: re-checked per object at read time by stat'ing the
     *   object's canonical path as the agent (unaudited); invisible objects are
     *   skipped, not redacted. Deletes are shown only to their actor or when
     *   the deleted object's parent directory is readable (the object itself
     *   no longer resolves).
     * - Line shape: {id, occurred_at, type, operation, path, backend_type,
     *   backend_id, version, actor_id, run_id}. No content, ever.
     *
     * The workspace event log will replace the audit table; this view then
     * becomes a subject-prefix query on the log with the same contract.
     *
     * @param  array<string, mixed>  $state
     * @return array{content: string, returned: int, has_more: bool, resumable: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function pageWorkspaceChanges(User $agent, VfsBudget $budget, array $state): array
    {
        $path = '/workspace/changes.ndjson';
        $after = $state['after'] ?? null;
        if ($after !== null && (! is_array($after) || ! is_string($after['occurred_at'] ?? null) || ! is_int($after['id'] ?? null))) {
            throw $this->invalidVfsPageCursor();
        }

        $lines = [];
        $usedBytes = 0;
        $scanned = 0;
        $skipped = 0;
        $hasMore = false;
        $cursor = $after;
        $context = app(VfsOperationContext::class);

        while (count($lines) < $budget->maxEntries && $scanned < $budget->maxFiles) {
            $batch = VfsOperationEvent::query()
                ->where('workspace_id', $agent->workspace_id)
                ->where('committed', true)
                ->when(is_array($cursor), function (Builder $query) use ($cursor): void {
                    $query->where(function (Builder $query) use ($cursor): void {
                        $query->where('occurred_at', '>', $cursor['occurred_at'])
                            ->orWhere(function (Builder $query) use ($cursor): void {
                                $query->where('occurred_at', $cursor['occurred_at'])->where('id', '>', $cursor['id']);
                            });
                    });
                })
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->limit(min(200, $budget->maxFiles - $scanned + 1))
                ->get();

            if ($batch->isEmpty()) {
                break;
            }

            foreach ($batch as $event) {
                if ($scanned >= $budget->maxFiles || count($lines) >= $budget->maxEntries) {
                    $hasMore = true;

                    break 2;
                }

                $scanned++;
                $position = ['occurred_at' => (string) $event->getRawOriginal('occurred_at'), 'id' => (int) $event->id];

                if (! $context->unaudited(fn (): bool => $this->changeIsVisible($agent, $event))) {
                    $skipped++;
                    $cursor = $position;

                    continue;
                }

                $line = json_encode([
                    'id' => $event->event_id,
                    'occurred_at' => $event->occurred_at->toIso8601ZuluString('microsecond'),
                    'type' => $event->type,
                    'operation' => $event->operation,
                    'path' => $event->canonical_path ?? $event->path,
                    'backend_type' => $event->backend_type,
                    'backend_id' => $event->backend_id,
                    'version' => $event->after_version,
                    'actor_id' => $event->agent_id,
                    'run_id' => $event->run_id,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
                $additionalBytes = strlen($line) + ($lines === [] ? 0 : 1);
                if (! $this->vfsRecordFitsByteBudget($path, $budget->maxBytes, $usedBytes, $additionalBytes, count($lines), $event->event_id)) {
                    $hasMore = true;

                    break 2;
                }

                $lines[] = $line;
                $usedBytes += $additionalBytes;
                $cursor = $position;
            }
        }

        // has_more is truthful (more changes are already known). `resumable`
        // asks the facade to hand back a cursor even when drained: polling
        // later with it returns only newer changes, and an empty or fully
        // filtered page still advances past what was scanned.
        return [
            'content' => implode("\n", $lines),
            'returned' => count($lines),
            'has_more' => $hasMore,
            'resumable' => true,
            'next_state' => ['after' => $cursor],
            'scanned' => $scanned,
            'skipped' => $skipped,
        ];
    }

    private function changeIsVisible(User $agent, VfsOperationEvent $event): bool
    {
        if (str_ends_with($event->type, '.deleted')) {
            if ($event->agent_id === $agent->id) {
                return true;
            }
            $parent = dirname((string) $event->path);
            if ($parent === '/' || str_ends_with($parent, '/by-id') || $parent === '.') {
                return false;
            }

            return $this->pathIsReadable($agent, $parent);
        }

        return $this->pathIsReadable($agent, (string) ($event->canonical_path ?? $event->path));
    }

    private function pathIsReadable(User $agent, string $path): bool
    {
        if ($path === '') {
            return false;
        }

        try {
            $this->stat($agent, $path);

            return true;
        } catch (VfsError) {
            return false;
        }
    }

    /**
     * Render one independently valid workspace-roster JSON chunk.
     *
     * @param  list<VfsEntry>  $entries
     * @param  string|null  $emailNote  why human emails are redacted (null when shown); part of every chunk so the byte budget of the empty chunk stays exact
     */
    private function workspaceMembersExportContent(array $entries, ?string $emailNote = null): string
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
            ...($emailNote !== null ? ['email_redaction' => $emailNote] : []),
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

    /**
     * Explicit members/contacts permission: an allow row for list_members
     * that does not require approval. Behavior-mode defaults do not count;
     * this is personal data.
     *
     * Invariant: the VFS never serves more than the backing tool would
     * without a human. An approval-gated list_members row used to expose
     * every email on a plain `cat`; now the emails stay redacted and the
     * redaction note points at the approval-gated tool. Reads never create
     * approval requests themselves.
     */
    private function agentMaySeeMemberEmails(User $agent): bool
    {
        return AgentPermission::forAgent($agent->id)
            ->tools()
            ->allowed()
            ->where('scope_key', 'list_members')
            ->where('requires_approval', false)
            ->exists();
    }

    /**
     * Model-facing reason for redacted emails when list_members is allowed
     * only with approval, so the agent knows the data exists behind the
     * gated tool. Null otherwise: without any grant the plain
     * `email_redacted: true` shape is unchanged (and its roster chunk size
     * stays the documented minimum).
     */
    private function memberEmailRedactionNote(User $agent): ?string
    {
        $gated = AgentPermission::forAgent($agent->id)
            ->tools()
            ->allowed()
            ->where('scope_key', 'list_members')
            ->where('requires_approval', true)
            ->exists();

        return $gated
            ? 'Human email addresses need human approval for this agent: call the list_members tool, which asks for approval, instead of reading this file.'
            : null;
    }

    /** @return array<string, mixed> */
    private function workspacePrincipalPayload(User $principal, bool $showEmails = false): array
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
            'email' => $showEmails ? $principal->email : null,
            ...($showEmails ? [] : ['email_redacted' => true]),
            'avatar' => $principal->avatar,
            'type' => $principal->type,
            'presence' => $principal->presence ?? 'offline',
            'role' => $principal->getAttribute('vfs_role'),
            'joined_at' => $joinedAt instanceof CarbonInterface ? $joinedAt->toIso8601String() : (is_string($joinedAt) ? $joinedAt : null),
            'vfs_path' => null,
        ];
    }
}
