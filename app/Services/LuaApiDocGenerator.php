<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use OpenCompany\IntegrationCore\Contracts\ProvidesLuaDocs;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class LuaApiDocGenerator
{
    /** @var array<string, array{description: string, functions: array}>|null */
    private ?array $cachedNamespaces = null;

    private ?User $cachedAgent = null;

    public function __construct(
        private ToolRegistry $registry,
        private ToolProviderRegistry $providerRegistry,
    ) {}

    /**
     * Generate a full namespace index listing all available functions.
     */
    public function generateNamespaceIndex(User $agent, ?string $filterNamespace = null): string
    {
        $namespaces = $this->buildNamespaces($agent);

        if ($filterNamespace !== null) {
            $namespaces = array_filter(
                $namespaces,
                fn (mixed $_value, string $key) => $key === $filterNamespace || str_starts_with($key, $filterNamespace . '.'),
                ARRAY_FILTER_USE_BOTH,
            );

            if (empty($namespaces)) {
                return "Namespace '{$filterNamespace}' not found. Available: " . implode(', ', array_keys($this->buildNamespaces($agent)));
            }
        }

        $lines = ['Available Lua API namespaces:', ''];

        foreach ($namespaces as $nsName => $ns) {
            $lines[] = "**app.{$nsName}** — {$ns['description']}";

            foreach ($ns['functions'] as $fn) {
                $sig = $this->buildSignature($fn);
                $lines[] = "  app.{$nsName}.{$sig}";
            }

            $lines[] = '';
        }

        // Append supplementary docs
        $staticPages = $this->getStaticPages();
        if (!empty($staticPages)) {
            $lines[] = 'Supplementary docs: ' . implode(', ', array_keys($staticPages));
            $lines[] = 'Use lua_read_doc to read any page or namespace in detail.';
        }

        return implode("\n", $lines);
    }

    /**
     * Generate detailed docs for a specific namespace.
     */
    public function generateNamespaceDocs(string $namespace, User $agent): string
    {
        $namespaces = $this->buildNamespaces($agent);

        if (!isset($namespaces[$namespace])) {
            return "Namespace '{$namespace}' not found. Available: " . implode(', ', array_keys($namespaces));
        }

        $ns = $namespaces[$namespace];
        $lines = ["# app.{$namespace} — {$ns['description']}", ''];

        foreach ($ns['functions'] as $fn) {
            $sig = $this->buildSignature($fn);
            $lines[] = "## app.{$namespace}.{$sig}";
            $lines[] = '';

            if (!empty($fn['description'])) {
                $lines[] = $fn['description'];
                $lines[] = '';
            }

            $lines[] = $this->formatParameterTable($fn['parameters']);
            $lines[] = '';
        }

        // Append integration-specific supplementary docs if available
        $supplementary = $this->getProviderLuaDocs($namespace);
        if ($supplementary !== null) {
            $lines[] = '---';
            $lines[] = '';
            $lines[] = $supplementary;
        }

        return implode("\n", $lines);
    }

    /**
     * Generate docs for a single function within a namespace.
     */
    public function generateFunctionDocs(string $namespace, string $function, User $agent): string
    {
        $namespaces = $this->buildNamespaces($agent);

        if (!isset($namespaces[$namespace])) {
            return "Namespace '{$namespace}' not found.";
        }

        $fn = collect($namespaces[$namespace]['functions'])->firstWhere('name', $function);

        if (!$fn) {
            $available = implode(', ', array_column($namespaces[$namespace]['functions'], 'name'));

            return "Function '{$function}' not found in app.{$namespace}. Available: {$available}";
        }

        $sig = $this->buildSignature($fn);
        $lines = [
            "# app.{$namespace}.{$sig}",
            '',
        ];

        if (!empty($fn['description'])) {
            $lines[] = $fn['description'];
            $lines[] = '';
        }

        if (!empty($fn['fullDescription']) && $fn['fullDescription'] !== ($fn['description'] ?? '')) {
            $lines[] = $fn['fullDescription'];
            $lines[] = '';
        }

        $lines[] = $this->formatParameterTable($fn['parameters']);

        if (!empty($fn['sourceToolSlug'])) {
            $lines[] = '';
            $lines[] = "*(Maps to tool: `{$fn['sourceToolSlug']}`)*";
        }

        return implode("\n", $lines);
    }

    /**
     * Search across all auto-generated and static docs.
     */
    public function search(string $query, User $agent, int $limit = 10): string
    {
        $queryLower = strtolower($query);
        $results = [];

        // Search auto-generated namespaces
        $namespaces = $this->buildNamespaces($agent);

        foreach ($namespaces as $nsName => $ns) {
            foreach ($ns['functions'] as $fn) {
                $score = $this->scoreMatch($fn, $nsName, $queryLower);

                if ($score > 0) {
                    $sig = $this->buildSignature($fn);
                    $results[] = [
                        'score' => $score,
                        'text' => "**app.{$nsName}.{$sig}** — {$fn['description']}",
                    ];
                }
            }
        }

        // Search static docs
        $staticPages = $this->getStaticPages();
        foreach ($staticPages as $slug => $path) {
            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }

            if (stripos($content, $query) !== false) {
                // Extract matching context
                $contextSnippet = $this->extractSearchContext($content, $query);
                $results[] = [
                    'score' => 1,
                    'text' => "**[{$slug}]** (supplementary doc)\n{$contextSnippet}",
                ];
            }
        }

        // Sort by score desc
        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);
        $results = array_slice($results, 0, $limit);

        if (empty($results)) {
            return "No results found for '{$query}'.";
        }

        $lines = ["Found " . count($results) . " result(s) for '{$query}':", ''];
        foreach ($results as $r) {
            $lines[] = $r['text'];
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Build the namespace structure from the tool catalog.
     *
     * @return array<string, array{description: string, functions: array}>
     */
    private function buildNamespaces(User $agent): array
    {
        // Cache per agent within the same request
        if ($this->cachedNamespaces !== null && $this->cachedAgent?->id === $agent->id) {
            return $this->cachedNamespaces;
        }

        $catalog = $this->registry->getToolCatalog($agent);
        $namespaces = [];

        foreach ($catalog as $app) {
            $appName = $app['name'];

            // Skip meta/system tools that aren't meaningful as Lua API
            if (in_array($appName, ['tasks', 'system', 'lua'])) {
                continue;
            }

            // Determine namespace
            if ($app['isIntegration'] ?? false) {
                $nsName = "integrations.{$appName}";
            } else {
                $nsName = $appName;
            }

            $functions = [];

            foreach ($app['tools'] as $tool) {
                $slug = $tool['slug'];

                // MCP tools: extract server name from slug pattern mcp_{server}__{tool}
                if (str_starts_with($slug, 'mcp_')) {
                    $nsName = $this->mcpNamespace($slug);
                    $fnName = $this->mcpFunctionName($slug);
                    $functions[] = $this->buildFunction($fnName, $tool, $slug);

                    continue;
                }

                $fnName = $this->deriveFunctionName($tool['name'], $appName);
                $functions[] = $this->buildFunction($fnName, $tool, $slug);
            }

            if (empty($functions)) {
                continue;
            }

            // MCP tools might create multiple namespaces, merge them
            if (!isset($namespaces[$nsName])) {
                $namespaces[$nsName] = [
                    'description' => $app['description'],
                    'functions' => [],
                ];
            }

            $namespaces[$nsName]['functions'] = array_merge(
                $namespaces[$nsName]['functions'],
                $functions,
            );
        }

        // Sort namespaces: internal first, then integrations, then mcp
        uksort($namespaces, function ($a, $b) {
            $aWeight = str_starts_with($a, 'mcp.') ? 2 : (str_starts_with($a, 'integrations.') ? 1 : 0);
            $bWeight = str_starts_with($b, 'mcp.') ? 2 : (str_starts_with($b, 'integrations.') ? 1 : 0);

            return $aWeight <=> $bWeight ?: strcmp($a, $b);
        });

        $this->cachedNamespaces = $namespaces;
        $this->cachedAgent = $agent;

        return $namespaces;
    }

    /**
     * Build a function entry from a tool without action decomposition.
     */
    private function buildFunction(string $name, array $tool, string $slug): array
    {
        return [
            'name' => $name,
            'description' => $tool['fullDescription'] ?? $tool['description'],
            'fullDescription' => $tool['fullDescription'] ?? '',
            'parameters' => $tool['parameters'] ?? [],
            'sourceToolSlug' => $slug,
        ];
    }

    /**
     * Derive a Lua function name from a tool's human-readable name and its app name.
     * Strips words that share a root with the app name, plus prepositions.
     *
     * Examples:
     *   ("Query Documents", "docs")    → "query"
     *   ("Comment on Document", "docs") → "comment"
     *   ("Manage Table Rows", "tables") → "manage_rows"
     *   ("Create Task", "clickup")      → "create_task"
     *   ("Send Channel Message", "chat") → "send_channel_message"
     */
    private function deriveFunctionName(string $toolName, string $appName): string
    {
        // Lowercase and replace any non-alphanumeric chars with underscores to ensure valid Lua identifiers
        $snake = strtolower(trim($toolName));
        $snake = preg_replace('/[^a-z0-9]+/', '_', $snake);
        $snake = trim($snake, '_');

        $words = explode('_', $snake);
        $appBase = rtrim(strtolower($appName), 's');

        $filtered = array_values(array_filter($words, function ($word) use ($appBase) {
            if (in_array($word, ['on', 'of', 'for', 'in', 'to', 'the', 'a', 'an'])) {
                return false;
            }

            $wordBase = rtrim($word, 's');

            return !str_contains($wordBase, $appBase) && !str_contains($appBase, $wordBase);
        }));

        return implode('_', $filtered) ?: $snake;
    }

    /**
     * Extract MCP namespace from tool slug: mcp_{server}__{tool} → mcp.{server}
     */
    private function mcpNamespace(string $slug): string
    {
        // Pattern: mcp_{server_slug}__{tool_name}
        if (preg_match('/^mcp_(.+?)__/', $slug, $matches)) {
            return 'mcp.' . $matches[1];
        }

        return 'mcp';
    }

    /**
     * Extract MCP function name from tool slug: mcp_{server}__{tool} → {tool}
     */
    private function mcpFunctionName(string $slug): string
    {
        if (preg_match('/^mcp_.+?__(.+)$/', $slug, $matches)) {
            return preg_replace('/[^a-z0-9]+/', '_', strtolower($matches[1]));
        }

        return preg_replace('/[^a-z0-9]+/', '_', strtolower($slug));
    }

    /**
     * Build a function signature string like: send(channelId, content)
     */
    private function buildSignature(array $fn): string
    {
        $params = [];
        foreach ($fn['parameters'] as $param) {
            $name = $param['name'];
            $required = $param['required'] ?? false;
            $params[] = $required ? $name : $name . '?';
        }

        return $fn['name'] . '(' . implode(', ', $params) . ')';
    }

    /**
     * Format parameters as a markdown table.
     */
    private function formatParameterTable(array $parameters): string
    {
        if (empty($parameters)) {
            return '*No parameters.*';
        }

        $lines = [
            '| Parameter | Type | Required | Description |',
            '|-----------|------|----------|-------------|',
        ];

        foreach ($parameters as $param) {
            $name = $param['name'];
            $type = $param['type'] ?? 'string';
            if (is_array($type)) {
                $type = implode(' | ', $type);
            }
            $required = ($param['required'] ?? false) ? 'yes' : 'no';
            $desc = $param['description'] ?? '';

            // Add enum values if present
            if (!empty($param['enum'])) {
                $enumStr = implode(', ', array_map(fn ($v) => "`{$v}`", $param['enum']));
                $desc .= ($desc ? ' ' : '') . "Values: {$enumStr}";
            }

            $lines[] = "| {$name} | {$type} | {$required} | {$desc} |";
        }

        return implode("\n", $lines);
    }

    /**
     * Score how well a function matches a search query.
     */
    private function scoreMatch(array $fn, string $nsName, string $queryLower): int
    {
        $score = 0;

        // Score against each query word individually
        $words = array_filter(explode(' ', $queryLower));

        foreach ($words as $word) {
            // Exact function name match
            if (strtolower($fn['name']) === $word) {
                $score += 10;
            } elseif (str_contains(strtolower($fn['name']), $word)) {
                $score += 5;
            }

            // Namespace match
            if (str_contains(strtolower($nsName), $word)) {
                $score += 3;
            }

            // Description match
            if (str_contains(strtolower($fn['description'] ?? ''), $word)) {
                $score += 2;
            }

            // Parameter name/description match
            foreach ($fn['parameters'] as $param) {
                if (str_contains(strtolower($param['name']), $word)) {
                    $score += 1;
                }
                if (str_contains(strtolower($param['description'] ?? ''), $word)) {
                    $score += 1;
                }
            }
        }

        // Bonus: full query appears in description
        if (str_contains(strtolower($fn['description'] ?? ''), $queryLower)) {
            $score += 5;
        }

        return $score;
    }

    /**
     * Extract context around a search match in static docs.
     */
    private function extractSearchContext(string $content, string $query): string
    {
        $lines = explode("\n", $content);
        $snippets = [];

        foreach ($lines as $i => $line) {
            if (stripos($line, $query) === false) {
                continue;
            }

            // Get ±2 lines of context
            $start = max(0, $i - 2);
            $end = min(count($lines) - 1, $i + 2);
            $context = array_slice($lines, $start, $end - $start + 1);
            $snippets[] = implode("\n", $context);

            if (count($snippets) >= 2) {
                break;
            }
        }

        return implode("\n...\n", $snippets);
    }

    /**
     * Get available static documentation pages.
     *
     * @return array<string, string> slug => file path
     */
    private function getStaticPages(): array
    {
        $dir = resource_path('lua-docs');

        if (!is_dir($dir)) {
            return [];
        }

        $pages = [];
        foreach (glob($dir . '/*.md') as $file) {
            $slug = pathinfo($file, PATHINFO_FILENAME);
            // Normalize _overview → overview
            $slug = ltrim($slug, '_');
            $pages[$slug] = $file;
        }

        return $pages;
    }

    /**
     * Read a static documentation page.
     */
    public function readStaticPage(string $slug): ?string
    {
        $pages = $this->getStaticPages();

        if (isset($pages[$slug])) {
            $content = file_get_contents($pages[$slug]);

            return $content !== false ? $content : null;
        }

        return null;
    }

    /**
     * Get all available page names (namespaces + static pages).
     *
     * @return list<string>
     */
    public function getAvailablePages(User $agent): array
    {
        $pages = array_keys($this->getStaticPages());
        $pages = array_merge($pages, array_keys($this->buildNamespaces($agent)));

        sort($pages);

        return $pages;
    }

    /**
     * Build a flat map of function paths to tool slugs.
     * Used by LuaBridge to route Lua app.* calls to PHP tools.
     *
     * @return array<string, string> path => toolSlug
     */
    public function buildFunctionMap(User $agent): array
    {
        $namespaces = $this->buildNamespaces($agent);
        $map = [];

        foreach ($namespaces as $nsName => $ns) {
            foreach ($ns['functions'] as $fn) {
                $map[$nsName . '.' . $fn['name']] = $fn['sourceToolSlug'];
            }
        }

        return $map;
    }

    /**
     * Get supplementary Lua docs from a ToolProvider if it implements ProvidesLuaDocs.
     * Works for integration namespaces (e.g., "integrations.clickup" → provider "clickup").
     */
    private function getProviderLuaDocs(string $namespace): ?string
    {
        // Extract app name: "integrations.clickup" → "clickup", "chat" → "chat"
        $appName = str_starts_with($namespace, 'integrations.')
            ? substr($namespace, strlen('integrations.'))
            : $namespace;

        $provider = $this->providerRegistry->get($appName);

        if ($provider instanceof ProvidesLuaDocs) {
            $path = $provider->luaDocsPath();
            if (is_file($path)) {
                $content = file_get_contents($path);

                return $content !== false ? $content : null;
            }
        }

        return null;
    }

    /**
     * Get a compact namespace summary for the system prompt, grouped by tier.
     * Shows Internal / Integrations / MCP categories with app.* prefixed names.
     */
    public function getNamespaceSummary(User $agent): string
    {
        $namespaces = $this->buildNamespaces($agent);

        $internal = [];
        $integrations = [];
        $mcp = [];

        foreach (array_keys($namespaces) as $ns) {
            if (str_starts_with($ns, 'mcp.')) {
                $mcp[] = "app.{$ns}";
            } elseif (str_starts_with($ns, 'integrations.')) {
                $integrations[] = "app.{$ns}";
            } else {
                $internal[] = "app.{$ns}";
            }
        }

        $lines = [];

        if (!empty($internal)) {
            $lines[] = '  Internal: ' . implode(', ', $internal);
        }
        if (!empty($integrations)) {
            $lines[] = '  Integrations: ' . implode(', ', $integrations);
        }
        if (!empty($mcp)) {
            $lines[] = '  MCP: ' . implode(', ', $mcp);
        }

        $lines[] = '  Use lua_read_doc(page) for function signatures and parameters.';

        return implode("\n", $lines);
    }
}
