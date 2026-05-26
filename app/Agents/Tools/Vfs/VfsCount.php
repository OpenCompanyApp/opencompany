<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsCount implements Tool
{
    use HandlesVfsJson;

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function description(): string
    {
        return 'Lua helper for counting bounded directory entries with truncation metadata.';
    }

    public function handle(Request $request): string
    {
        try {
            $path = (string) ($request['path'] ?? '/');
            $budget = $this->budgetFromRequest($request);
            $offset = $this->cursorOffset($request);
            $probeBudget = new VfsBudget(
                maxEntries: min($offset + $budget->maxEntries + 1, 1_000),
                maxDepth: $budget->maxDepth,
                maxBytes: $budget->maxBytes,
                maxFiles: $budget->maxFiles,
                maxMatches: $budget->maxMatches,
                maxLineLength: $budget->maxLineLength,
            );
            $rawEntries = $this->vfs->list($this->agent, $path, $probeBudget);
            $entries = array_slice($rawEntries, $offset, $budget->maxEntries);
            $truncated = count($rawEntries) > $offset + $budget->maxEntries;

            return $this->encodeResult([
                'path' => $this->vfs->normalizePath($path),
                'count' => count($entries),
                'sampled_count' => count($entries),
                'total_count' => $truncated ? null : $offset + count($entries),
                'count_mode' => $truncated ? 'sampled' : 'exact',
                'returned' => count($entries),
                'limit' => $budget->maxEntries,
                'cursor' => $request['cursor'] ?? null,
                'next_cursor' => $this->nextCursor($offset, count($entries), $truncated),
                'truncated' => $truncated,
                'count_is_exact' => ! $truncated,
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
            'cursor' => $schema->string()->description('Opaque cursor from a previous truncated response.'),
        ];
    }
}
