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
 */
trait MountsVfsLists
{
    /**
     * @return list<VfsEntry>
     */
    private function listLists(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        if (count($segments) === 1) {
            return [
                new VfsEntry('projects', '/lists/projects', 'directory', capabilities: ['browse', 'read']),
                new VfsEntry('by-id', '/lists/by-id', 'directory', capabilities: ['browse', 'read']),
                new VfsEntry('by-status', '/lists/by-status', 'directory', capabilities: ['browse', 'read']),
                new VfsEntry('by-assignee', '/lists/by-assignee', 'directory', capabilities: ['browse', 'read']),
                new VfsEntry('counts.json', '/lists/counts.json', 'file', capabilities: ['read']),
            ];
        }

        $label = $segments[1] ?? '';
        if ($label === 'by-id') {
            if (! isset($segments[2])) {
                return ListItem::forWorkspace()->latest('updated_at')->limit($budget->maxEntries)->get()->map(fn (ListItem $item): VfsEntry => $this->listItemEntry($item))->all();
            }
            $item = ListItem::forWorkspace()->find($segments[2]) ?? throw VfsError::notFound($path);
            if (isset($segments[3])) {
                $item = $this->resolveListChild($item, $segments[3], $path);
                if (isset($segments[4])) {
                    throw VfsError::notFound($path);
                }
            }
            if (! $item->is_folder) {
                throw VfsError::notReadable($path);
            }

            return ListItem::forWorkspace()
                ->where('parent_id', $item->id)
                ->latest('updated_at')
                ->limit($budget->maxEntries)
                ->get()
                ->map(fn (ListItem $child): VfsEntry => $this->listItemEntry($child, rtrim($path, '/').'/'.$this->listItemName($child)))
                ->all();
        }

        $query = ListItem::forWorkspace()->latest('updated_at');
        if ($label === 'projects') {
            $query->where('is_folder', true);
        } elseif ($label === 'by-status' && ! isset($segments[2])) {
            return ListItem::forWorkspace()
                ->select('status')
                ->whereNotNull('status')
                ->distinct()
                ->orderBy('status')
                ->limit($budget->maxEntries)
                ->pluck('status')
                ->map(fn (string $status): VfsEntry => new VfsEntry($status, "/lists/by-status/{$status}", 'directory', capabilities: ['browse', 'read', 'search']))
                ->all();
        } elseif ($label === 'by-status' && isset($segments[2])) {
            $query->where('status', $segments[2]);
        } elseif ($label === 'by-assignee' && ! isset($segments[2])) {
            return User::where('workspace_id', $agent->workspace_id)
                ->orWhereHas('workspaces', fn ($query) => $query->where('workspaces.id', $agent->workspace_id))
                ->orderBy('name')
                ->limit($budget->maxEntries)
                ->get()
                ->map(fn (User $target): VfsEntry => new VfsEntry($this->slug($target->name), "/lists/by-assignee/{$this->slug($target->name)}", 'directory', capabilities: ['browse', 'read', 'search'], metadata: ['user_id' => $target->id, 'name' => $target->name]))
                ->all();
        } elseif ($label === 'by-assignee' && isset($segments[2])) {
            $target = User::where('workspace_id', $agent->workspace_id)
                ->orWhereHas('workspaces', fn ($query) => $query->where('workspaces.id', $agent->workspace_id))
                ->get()
                ->first(fn (User $candidate): bool => Str::slug($candidate->name) === $segments[2]);
            $target ? $query->where('assignee_id', $target->id) : $query->whereRaw('1 = 0');
        } else {
            throw VfsError::notFound($path);
        }

        return $query->limit($budget->maxEntries)->get()->map(fn (ListItem $item): VfsEntry => $this->listItemEntry($item))->all();
    }

    private function readLists(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (($segments[1] ?? null) === 'counts.json') {
            return json_encode([
                'total' => ListItem::forWorkspace()->count(),
                'projects' => ListItem::forWorkspace()->where('is_folder', true)->count(),
                'items' => ListItem::forWorkspace()->where('is_folder', false)->count(),
            ], JSON_PRETTY_PRINT);
        }

        if (($segments[1] ?? null) === 'by-id' && isset($segments[2])) {
            if (isset($segments[4])) {
                throw VfsError::notFound($path);
            }
            $item = ListItem::forWorkspace()->with(['assignee:id,name', 'creator:id,name'])->find($segments[2]) ?? throw VfsError::notFound($path);
            if (isset($segments[3])) {
                $item = $this->resolveListChild($item, $segments[3], $path);
            }
            if ($item->is_folder) {
                return $this->entriesToText($this->listLists($agent, $segments, $path, $budget));
            }

            return $this->listItemMarkdown($item);
        }

        return $this->entriesToText($this->listLists($agent, $segments, $path, $budget));
    }

    private function listItemEntry(ListItem $item, ?string $path = null): VfsEntry
    {
        return new VfsEntry(
            name: $this->listItemName($item),
            path: $path ?? "/lists/by-id/{$item->id}",
            type: $item->is_folder ? 'directory' : 'file',
            canonicalPath: "/lists/by-id/{$item->id}",
            backendType: 'list_item',
            backendId: $item->id,
            version: $this->version($item->updated_at?->toIso8601String(), $item->status ?? ''),
            capabilities: ['read', 'search', 'patch'],
            metadata: [
                'title' => $item->title,
                'status' => $item->status,
                'priority' => $item->priority,
                'is_folder' => $item->is_folder,
            ],
        );
    }

    private function listItemName(ListItem $item): string
    {
        return $this->slug($item->title).($item->is_folder ? '' : '.md');
    }

    private function resolveListChild(ListItem $folder, string $token, string $path): ListItem
    {
        if (! $folder->is_folder) {
            throw VfsError::notFound($path);
        }

        $normalized = preg_replace('/\.md$/', '', $token) ?? $token;

        return ListItem::forWorkspace()
            ->where('parent_id', $folder->id)
            ->get()
            ->first(fn (ListItem $candidate): bool => $this->slug($candidate->title) === $normalized || $this->listItemName($candidate) === $token)
            ?? throw VfsError::notFound($path);
    }

    private function listItemMarkdown(ListItem $item): string
    {
        return implode("\n", [
            '# '.$item->title,
            '',
            "- ID: {$item->id}",
            "- Status: {$item->status}",
            "- Priority: {$item->priority}",
            '- Assignee: '.($item->assignee?->name ?? 'Unassigned'),
            '- Folder: '.($item->is_folder ? 'yes' : 'no'),
            '',
            '## Description',
            '',
            (string) ($item->description ?? ''),
        ]);
    }
}
