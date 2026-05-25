<?php

namespace App\Services\Mcp;

class McpConfigImporter
{
    /**
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    public function normalizeServers(array $config): array
    {
        $servers = $config['mcpServers'] ?? $config['servers'] ?? [];

        return collect($servers)
            ->map(fn (array $server, string $name) => array_merge(['name' => $name], $server))
            ->values()
            ->all();
    }
}
