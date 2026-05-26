<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsSearch implements Tool
{
    use HandlesVfsJson;

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function description(): string
    {
        return 'Lua helper for lexical VFS search. This is separate from embeddings and returns exact VFS paths/snippets.';
    }

    public function handle(Request $request): string
    {
        try {
            $query = (string) ($request['query'] ?? $request['pattern'] ?? '');
            $paths = $request['paths'] ?? [$request['path'] ?? '/'];
            $paths = is_array($paths) ? array_values($paths) : [(string) $paths];
            $budget = $this->budgetFromRequest($request);
            $ignoreCase = (bool) ($request['ignoreCase'] ?? $request['ignore_case'] ?? $request['caseInsensitive'] ?? $request['case_insensitive'] ?? false);
            $search = $this->vfs->searchWithMetadata($this->agent, $query, $paths, $budget, false, $ignoreCase);
            $matches = $search['matches'];

            return $this->encodeResult([
                'query' => $query,
                'mode' => 'lexical',
                'paths' => $paths,
                'matches' => $matches,
                'count' => count($matches),
                'returned' => count($matches),
                'limit' => $budget->maxMatches,
                'scanned' => $search['scanned'],
                'max_files' => $search['max_files'],
                'skipped' => $search['skipped'],
                'backend' => $search['backend'],
                'truncated' => $search['truncated'],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('Lexical query text.'),
            'paths' => $schema->array()->description('Virtual paths to search. Defaults to /.'),
            'maxMatches' => $schema->integer()->description('Maximum matches to return. Default: 100.'),
            'maxFiles' => $schema->integer()->description('Maximum files to scan. Default: 100.'),
            'maxDepth' => $schema->integer()->description('Maximum directory recursion depth. Default: 3.'),
            'ignoreCase' => $schema->boolean()->description('Case-insensitive search.'),
        ];
    }
}
