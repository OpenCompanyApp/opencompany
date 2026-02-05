<?php

namespace Database\Seeders;

use App\Models\IntegrationSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IntegrationSettingSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $apiKey = env('GLM_API_KEY', '');
        $codingUrl = env('GLM_URL', 'https://api.z.ai/api/coding/paas/v4');

        // GLM general (Zhipu AI open platform)
        IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'integration_id' => 'glm',
            'config' => [
                'api_key' => $apiKey,
                'url' => 'https://open.bigmodel.cn/api/paas/v4',
                'default_model' => 'glm-4-plus',
            ],
            'enabled' => !empty($apiKey),
        ]);

        // GLM Coding Plan (z.ai coding endpoint)
        IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'integration_id' => 'glm-coding',
            'config' => [
                'api_key' => $apiKey,
                'url' => $codingUrl,
                'default_model' => 'glm-4.7',
            ],
            'enabled' => !empty($apiKey),
        ]);
    }
}
