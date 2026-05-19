<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenCompany\IntegrationCore\Contracts\HasIntegrationCapabilities;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

/**
 * Resolves integration account aliases and workspace-scoped settings.
 *
 * OpenCompany supports multiple accounts for the same integration. Keep alias
 * parsing and default-setting creation here so controllers and runtime services
 * do not accidentally mix default and named credentials.
 */
class IntegrationAccountResolver
{
    public function __construct(
        private ToolProviderRegistry $registry,
    ) {}

    public function accountFromRequest(Request $request): ?string
    {
        $account = $request->input('account', $request->query('account'));
        if ($account === null) {
            $account = $request->input('accountAlias', $request->query('accountAlias'));
        }

        if ($account === null) {
            return null;
        }

        return trim((string) $account);
    }

    public function findSetting(string $id, ?string $account = null): ?IntegrationSetting
    {
        return IntegrationSetting::forWorkspace()
            ->where('integration_id', $id)
            ->forAccount($account)
            ->first();
    }

    public function findOrNewSetting(string $id, ?string $account = null): IntegrationSetting
    {
        $setting = $this->findSetting($id, $account);
        if ($setting) {
            return $setting;
        }

        $hasOthers = IntegrationSetting::forWorkspace()
            ->where('integration_id', $id)
            ->exists();

        // The first account for an integration becomes the default account.
        // Later named accounts are opt-in and must not steal default runtime
        // behavior from existing agents.
        $setting = new IntegrationSetting;
        $setting->id = Str::uuid()->toString();
        $setting->workspace_id = workspace()->id;
        $setting->integration_id = $id;
        $setting->account_alias = $account ?? '';
        $setting->is_default = ! $hasOthers;
        $setting->enabled = true;

        return $setting;
    }

    /**
     * @return list<string>
     */
    public function sharedCredentialSiblings(string $id): array
    {
        return $this->sharedCredentialGroup($id)['integration_ids'];
    }

    /**
     * @return array{integration_ids: list<string>, keys: list<string>}
     */
    public function sharedCredentialGroup(string $id): array
    {
        $group = $this->sharedCredentialMetadata($id);
        if ($group === null) {
            return ['integration_ids' => [], 'keys' => []];
        }

        $ids = [];
        foreach ($this->registry->all() as $provider) {
            if (! $provider instanceof HasIntegrationCapabilities) {
                continue;
            }

            $candidateGroup = $this->sharedCredentialMetadata($provider->appName());
            if (($candidateGroup['group'] ?? null) === $group['group']) {
                $ids[] = $provider->appName();
            }
        }

        return [
            'integration_ids' => array_values(array_unique($ids)),
            'keys' => $group['keys'],
        ];
    }

    /**
     * @return array{group: string, keys: list<string>}|null
     */
    private function sharedCredentialMetadata(string $id): ?array
    {
        $provider = $this->registry->get($id);
        if (! $provider instanceof HasIntegrationCapabilities) {
            return null;
        }

        $metadata = $provider->integrationCapabilities()['shared_credentials'] ?? null;
        if (! is_array($metadata) || ! is_string($metadata['group'] ?? null)) {
            return null;
        }

        $keys = array_values(array_filter(
            $metadata['keys'] ?? [],
            static fn ($key): bool => is_string($key) && $key !== '',
        ));

        if ($keys === []) {
            return null;
        }

        return [
            'group' => $metadata['group'],
            'keys' => $keys,
        ];
    }
}
