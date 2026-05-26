<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsFind implements Tool
{
    use HandlesVfsJson;

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function description(): string
    {
        return 'Lua helper for finding VFS entries by name/type across bounded directory trees.';
    }

    public function handle(Request $request): string
    {
        try {
            $paths = $request['paths'] ?? [$request['path'] ?? '/'];
            $paths = is_array($paths) ? array_values($paths) : [(string) $paths];
            $budget = $this->budgetFromRequest($request);
            $offset = $this->cursorOffset($request);
            $returnLimit = $budget->maxEntries;
            $walkBudget = new VfsBudget(
                maxEntries: min($offset + $returnLimit + 1, 1_000),
                maxDepth: $budget->maxDepth,
                maxBytes: $budget->maxBytes,
                maxFiles: $budget->maxFiles,
                maxMatches: $budget->maxMatches,
                maxLineLength: $budget->maxLineLength,
            );
            $items = [];
            $seen = [];
            $scanned = 0;
            $skipped = 0;
            $truncated = false;

            foreach ($paths as $path) {
                $this->walk(
                    (string) $path,
                    $walkBudget,
                    $items,
                    $seen,
                    (string) ($request['name'] ?? $request['glob'] ?? '*'),
                    $request['type'] ?? null,
                    0,
                    $scanned,
                    $skipped,
                    $truncated,
                );
            }

            $truncated = $truncated || count($items) > $offset + $returnLimit;
            $items = array_slice($items, $offset, $returnLimit);

            return $this->encodeResult([
                'paths' => $paths,
                'items' => $items,
                'count' => count($items),
                'returned' => count($items),
                'limit' => $returnLimit,
                'cursor' => $request['cursor'] ?? null,
                'next_cursor' => $this->nextCursor($offset, count($items), $truncated || $scanned >= $budget->maxFiles),
                'scanned' => $scanned,
                'max_files' => $budget->maxFiles,
                'skipped' => $skipped,
                'truncated' => $truncated || $scanned >= $budget->maxFiles,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'paths' => $schema->array()->description('Directory paths to search. Defaults to /.'),
            'name' => $schema->string()->description('fnmatch-style name filter such as *.md. Default: *.'),
            'glob' => $schema->string()->description('Alias for name, for Lua callers that think in glob filters.'),
            'type' => $schema->string()->description('Optional entry type: file or directory.'),
            'limit' => $schema->integer()->description('Maximum entries to return. Default: 100.'),
            'cursor' => $schema->string()->description('Opaque cursor from a previous truncated response.'),
            'maxDepth' => $schema->integer()->description('Maximum recursion depth. Default: 3.'),
            'maxFiles' => $schema->integer()->description('Maximum entries to scan. Default: 100.'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function walk(string $path, VfsBudget $budget, array &$items, array &$seen, string $name, mixed $type, int $depth, int &$scanned, int &$skipped, bool &$truncated): void
    {
        if ($depth > $budget->maxDepth || count($items) >= $budget->maxEntries || $scanned >= $budget->maxFiles) {
            $truncated = $depth > $budget->maxDepth || count($items) >= $budget->maxEntries || $scanned >= $budget->maxFiles;

            return;
        }

        $listBudget = new VfsBudget(
            min(max($budget->maxEntries, $budget->maxFiles), 1_000),
            $budget->maxDepth,
            $budget->maxBytes,
            $budget->maxFiles,
            $budget->maxMatches,
            $budget->maxLineLength,
        );

        try {
            $stat = $this->vfs->stat($this->agent, $path);
            $scanned++;
            $item = [
                'name' => basename($path) === '' ? '/' : basename($path),
                'path' => $this->vfs->normalizePath($path),
                'type' => $stat['type'] ?? 'directory',
                'canonical_path' => $stat['canonical_path'] ?? $this->vfs->normalizePath($path),
                'capabilities' => $stat['capabilities'] ?? [],
                'metadata' => $stat['metadata'] ?? [],
            ];
            $typeMatches = $type === null || $item['type'] === $type || ($type === 'f' && $item['type'] === 'file') || ($type === 'd' && $item['type'] === 'directory');
            $nameMatches = $name === '*' || fnmatch($name, $item['name']);
            if ($typeMatches && $nameMatches) {
                $key = (string) ($item['canonical_path'] ?? $item['path']);
                $seen[$key] = true;
                $items[] = $item;
            }
            if ($depth >= $budget->maxDepth || count($items) >= $budget->maxEntries || $scanned >= $budget->maxFiles) {
                $truncated = count($items) >= $budget->maxEntries || $scanned >= $budget->maxFiles;

                return;
            }

            $entries = $this->vfs->list($this->agent, $path, $listBudget);
        } catch (\Throwable) {
            $skipped++;

            return;
        }

        foreach ($entries as $entry) {
            if ($scanned >= $budget->maxFiles) {
                $truncated = true;

                return;
            }
            $scanned++;
            $typeMatches = $type === null || $entry->type === $type || ($type === 'f' && $entry->type === 'file') || ($type === 'd' && $entry->type === 'directory');
            $nameMatches = $name === '*'
                || fnmatch($name, $entry->name)
                || ($name === '*.md' && $entry->type === 'file' && (($entry->metadata['content_format'] ?? null) === 'markdown'));

            if ($typeMatches && $nameMatches) {
                $key = $entry->canonicalPath ?? $entry->path;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $items[] = $entry->jsonSerialize();
                if (count($items) >= $budget->maxEntries) {
                    $truncated = true;

                    return;
                }
            }

            if ($entry->type === 'directory') {
                $this->walk($entry->canonicalPath ?? $entry->path, $budget, $items, $seen, $name, $type, $depth + 1, $scanned, $skipped, $truncated);
            }
        }
    }
}
