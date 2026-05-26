<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use OpenCompany\IntegrationCore\Lua\LuaBridge as SharedLuaBridge;

/**
 * App-specific wrapper around the shared integration-core Lua bridge.
 *
 * The sibling integration package owns argument mapping and Lua call dispatch.
 * OpenCompany supplies the agent-specific function map, account map, and tool
 * invoker so Lua scripts execute under the same permissions as normal tools.
 */
class LuaBridge
{
    private SharedLuaBridge $bridge;

    /** @var list<array{path: string, durationMs: float, status: string, error?: string, icon?: string, name?: string, group?: string}> */
    private array $localCallLog = [];

    private OpenCompanyLuaToolInvoker $invoker;

    public function __construct(
        private User $agent,
        private ToolRegistry $registry,
        LuaApiDocGenerator $docGenerator,
    ) {
        $this->invoker = new OpenCompanyLuaToolInvoker($agent, $registry);
        $this->bridge = new SharedLuaBridge(
            $docGenerator->buildFunctionMap($agent),
            $docGenerator->buildParameterMap($agent),
            $this->invoker,
            $docGenerator->buildAccountMap($agent),
        );
    }

    public function call(string $path, mixed ...$args): mixed
    {
        if (str_starts_with($path, 'vfs.')) {
            return $this->callVfs($path, $args);
        }

        // Delegate to the package bridge so OpenCompany does not duplicate the
        // Lua function binding semantics used by package integrations.
        return $this->bridge->call($path, ...$args);
    }

    /** @return list<array{path: string, durationMs: float, status: string, error?: string, icon?: string, name?: string, group?: string}> */
    public function getCallLog(): array
    {
        return array_merge($this->bridge->getCallLog(), $this->localCallLog);
    }

    /**
     * VFS is a first-class app-local namespace rather than a package
     * integration. Handling it here lets Lua expose ergonomic unix-like helper
     * signatures such as `app.vfs.rg(pattern, paths, opts)` while still routing
     * execution through the same primitive tool slugs and permission checks.
     *
     * @param  array<int, mixed>  $args
     */
    private function callVfs(string $path, array $args): mixed
    {
        $function = substr($path, strlen('vfs.'));
        $toolSlug = match ($function) {
            'exec' => 'vfs_exec',
            'patch' => 'vfs_patch',
            'write' => 'vfs_write',
            'stat' => 'vfs_stat',
            'ls' => 'vfs_ls',
            'read', 'cat' => 'vfs_read',
            'rg', 'grep' => 'vfs_rg',
            'find' => 'vfs_find',
            'search' => 'vfs_search',
            'exists' => 'vfs_exists',
            'mkdir' => 'vfs_mkdir',
            'cp' => 'vfs_cp',
            'mv' => 'vfs_mv',
            'rm' => 'vfs_rm',
            'count' => 'vfs_count',
            default => throw new \RuntimeException("Unknown function: app.{$path}. Did you mean: exec, stat, ls, read, rg, find, search, exists, mkdir, cp, mv, rm, count, patch, write"),
        };

        $params = $this->vfsParams($function, $args);
        $toolMeta = $this->registry->getToolMetaBySlug($toolSlug);
        $start = microtime(true);

        try {
            $result = $this->invoker->invoke($toolSlug, $params);
            $this->localCallLog[] = [
                'path' => $path,
                'durationMs' => round((microtime(true) - $start) * 1000, 1),
                'status' => 'ok',
                'icon' => $toolMeta['icon'] ?? 'ph:tree-structure',
                'name' => $toolMeta['name'] ?? $toolSlug,
                'group' => 'vfs',
            ];

            return $result;
        } catch (\Throwable $e) {
            $this->localCallLog[] = [
                'path' => $path,
                'durationMs' => round((microtime(true) - $start) * 1000, 1),
                'status' => 'error',
                'error' => $e->getMessage(),
                'icon' => $toolMeta['icon'] ?? 'ph:tree-structure',
                'name' => $toolMeta['name'] ?? $toolSlug,
                'group' => 'vfs',
            ];

            throw $e;
        }
    }

    /**
     * @param  array<int, mixed>  $args
     * @return array<string, mixed>
     */
    private function vfsParams(string $function, array $args): array
    {
        if (isset($args[0]) && is_array($args[0]) && $this->hasStringKey($args[0]) && in_array($function, ['exec', 'patch', 'write', 'stat', 'ls', 'read', 'cat', 'rg', 'grep', 'find', 'search', 'exists', 'mkdir', 'cp', 'mv', 'rm', 'count'], true)) {
            $params = $this->normalizeOptionKeys($args[0]);
            if ($function === 'grep') {
                $params['regex'] ??= false;
            } elseif ($function === 'rg') {
                $params['regex'] ??= true;
            } elseif ($function === 'write') {
                $params['mode'] ??= 'overwrite';
            }

            return $params;
        }

        return match ($function) {
            'exec' => array_merge(['command' => (string) ($args[0] ?? '')], $this->options($args[1] ?? [])),
            'stat', 'ls', 'read', 'cat', 'exists', 'mkdir', 'rm', 'count' => array_merge(['path' => (string) ($args[0] ?? '/')], $this->options($args[1] ?? [])),
            'rg', 'grep' => array_merge([
                'pattern' => (string) ($args[0] ?? ''),
                'paths' => $this->paths($args[1] ?? ['/']),
                'regex' => $function === 'rg',
            ], $this->options($args[2] ?? [])),
            'search' => array_merge([
                'query' => (string) ($args[0] ?? ''),
                'paths' => $this->paths($args[1] ?? ['/']),
            ], $this->options($args[2] ?? [])),
            'find' => array_merge([
                'paths' => $this->paths($args[0] ?? ['/']),
            ], $this->options($args[1] ?? [])),
            'patch' => array_merge([
                'path' => (string) ($args[0] ?? ''),
                'patch' => (string) ($args[1] ?? ''),
            ], $this->options($args[2] ?? [])),
            'write' => array_merge([
                'path' => (string) ($args[0] ?? ''),
                'content' => (string) ($args[1] ?? ''),
                'mode' => 'overwrite',
            ], $this->options($args[2] ?? [])),
            'cp', 'mv' => array_merge([
                'source' => (string) ($args[0] ?? ''),
                'destination' => (string) ($args[1] ?? ''),
            ], $this->options($args[2] ?? [])),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function options(mixed $value): array
    {
        return is_array($value) ? $this->normalizeOptionKeys($value) : [];
    }

    /**
     * @return list<string>
     */
    private function paths(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map(fn (mixed $path): string => (string) $path, $value));
        }

        return [(string) $value];
    }

    /**
     * @param  array<string|int, mixed>  $options
     * @return array<string, mixed>
     */
    private function normalizeOptionKeys(array $options): array
    {
        $normalized = [];
        foreach ($options as $key => $value) {
            $normalizedKey = is_string($key) ? $this->snakeToCamel($key) : $key;
            $normalized[$normalizedKey] = $value;
        }
        if (isset($normalized['src']) && ! isset($normalized['source'])) {
            $normalized['source'] = $normalized['src'];
        }
        if (isset($normalized['dst']) && ! isset($normalized['destination'])) {
            $normalized['destination'] = $normalized['dst'];
        }
        if (isset($normalized['dest']) && ! isset($normalized['destination'])) {
            $normalized['destination'] = $normalized['dest'];
        }
        if (isset($normalized['overwrite']) && ! isset($normalized['mode'])) {
            $normalized['mode'] = $normalized['overwrite'] ? 'overwrite' : 'create';
        }

        return $normalized;
    }

    private function snakeToCamel(string $key): string
    {
        return lcfirst(str_replace('_', '', ucwords($key, '_')));
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function hasStringKey(array $value): bool
    {
        foreach (array_keys($value) as $key) {
            if (is_string($key)) {
                return true;
            }
        }

        return false;
    }
}
