<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\Policy\VfsActionClass;
use App\Models\ListItem;
use App\Models\ListStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * List items as markdown records with YAML front matter.
 *
 * Kept deliberately generic (Lists fold into Issues per the product
 * direction): the record format, field normalization and lifecycle here are
 * what an `/issues` mount should reuse, with only the backing model swapped.
 *
 * Format (read and write):
 *
 *   ---
 *   id: …            read-only
 *   title: …         writable
 *   status: todo     writable; must be one of the workspace list statuses
 *   priority: medium writable; low | medium | high | urgent
 *   assignee: Name   writable; a workspace member/agent name, slug or id; null unassigns
 *   due_date: null   writable; YYYY-MM-DD or null
 *   project: …       read-only (moving between projects is not a VFS operation)
 *   project_id: …    read-only
 *   version: …       the version token; used as the base when no version parameter is given
 *   ---
 *
 *   Description markdown (writable).
 *
 * Paths: `/lists/by-id/{id}` (canonical; `.md` accepted),
 * `/lists/by-id/{projectId}/{item}.md`, `/lists/projects/{project}/{item}.md`.
 * Create: vfs_write `/lists/by-id/{projectId}/new.md` or
 * `/lists/projects/{project}/new.md`. Trash: rm the item; restore: mv it out of
 * `/lists/.trash/` (it returns to its project).
 *
 * Governance: create = vfs_write + create_list_item, overwrite = vfs_write +
 * update_list_item, patch = vfs_patch + update_list_item (reversible_edit);
 * trash/restore = delete_list_item (delete). Today's ownership rule stays:
 * only the item's creator or assignee may change or trash it.
 */
trait MutatesVfsListItems
{
    private const LIST_ITEM_PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    private const LIST_ITEM_READ_ONLY_FIELDS = ['id', 'project', 'project_id', 'creator', 'created_at', 'updated_at', 'path', 'canonical_path'];

    /**
     * Resolve a list item from its path forms.
     *
     * @param  list<string>  $segments
     */
    private function resolveListItemSegments(array $segments, string $path): ListItem
    {
        if (($segments[1] ?? null) === 'projects' && isset($segments[2])) {
            $project = $this->resolveListProject($segments[2], $path);
            if (! isset($segments[3])) {
                return $project;
            }
            if (isset($segments[4])) {
                throw VfsError::notFound($path);
            }

            return $this->resolveListChild($project, $segments[3], $path);
        }

        if (($segments[1] ?? null) !== 'by-id' || ! isset($segments[2]) || isset($segments[4])) {
            throw VfsError::notFound($path);
        }

        $id = preg_replace('/\.md$/i', '', $segments[2]) ?? $segments[2];
        $item = ListItem::forWorkspace()->find($id) ?? throw new VfsError('not_found', "Path not found: {$path}", [
            'path' => $path,
            'hint' => 'List /lists/by-id or /lists/projects to find items.',
        ]);

        return isset($segments[3]) ? $this->resolveListChild($item, $segments[3], $path) : $item;
    }

    private function resolveListProject(string $token, string $path): ListItem
    {
        $project = $this->resolveFriendlySegment(
            ListItem::forWorkspace()->where('is_folder', true)->whereNull('parent_id'),
            'title',
            $token,
            '/lists/projects',
            $path,
            fn (Model $candidate): string => "/lists/by-id/{$candidate->getKey()}",
        );

        return $project instanceof ListItem ? $project : throw VfsError::notFound($path);
    }

    private function assertListItemWritable(User $agent, ListItem $item, string $path): void
    {
        if ($item->is_folder) {
            throw new VfsError('not_writable', "Path is not writable through VFS: {$path}", [
                'path' => $path,
                'hint' => 'Projects are managed with list tools; write items inside them.',
            ]);
        }
        if ($item->creator_id !== $agent->id && $item->assignee_id !== $agent->id) {
            throw VfsError::permissionDenied($path, 'list item is not owned by or assigned to this agent');
        }
    }

    /**
     * Validate and normalize writable fields (status, priority, assignee,
     * due_date) into model attributes. Unknown statuses are rejected with
     * the allowed list.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function normalizeListItemFields(array $fields, ?ListItem $item, string $path): array
    {
        if (array_key_exists('title', $fields)) {
            $title = trim((string) $fields['title']);
            if ($title === '') {
                throw VfsError::invalid('title must not be empty.', ['path' => $path, 'field' => 'title']);
            }
            $fields['title'] = $title;
        }

        if (array_key_exists('status', $fields)) {
            $status = (string) $fields['status'];
            $statuses = ListStatus::forWorkspace()->orderBy('position')->get(['slug', 'is_done']);
            $match = $statuses->firstWhere('slug', $status);
            if ($statuses->isNotEmpty() && $match === null) {
                throw VfsError::invalid("Unknown list status: {$status}", [
                    'path' => $path,
                    'field' => 'status',
                    'allowed' => $statuses->pluck('slug')->all(),
                ]);
            }
            $fields['completed_at'] = $match?->is_done ? ($item->completed_at ?? now()) : null;
        }

        if (array_key_exists('priority', $fields) && ! in_array($fields['priority'], self::LIST_ITEM_PRIORITIES, true)) {
            throw VfsError::invalid('Unknown priority: '.json_encode($fields['priority']), [
                'path' => $path,
                'field' => 'priority',
                'allowed' => self::LIST_ITEM_PRIORITIES,
            ]);
        }

        if (array_key_exists('assignee', $fields)) {
            $fields['assignee_id'] = $this->resolveListAssignee($fields['assignee'], $path);
            unset($fields['assignee']);
        }

        if (array_key_exists('due_date', $fields)) {
            $due = $fields['due_date'];
            if ($due instanceof \DateTimeInterface) {
                $due = $due->format('Y-m-d');
            } elseif (is_int($due)) {
                $due = date('Y-m-d', $due);
            }
            if ($due !== null && $due !== '' && (! is_string($due) || strtotime($due) === false)) {
                throw VfsError::invalid('due_date must be YYYY-MM-DD or null.', ['path' => $path, 'field' => 'due_date']);
            }
            $fields['due_date'] = $due === '' ? null : $due;
        }

        return $fields;
    }

    private function resolveListAssignee(mixed $value, string $path): ?string
    {
        if ($value === null || $value === '' || $value === 'Unassigned') {
            return null;
        }

        $token = trim((string) $value);
        $workspaceId = workspace()->id;
        $users = User::query()->where(function ($query) use ($workspaceId): void {
            $query->where('workspace_id', $workspaceId)
                ->orWhereHas('workspaces', fn ($membership) => $membership->where('workspaces.id', $workspaceId));
        });

        $matches = (clone $users)->where(fn ($query) => $query->whereKey($token)->orWhere('name', $token))->limit(2)->get();
        if ($matches->isEmpty()) {
            $matches = (clone $users)->limit(500)->get()->filter(fn (User $user): bool => $this->slug((string) $user->name) === $token)->values();
        }
        if ($matches->count() !== 1) {
            throw VfsError::invalid("Unknown or ambiguous assignee: {$token}", [
                'path' => $path,
                'field' => 'assignee',
                'hint' => 'Use a workspace member or agent name, its slug, or its id; null unassigns.',
            ]);
        }

        return (string) $matches->first()->id;
    }

    /**
     * Render one list item as front-matter markdown.
     */
    private function listItemMarkdown(ListItem $item): string
    {
        $item->loadMissing(['assignee:id,name', 'parent:id,title']);
        $front = [
            'id' => $item->id,
            'title' => $item->title,
            'status' => $item->status,
            'priority' => $item->priority,
            'assignee' => $item->assignee?->name,
            'assignee_id' => $item->assignee_id,
            'due_date' => $item->due_date?->format('Y-m-d'),
            'project' => $item->parent?->title,
            'project_id' => $item->parent_id,
            'version' => $this->listItemVersion($item),
        ];

        return "---\n".Yaml::dump($front, 2, 2, Yaml::DUMP_NULL_AS_TILDE)."---\n\n".(string) ($item->description ?? '');
    }

    /**
     * @return array{front: array<string, mixed>, body: string}
     */
    private function parseListItemMarkdown(string $content, string $path): array
    {
        $content = str_replace("\r\n", "\n", $content);
        if (preg_match('/\A---\n(.*?)\n---\n?(.*)\z/s', $content, $m) !== 1) {
            throw VfsError::invalid('List items are markdown with YAML front matter between --- lines.', [
                'path' => $path,
                'hint' => "Start with:\n---\ntitle: …\nstatus: …\n---\nThen the description.",
            ]);
        }

        try {
            $front = Yaml::parse($m[1], Yaml::PARSE_DATETIME);
        } catch (ParseException $e) {
            throw VfsError::invalid('Front matter is not valid YAML: '.$e->getMessage(), ['path' => $path]);
        }
        if (! is_array($front)) {
            throw VfsError::invalid('Front matter must be a YAML mapping.', ['path' => $path]);
        }
        foreach (['due_date'] as $key) {
            if (($front[$key] ?? null) instanceof \DateTimeInterface) {
                $front[$key] = $front[$key]->format('Y-m-d');
            }
        }

        return ['front' => $front, 'body' => ltrim($m[2], "\n")];
    }

    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function writeListPath(User $agent, array $segments, string $path, string $content, string $mode, ?string $version, array $toolParameters): array
    {
        if (end($segments) === 'new.md') {
            $projectSegments = array_slice($segments, 0, -1);
            $project = $this->resolveListItemSegments($projectSegments, $path);
            if (! $project->is_folder) {
                throw VfsError::invalid('new.md must be written inside a project.', ['path' => $path]);
            }

            return $this->createListItemRecord($agent, $project, $path, $content, $toolParameters);
        }

        $item = $this->resolveListItemSegments($segments, $path);
        if ($mode === 'create') {
            throw VfsError::invalid("Path already exists: {$path}", ['path' => $path, 'hint' => 'Use mode "overwrite" with the item version.']);
        }

        return $this->overwriteListItemRecord($agent, $item, $path, $content, $version, $toolParameters);
    }

    /**
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function createListItemRecord(User $agent, ListItem $project, string $path, string $content, array $toolParameters): array
    {
        ['front' => $front, 'body' => $body] = $this->parseListItemMarkdown($content, $path);
        $unknown = array_diff(array_keys($front), ['title', 'status', 'priority', 'assignee', 'due_date']);
        if ($unknown !== []) {
            throw VfsError::invalid('Unsupported front matter for a new item: '.implode(', ', $unknown), [
                'path' => $path,
                'allowed' => ['title', 'status', 'priority', 'assignee', 'due_date'],
            ]);
        }
        if (! isset($front['title'])) {
            throw VfsError::invalid('A new item needs a title in its front matter.', ['path' => $path, 'field' => 'title']);
        }

        $fields = $this->normalizeListItemFields([
            'status' => $front['status'] ?? (ListStatus::forWorkspace()->where('is_default', true)->value('slug') ?? 'backlog'),
            'priority' => $front['priority'] ?? 'medium',
            ...$front,
        ], null, $path);
        $fields['assignee_id'] ??= array_key_exists('assignee', $front) ? null : $agent->id;

        $this->assertMutationToolsAllowed($agent, $path, ['vfs_write', 'create_list_item'], $toolParameters, $this->channelIdFrom($toolParameters), [
            'kind' => 'list_item',
            'action_class' => 'reversible_edit',
            'mode' => 'create',
            'project' => $project->title,
            'title' => $fields['title'],
        ], 'write', 'Creating list items requires vfs_write and create_list_item.', actionClass: VfsActionClass::REVERSIBLE_EDIT);

        $item = DB::transaction(function () use ($agent, $project, $fields, $body): ListItem {
            $position = (int) (ListItem::forWorkspace()->where('parent_id', $project->id)->where('is_folder', false)->where('status', $fields['status'])->max('position') ?? 0);

            return ListItem::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $agent->workspace_id,
                'parent_id' => $project->id,
                'is_folder' => false,
                'description' => $body,
                'creator_id' => $agent->id,
                'position' => $position + 1,
                ...array_intersect_key($fields, array_flip(['title', 'status', 'priority', 'assignee_id', 'due_date', 'completed_at'])),
            ]);
        });

        return $this->listItemWriteResult('created', $item->fresh() ?? $item);
    }

    /**
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function overwriteListItemRecord(User $agent, ListItem $item, string $path, string $content, ?string $version, array $toolParameters): array
    {
        $this->assertListItemWritable($agent, $item, $path);
        ['front' => $front, 'body' => $body] = $this->parseListItemMarkdown($content, $path);

        $version ??= isset($front['version']) ? (string) $front['version'] : null;
        if ($version === null) {
            throw VfsError::invalid("Overwriting {$path} requires the version you read.", [
                'path' => $path,
                'field' => 'version',
                'hint' => 'Read the item first; keep its front matter `version` line (or pass version).',
            ]);
        }
        $this->assertVersion($version, $this->listItemVersion($item), $path);
        unset($front['version']);

        $current = $this->parseListItemMarkdown($this->listItemMarkdown($item), $path)['front'];
        foreach (self::LIST_ITEM_READ_ONLY_FIELDS as $key) {
            if (array_key_exists($key, $front) && (string) ($front[$key] ?? '') !== (string) ($current[$key] ?? '')) {
                throw VfsError::invalid("{$key} is read-only.", ['path' => $path, 'field' => $key, 'hint' => 'Keep read-only front matter as you read it.']);
            }
        }
        $writable = array_diff_key($front, array_flip([...self::LIST_ITEM_READ_ONLY_FIELDS, 'assignee_id']));
        if (array_key_exists('assignee_id', $front) && ! array_key_exists('assignee', $front)) {
            $writable['assignee'] = $front['assignee_id'];
        } elseif (array_key_exists('assignee', $front) && ($front['assignee'] ?? null) === ($current['assignee'] ?? null)) {
            unset($writable['assignee']);
        }
        $unknown = array_diff(array_keys($writable), ['title', 'status', 'priority', 'assignee', 'due_date']);
        if ($unknown !== []) {
            throw VfsError::invalid('Unsupported front matter: '.implode(', ', $unknown), ['path' => $path]);
        }

        $fields = $this->normalizeListItemFields([...$writable, 'description' => $body], $item, $path);
        if (! array_key_exists('status', $writable)) {
            unset($fields['completed_at']);
        }

        $this->assertMutationToolsAllowed($agent, $path, ['vfs_write', 'update_list_item'], $toolParameters, $this->channelIdFrom($toolParameters), [
            'kind' => 'list_item',
            'action_class' => 'reversible_edit',
            'mode' => 'overwrite',
            'list_item_id' => $item->id,
            'fields' => array_keys($fields),
        ], 'write', 'Overwriting list items requires vfs_write and update_list_item.', actionClass: VfsActionClass::REVERSIBLE_EDIT);

        $fresh = DB::transaction(function () use ($item, $fields, $version, $path): ListItem {
            $locked = ListItem::forWorkspace()->whereKey($item->id)->lockForUpdate()->first() ?? throw VfsError::notFound($path);
            $this->assertVersion($version, $this->listItemVersion($locked), $path);
            $locked->update($fields);

            return $locked->fresh() ?? $locked;
        });

        return $this->listItemWriteResult('updated', $fresh);
    }

    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function trashListItemPath(User $agent, array $segments, string $path, array $toolParameters): array
    {
        $item = $this->resolveListItemSegments($segments, $path);
        $this->assertListItemWritable($agent, $item, $path);

        $this->mutationPolicy->assertAllowedOrRequestApproval(
            agent: $agent,
            directToolSlug: 'vfs_rm',
            operation: 'delete',
            subject: $path,
            requiredTools: ['delete_list_item'],
            parameters: $toolParameters,
            channelId: $this->channelIdFrom($toolParameters),
            hint: 'Moving a list item to the trash requires delete_list_item.',
            preview: ['kind' => 'list_item', 'action_class' => 'delete', 'title' => $item->title, 'id' => $item->id],
            actionClass: VfsActionClass::DELETE,
        );

        DB::transaction(function () use ($agent, $item, $path): void {
            $locked = ListItem::forWorkspace()->whereKey($item->id)->lockForUpdate()->first() ?? throw VfsError::notFound($path);
            $locked->forceFill(['deleted_by' => $agent->id])->save();
            $locked->delete();
        });

        return [
            'status' => 'trashed',
            'path' => $path,
            'canonical_path' => "/lists/.trash/{$item->id}.md",
            'restore_hint' => "mv /lists/.trash/{$item->id}.md /lists/by-id/{$item->parent_id} restores it.",
        ];
    }

    /**
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function restoreListItemPath(User $agent, string $source, string $destination, array $toolParameters): array
    {
        $segments = $this->segments($source);
        if (($segments[1] ?? null) !== '.trash' || ! isset($segments[2]) || isset($segments[3])) {
            throw VfsError::unsupported('List items cannot be moved through the VFS; only trashed items can be restored with mv.', [
                'source' => $source,
                'hint' => 'Use list tools to move an item to another project.',
            ]);
        }
        if (! str_starts_with($destination, '/lists')) {
            throw VfsError::invalid('Restore a list item into /lists (it returns to its project).', ['destination' => $destination]);
        }
        $item = $this->resolveTrashedListItem($segments[2], $source);
        $this->assertListItemWritable($agent, $item, $source);

        $this->mutationPolicy->assertAllowedOrRequestApproval(
            agent: $agent,
            directToolSlug: 'vfs_mv',
            operation: 'restore',
            subject: $source,
            requiredTools: ['delete_list_item'],
            parameters: $toolParameters,
            channelId: $this->channelIdFrom($toolParameters),
            hint: 'Restoring a trashed list item requires delete_list_item.',
            preview: ['kind' => 'list_item', 'action_class' => 'reversible_edit', 'title' => $item->title, 'id' => $item->id],
            actionClass: VfsActionClass::REVERSIBLE_EDIT,
        );

        $fresh = DB::transaction(function () use ($item, $source): ListItem {
            $locked = ListItem::onlyTrashed()->where('workspace_id', $item->workspace_id)->whereKey($item->id)->lockForUpdate()->first() ?? throw VfsError::notFound($source);
            $locked->forceFill(['deleted_by' => null]);
            $locked->restore();

            return $locked->fresh() ?? $locked;
        });

        return $this->listItemWriteResult('restored', $fresh);
    }

    private function resolveTrashedListItem(string $token, string $path): ListItem
    {
        $id = preg_replace('/\.md$/i', '', $token) ?? $token;

        return ListItem::onlyTrashed()->where('workspace_id', workspace()->id)->find($id) ?? throw VfsError::notFound($path);
    }

    /**
     * @return list<VfsEntry>
     */
    private function listTrashedListItems(User $agent, VfsBudget $budget): array
    {
        return ListItem::onlyTrashed()
            ->where('workspace_id', $agent->workspace_id)
            ->where('is_folder', false)
            ->orderByDesc('deleted_at')
            ->orderBy('id')
            ->limit($budget->maxEntries)
            ->get()
            ->map(fn (ListItem $item): VfsEntry => new VfsEntry(
                "{$item->id}.md",
                "/lists/.trash/{$item->id}.md",
                'file',
                "/lists/.trash/{$item->id}.md",
                'list_item',
                $item->id,
                capabilities: ['read'],
                metadata: [
                    'title' => $item->title,
                    'trashed' => true,
                    'deleted_at' => $item->deleted_at?->toIso8601String(),
                    'deleted_by' => $item->getAttribute('deleted_by'),
                    'restore_hint' => "mv /lists/.trash/{$item->id}.md /lists/by-id/{$item->parent_id} restores it.",
                ],
            ))
            ->all();
    }

    private function listItemVersion(ListItem $item): string
    {
        return $this->version($item->updated_at?->toIso8601String(), json_encode([
            $item->title,
            $item->description,
            $item->status,
            $item->priority,
            $item->assignee_id,
            $item->due_date?->format('Y-m-d'),
            $item->parent_id,
        ]) ?: '');
    }

    /**
     * @return array<string, mixed>
     */
    private function listItemWriteResult(string $status, ListItem $item): array
    {
        return [
            'status' => $status,
            'id' => $item->id,
            'path' => "/lists/by-id/{$item->id}",
            'canonical_path' => "/lists/by-id/{$item->id}",
            'version' => $this->listItemVersion($item),
        ];
    }
}
