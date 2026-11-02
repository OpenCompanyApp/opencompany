<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\ApprovalRequest;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\ListItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Direct entry builders for non-file OpenCompany resource mounts.
 */
trait ResolvesVfsResourceDirectEntries
{
    /**
     * @param  list<string>  $segments
     */
    private function directAgentEntry(User $agent, array $segments, string $path): ?VfsEntry
    {
        if (isset($segments[3]) || ! isset($segments[1])) {
            return null;
        }

        $target = $this->resolveAgent($agent, array_slice($segments, 1), $path);

        return new VfsEntry(
            name: $segments[2] ?? $this->slug($target->name),
            path: $path,
            type: 'directory',
            canonicalPath: "/agents/by-id/{$target->id}",
            backendType: 'agent',
            backendId: $target->id,
            capabilities: ['browse', 'read'],
            metadata: ['name' => $target->name, 'status' => $target->status],
        );
    }

    /**
     * @param  list<string>  $segments
     */
    private function directTaskEntry(User $agent, array $segments): ?VfsEntry
    {
        if (($segments[1] ?? null) !== 'by-id' || ! isset($segments[2]) || isset($segments[3])) {
            return null;
        }

        $task = Task::forWorkspace()->with('agent:id,name')->find($segments[2]);

        return $task ? $this->taskEntry($agent, $task) : null;
    }

    /**
     * @param  list<string>  $segments
     */
    private function directListEntry(User $agent, array $segments): ?VfsEntry
    {
        if (($segments[1] ?? null) !== 'by-id' || ! isset($segments[2]) || isset($segments[3])) {
            return null;
        }

        $item = ListItem::forWorkspace()->find($segments[2]);

        return $item ? $this->listItemEntry($agent, $item) : null;
    }

    /**
     * @param  list<string>  $segments
     */
    private function directChannelEntry(User $agent, array $segments, string $path): ?VfsEntry
    {
        $offset = ($segments[1] ?? null) === 'by-id' ? 3 : 2;
        if (($segments[$offset] ?? null) !== 'messages' || ! isset($segments[$offset + 1]) || isset($segments[$offset + 2])) {
            return null;
        }

        $last = $segments[$offset + 1];
        if (preg_match('/^\d{4}-\d{2}-\d{2}\.md$/', $last) !== 1) {
            return null;
        }

        $channel = $this->resolveChannel($agent, array_slice($segments, 1, $offset - 1), $path);

        return new VfsEntry(
            $last,
            $path,
            'file',
            backendType: 'channel',
            backendId: $channel->id,
            capabilities: ['read', 'search'],
            metadata: [
                'paged_collection' => true,
                ...$this->readOnlyMetadata(
                    'Channel transcripts are generated, cursor-paged snapshots.',
                    'chat tools',
                    'Use vfs_read with next_cursor to continue; use chat tools to post messages.',
                ),
            ],
        );
    }

    /**
     * @param  list<string>  $segments
     */
    private function directTableEntry(User $agent, array $segments, string $path): ?VfsEntry
    {
        if (! isset($segments[1])) {
            return null;
        }

        $table = DataTable::forWorkspace()->lazy(100)->first(fn (DataTable $candidate): bool => $this->slug($candidate->name) === $segments[1]);
        if (! $table) {
            return null;
        }

        if (! isset($segments[2])) {
            return new VfsEntry(
                $this->slug($table->name),
                $path,
                'directory',
                "/tables/{$this->slug($table->name)}",
                'data_table',
                $table->id,
                capabilities: ['browse', 'read', 'search'],
                metadata: ['name' => $table->name],
            );
        }

        if ($segments[2] === 'rows') {
            if (! isset($segments[3])) {
                return new VfsEntry('rows', $path, 'directory', "/tables/{$this->slug($table->name)}/rows", 'data_table', $table->id, capabilities: ['browse', 'read', 'search']);
            }

            if ($segments[3] === 'by-id' && ! isset($segments[4])) {
                return new VfsEntry('by-id', $path, 'directory', "/tables/{$this->slug($table->name)}/rows/by-id", 'data_table', $table->id, capabilities: ['browse', 'read', 'search']);
            }

            if ($segments[3] === 'by-id' && isset($segments[4]) && ! isset($segments[5])) {
                $rowId = Str::before($segments[4], '.json');
                $row = DataTableRow::where('table_id', $table->id)->find($rowId);

                return $row ? $this->tableRowEntry($agent, $table, $row) : null;
            }

            return null;
        }

        if (isset($segments[3]) || ! in_array($segments[2], ['schema.json', 'rows.ndjson', 'views'], true)) {
            return null;
        }

        if ($segments[2] === 'views') {
            return new VfsEntry('views', $path, 'directory', backendType: 'data_table', backendId: $table->id, capabilities: ['browse', 'read']);
        }

        return new VfsEntry(
            $segments[2],
            $path,
            'file',
            backendType: 'data_table',
            backendId: $table->id,
            capabilities: $segments[2] === 'rows.ndjson' ? ['read', 'search'] : ['read'],
            metadata: $segments[2] === 'rows.ndjson'
                ? [
                    'paged_collection' => true,
                    ...$this->readOnlyMetadata('rows.ndjson is a bulk export and cannot be patched safely.', 'vfs_patch', 'Use vfs_read with next_cursor to continue; patch individual rows under rows/by-id.'),
                ]
                : $this->readOnlyMetadata('Table schema is a generated snapshot.', 'table column tools', 'Use table column tools to change schema; use rows/by-id for row edits.'),
        );
    }

    /**
     * @param  list<string>  $segments
     */
    private function directApprovalEntry(User $agent, array $segments, string $path): ?VfsEntry
    {
        if (! isset($segments[1]) || isset($segments[2])) {
            return null;
        }

        $id = Str::before($segments[1], '.json');
        $approval = ApprovalRequest::query()
            ->where('id', $id)
            ->whereHas('requester', fn (Builder $query) => $query->where('workspace_id', $agent->workspace_id))
            ->where('requester_id', $agent->id)
            ->first();

        return $approval
            ? new VfsEntry($approval->id.'.json', $path, 'file', backendType: 'approval', backendId: $approval->id, capabilities: ['read'], metadata: [
                'status' => $approval->status,
                'title' => $approval->title,
                ...$this->readOnlyMetadata(
                    'Approval JSON is a generated approval record.',
                    'approval tools',
                    'Use approval tools to approve, reject, or inspect approval state.',
                ),
            ])
            : null;
    }

    /**
     * @param  list<string>  $segments
     */
    private function directRoutineEntry(array $segments, string $path): ?VfsEntry
    {
        if (! isset($segments[1]) || isset($segments[2])) {
            return null;
        }

        $token = Str::before($segments[1], '.json');
        try {
            $routine = $this->resolveRoutineToken($token, $path);
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_found') {
                return null;
            }

            throw $e;
        }

        return $this->routineEntry($routine, $path);
    }

    /**
     * @param  list<string>  $segments
     */
    private function directWorkspaceEntry(array $segments, string $path): ?VfsEntry
    {
        if (isset($segments[2]) || ! in_array($segments[1] ?? '', ['members.json', 'settings.json'], true)) {
            return null;
        }

        $metadata = $segments[1] === 'members.json'
            ? [
                'paged_collection' => true,
                ...$this->readOnlyMetadata(
                    'Workspace members JSON is a cursor-paged generated snapshot.',
                    'workspace/admin tools',
                    'Use vfs_read with next_cursor to continue; use workspace/admin tools to change membership.',
                ),
            ]
            : $this->readOnlyMetadata(
                'Workspace settings JSON is a generated snapshot.',
                'workspace/admin tools',
                'Use workspace/admin tools to change workspace settings.',
            );

        return new VfsEntry($segments[1], $path, 'file', capabilities: ['read'], metadata: $metadata);
    }
}
