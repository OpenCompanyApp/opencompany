<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsLs implements Tool
{
    use HandlesVfsJson;

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function description(): string
    {
        return 'Code Mode helper for listing a VFS directory. Returns structured entries instead of command text.';
    }

    public function handle(Request $request): string
    {
        try {
            $path = (string) ($request['path'] ?? '/');
            $budget = $this->budgetFromRequest($request);
            $cursor = $this->pageCursor($request);
            $page = $this->vfs->listPage($this->agent, $path, $budget, $cursor);
            $entries = array_map(
                fn ($entry): array => $entry->jsonSerialize(),
                $page->entries,
            );

            return $this->encodeResult([
                'path' => $this->vfs->normalizePath($path),
                'items' => $entries,
                'count' => count($entries),
                'returned' => count($entries),
                'limit' => $budget->maxEntries,
                'cursor' => $cursor,
                'next_cursor' => $page->nextCursor,
                'truncated' => $page->truncated,
                'seen_before' => $page->seenBefore,
                'scanned' => $page->scanned,
                'skipped' => $page->skipped,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('Directory path to list. Defaults to /.'),
            'limit' => $schema->integer()->description('Maximum entries to return. Default: 100.'),
            'maxFiles' => $schema->integer()->description('Requested backend scan ceiling when permissions hide entries; the effective ceiling is never below limit. Default: 100.'),
            'cursor' => $schema->string()->description('Opaque cursor from a previous truncated response.'),
        ];
    }
}
