<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsRg implements Tool
{
    use HandlesVfsJson;

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function description(): string
    {
        return 'Lua helper for exact or regex text search over VFS paths. Search is deterministic and permission-filtered, not semantic embedding retrieval.';
    }

    public function handle(Request $request): string
    {
        try {
            $pattern = (string) ($request['pattern'] ?? $request['query'] ?? '');
            $paths = $request['paths'] ?? [$request['path'] ?? '/'];
            $paths = is_array($paths) ? array_values($paths) : [(string) $paths];
            $budget = $this->budgetFromRequest($request);
            $ignoreCase = (bool) ($request['ignoreCase'] ?? $request['ignore_case'] ?? $request['caseInsensitive'] ?? $request['case_insensitive'] ?? false);
            $search = $this->vfs->searchWithMetadata($this->agent, $pattern, $paths, $budget, (bool) ($request['regex'] ?? true), $ignoreCase);
            $matches = $search['matches'];

            return $this->encodeResult([
                'pattern' => $pattern,
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
            'pattern' => $schema->string()->required()->description('Literal or regex pattern to search for.'),
            'paths' => $schema->array()->description('Virtual paths to search. Defaults to /.'),
            'regex' => $schema->boolean()->description('Treat pattern as a regular expression. Default: true for rg/app.vfs.rg; app.vfs.grep passes false.'),
            'maxMatches' => $schema->integer()->description('Maximum matches to return. Default: 100.'),
            'maxFiles' => $schema->integer()->description('Maximum files to scan. Default: 100.'),
            'maxDepth' => $schema->integer()->description('Maximum directory recursion depth. Default: 3.'),
            'ignoreCase' => $schema->boolean()->description('Case-insensitive search.'),
        ];
    }
}
