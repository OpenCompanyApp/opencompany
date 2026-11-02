<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsCount implements ReturnsVfsEnvelope, Tool
{
    use HandlesVfsJson;

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function description(): string
    {
        return 'Code Mode helper for counting bounded directory entries with truncation metadata.';
    }

    public function handle(Request $request): string
    {
        try {
            $path = (string) ($request['path'] ?? '/');
            $budget = $this->budgetFromRequest($request);
            $cursor = $this->pageCursor($request);
            $page = $this->vfs->listPage($this->agent, $path, $budget, $cursor);
            $count = count($page->entries);

            return $this->encodeResult([
                'path' => $this->vfs->normalizePath($path),
                'count' => $count,
                'sampled_count' => $count,
                'total_count' => $page->truncated ? null : $page->seenBefore + $count,
                'count_mode' => $page->truncated ? 'sampled' : 'exact',
                'returned' => $count,
                'limit' => $budget->maxEntries,
                'cursor' => $cursor,
                'next_cursor' => $page->nextCursor,
                'truncated' => $page->truncated,
                'count_is_exact' => ! $page->truncated,
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
            'path' => $schema->string()->required()->description('Directory path to count.'),
            'limit' => $schema->integer()->description('Maximum entries to count. Default: 100.'),
            'maxFiles' => $schema->integer()->description('Requested backend scan ceiling when permissions hide entries; the effective ceiling is never below limit. Default: 100.'),
            'cursor' => $schema->string()->description('Opaque cursor from a previous truncated response.'),
        ];
    }
}
