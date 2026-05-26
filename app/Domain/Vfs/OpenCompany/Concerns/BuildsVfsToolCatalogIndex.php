<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Models\User;

/**
 * Builds compact JSON indexes for the /tools discovery mount.
 */
trait BuildsVfsToolCatalogIndex
{
    /**
     * @return array{kind: string, groups: list<array<string, mixed>>, tool_count: int}
     */
    private function toolCatalogIndex(User $agent, bool $integrations): array
    {
        $groups = [];
        $toolCount = 0;

        foreach ($this->tools->getToolCatalog($agent) as $group) {
            if ((bool) ($group['isIntegration'] ?? false) !== $integrations) {
                continue;
            }

            $tools = array_map(fn (array $tool): array => [
                'slug' => $tool['slug'] ?? null,
                'name' => $tool['name'] ?? null,
                'description' => $tool['description'] ?? null,
                'parameters' => $tool['parameters'] ?? [],
            ], $group['tools'] ?? []);

            $toolCount += count($tools);
            $groups[] = [
                'name' => $group['name'] ?? 'unknown',
                'description' => $group['description'] ?? '',
                'icon' => $group['icon'] ?? null,
                'logo' => $group['logo'] ?? null,
                'tools' => $tools,
                'tool_count' => count($tools),
            ];
        }

        return [
            'kind' => $integrations ? 'integration_tools' : 'app_tools',
            'groups' => $groups,
            'tool_count' => $toolCount,
        ];
    }
}
