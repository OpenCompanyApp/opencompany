<?php

namespace App\Agents\Tools\Workspace;

use App\Models\IntegrationSetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class ListIntegrations implements Tool
{
    public function description(): string
    {
        return 'List all available integrations in the workspace with their enabled/configured status.';
    }

    public function handle(Request $request): string
    {
        try {
            $settings = IntegrationSetting::forWorkspace()->get()->keyBy('integration_id');
            $available = IntegrationSetting::getAvailableIntegrations();

            $integrations = [];

            // Static integrations
            foreach ($available as $id => $info) {
                /** @var \App\Models\IntegrationSetting|null $setting */
                $setting = $settings->get($id);
                $integrations[] = [
                    'id' => $id,
                    'name' => $info['name'],
                    'enabled' => (bool) ($setting && $setting->enabled),
                    'configured' => (bool) ($setting && $setting->hasValidConfig()),
                ];
            }

            // Dynamic integrations from packages
            foreach (app(ToolProviderRegistry::class)->all() as $provider) {
                if (!$provider instanceof ConfigurableIntegration) {
                    continue;
                }
                $id = $provider->appName();
                if (isset($available[$id])) {
                    continue;
                }
                $meta = $provider->integrationMeta();
                /** @var \App\Models\IntegrationSetting|null $setting */
                $setting = $settings->get($id);
                $integrations[] = [
                    'id' => $id,
                    'name' => $meta['name'],
                    'enabled' => (bool) ($setting && $setting->enabled),
                    'configured' => (bool) ($setting && !empty($setting->config)),
                ];
            }

            return json_encode($integrations, JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
