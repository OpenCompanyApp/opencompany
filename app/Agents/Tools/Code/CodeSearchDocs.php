<?php

namespace App\Agents\Tools\Code;

use App\Models\User;
use App\Services\CodeApiDocGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Searches permission-visible function metadata and supplementary Code Mode docs.
 */
final class CodeSearchDocs implements Tool
{
    public function __construct(
        private CodeApiDocGenerator $docs,
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Search Code Mode docs by intent, function, parameter, or provider term. Read the selected function page before execution.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! isset($request['query']) || ! is_string($request['query']) || trim($request['query']) === '') {
                return 'Missing required parameter "query". Provide a search term (e.g. "send message", "calendar").';
            }

            $query = $request['query'];
            $limit = $request['limit'] ?? 10;

            return $this->docs->search($query, $this->agent, $limit);
        } catch (\Throwable $e) {
            return "Error searching Code Mode docs: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('The search query (e.g. "send message", "calendar", "create task").')
                ->required(),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of results. Default: 10.'),
        ];
    }
}
