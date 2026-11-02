<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsPageCursor;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Code Mode helper `app.vfs.find` (`vfs_find`, script-only, read).
 *
 * Resumable depth-first name/type search; cursors are bound to paths, filters, workspace and actor. Governed by the `vfs_exec` permission
 * (ToolRegistry `permissionAs`); authorization and audit are the
 * OpenCompanyVfs facade's. Description and schema come from
 * VfsOperationSpecRegistry.
 */
class VfsFind implements ReturnsVfsEnvelope, Tool
{
    use HandlesVfsJson;

    public const TOOL_SLUG = 'vfs_find';

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function handle(Request $request): string
    {
        try {
            $input = $this->input($request);
            $paths = $request['paths'] ?? [$request['path'] ?? '/'];
            $paths = is_array($paths) ? array_map(fn (mixed $path): string => $this->vfs->normalizePath((string) $path), array_values($paths)) : [$this->vfs->normalizePath((string) $paths)];
            $budget = $input->budget();
            $name = $input->string('name');
            $type = $request['type'] ?? null;
            if ($type !== null && ! is_string($type)) {
                throw VfsError::invalid('type must be a string.', ['field' => 'type']);
            }

            $binding = 'vfs-find:'.hash('sha256', json_encode([
                'paths' => $paths,
                'name' => $name,
                'type' => $type,
                'max_depth' => $budget->maxDepth,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $scope = hash('sha256', (string) $this->agent->workspace_id.'|'.(string) $this->agent->id);
            $cursor = $input->cursor();
            $payload = VfsPageCursor::decode($cursor, $binding, 'find-depth-first-v1', $scope);
            $state = $this->findState($payload['state']);
            $items = [];
            $scanned = 0;
            $skipped = 0;
            $this->walkFindPage($paths, $name, $type, $budget, $state, $items, $scanned, $skipped);
            $hasMore = $state['stack'] !== [] || $state['root_index'] < count($paths);
            $nextCursor = $hasMore
                ? VfsPageCursor::encode(
                    $binding,
                    'find-depth-first-v1',
                    $scope,
                    $state,
                    $payload['seen'] + count($items),
                )
                : null;

            return $this->encodeResult([
                'paths' => $paths,
                'items' => $items,
                'count' => count($items),
                'returned' => count($items),
                'limit' => $budget->maxEntries,
                'cursor' => $cursor,
                'next_cursor' => $nextCursor,
                'seen_before' => $payload['seen'],
                'scanned' => $scanned,
                'max_files' => $budget->maxFiles,
                'skipped' => $skipped,
                'truncated' => $hasMore,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Resume a depth-first traversal without replaying earlier result offsets.
     * Directory frames retain their mount-owned listing cursor, while visited
     * canonical-path hashes prevent alias cycles without granting access.
     *
     * @param  list<string>  $paths
     * @param  array{root_index: int, stack: list<array<string, mixed>>, visited: array<string, true>}  $state
     * @param  list<array<string, mixed>>  $items
     */
    private function walkFindPage(array $paths, string $name, ?string $type, VfsBudget $budget, array &$state, array &$items, int &$scanned, int &$skipped): void
    {
        while (count($items) < $budget->maxEntries && $scanned < $budget->maxFiles) {
            if ($state['stack'] === []) {
                if ($state['root_index'] >= count($paths)) {
                    return;
                }

                $path = $paths[$state['root_index']];
                $state['root_index']++;
                $state['stack'][] = [...$this->findFrame($path, basename($path) === '' ? '/' : basename($path), 0), 'display' => $path];
            }

            $frameIndex = array_key_last($state['stack']);
            $frame = &$state['stack'][$frameIndex];

            if (! $frame['self_done']) {
                try {
                    $item = $frame['item'] !== null
                        ? $this->findCursorItem($frame['item'])
                        : $this->findRootItem((string) $frame['path']);
                    if ($frame['item'] === null) {
                        $scanned++;
                    }
                } catch (\Throwable) {
                    $skipped++;
                    array_pop($state['stack']);
                    unset($frame);

                    continue;
                }

                $canonicalPath = (string) ($item['canonical_path'] ?? $item['path'] ?? $frame['path']);
                $visitedKey = hash('sha256', $canonicalPath);
                if (isset($state['visited'][$visitedKey])) {
                    array_pop($state['stack']);
                    unset($frame);

                    continue;
                }
                $state['visited'][$visitedKey] = true;
                $frame['self_done'] = true;
                $frame['path'] = $canonicalPath;
                $frame['item'] = $item;

                if ($this->findItemMatches($item, $name, $type)) {
                    $items[] = $item;
                }
                if (($item['type'] ?? null) !== 'directory' || $frame['depth'] >= $budget->maxDepth) {
                    $frame['list_done'] = true;
                }
                if (count($items) >= $budget->maxEntries) {
                    unset($frame);

                    return;
                }
            }

            if ($frame['list_done']) {
                array_pop($state['stack']);
                unset($frame);

                continue;
            }

            try {
                $remainingScan = max(1, $budget->maxFiles - $scanned);
                $page = $this->vfs->listPage($this->agent, (string) $frame['path'], new VfsBudget(
                    maxEntries: 1,
                    maxDepth: $budget->maxDepth,
                    maxBytes: $budget->maxBytes,
                    maxFiles: $remainingScan,
                    maxMatches: $budget->maxMatches,
                    maxLineLength: $budget->maxLineLength,
                ), is_string($frame['list_cursor']) ? $frame['list_cursor'] : null);
                $scanned += $page->scanned;
                $skipped += $page->skipped;
                $frame['list_cursor'] = $page->nextCursor;
                if ($page->nextCursor === null) {
                    $frame['list_done'] = true;
                }
                if ($page->entries === []) {
                    unset($frame);

                    continue;
                }

                $entry = $page->entries[0];
                // Alias mounts (/me) duplicate canonical objects (VfsEntry::isAlias()).
                if ($entry->isAlias()) {
                    unset($frame);

                    continue;
                }
                // Items report the friendly path under the requested root
                // (parent display path + disambiguated entry name) and keep
                // the by-id path in canonical_path; traversal and the
                // visited set use the canonical path (docs/tools/vfs.md
                // "Paths in search and find output").
                $display = rtrim((string) ($frame['display'] ?? $frame['item']['path'] ?? $frame['path']), '/').'/'.$entry->name;
                $payload = $this->entryPayload($entry);
                $payload['canonical_path'] ??= $entry->canonicalPath ?? $entry->path;
                $payload['path'] = $display;
                $state['stack'][] = [...$this->findFrame(
                    $entry->canonicalPath ?? $entry->path,
                    $entry->name,
                    ((int) $frame['depth']) + 1,
                    $payload,
                ), 'display' => $display];
                unset($frame);
            } catch (\Throwable) {
                $skipped++;
                $frame['list_done'] = true;
                unset($frame);
            }
        }
    }

    /** @return array<string, mixed> */
    private function findRootItem(string $path): array
    {
        $stat = $this->vfs->stat($this->agent, $path);
        $canonicalPath = is_string($stat['canonical_path'] ?? null) ? $stat['canonical_path'] : $this->vfs->normalizePath($path);

        return [
            'name' => basename($path) === '' ? '/' : basename($path),
            'path' => $this->vfs->normalizePath($path),
            'type' => $stat['type'] ?? 'directory',
            'canonical_path' => $canonicalPath,
            'capabilities' => $stat['capabilities'] ?? [],
            'metadata' => $stat['metadata'] ?? [],
        ];
    }

    /** @param array<string, mixed> $item */
    private function findItemMatches(array $item, string $name, ?string $type): bool
    {
        $itemType = (string) ($item['type'] ?? 'directory');
        $typeMatches = $type === null || $itemType === $type || ($type === 'f' && $itemType === 'file') || ($type === 'd' && $itemType === 'directory');
        $itemName = (string) ($item['name'] ?? '');
        $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
        $nameMatches = $name === '*'
            || fnmatch($name, $itemName)
            || ($name === '*.md' && $itemType === 'file' && (($metadata['content_format'] ?? null) === 'markdown'));

        return $typeMatches && $nameMatches;
    }

    /**
     * @param  array<string, mixed>|null  $item
     * @return array<string, mixed>
     */
    private function findFrame(string $path, string $name, int $depth, ?array $item = null): array
    {
        return [
            'path' => $path,
            'name' => $name,
            'depth' => $depth,
            'item' => $item,
            'self_done' => false,
            'list_cursor' => null,
            'list_done' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{root_index: int, stack: list<array<string, mixed>>, visited: array<string, true>}
     */
    private function findState(array $state): array
    {
        if ($state === []) {
            return ['root_index' => 0, 'stack' => [], 'visited' => []];
        }
        if (
            ! isset($state['root_index'], $state['stack'], $state['visited'])
            || ! is_int($state['root_index'])
            || $state['root_index'] < 0
            || ! is_array($state['stack'])
            || ! is_array($state['visited'])
        ) {
            throw VfsError::invalid('cursor is invalid or expired.', ['field' => 'cursor']);
        }

        foreach ($state['stack'] as $frame) {
            if (! is_array($frame)
                || ! is_string($frame['path'] ?? null)
                || ! is_string($frame['name'] ?? null)
                || ! is_int($frame['depth'] ?? null)
                || ! is_bool($frame['self_done'] ?? null)
                || ! is_bool($frame['list_done'] ?? null)
                || (! is_null($frame['item'] ?? null) && ! is_array($frame['item']))
                || (! is_null($frame['list_cursor'] ?? null) && ! is_string($frame['list_cursor']))) {
                throw VfsError::invalid('cursor is invalid or expired.', ['field' => 'cursor']);
            }
        }
        foreach ($state['visited'] as $visited) {
            if ($visited !== true) {
                throw VfsError::invalid('cursor is invalid or expired.', ['field' => 'cursor']);
            }
        }

        /** @var array{root_index: int, stack: list<array<string, mixed>>, visited: array<string, true>} $state */
        return $state;
    }

    /** @return array<string, mixed> */
    private function findCursorItem(mixed $value): array
    {
        if (! is_array($value)) {
            throw VfsError::invalid('cursor is invalid or expired.', ['field' => 'cursor']);
        }

        $item = [];
        foreach ($value as $key => $field) {
            if (! is_string($key)) {
                throw VfsError::invalid('cursor is invalid or expired.', ['field' => 'cursor']);
            }
            $item[$key] = $field;
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function entryPayload(VfsEntry $entry): array
    {
        return $entry->jsonSerialize();
    }
}
