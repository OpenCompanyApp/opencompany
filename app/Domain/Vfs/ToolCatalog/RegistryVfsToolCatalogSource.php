<?php

namespace App\Domain\Vfs\ToolCatalog;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;

/**
 * VfsToolCatalogSource backed by the in-process ToolRegistry.
 *
 * - `direct`: exactly the model-visible tools ToolRegistry hands this agent
 *   (getToolSlugsForAgent), with approval state; tools that borrow another
 *   slug's permission (vfs_read, vfs_edit) report that slug's state.
 * - `code_mode`: every other enabled tool, grouped by app, slugs only
 *   (signatures come from code_read_doc, which renders live schemas).
 *
 * Uses permission metadata only; it never instantiates tools, so reading
 * `/tools` stays cheap even with many registered tools.
 */
class RegistryVfsToolCatalogSource implements VfsToolCatalogSource
{
    public function __construct(private readonly ToolRegistry $tools) {}

    public function effectiveTools(User $agent): array
    {
        $states = [];
        $codeMode = [];
        foreach ($this->tools->getAllToolsMeta($agent) as $tool) {
            if (! ($tool['enabled'] ?? false)) {
                continue;
            }
            $states[(string) $tool['id']] = (bool) ($tool['requiresApproval'] ?? false);
            $codeMode[(string) ($tool['app'] ?? 'other')][] = (string) $tool['id'];
        }

        $direct = [];
        $directSlugs = $this->tools->getToolSlugsForAgent($agent);
        foreach ($directSlugs as $slug) {
            $meta = $this->tools->getToolDefinitionBySlug($slug) ?? [];
            $governing = is_string($meta['permissionAs'] ?? null) ? $meta['permissionAs'] : $slug;
            $direct[] = [
                'slug' => $slug,
                'name' => (string) ($meta['name'] ?? $slug),
                'description' => (string) ($meta['description'] ?? ''),
                'type' => (string) ($meta['type'] ?? 'action'),
                'requires_approval' => $states[$governing] ?? false,
            ];
        }

        $groups = [];
        foreach ($codeMode as $group => $slugs) {
            $slugs = array_values(array_diff($slugs, $directSlugs));
            if ($slugs !== []) {
                $groups[] = ['group' => $group, 'tools' => $slugs];
            }
        }

        return ['direct' => $direct, 'code_mode' => $groups];
    }
}
