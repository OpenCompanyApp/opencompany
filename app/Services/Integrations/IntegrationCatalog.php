<?php

namespace App\Services\Integrations;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenCompany\IntegrationCatalog\CatalogLocator;

class IntegrationCatalog
{
    /** @var array<string, mixed>|null */
    private ?array $catalog = null;

    private ?string $resolvedPath = null;
    private ?string $resolvedPathSource = null;

    public function available(): bool
    {
        return is_file($this->path());
    }

    public function pathSource(): ?string
    {
        $this->path();

        return $this->resolvedPathSource;
    }

    public function resolvedPath(): string
    {
        return $this->path();
    }

    public function generatedAt(): ?string
    {
        return $this->data()['generated_at'] ?? null;
    }

    public function totalIntegrations(): int
    {
        return (int) ($this->data()['total_integrations'] ?? $this->all()->count());
    }

    public function totalTools(): int
    {
        return (int) ($this->data()['total_tools'] ?? $this->all()->sum('tool_count'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(): Collection
    {
        return collect($this->data()['integrations'] ?? [])
            ->filter(fn ($integration) => is_array($integration) && isset($integration['slug']))
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        $normalized = Str::slug($slug);

        return $this->all()
            ->first(fn (array $integration) => ($integration['slug'] ?? null) === $normalized);
    }

    /**
     * @return list<string>
     */
    public function categories(): array
    {
        return $this->all()
            ->pluck('category')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $integration
     * @param  array<string, mixed>  $runtime
     * @return array<string, mixed>
     */
    public function summarize(array $integration, array $runtime = []): array
    {
        $slug = (string) ($integration['slug'] ?? '');
        $category = (string) ($integration['category'] ?? 'data');

        return [
            'id' => $slug,
            'slug' => $slug,
            'name' => (string) ($integration['name'] ?? Str::headline($slug)),
            'description' => (string) ($integration['description'] ?? ''),
            'category' => $category,
            'icon' => $runtime['icon'] ?? $integration['icon'] ?? $this->iconForCategory($category),
            'package' => $integration['package'] ?? null,
            'routeSlug' => $integration['route_slug'] ?? null,
            'toolCount' => (int) ($integration['tool_count'] ?? count($integration['tools'] ?? [])),
            'authType' => $integration['auth_type'] ?? null,
            'authSummary' => $integration['auth_summary'] ?? null,
            'docsUrl' => $integration['docs_url'] ?? $integration['documentation_url'] ?? null,
            'catalog' => true,
            'packageInstalled' => (bool) ($runtime['packageInstalled'] ?? false),
            'enabled' => (bool) ($runtime['enabled'] ?? false),
            'configured' => (bool) ($runtime['configured'] ?? false),
            'configurable' => (bool) ($runtime['configurable'] ?? false),
            'runnable' => (bool) ($runtime['runnable'] ?? $runtime['packageInstalled'] ?? false),
            'badge' => $runtime['badge'] ?? $integration['badge'] ?? (($runtime['packageInstalled'] ?? false) ? 'verified' : null),
        ];
    }

    public function iconForCategory(?string $category): string
    {
        return match ($category) {
            'analytics' => 'ph:chart-line-up',
            'communication', 'chat' => 'ph:chat-dots',
            'commerce', 'payments' => 'ph:credit-card',
            'crm', 'sales' => 'ph:users-three',
            'developer', 'devtools' => 'ph:code',
            'files', 'storage' => 'ph:folder',
            'marketing' => 'ph:megaphone',
            'productivity' => 'ph:briefcase',
            'security' => 'ph:shield-check',
            default => 'ph:puzzle-piece',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        if (! $this->available()) {
            return $this->catalog = [
                'generated_at' => null,
                'total_integrations' => 0,
                'total_tools' => 0,
                'integrations' => [],
            ];
        }

        $decoded = json_decode((string) file_get_contents($this->path()), true);

        return $this->catalog = is_array($decoded) ? $decoded : [
            'generated_at' => null,
            'total_integrations' => 0,
            'total_tools' => 0,
            'integrations' => [],
        ];
    }

    private function path(): string
    {
        if ($this->resolvedPath !== null) {
            return $this->resolvedPath;
        }

        $configured = (string) config('integration_catalog.path', '');
        if ($configured !== '') {
            $this->resolvedPath = $configured;
            $this->resolvedPathSource = is_file($configured) ? 'config' : null;

            return $configured;
        }

        foreach ($this->pathCandidates() as $source => $path) {
            if ($path !== '' && is_file($path)) {
                $this->resolvedPath = $path;
                $this->resolvedPathSource = $source;

                return $path;
            }
        }

        $this->resolvedPath = CatalogLocator::path();
        $this->resolvedPathSource = null;

        return $this->resolvedPath;
    }

    /**
     * @return array<string, string>
     */
    private function pathCandidates(): array
    {
        return [
            'package' => CatalogLocator::path(),
        ];
    }
}
