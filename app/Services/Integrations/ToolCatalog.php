<?php

namespace App\Services\Integrations;

use App\Agents\Tools\ToolRegistry;

/**
 * Backward-compatible name for the agent tool registry.
 *
 * Older generated actions and long-running local processes may still resolve
 * this service name while the current runtime uses App\Agents\Tools\ToolRegistry
 * directly. Keep this adapter thin so tool ownership, permissions, package
 * discovery, and Lua catalog behavior remain centralized in ToolRegistry.
 */
class ToolCatalog extends ToolRegistry
{
    //
}
