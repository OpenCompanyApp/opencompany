<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use OpenCompany\IntegrationCore\Contracts\CredentialResolver;

/**
 * CredentialResolver backed by workspace IntegrationSetting rows.
 *
 * Integration packages call this interface without knowing OpenCompany's
 * encrypted settings schema or multi-account conventions.
 */
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
        if (! app()->bound('currentWorkspace')) {
            return IntegrationSetting::query()->whereNull('workspace_id')
                ->where('integration_id', $integration)
                ->where('account_alias', '!=', '')
                ->pluck('account_alias')->values()->all();
        }

        return IntegrationSetting::getAccountsFor($integration);
    }

    private function findSetting(string $integration, ?string $account): ?IntegrationSetting
    {
        // Invariant: runtime calls inside a workspace stay tenant-scoped. With
        // no workspace bound (CLI, misconfigured queue path) only global rows
        // (workspace_id NULL) are visible — never another tenant's credentials.
        $query = app()->bound('currentWorkspace')
            ? IntegrationSetting::forWorkspace()
            : IntegrationSetting::query()->whereNull('workspace_id');

        return $query
            ->where('integration_id', $integration)
            ->forAccount($account)
            ->first();
    }
}
