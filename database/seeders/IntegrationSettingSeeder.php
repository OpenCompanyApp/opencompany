<?php

namespace Database\Seeders;

use App\Models\IntegrationSetting;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IntegrationSettingSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $workspace = Workspace::where('slug', 'default')->first();
        $workspaceId = $workspace->id;

        $apiKey = env('ZAI_API_KEY', env('GLM_API_KEY', ''));
        $codingUrl = env('Z_URL', env('GLM_URL', 'https://api.z.ai/api/coding/paas/v4'));
        $apiUrl = env('Z_API_URL', 'https://open.bigmodel.cn/api/paas/v4');

        // Z.AI general API endpoint.
        IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'integration_id' => 'z-api',
            'config' => [
                'api_key' => $apiKey,
                'url' => $apiUrl,
                'default_model' => 'glm-5.1',
            ],
            'enabled' => !empty($apiKey),
            'workspace_id' => $workspaceId,
        ]);

        // Z.AI Coding Plan endpoint.
        IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'integration_id' => 'z',
            'config' => [
                'api_key' => $apiKey,
                'url' => $codingUrl,
                'default_model' => 'glm-5.1',
            ],
            'enabled' => !empty($apiKey),
            'workspace_id' => $workspaceId,
        ]);
    }
}
