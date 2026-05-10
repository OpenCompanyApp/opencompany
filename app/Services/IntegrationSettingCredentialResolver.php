<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use OpenCompany\IntegrationCore\Contracts\CredentialResolver;

class IntegrationSettingCredentialResolver implements CredentialResolver
{
    public function get(string $integration, string $key, mixed $default = null, ?string $account = null): mixed
    {
        $setting = $this->findSetting($integration, $account);

        return $setting?->getConfigValue($key, $default) ?? $default;
    }

    public function isConfigured(string $integration, ?string $account = null): bool
    {
        return (bool) $this->findSetting($integration, $account)?->hasValidConfig();
    }

    public function getAccounts(string $integration): array
    {
        return IntegrationSetting::getAccountsFor($integration);
    }

    private function findSetting(string $integration, ?string $account): ?IntegrationSetting
    {
        $query = app()->bound('currentWorkspace')
            ? IntegrationSetting::forWorkspace()
            : IntegrationSetting::query();

        return $query
            ->where('integration_id', $integration)
            ->forAccount($account)
            ->first();
    }
}
