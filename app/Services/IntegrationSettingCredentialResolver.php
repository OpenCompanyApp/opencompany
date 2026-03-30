<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use OpenCompany\IntegrationCore\Contracts\CredentialResolver;

class IntegrationSettingCredentialResolver implements CredentialResolver
{
    public function get(string $integration, string $key, mixed $default = null, ?string $account = null): mixed
    {
        // OpenCompany uses workspace-scoped settings; account parameter is ignored
        // (each workspace has one set of credentials per integration).
        $setting = app()->bound('currentWorkspace')
            ? IntegrationSetting::forWorkspace()->where('integration_id', $integration)->first()
            : IntegrationSetting::where('integration_id', $integration)->first();

        return $setting?->getConfigValue($key, $default) ?? $default;
    }

    public function isConfigured(string $integration, ?string $account = null): bool
    {
        return ! empty($this->get($integration, 'api_key'));
    }
}
