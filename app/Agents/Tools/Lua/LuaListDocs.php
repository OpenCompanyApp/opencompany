<?php

namespace App\Agents\Tools\Lua;

use App\Models\User;
use App\Services\LuaApiDocGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class LuaListDocs implements Tool
{
    public function __construct(
        private LuaApiDocGenerator $docs,
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List available Lua API namespaces and functions. Each namespace maps to a workspace app (chat, docs, tables, etc.). Shows function signatures with parameter names.';
    }

    public function handle(Request $request): string
    {
        try {
            $namespace = $request['namespace'] ?? null;

            return $this->docs->generateNamespaceIndex($this->agent, $namespace);
        } catch (\Throwable $e) {
            return "Error listing Lua API docs: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'namespace' => $schema
                ->string()
                ->description('Filter to a specific namespace (e.g. "chat", "docs", "mcp.github"). Omit to list all.'),
        ];
    }
}
