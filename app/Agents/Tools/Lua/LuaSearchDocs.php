<?php

namespace App\Agents\Tools\Lua;

use App\Models\User;
use App\Services\LuaApiDocGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class LuaSearchDocs implements Tool
{
    public function __construct(
        private LuaApiDocGenerator $docs,
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Search the Lua scripting API documentation by keyword. Searches function names, descriptions, and parameter info across all available namespaces and supplementary docs.';
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
            return "Error searching Lua API docs: {$e->getMessage()}";
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
