<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        return $this->normalizeAlias((string) $account, allowDefault: true);
    }

    /**
     * Normalize and validate an integration account alias.
     *
     * The database has a 32-character account_alias column and all UI/runtime
     * selectors assume lowercase token-like aliases. Enforcing that contract
     * here keeps direct config updates, OAuth callbacks, and explicit account
     * management routes from drifting apart.
     */
    public function normalizeAlias(?string $alias, bool $allowDefault = false): ?string
    {
        $alias = trim((string) $alias);
        if ($alias === '') {
            if ($allowDefault) {
                return null;
            }

            throw ValidationException::withMessages([
                'alias' => 'The account alias field is required.',
            ]);
        }

        if (strlen($alias) > 32 || ! preg_match('/^[a-z0-9_]+$/', $alias)) {
            throw ValidationException::withMessages([
                'alias' => 'Account aliases may only contain lowercase letters, numbers, and underscores, and must be 32 characters or fewer.',
            ]);
        }

        return $alias;
    }

    public function findSetting(string $id, ?string $account = null): ?IntegrationSetting
    {
        $id = IntegrationIdentity::rawId($id);
        $account = $account === null ? null : $this->normalizeAlias($account, allowDefault: true);

        return IntegrationSetting::forWorkspace()
            ->where('integration_id', $id)
            ->forAccount($account)
            ->first();
    }

    public function findOrNewSetting(string $id, ?string $account = null): IntegrationSetting
    {
        $id = IntegrationIdentity::rawId($id);
        $account = $account === null ? null : $this->normalizeAlias($account, allowDefault: true);
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
        // Shared credential groups were a package-family feature (e.g. the
        // Google packages). App-owned settings never share credentials.
        unset($id);

        return ['integration_ids' => [], 'keys' => []];
    }

}
