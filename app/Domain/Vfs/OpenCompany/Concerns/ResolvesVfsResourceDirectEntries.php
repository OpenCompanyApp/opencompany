<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\ListItem;
use App\Models\Task;
use App\Models\User;
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
     * Direct stat entry for /tables paths (friendly or by-id). Returns null
     * when the path does not name an entry so stat can fall back; ambiguous
     * friendly names still throw ambiguous_match.
     *
     * @param  list<string>  $segments
     */
    private function directTableEntry(User $agent, array $segments, string $path): ?VfsEntry
    {
        if (! isset($segments[1]) || ($segments[1] === 'by-id' && ! isset($segments[2]))) {
            return null;
        }

        try {
            ['table' => $table, 'rest' => $rest, 'base' => $base] = $this->resolveTableSegments($segments, $path);
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_found') {
                return null;
            }

            throw $e;
        }
        $canonical = "/tables/by-id/{$table->id}";

        if ($rest === []) {
            return new VfsEntry(
                $segments[1] === 'by-id' ? (string) $table->id : $this->tableToken($table),
                $path,
                'directory',
                $canonical,
                'data_table',
                $table->id,
                capabilities: ['browse', 'read', 'search'],
                metadata: ['name' => $table->name, 'description' => $table->description],
            );
        }

        $directories = [
            'rows' => ['browse', 'read', 'search'],
            'rows/by-id' => ['browse', 'read', 'search'],
            'rows/.trash' => ['browse', 'read'],
            'views' => ['browse', 'read'],
        ];
        $joined = implode('/', $rest);
        if (isset($directories[$joined])) {
            return new VfsEntry(basename($joined), $path, 'directory', "{$canonical}/{$joined}", 'data_table', $table->id, capabilities: $directories[$joined]);
        }

        if (count($rest) === 1) {
            foreach ($this->tableDirectoryEntries($table, $base) as $entry) {
                if ($entry->name === $rest[0] && $entry->type === 'file') {
                    return new VfsEntry($entry->name, $path, 'file', $entry->canonicalPath, $entry->backendType, $entry->backendId, capabilities: $entry->capabilities, metadata: $entry->metadata);
                }
            }

            return null;
        }

        if ($rest[0] === 'rows') {
            if (($rest[1] ?? null) === '.trash' && count($rest) === 3) {
                try {
                    $row = $this->resolveTrashedTableRow($table, $rest[2], $path);
                } catch (VfsError) {
                    return null;
                }

                return new VfsEntry("{$row->id}.json", $path, 'file', "{$canonical}/rows/.trash/{$row->id}.json", 'data_table_row', $row->id, capabilities: ['read'], metadata: ['trashed' => true]);
            }

            try {
                $row = $this->resolveTableRowFromRest($table, $rest, $path, $base);
            } catch (VfsError) {
                return null;
            }

            return $this->tableRowEntry($agent, $table, $row, $path);
        }

        return null;
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
        // Same scope as the approvals mount (MountsVfsApprovals::agentApprovalsQuery).
        $approval = $this->agentApprovalsQuery($agent)
            ->where('id', $id)
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
