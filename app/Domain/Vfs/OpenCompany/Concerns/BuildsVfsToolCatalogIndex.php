<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\ToolCatalog\RegistryVfsToolCatalogSource;
use App\Domain\Vfs\ToolCatalog\VfsToolCatalogSource;
use App\Models\User;

/**
 * Builds the `/tools/index.json` and `/tools/catalog.md` content from the
 * agent's effective tool surface.
 *
 * Source-agnostic on purpose: the data comes from VfsToolCatalogSource
 * (today ToolRegistry; later connections, MCP tool definitions and API
 * manifests). The former integration-package indexes (`/tools/apps`,
 * `/tools/integrations`, `/tools/mcp`) were removed with the package catalog.
 */
trait BuildsVfsToolCatalogIndex
{
    /**
     * @return array<string, mixed>
     */
    private function toolCatalogIndex(User $agent): array
    {
        // A container binding (e.g. a connections-directory source) wins;
        // the default reads the registry this facade already holds.
        $source = app()->bound(VfsToolCatalogSource::class)
            ? app(VfsToolCatalogSource::class)
            : new RegistryVfsToolCatalogSource($this->tools);
        $surface = $source->effectiveTools($agent);

        return [
            'kind' => 'tool_surface',
            'direct_tools' => $surface['direct'],
            'code_mode' => [
                'how' => 'Call these inside code_exec as app.<group>.<name>; read signatures with code_read_doc("<group>").',
                'groups' => $surface['code_mode'],
            ],
        ];
    }

    private function toolCatalogMarkdown(User $agent): string
    {
        $index = $this->toolCatalogIndex($agent);
        $lines = ['# Your tools', '', '## Direct tools', ''];
        foreach ($index['direct_tools'] as $tool) {
            $lines[] = '- `'.$tool['slug'].'`'.($tool['requires_approval'] ? ' (approval required)' : '').': '.strtok($tool['description'], "\n");
        }
        $lines[] = '';
        $lines[] = '## Code Mode (code_exec)';
        $lines[] = '';
        foreach ($index['code_mode']['groups'] as $group) {
            $lines[] = '- `'.$group['group'].'`: '.count($group['tools']).' tools';
        }
        $lines[] = '';
        $lines[] = 'Full machine-readable list: /tools/index.json. VFS reference: /tools/vfs.md.';

        return implode("\n", $lines)."\n";
    }
}
