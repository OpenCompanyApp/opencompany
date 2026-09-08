<?php

namespace App\Agents\Tools\Code;

use App\Models\User;
use App\Services\CodeApiDocGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Lists permission-visible Code Mode namespaces and supplementary guides.
 */
final class CodeListDocs implements Tool
{
    public function __construct(
        private CodeApiDocGenerator $docs,
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List permission-visible Code Mode namespaces. Use code_read_doc for exact inputs, effects, return shapes, and examples before calling a capability.';
    }

    public function handle(Request $request): string
    {
        try {
            $namespace = $request['namespace'] ?? null;

            return $this->docs->generateNamespaceIndex($this->agent, $namespace);
        } catch (\Throwable $e) {
            return "Error listing Code Mode docs: {$e->getMessage()}";
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
