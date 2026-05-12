<?php

namespace App\Services\Mcp;

use App\Models\McpServer;
use Illuminate\Support\Str;

class McpToolCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forServer(McpServer $server): array
    {
        return collect($server->discovered_tools ?? [])
            ->map(fn (array $tool) => [
                'id' => 'mcp_'.$server->slug.'__'.Str::snake((string) $tool['name']),
                'serverId' => $server->id,
                'server' => $server->slug,
                'name' => $tool['name'],
                'description' => $tool['description'] ?? '',
                'schema' => $tool['inputSchema'] ?? [],
                'type' => 'mcp',
            ])
            ->values()
            ->all();
    }
}
