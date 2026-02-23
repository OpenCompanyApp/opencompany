<?php

namespace App\Agents\Tools\Workspace;

use App\Models\IntegrationSetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListAvailableModels implements Tool
{
    public function description(): string
    {
        return 'List all available AI models from enabled integrations in the workspace.';
    }

    public function handle(Request $request): string
    {
        try {
            $settings = IntegrationSetting::forWorkspace()->where('enabled', true)->get();
            $available = IntegrationSetting::getAvailableIntegrations();

            $models = [];

            foreach ($settings as $setting) {
                if (!isset($available[$setting->integration_id])) {
                    continue;
                }

                $info = $available[$setting->integration_id];
                if (empty($info['models'])) {
                    continue;
                }

                foreach ($info['models'] as $modelId => $modelName) {
                    $models[] = [
                        'brainId' => $setting->integration_id . ':' . $modelId,
                        'modelId' => $modelId,
                        'modelName' => $modelName,
                        'integration' => $info['name'],
                    ];
                }
            }

            return json_encode($models, JSON_PRETTY_PRINT);
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