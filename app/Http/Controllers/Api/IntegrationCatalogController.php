<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Services\Integrations\IntegrationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class IntegrationCatalogController extends Controller
{
    public function index(Request $request, IntegrationCatalog $catalog): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $category = trim((string) $request->query('category', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('perPage', 50)));
        $runtime = $this->runtimeState();

        $items = $catalog->all()
            ->when($category !== '', fn ($collection) => $collection->filter(
                fn (array $item) => ($item['category'] ?? null) === $category
            ))
            ->when($search !== '', fn ($collection) => $collection->filter(function (array $item) use ($search) {
                $haystack = strtolower(implode(' ', [
                    $item['slug'] ?? '',
                    $item['name'] ?? '',
                    $item['description'] ?? '',
                    $item['category'] ?? '',
                    $item['package'] ?? '',
                ]));

                return str_contains($haystack, strtolower($search));
            }))
            ->values();

        $total = $items->count();
        $data = $items
            ->forPage($page, $perPage)
            ->map(fn (array $item) => $catalog->summarize($item, $runtime[$item['slug']] ?? []))
            ->values()
            ->all();

        return response()->json([
            'available' => $catalog->available(),
            'pathSource' => $catalog->pathSource(),
            'generatedAt' => $catalog->generatedAt(),
            'totalIntegrations' => $catalog->totalIntegrations(),
            'totalTools' => $catalog->totalTools(),
            'categories' => $catalog->categories(),
            'data' => $data,
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'hasMore' => $page * $perPage < $total,
            ],
        ]);
    }

    public function show(string $slug, IntegrationCatalog $catalog): JsonResponse
    {
        $item = $catalog->find($slug);

        if ($item === null) {
            return response()->json(['error' => 'Integration not found'], 404);
        }

        $runtime = $this->runtimeState();
        $summary = $catalog->summarize($item, $runtime[$item['slug']] ?? []);

        return response()->json(array_merge($item, $summary));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function runtimeState(): array
    {
        $settings = IntegrationSetting::forWorkspace()->default()->get()->keyBy('integration_id');
        $state = [];

        foreach (app(ToolProviderRegistry::class)->all() as $provider) {
            $id = $provider->appName();
            $setting = $settings->get($id);
            $meta = $provider instanceof ConfigurableIntegration
                ? $provider->integrationMeta()
                : $provider->appMeta();

            $state[$id] = [
                'packageInstalled' => $provider->isIntegration(),
                'runnable' => $provider->isIntegration(),
                'enabled' => (bool) ($setting?->enabled ?? false),
                'configured' => $setting?->hasValidConfig() ?? ! ($provider instanceof ConfigurableIntegration),
                'configurable' => $provider instanceof ConfigurableIntegration,
                'icon' => $meta['icon'] ?? 'ph:puzzle-piece',
                'badge' => $meta['badge'] ?? null,
            ];

            $legacyId = str_replace('-', '_', $id);
            if ($legacyId !== $id && ! isset($state[$legacyId])) {
                $state[$legacyId] = $state[$id];
            }
        }

        return $state;
    }
}
