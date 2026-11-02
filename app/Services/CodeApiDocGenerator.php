<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Domain\Vfs\Spec\VfsOperationSpecRegistry;
use App\Models\McpServer;
use App\Models\User;
use App\Domain\CodeMode\Script\ScriptCatalogBuilder;
use App\Domain\CodeMode\Script\ScriptDocRenderer;
use App\Agents\Tools\External\ToolProviderRegistry;

/**
 * Builds permission-scoped, searchable app.* documentation for Code Mode.
 *
 * Function/schema mapping lives in App\Domain\CodeMode\Script. OpenCompany adds
 * active account aliases, static product guides, and workspace-visible tools;
 * it never places credentials or live tool results in documentation caches.
 */
class CodeApiDocGenerator
{
    /**
     * Host-owned argument defaults for alias paths, applied by CodeBridge
     * before dispatch (caller values win), e.g. `vfs.grep` is literal and
     * `vfs.cat` raw. Generated from the VFS operation spec, like the alias
     * paths in buildFunctionMap(). A constant (not a method) so it holds even
     * when the generator is mocked.
     *
     * @var array<string, array<string, mixed>>
     */
    public const ALIAS_ARGUMENT_DEFAULTS = VfsOperationSpecRegistry::RUBY_ALIAS_DEFAULTS;

    /**
     * @var array<string, array{
     *     description: string,
     *     functions: array<int, array{
     *         name: string,
     *         description: string,
     *         fullDescription: string,
     *         parameters: array<int, array<string, mixed>>,
     *         sourceToolSlug: string
     *     }>
     * }>|null
     */
    private ?array $cachedNamespaces = null;

    private ?string $cachedNamespaceFingerprint = null;

    public function __construct(
        private ToolRegistry $registry,
    ) {}

    public function generateNamespaceIndex(User $agent, ?string $filterNamespace = null): string
    {
        $renderer = $this->docRenderer();

        if ($renderer === null) {
            return $this->getNamespaceSummary($agent);
        }

        $namespaces = $filterNamespace !== null && str_ends_with($filterNamespace, '.default')
            ? $this->buildScriptNamespaces($agent)
            : $this->buildVisibleNamespaces($agent);

        return $renderer->generateNamespaceIndex(
            $namespaces,
            $this->getStaticPageContents(),
            $filterNamespace,
        );
    }

    public function generateNamespaceDocs(string $namespace, User $agent): string
    {
        if ($namespace === 'vfs' && ($docs = $this->readStaticPage('vfs')) !== null) {
            return $docs;
        }

        $renderer = $this->docRenderer();

        if ($renderer === null) {
            return $this->getProviderScriptDocs($namespace) ?? "No Code Mode docs available for namespace '{$namespace}'.";
        }

        return $renderer->generateNamespaceDocs(
            $namespace,
            $this->buildScriptNamespaces($agent),
            fn (string $ns) => $this->getProviderScriptDocs($ns),
        );
    }

    public function generateFunctionDocs(string $namespace, string $function, User $agent): string
    {
        $renderer = $this->docRenderer();

        if ($renderer === null) {
            return "Code Mode docs renderer unavailable for {$namespace}.{$function}.";
        }

        return $renderer->generateFunctionDocs(
            $namespace,
            $function,
            $this->buildScriptNamespaces($agent),
        );
    }

    public function search(string $query, User $agent, int $limit = 10): string
    {
        $renderer = $this->docRenderer();

        if ($renderer === null) {
            return $this->getNamespaceSummary($agent);
        }

        return $renderer->search(
            $query,
            $this->buildVisibleNamespaces($agent),
            $this->getStaticPageContents(),
            $limit,
        );
    }

    /**
     * @return array<string, array{
     *     description: string,
     *     functions: array<int, array{
     *         name: string,
     *         description: string,
     *         fullDescription: string,
     *         parameters: array<int, array<string, mixed>>,
     *         sourceToolSlug: string
     *     }>
     * }>
     */
    private function buildScriptNamespaces(User $agent): array
    {
        $builder = $this->catalogBuilder();
        $catalog = $this->withAccountAliases($this->registry->getScriptToolCatalog($agent));
        $fingerprint = $this->namespaceFingerprint($agent, $catalog);

        // Permission, workspace, visible catalog, and account changes all
        // alter executable capability paths. Recompute the source catalog
        // before consulting this cache; an agent id alone is never safe here.
        if ($this->cachedNamespaces !== null && $this->cachedNamespaceFingerprint === $fingerprint) {
            return $this->cachedNamespaces;
        }

        $this->cachedNamespaces = $builder !== null
            ? $builder->buildNamespaces(
                $catalog,
                ['tasks', 'system', 'code'],
            )
            : [];
        $this->cachedNamespaceFingerprint = $fingerprint;

        return $this->cachedNamespaces;
    }

    /**
     * Build the unfiltered developer inventory.
     *
     * Developer tooling intentionally exposes disabled integrations for
     * browsing and configuration. It must not be reused for agent discovery.
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildDeveloperNamespaces(User $agent): array
    {
        $builder = $this->catalogBuilder();
        $catalog = $this->withAccountAliases($this->registry->getToolCatalog($agent));

        return $builder !== null
            ? $builder->buildNamespaces(
                $catalog,
                ['tasks', 'system', 'code'],
            )
            : [];
    }

    /**
     * Add only currently configured account aliases to a visible catalog.
     *
     * @param  array<int, array<string, mixed>>  $catalog
     * @return array<int, array<string, mixed>>
     */
    private function withAccountAliases(array $catalog): array
    {
        // Account aliases exist only for multi-account MCP servers now that the
        // integration package system (and its credential resolver) is gone.
        foreach ($catalog as &$app) {
            $appName = (string) ($app['name'] ?? '');
            if (! str_starts_with($appName, 'mcp_') || empty($app['isIntegration'])) {
                continue;
            }

            $accounts = McpServer::getAccountsFor(substr($appName, 4));
            if ($accounts !== []) {
                $app['accounts'] = $accounts;
            }
        }
        unset($app);

        return $catalog;
    }

    /**
     * Describe every input that may change executable script documentation.
     *
     * The registry recalculates visible integrations and permissions before
     * this is called. Hashing its resulting catalog covers permission decisions
     * (including approval-required writes), while the account-enriched payload
     * covers credential and MCP account changes without exposing secrets.
     *
     * @param  array<int, array<string, mixed>>  $catalog
     */
    private function namespaceFingerprint(User $agent, array $catalog): string
    {
        return hash('sha256', serialize([
            'actor_id' => $agent->getKey(),
            'workspace_id' => $agent->workspace_id,
            'catalog' => $catalog,
        ]));
    }

    /**
     * @return array<string, string> path => toolSlug
     */
    public function buildFunctionMap(User $agent): array
    {
        $builder = $this->catalogBuilder();

        $map = $builder !== null
            ? $builder->buildFunctionMap($this->buildScriptNamespaces($agent))
            : [];

        return $this->withVfsAliases($map);
    }

    /**
     * @return array<string, list<array<string, mixed>>> path => parameter definitions
     */
    public function buildParameterMap(User $agent): array
    {
        $builder = $this->catalogBuilder();

        $map = $builder !== null
            ? $builder->buildParameterMap($this->buildScriptNamespaces($agent))
            : [];

        return $this->withVfsAliases($map);
    }

    /**
     * Add the VFS alias paths (`vfs.shell`, `vfs.cat`, `vfs.grep`) declared in
     * VfsOperationSpecRegistry::RUBY_ALIASES. An alias exists only when its
     * target is visible to the agent, so it can never widen access.
     *
     * @param  array<string, mixed>  $map
     * @return array<string, mixed>
     */
    private function withVfsAliases(array $map): array
    {
        foreach (VfsOperationSpecRegistry::RUBY_ALIASES as $alias => $target) {
            if (isset($map[$target])) {
                $map[$alias] = $map[$target];
            }
        }

        return $map;
    }

    /**
     * @return array<string, string> path => accountAlias (only for multi-account function paths)
     */
    public function buildAccountMap(User $agent): array
    {
        $builder = $this->catalogBuilder();

        return $builder !== null
            ? $builder->buildAccountMap($this->buildScriptNamespaces($agent))
            : [];
    }

    /**
     * @return list<string>
     */
    public function getAvailablePages(User $agent): array
    {
        $renderer = $this->docRenderer();

        if ($renderer === null) {
            return array_keys($this->getStaticPageContents());
        }

        return array_values(array_unique($renderer->getAvailablePages(
            $this->buildVisibleNamespaces($agent),
            $this->getStaticPageContents(),
        )));
    }

    /**
     * Get supplementary Code Mode docs from a ToolProvider via scriptDocsPath().
     * Works for integration namespaces (e.g., "integrations.clickup" → provider "clickup").
     */
    private function getProviderScriptDocs(string $namespace): ?string
    {
        $providerRegistry = $this->providerRegistry();

        if ($providerRegistry === null) {
            return null;
        }

        $appName = str_starts_with($namespace, 'integrations.')
            ? substr($namespace, strlen('integrations.'))
            : $namespace;

        // Strip account segment for multi-account namespaces (e.g., "clickup.work" → "clickup")
        if ($providerRegistry->get($appName) === null && str_contains($appName, '.')) {
            $appName = explode('.', $appName, 2)[0];
        }

        $provider = $providerRegistry->get($appName);
        if ($provider === null) {
            return null;
        }

        $path = $provider->scriptDocsPath();
        if ($path === null || ! is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content !== false ? $content : null;
    }

    /**
     * @return array<string, array{
     *     description: string,
     *     functions: array<int, array{
     *         name: string,
     *         description: string,
     *         fullDescription: string,
     *         parameters: array<int, array<string, mixed>>,
     *         sourceToolSlug: string
     *     }>
     * }>
     */
    public function getNamespacesForCatalog(User $agent): array
    {
        return $this->buildDeveloperNamespaces($agent);
    }

    public function getSupplementaryDocs(string $namespace): ?string
    {
        return $this->getProviderScriptDocs($namespace);
    }

    /**
     * @return list<array{slug: string, title: string, content: string}>
     */
    public function getStaticDocsForCatalog(): array
    {
        $pages = $this->getStaticPages();
        $result = [];

        foreach ($pages as $slug => $path) {
            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }

            $title = ucfirst($slug);
            if (preg_match('/^#\s+(.+)$/m', $content, $matches) === 1) {
                $title = $matches[1];
            }

            $result[] = [
                'slug' => $slug,
                'title' => $title,
                'content' => $content,
            ];
        }

        return $result;
    }

    public function getNamespaceSummary(User $agent): string
    {
        $renderer = $this->docRenderer();
        $namespaces = $this->buildVisibleNamespaceSummary($agent);

        if ($renderer === null) {
            $namespaces = array_keys($namespaces);

            if ($namespaces === []) {
                return 'No external Code Mode API namespaces are available in this workspace.';
            }

            return "Available Code Mode namespaces:\n- ".implode("\n- ", $namespaces);
        }

        return $renderer->getNamespaceSummary($namespaces);
    }

    /**
     * Build prompt-summary namespace names from the same permission-scoped
     * catalog as executable documentation. Full docs still use
     * buildScriptNamespaces().
     *
     * @return array<string, array{description: string, functions: array<int, array{name: string, description: string, fullDescription: string, parameters: array<int, array<string, mixed>>, sourceToolSlug: string}>}>
     */
    private function buildVisibleNamespaceSummary(User $agent): array
    {
        $namespaces = [];

        foreach ($this->registry->getScriptToolCatalog($agent) as $app) {
            $appName = (string) ($app['name'] ?? '');

            if ($appName === '' || in_array($appName, ['tasks', 'system', 'code'], true)) {
                continue;
            }

            $namespace = ! empty($app['isIntegration'])
                ? "integrations.{$appName}"
                : $appName;

            $namespaces[$namespace] = [
                'description' => (string) ($app['description'] ?? ''),
                'functions' => [],
            ];
        }

        uksort($namespaces, function (string $a, string $b): int {
            $aWeight = str_starts_with($a, 'mcp.') ? 2 : (str_starts_with($a, 'integrations.') ? 1 : 0);
            $bWeight = str_starts_with($b, 'mcp.') ? 2 : (str_starts_with($b, 'integrations.') ? 1 : 0);

            return $aWeight <=> $bWeight ?: strcmp($a, $b);
        });

        return $namespaces;
    }

    /**
     * Hide redundant ".default" aliases from discovery surfaces while keeping
     * them available for explicit lookups and runtime execution.
     *
     * @return array<string, array{
     *     description: string,
     *     functions: array<int, array{
     *         name: string,
     *         description: string,
     *         fullDescription: string,
     *         parameters: array<int, array<string, mixed>>,
     *         sourceToolSlug: string
     *     }>
     * }>
     */
    private function buildVisibleNamespaces(User $agent): array
    {
        $namespaces = $this->buildScriptNamespaces($agent);

        foreach (array_keys($namespaces) as $namespace) {
            if (! str_ends_with($namespace, '.default')) {
                continue;
            }

            $base = substr($namespace, 0, -strlen('.default'));
            if ($base !== '' && array_key_exists($base, $namespaces)) {
                unset($namespaces[$namespace]);
            }
        }

        return $namespaces;
    }

    /**
     * @return array<string, string> slug => file path
     */
    private function getStaticPages(): array
    {
        $dir = resource_path('code-docs');

        if (! is_dir($dir)) {
            return [];
        }

        $pages = [];
        foreach (glob($dir.'/*.md') ?: [] as $file) {
            $slug = pathinfo($file, PATHINFO_FILENAME);
            $pages[ltrim($slug, '_')] = $file;
        }

        return $pages;
    }

    /**
     * @return array<string, string> slug => content
     */
    private function getStaticPageContents(): array
    {
        $contents = [];

        foreach ($this->getStaticPages() as $slug => $path) {
            $content = file_get_contents($path);
            if ($content !== false) {
                $contents[$slug] = $content;
            }
        }

        return $contents;
    }

    public function readStaticPage(string $slug): ?string
    {
        $pages = $this->getStaticPages();

        if (! isset($pages[$slug])) {
            return null;
        }

        $content = file_get_contents($pages[$slug]);

        return $content !== false ? $content : null;
    }

    /**
     * Kept for backward compatibility with tests and any internal reflection.
     */
    private function deriveFunctionName(string $toolName, string $appName): string
    {
        $builder = $this->catalogBuilder();

        return $builder !== null
            ? $builder->deriveFunctionName($toolName, $appName)
            : $toolName;
    }

    private function providerRegistry(): ?object
    {
        $class = ToolProviderRegistry::class;

        if (! class_exists($class) || ! app()->bound($class)) {
            return null;
        }

        return app($class);
    }

    private function catalogBuilder(): ?object
    {
        $class = ScriptCatalogBuilder::class;

        if (! class_exists($class) || ! app()->bound($class)) {
            return null;
        }

        return app($class);
    }

    private function docRenderer(): ?object
    {
        $class = ScriptDocRenderer::class;

        if (! class_exists($class) || ! app()->bound($class)) {
            return null;
        }

        return app($class);
    }
}
