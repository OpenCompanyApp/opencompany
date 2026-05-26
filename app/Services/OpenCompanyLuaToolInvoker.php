<?php

namespace App\Services;

use App\Agents\Runtime\Permissions\OpenCompanyPermissionEvaluator;
use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\Integrations\IntegrationRuntime;
use OpenCompany\IntegrationCore\Contracts\LuaToolInvoker;

/**
 * Routes Lua bridge calls into OpenCompany's integration runtime.
 *
 * Lua scripts should not instantiate tools directly. This invoker keeps Lua
 * calls on the same permission, account-alias, and result-normalization path as
 * agent SDK tool calls.
 */
class OpenCompanyLuaToolInvoker implements LuaToolInvoker
{
    public function __construct(
        private User $agent,
        private ToolRegistry $registry,
        private ?IntegrationRuntime $runtime = null,
        private ?OpenCompanyPermissionEvaluator $permissions = null,
    ) {}

    public function invoke(string $toolSlug, array $args, ?string $account = null): mixed
    {
        $toolMeta = $this->registry->getToolDefinitionBySlug($toolSlug);
        if ($toolMeta === null) {
            throw new \RuntimeException("Tool not available: {$toolSlug}");
        }

        $permissionSlug = $this->permissionSlugFor($toolSlug, $toolMeta);
        $permissionMeta = $this->registry->getToolDefinitionBySlug($permissionSlug) ?? $toolMeta;

        $decision = ($this->permissions ?? app(OpenCompanyPermissionEvaluator::class))
            ->evaluate($this->agent, $permissionSlug, [], $permissionMeta);

        if ($decision->decision === 'deny') {
            throw new \RuntimeException("Permission denied for app tool {$toolSlug}: {$decision->reason}");
        }

        if ($decision->decision === 'approval_required') {
            throw new \RuntimeException("Approval required for app tool {$toolSlug}: {$decision->reason}");
        }

        $runtime = $this->runtime ?? new IntegrationRuntime($this->registry);

        // Permission evaluation happens above because Lua reaches tools through
        // direct slug invocation rather than ToolRegistry::getToolsForAgent().
        // The runtime still owns package/MCP dispatch and result normalization.
        $result = $runtime->call($this->agent, $toolSlug, $args, $account);

        return $this->normalizeVfsLuaResult($toolSlug, $toolMeta, $result);
    }

    /**
     * Lua-only VFS helpers are convenience functions over the primitive VFS
     * interface. Permission toggles should continue to live on the primitives,
     * not on hidden helper slugs that humans never see in the capabilities UI.
     *
     * @param  array<string, mixed>  $toolMeta
     */
    private function permissionSlugFor(string $toolSlug, array $toolMeta): string
    {
        if (! str_starts_with($toolSlug, 'vfs_') || empty($toolMeta['luaOnly'])) {
            return $toolSlug;
        }

        return match ((string) ($toolMeta['type'] ?? 'read')) {
            'write' => 'vfs_write',
            default => 'vfs_exec',
        };
    }

    public function getToolMeta(string $toolSlug): array
    {
        return $this->registry->getToolMetaBySlug($toolSlug);
    }

    /**
     * Keep the stable `{ ok, result = ... }` envelope while making Lua helper
     * results easier to script: result fields are mirrored at the top level, and
     * list-like helpers expose their items as numeric array entries so `#res`
     * works in natural Lua code.
     */
    private function normalizeVfsLuaResult(string $toolSlug, array $toolMeta, mixed $result): mixed
    {
        if (! str_starts_with($toolSlug, 'vfs_') || ! is_array($result) || ($result['ok'] ?? null) !== true || ! is_array($result['result'] ?? null)) {
            return $result;
        }

        $payload = $result['result'];
        $sequenceKey = is_array($payload['items'] ?? null)
            ? 'items'
            : (is_array($payload['matches'] ?? null) ? 'matches' : null);
        $items = $sequenceKey !== null ? $payload[$sequenceKey] : null;

        foreach ($payload as $key => $value) {
            $result[$key] ??= $value;
        }

        if ($items !== null) {
            $result['__vfs_sequence_key'] = $sequenceKey;
            foreach (array_values($items) as $index => $item) {
                $result[$index + 1] = $item;
            }
        }

        return $result;
    }
}
