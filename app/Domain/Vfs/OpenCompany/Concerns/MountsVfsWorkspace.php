<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use Illuminate\Support\Str;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Domain\Vfs\Events\VfsOperationContext;
use App\Models\AgentPermission;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\ListItem;
use App\Models\User;
use App\Models\VfsOperationEvent;
use App\Models\WorkspaceFile;
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
     *   keyset-paged on the ULID primary key `id` ALONE (assigned at insert
     *   by DatabaseVfsEventSink; time-ordered, string-comparable on SQLite and
     *   PostgreSQL; pillar 11 forbids auto-increment keys on archived tables).
     * - Settle window (`vfs.change_feed_settle_seconds`, default 5): only
     *   events whose ULID time is at least that old are delivered, and the
     *   cursor never moves past the settle boundary. Neither an id nor a
     *   timestamp is commit-ordered under concurrent PostgreSQL transactions
     *   (or across app servers with slightly skewed clocks), so a row that is
     *   still in flight when a poll runs could otherwise be skipped forever.
     *   Contract: an event is delivered exactly once, in id order, provided
     *   its transaction commits (and clocks agree) within the settle window;
     *   VFS write events are single short INSERTs after the write committed,
     *   far inside it. The cost is that a change appears in the feed up to
     *   the settle window after it happened.
     * - Permission: re-checked per object at read time by stat'ing the
     *   object's canonical path as the agent (unaudited); invisible objects are
     *   skipped, not redacted. Deletes are shown to their actor, or when the
     *   agent can read the place the object was deleted FROM (its original
     *   parent, resolved from the trashed record whatever alias the writer
     *   used), see changeIsVisible().
     * - Line shape: {id, occurred_at, type, operation, path, backend_type,
     *   backend_id, version, actor_id, run_id}. No content, ever. Every line,
     *   including the last, ends with a newline (NDJSON).
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
        if ($after !== null && (! is_array($after) || ! is_string($after['id'] ?? null) || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $after['id']) !== 1)) {
            throw $this->invalidVfsPageCursor();
        }

        // Settle boundary: the smallest ULID of (now - settle window). Rows at
        // or after it are not delivered yet; see the docblock contract.
        $settleSeconds = max(0, (int) config('vfs.change_feed_settle_seconds', 5));
        $boundary = substr((string) Str::ulid(now()->subSeconds($settleSeconds)), 0, 10).str_repeat('0', 16);

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
                ->when(is_array($cursor), fn (Builder $query): Builder => $query->where('id', '>', $cursor['id']))
                ->where('id', '<', $boundary)
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
                $position = ['id' => (string) $event->id];

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
                $additionalBytes = strlen($line) + 1;
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
            'content' => $lines === [] ? '' : implode("\n", $lines)."\n",
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

            // Deletion visibility comes from where the object WAS, looked up
            // from the backend record, so it does not depend on which alias
            // (friendly or by-id) the writer happened to use.
            $fromRecord = $this->deletedObjectIsVisible($agent, (string) $event->backend_type, (string) $event->backend_id);
            if ($fromRecord !== null) {
                return $fromRecord;
            }

            // Purged or unknown backend: fall back to the written path's
            // parent. A by-id parent or a mount root says nothing about the
            // object's scope, so it never grants visibility.
            $parent = dirname((string) $event->path);
            if ($parent === '/' || str_ends_with($parent, '/by-id') || $parent === '.' || substr_count($parent, '/') < 2) {
                return false;
            }

            return $this->pathIsReadable($agent, $parent);
        }

        return $this->pathIsReadable($agent, (string) ($event->canonical_path ?? $event->path));
    }

    /**
     * Whether the agent may learn that this deleted object existed: the same
     * read rule as the object's original location. Null when the backend
     * record is gone (purged) or the backend type is not a trashable record.
     */
    private function deletedObjectIsVisible(User $agent, string $backendType, string $backendId): ?bool
    {
        if ($backendId === '') {
            return null;
        }

        return match ($backendType) {
            'workspace_file' => ($file = WorkspaceFile::withTrashed()->where('workspace_id', $agent->workspace_id)->find($backendId)) === null
                ? null
                : ($file->trashed() ? $this->canAccessTrashedFile($agent, $file) : $this->pathIsReadable($agent, "/files/by-id/{$file->id}")),
            'document' => ($doc = Document::withTrashed()->where('workspace_id', $agent->workspace_id)->find($backendId)) === null
                ? null
                : ($doc->trashed() ? $this->canAccessTrashedDocument($agent, $doc) : $this->pathIsReadable($agent, "/docs/by-id/{$doc->id}")),
            'list_item' => ($item = ListItem::withTrashed()->where('workspace_id', $agent->workspace_id)->find($backendId)) === null
                ? null
                : ($item->parent_id !== null && $this->pathIsReadable($agent, "/lists/by-id/{$item->parent_id}")),
            'data_table_row' => ($row = DataTableRow::withTrashed()->find($backendId)) === null
                || ($table = DataTable::forWorkspace()->find($row->table_id)) === null
                ? null
                : $this->pathIsReadable($agent, "/tables/by-id/{$table->id}"),
            default => null,
        };
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
