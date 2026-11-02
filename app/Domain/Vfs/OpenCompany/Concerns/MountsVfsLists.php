<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\ListItem;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * List-item mount operations for OpenCompanyVfs.
 *
 * Read side only: items render as front-matter markdown (MutatesVfsListItems
 * owns the format and every write). `/lists/projects/{project}` lists a
 * project's items by friendly name; `/lists/.trash/` lists trashed items.
 */
trait MountsVfsLists
{
    /**
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    private function listLists(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        return $this->pageLists($agent, $segments, $path, $budget, [])['entries'];
    }

    /**
     * Return an adapter-owned list-item page before cursor encoding.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $state
     * @return array{
     *     entries: list<VfsEntry>,
     *     has_more: bool,
     *     next_state: array<string, mixed>,
     *     scanned: int,
     *     skipped: int
     * }
     */
    private function pageLists(User $agent, array $segments, string $path, VfsBudget $budget, array $state): array
    {
        if (count($segments) === 1) {
            return $this->paginateVfsStaticEntries($this->listRootEntries(), $budget->maxEntries, $state);
        }

        $label = $segments[1] ?? '';
        if ($label === '.trash' && ! isset($segments[2])) {
            return $this->paginateVfsStaticEntries($this->listTrashedListItems($agent, $budget), $budget->maxEntries, $state);
        }
        if ($label === 'projects' && isset($segments[2])) {
            $project = $this->resolveListItemSegments($segments, $path);
            if (! $project->is_folder) {
                throw VfsError::notReadable($path);
            }

            return $this->paginateVfsRecentQuery(
                ListItem::forWorkspace()->where('parent_id', $project->id),
                $budget->maxEntries,
                $budget->maxFiles,
                $state,
                fn (ListItem $child): VfsEntry => $this->listItemEntry($agent, $child, rtrim($path, '/').'/'.$this->listItemName($child)),
            );
        }
        if ($label === 'by-id') {
            if (! isset($segments[2])) {
                return $this->paginateVfsRecentQuery(
                    ListItem::forWorkspace(),
                    $budget->maxEntries,
                    $budget->maxFiles,
                    $state,
                    fn (ListItem $item): VfsEntry => $this->listItemEntry($agent, $item),
                );
            }
            $item = $this->resolveListItemSegments($segments, $path);
            if (! $item->is_folder) {
                throw VfsError::notReadable($path);
            }

            return $this->paginateVfsRecentQuery(
                ListItem::forWorkspace()->where('parent_id', $item->id),
                $budget->maxEntries,
                $budget->maxFiles,
                $state,
                fn (ListItem $child): VfsEntry => $this->listItemEntry(
                    $agent,
                    $child,
                    rtrim($path, '/').'/'.$this->listItemName($child),
                ),
            );
        }

        $query = ListItem::forWorkspace();
        if ($label === 'projects') {
            $projectsQuery = ListItem::forWorkspace()->where('is_folder', true)->whereNull('parent_id');

            return $this->paginateVfsRecentQuery(
                $projectsQuery,
                $budget->maxEntries,
                $budget->maxFiles,
                $state,
                fn (ListItem $project): VfsEntry => $this->listItemEntry($agent, $project, '/lists/projects/'.$this->friendlyToken(ListItem::forWorkspace()->where('is_folder', true)->whereNull('parent_id'), 'title', $project)),
            );
        } elseif ($label === 'by-status' && ! isset($segments[2])) {
            $entries = ListItem::forWorkspace()
                ->select('status')
                ->whereNotNull('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status')
                ->map(fn (string $status): VfsEntry => new VfsEntry($status, "/lists/by-status/{$status}", 'directory', capabilities: ['browse', 'read', 'search']))
                ->all();

            return $this->paginateVfsStaticEntries($entries, $budget->maxEntries, $state);
        } elseif ($label === 'by-status' && isset($segments[2])) {
            $query->where('status', $segments[2]);
        } elseif ($label === 'by-assignee' && ! isset($segments[2])) {
            return $this->paginateVfsLabelQuery(
                User::query()->where(function ($query) use ($agent): void {
                    $query->where('workspace_id', $agent->workspace_id)
                        ->orWhereHas('workspaces', fn ($membership) => $membership->where('workspaces.id', $agent->workspace_id));
                }),
                'name',
                $budget->maxEntries,
                $state,
                fn (User $target): VfsEntry => new VfsEntry(
                    $this->slug($target->name),
                    "/lists/by-assignee/{$this->slug($target->name)}",
                    'directory',
                    capabilities: ['browse', 'read', 'search'],
                    metadata: ['user_id' => $target->id, 'name' => $target->name],
                ),
            );
        } elseif ($label === 'by-assignee' && isset($segments[2])) {
            $target = User::query()
                ->where(function ($query) use ($agent): void {
                    $query->where('workspace_id', $agent->workspace_id)
                        ->orWhereHas('workspaces', fn ($membership) => $membership->where('workspaces.id', $agent->workspace_id));
                })
                ->lazy(100)
                ->first(fn (User $candidate): bool => Str::slug($candidate->name) === $segments[2]);
            $target ? $query->where('assignee_id', $target->id) : $query->whereRaw('1 = 0');
        } else {
            throw VfsError::notFound($path);
        }

        return $this->paginateVfsRecentQuery(
            $query,
            $budget->maxEntries,
            $budget->maxFiles,
            $state,
            fn (ListItem $item): VfsEntry => $this->listItemEntry($agent, $item),
        );
    }

    /** @return list<VfsEntry> */
    private function listRootEntries(): array
    {
        return [
            new VfsEntry('projects', '/lists/projects', 'directory', capabilities: ['browse', 'read']),
            new VfsEntry('by-id', '/lists/by-id', 'directory', capabilities: ['browse', 'read']),
            new VfsEntry('by-status', '/lists/by-status', 'directory', capabilities: ['browse', 'read']),
            new VfsEntry('by-assignee', '/lists/by-assignee', 'directory', capabilities: ['browse', 'read']),
            new VfsEntry('counts.json', '/lists/counts.json', 'file', capabilities: ['read']),
            new VfsEntry('.trash', '/lists/.trash', 'directory', capabilities: ['browse', 'read'], metadata: [
                'description' => 'Trashed list items; mv one out of here to restore it.',
            ]),
        ];
    }

    /**
     * @param  list<string>  $segments
     */
    private function readLists(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (($segments[1] ?? null) === 'counts.json') {
            return json_encode([
                'total' => ListItem::forWorkspace()->count(),
                'projects' => ListItem::forWorkspace()->where('is_folder', true)->count(),
                'items' => ListItem::forWorkspace()->where('is_folder', false)->count(),
            ], JSON_PRETTY_PRINT);
        }

        if (($segments[1] ?? null) === '.trash' && isset($segments[2])) {
            if (isset($segments[3])) {
                throw VfsError::notFound($path);
            }

            return $this->listItemMarkdown($this->resolveTrashedListItem($segments[2], $path));
        }

        if ((($segments[1] ?? null) === 'by-id' || ($segments[1] ?? null) === 'projects') && isset($segments[2])) {
            $item = $this->resolveListItemSegments($segments, $path);
            if ($item->is_folder) {
                if ($budget->isContentRead()) {
                    throw VfsError::notReadable($path);
                }

                return $this->entriesToText($this->listLists($agent, $segments, $path, $budget));
            }

            $budget->recordReadVersion($this->listItemVersion($item));
            $budget->recordCanonicalPath("/lists/by-id/{$item->id}");

            return $this->listItemMarkdown($item);
        }

        return $this->entriesToText($this->listLists($agent, $segments, $path, $budget));
    }

    private function listItemEntry(User $agent, ListItem $item, ?string $path = null): VfsEntry
    {
        $path ??= "/lists/by-id/{$item->id}";
        $capabilities = $item->is_folder ? ['browse', 'read', 'search'] : ['read', 'search'];
        $metadata = [
            'title' => $item->title,
            'status' => $item->status,
            'priority' => $item->priority,
            'is_folder' => $item->is_folder,
        ];

        if (! $item->is_folder) {
            $patchDecision = $this->entryMutationDecision(
                $agent,
                $path,
                'patch',
                ['vfs_patch'],
                'Patch this list item with vfs_patch; only the supported list fields may be changed.',
                $item->creator_id === $agent->id || $item->assignee_id === $agent->id,
                'Only an item creator or assignee can patch the item through VFS.',
            );
            $capabilities = $this->mutationCapabilities($capabilities, ['patch' => $patchDecision]);
            $metadata = [
                ...$metadata,
                ...$patchDecision->metadata(),
                ...$this->mutationActionsMetadata(['patch' => $patchDecision]),
            ];
        }

        return new VfsEntry(
            name: $this->listItemName($item),
            path: $path,
            type: $item->is_folder ? 'directory' : 'file',
            canonicalPath: "/lists/by-id/{$item->id}",
            backendType: 'list_item',
            backendId: $item->id,
            version: $this->listItemVersion($item),
            capabilities: $capabilities,
            metadata: $metadata,
        );
    }

    private function listItemName(ListItem $item): string
    {
        $token = $this->friendlyToken(ListItem::forWorkspace()->where('parent_id', $item->parent_id), 'title', $item);

        return $token.($item->is_folder ? '' : '.md');
    }

    /**
     * Resolve a child of a project by friendly name (`.md` optional,
     * `--{id8}` on collisions; ambiguous names fail with ambiguous_match).
     */
    private function resolveListChild(ListItem $folder, string $token, string $path): ListItem
    {
        if (! $folder->is_folder) {
            throw VfsError::notFound($path);
        }

        $child = $this->resolveFriendlySegment(
            ListItem::forWorkspace()->where('parent_id', $folder->id),
            'title',
            $token,
            "/lists/by-id/{$folder->id}",
            $path,
            fn (\Illuminate\Database\Eloquent\Model $candidate): string => "/lists/by-id/{$candidate->getKey()}",
            allowMarkdownSuffix: true,
        );

        return $child instanceof ListItem ? $child : throw VfsError::notFound($path);
    }
}
