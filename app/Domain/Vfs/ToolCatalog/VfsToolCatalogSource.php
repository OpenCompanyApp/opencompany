<?php

namespace App\Domain\Vfs\ToolCatalog;

use App\Models\User;

/**
 * Source of the `/tools/index.json` and `/tools/catalog.md` content: the tool
 * surface one agent can use right now.
 *
 * Today it is backed by ToolRegistry (RegistryVfsToolCatalogSource, the
 * default when nothing is bound in the container). The
 * integration package catalog is being deleted; the intended next sources are
 * the connections directory, pinned MCP tool definitions and API manifests.
 * Implement this interface for them instead of teaching the `/tools` mount
 * about any one backend (agent breadcrumb: keep MountsVfsTools source-agnostic).
 *
 * Contract: permission-filtered for $agent (never list a tool the agent cannot
 * call), deterministic order, no credentials or live tool results.
 */
interface VfsToolCatalogSource
{
    /**
     * @return array{
     *     direct: list<array{slug: string, name: string, description: string, type: string, requires_approval: bool}>,
     *     code_mode: list<array{group: string, tools: list<string>}>
     * }
     */
    public function effectiveTools(User $agent): array;
}
