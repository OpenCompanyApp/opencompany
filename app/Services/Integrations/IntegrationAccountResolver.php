<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Resolves integration account aliases and workspace-scoped settings.
 *
 * OpenCompany supports multiple accounts for the same integration. Keep alias
 * parsing and default-setting creation here so controllers and runtime services
 * do not accidentally mix default and named credentials.
 */
class IntegrationAccountResolver
{
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
        // Google integrations share one OAuth client in practice, but keep the
        // list app-local until package metadata can describe shared credential
        // groups without coupling OpenCompany to vendor-specific IDs.
        $google = [
            'google-calendar',
            'gmail',
            'google-drive',
            'google-contacts',
            'google-sheets',
            'google-search-console',
            'google-tasks',
            'google-analytics',
            'google-docs',
            'google-forms',
        ];

        return in_array($id, $google, true) ? $google : [];
    }
}
