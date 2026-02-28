<?php

namespace App\Agents\Tools\Lua;

use App\Models\User;
use App\Services\LuaApiDocGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class LuaReadDoc implements Tool
{
    public function __construct(
        private LuaApiDocGenerator $docs,
        private User $agent,
    ) {}

    public function description(): string
    {
        return <<<'DESC'
Read Lua API documentation for a namespace, function, or supplementary guide.

- Namespace (e.g. "chat") → full API reference with all functions and parameters
- Function (e.g. "chat.send") → detailed single-function docs
- Guide (e.g. "context", "errors", "examples") → supplementary documentation
DESC;
    }

    public function handle(Request $request): string
    {
        try {
            if (! isset($request['page']) || ! is_string($request['page']) || trim($request['page']) === '') {
                return 'Missing required parameter "page". Provide a namespace (e.g. "chat"), function path (e.g. "chat.send"), or guide name (e.g. "examples").';
            }

            $page = $request['page'];

            // Strip "app." prefix — docs display functions as app.namespace.fn()
            // but internal namespace keys don't include the "app." prefix
            if (str_starts_with($page, 'app.')) {
                $page = substr($page, 4);
            }

            // Try static page first
            $static = $this->docs->readStaticPage($page);
            if ($static !== null) {
                return $static;
            }

            // Try namespace.function format (e.g. "chat.send")
            if (str_contains($page, '.')) {
                $parts = explode('.', $page, 2);
                $namespace = $parts[0];
                $function = $parts[1];

                // Handle multi-level: "integrations.gmail.send_email" or "mcp.exa_search.search"
                if (in_array($namespace, ['integrations', 'mcp']) && str_contains($function, '.')) {
                    $subParts = explode('.', $function, 2);
                    $namespace = $namespace . '.' . $subParts[0];
                    $function = $subParts[1];
                }
                // Handle namespace-only: "integrations.gmail" or "mcp.exa_search"
                elseif (in_array($namespace, ['integrations', 'mcp'])) {
                    return $this->docs->generateNamespaceDocs($namespace . '.' . $function, $this->agent);
                }

                return $this->docs->generateFunctionDocs($namespace, $function, $this->agent);
            }

            // Try as namespace
            $namespaceDocs = $this->docs->generateNamespaceDocs($page, $this->agent);
            if (!str_starts_with($namespaceDocs, "Namespace '{$page}' not found.")) {
                return $namespaceDocs;
            }

            // Not found — show available pages
            $available = $this->docs->getAvailablePages($this->agent);

            return "Page '{$page}' not found. Available pages:\n\n"
                . "**Namespaces:** " . implode(', ', array_filter($available, fn ($p) => !in_array($p, ['overview', 'context', 'errors', 'examples']))) . "\n"
                . "**Guides:** overview, context, errors, examples";
        } catch (\Throwable $e) {
            return "Error reading Lua doc: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema
                ->string()
                ->description('Page to read: namespace name (e.g. "chat", "docs"), function path (e.g. "chat.send"), or guide name (e.g. "context", "errors", "examples", "overview").')
                ->required(),
        ];
    }
}
