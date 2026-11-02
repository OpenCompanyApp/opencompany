<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\Mounts\BuiltinVfsMount;
use App\Domain\Vfs\OpenCompany\Mounts\VfsMountRegistry;
use App\Models\User;

/**
 * The routing table of OpenCompanyVfs: built-in mounts plus every mount on
 * the container's VfsMountRegistry.
 *
 * Owns the declaration of the built-in mounts: prefix, `ls /` description
 * (also the README mount map), cursor adapter names and which mount trait
 * method serves each operation. The order below is the `ls /` order and must
 * stay stable (byte-stable README, agent muscle memory).
 *
 * Agent breadcrumb: the adapters intentionally delegate to the existing
 * MountsVfs… / MutatesVfs… traits instead of copying their logic; behaviour must stay
 * identical to the pre-registry per-root `match` dispatch. Cursor adapter
 * names are bound into issued cursors: never rename them casually.
 */
trait RegistersVfsBuiltinMounts
{
    private ?VfsMountRegistry $builtinMountRegistry = null;

    /**
     * Built-in mounts followed by app/test-registered mounts. Rebuilt per
     * call from the memoized built-ins so mounts registered after this
     * facade was resolved are still routed.
     */
    public function mounts(): VfsMountRegistry
    {
        $builtins = $this->builtinMountRegistry ??= $this->builtinMounts();
        $registered = app(VfsMountRegistry::class);

        return $registered->all() === [] ? $builtins : $builtins->merge($registered);
    }

    private function builtinMounts(): VfsMountRegistry
    {
        $registry = new VfsMountRegistry;

        $registry->register(new BuiltinVfsMount(
            prefix: '/me',
            description: 'Your own area: memory/, notes/, todo.md, scratch/, results/, pending/ approvals.',
            list: $this->listMe(...),
            read: $this->readMe(...),
            alias: true,
        ));
        $registry->register(new BuiltinVfsMount(
            prefix: '/agents',
            description: 'Agents: identity, memory, notes, todo.md and tasks (relationship-gated).',
            list: $this->listAgents(...),
            read: $this->readAgents(...),
            entry: $this->directAgentEntry(...),
            pageAdapter: 'agents-paged-v1',
            page: $this->pageAgents(...),
            write: fn (User $agent, array $segments, string $path, string $content, string $mode, ?string $version, array $toolParameters): array => $this->writeAgentAreaPath($agent, $segments, $path, $content, $mode, $version, $toolParameters),
            patch: $this->patchAgentDocument(...),
            remove: fn (User $agent, array $segments, string $path): array => $this->trashAgentAreaPath($agent, $segments, $path),
            // Only restore from the agent's own .trash/ (MutatesVfsAgentArea).
            move: fn (User $agent, array $segments, string $source, string $destination, array $toolParameters): array => $this->restoreAgentAreaPath($agent, $segments, $source, $destination, $toolParameters),
        ));
        $registry->register(new BuiltinVfsMount(
            prefix: '/docs',
            description: 'Documents and folders by title; read, write, edit, rm (trash), mv, mkdir.',
            list: $this->listDocs(...),
            read: $this->readDocs(...),
            entry: $this->directDocumentEntry(...),
            pageAdapter: 'documents-keyset-v1',
            page: $this->pageDocs(...),
            write: fn (User $agent, array $segments, string $path, string $content, string $mode, ?string $version, array $toolParameters): array => $this->writeDocumentPath($agent, $segments, $path, $content, $mode, $version, $toolParameters),
            patch: $this->patchTextPath(...),
            remove: $this->trashDocumentPath(...),
            move: fn (User $agent, array $segments, string $source, string $destination, array $toolParameters): array => $this->moveDocumentPath($agent, $source, $destination, $toolParameters),
            makeDirectory: fn (User $agent, array $segments, string $path, array $toolParameters): array => $this->makeDocumentFolderPath($agent, $path, $toolParameters),
        ));
        $registry->register(new BuiltinVfsMount(
            prefix: '/files',
            description: 'Workspace files and folders; full read/write file semantics.',
            list: $this->listFiles(...),
            read: $this->readFiles(...),
            entry: $this->directFileEntry(...),
            pageAdapter: 'workspace-files-keyset-v1',
            page: $this->pageFiles(...),
            write: fn (User $agent, array $segments, string $path, string $content, string $mode, ?string $version, array $toolParameters, bool $permissionAlreadyChecked): array => ($segments[1] ?? null) === '.trash'
                ? throw new VfsError('not_writable', "Path is not writable through VFS: {$path}", ['path' => $path, 'hint' => 'Trash is read-only. Restore with mv.'])
                // Shell mutations already passed vfs_exec + vfs_write; every
                // other caller is checked by writeWorkspaceFile itself.
                : $this->writeWorkspaceFile($agent, $segments, $path, $content, $mode, $version, $toolParameters, ! $permissionAlreadyChecked),
            patch: $this->patchTextPath(...),
            remove: fn (User $agent, array $segments, string $path, array $toolParameters): array => $this->trashWorkspaceFilePath($agent, $path, $toolParameters),
            move: fn (User $agent, array $segments, string $source, string $destination, array $toolParameters): array => ($segments[1] ?? null) === '.trash'
                ? $this->restoreWorkspaceFilePath($agent, $source, $destination, $toolParameters)
                : $this->moveWorkspaceFilePath($agent, $source, $destination),
            makeDirectory: fn (User $agent, array $segments, string $path): array => $this->makeFilesDirectory($agent, $path),
        ));
        $registry->register(new BuiltinVfsMount(
            prefix: '/tables',
            description: 'Data tables: schema.json, rows.ndjson, rows.csv, rows/by-id/{id}.json.',
            list: $this->listTables(...),
            read: $this->readTables(...),
            entry: $this->directTableEntry(...),
            pageAdapter: 'tables-paged-v1',
            page: $this->pageTables(...),
            // History views of rows.ndjson are plain reads of a snapshot.
            readPageAdapter: fn (array $segments): ?string => ! $this->isHistoryPath($segments) && $this->isTableRowsExportPath($segments) ? 'table-rows-export-keyset-v1' : null,
            readPage: fn (User $agent, array $segments, string $path, VfsBudget $budget, array $state): array => $this->readTableRowsExportPage($segments, $path, $budget, $state),
            write: fn (User $agent, array $segments, string $path, string $content, string $mode, ?string $version, array $toolParameters): array => $this->writeTablePath($agent, $segments, $path, $content, $mode, $version, $toolParameters),
            patch: $this->patchTableRow(...),
            remove: $this->trashTableRowPath(...),
            move: fn (User $agent, array $segments, string $source, string $destination, array $toolParameters): array => $this->restoreTableRowPath($agent, $source, $destination, $toolParameters),
        ));
        $registry->register(new BuiltinVfsMount(
            prefix: '/lists',
            description: 'List items as markdown with front matter, grouped by project/status/assignee.',
            list: $this->listLists(...),
            read: $this->readLists(...),
            entry: fn (User $agent, array $segments): ?VfsEntry => $this->directListEntry($agent, $segments),
            pageAdapter: 'lists-paged-v1',
            page: $this->pageLists(...),
            write: fn (User $agent, array $segments, string $path, string $content, string $mode, ?string $version, array $toolParameters): array => $this->writeListPath($agent, $segments, $path, $content, $mode, $version, $toolParameters),
            patch: $this->patchListItem(...),
            remove: $this->trashListItemPath(...),
            move: fn (User $agent, array $segments, string $source, string $destination, array $toolParameters): array => $this->restoreListItemPath($agent, $source, $destination, $toolParameters),
        ));
        $registry->register(new BuiltinVfsMount(
            prefix: '/tasks',
            description: 'Agent task (run) records: open, completed, by-status, by-agent.',
            list: $this->listTasks(...),
            read: $this->readTasks(...),
            entry: fn (User $agent, array $segments, string $path): ?VfsEntry => $this->directTaskEntry($agent, $segments, $path),
            pageAdapter: 'tasks-paged-v1',
            page: $this->pageTasks(...),
            patch: $this->patchTask(...),
        ));
        $registry->register(new BuiltinVfsMount(
            prefix: '/channels',
            description: 'Channel transcripts by day (read-only).',
            list: $this->listChannels(...),
            read: $this->readChannels(...),
            entry: $this->directChannelEntry(...),
            pageAdapter: 'channels-paged-v1',
            page: $this->pageChannels(...),
            readPageAdapter: fn (array $segments): ?string => in_array('messages', $segments, true) && preg_match('/^\d{4}-\d{2}-\d{2}\.md$/', end($segments) ?: '') === 1 ? 'channel-transcript-keyset-v1' : null,
            readPage: fn (User $agent, array $segments, string $path, VfsBudget $budget, array $state): array => $this->readChannelTranscriptPage($agent, $segments, $path, $budget, $state),
        ));
        $registry->register(new BuiltinVfsMount(
            prefix: '/tools',
            description: 'Your tool catalog and the VFS reference (vfs.md).',
            list: fn (User $agent, array $segments, string $path): array => $this->listTools($path),
            read: fn (User $agent, array $segments, string $path, VfsBudget $budget): string => $this->readTools($agent, $path, $budget),
            entry: fn (User $agent, array $segments, string $path): ?VfsEntry => $this->directToolEntry($path),
        ));
        $registry->register(new BuiltinVfsMount(
            prefix: '/routines',
            description: 'Routines (read-only).',
            list: $this->listRoutines(...),
            read: $this->readRoutines(...),
            entry: fn (User $agent, array $segments, string $path): ?VfsEntry => $this->directRoutineEntry($segments, $path),
            pageAdapter: 'routines-paged-v1',
            page: $this->pageRoutines(...),
        ));
        $registry->register(new BuiltinVfsMount(
            prefix: '/approvals',
            description: 'Your approval requests (read-only).',
            list: $this->listApprovals(...),
            read: $this->readApprovals(...),
            entry: $this->directApprovalEntry(...),
            pageAdapter: 'approvals-paged-v1',
            page: $this->pageApprovals(...),
        ));
        $registry->register(new BuiltinVfsMount(
            prefix: '/workspace',
            description: 'Workspace members and settings (read-only).',
            list: fn (User $agent, array $segments, string $path): array => $this->listWorkspace($path),
            read: $this->readWorkspace(...),
            entry: fn (User $agent, array $segments, string $path): ?VfsEntry => $this->directWorkspaceEntry($segments, $path),
            readPageAdapter: fn (array $segments): ?string => match ($segments) {
                ['workspace', 'members.json'] => 'workspace-members-keyset-v1',
                ['workspace', 'changes.ndjson'] => 'workspace-changes-keyset-v1',
                default => null,
            },
            readPage: fn (User $agent, array $segments, string $path, VfsBudget $budget, array $state, string $adapter): array => $adapter === 'workspace-changes-keyset-v1'
                ? $this->pageWorkspaceChanges($agent, $budget, $state)
                : $this->pageWorkspaceMembersExport($agent, $budget, $state),
        ));

        return $registry;
    }
}
