<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use OpenCompany\IntegrationCore\Contracts\CredentialResolver;

class IntegrationSettingCredentialResolver implements CredentialResolver
{
    public function get(string $integration, string $key, mixed $default = null): mixed
    {
        $setting = IntegrationSetting::where('integration_id', $integration)->first();

        return $setting?->getConfigValue($key, $default) ?? $default;
    }

    public function isConfigured(string $integration): bool
    {
        return ! empty($this->get($integration, 'api_key'));
    }
}
