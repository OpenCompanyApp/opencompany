<?php

namespace App\Domain\Integrations\Application;

use App\Models\ApprovalRequest;
use App\Models\ChannelMember;
use App\Models\IntegrationSetting;
use App\Models\Message;
use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Services\Integrations\IntegrationAccountResolver;
use App\Services\Integrations\IntegrationIdentity;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Workspace integration settings use cases.
 *
 * Package integration schemas, runtime execution, and credential resolution
 * remain in the integration runtime layer. This service owns OpenCompany's
 * workspace-local policy: enablement rows, multi-account aliases/defaults,
 * masked secret preservation, and external identity links to local users.
 */
class ManageIntegrationSettings
{
    /**
     * Toggle an integration's workspace enablement row.
     */
    public function toggle(string $integrationId, bool $enabled): IntegrationSetting
    {
        $integrationId = IntegrationIdentity::rawId($integrationId);

        $setting = IntegrationSetting::forWorkspace()
            ->where('integration_id', $integrationId)
            ->where('account_alias', '')
            ->first();

        if ($setting) {
            $setting->update(['enabled' => $enabled]);

            return $setting->fresh();
        }

        return IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'integration_id' => $integrationId,
            'account_alias' => '',
            'config' => [],
            'enabled' => $enabled,
            'is_default' => true,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listAccounts(string $integrationId): Collection
    {
        $integrationId = IntegrationIdentity::rawId($integrationId);

        return IntegrationSetting::forWorkspace()
            ->where('integration_id', $integrationId)
            ->orderByDesc('is_default')
            ->orderBy('account_alias')
            ->get()
            ->map(fn (IntegrationSetting $setting) => [
                'alias' => $setting->account_alias,
                'is_default' => $setting->is_default,
                'enabled' => $setting->enabled,
                'configured' => $setting->hasValidConfig(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function createAccount(string $integrationId, string $alias, array $config = []): IntegrationSetting
    {
        $integrationId = IntegrationIdentity::rawId($integrationId);
        $alias = app(IntegrationAccountResolver::class)->normalizeAlias($alias);

        $hasOthers = IntegrationSetting::forWorkspace()
            ->where('integration_id', $integrationId)
            ->exists();

        return IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'integration_id' => $integrationId,
            'account_alias' => $alias,
            'config' => $config,
            'enabled' => true,
            'is_default' => ! $hasOthers,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function updateAccountConfig(string $integrationId, string $alias, array $config): bool
    {
        $integrationId = IntegrationIdentity::rawId($integrationId);
        $alias = app(IntegrationAccountResolver::class)->normalizeAlias($alias);
        $setting = $this->findAccount($integrationId, $alias);

        if (! $setting) {
            return false;
        }

        $merged = $setting->config ?? [];
        foreach ($config as $key => $value) {
            if (is_string($value) && str_contains($value, '*')) {
                continue;
            }

            $merged[$key] = $value;
        }

        $setting->config = $merged;
        $setting->save();

        return true;
    }

    public function deleteAccount(string $integrationId, string $alias): ?bool
    {
        $integrationId = IntegrationIdentity::rawId($integrationId);
        if ($alias === '') {
            return null;
        }
        $alias = app(IntegrationAccountResolver::class)->normalizeAlias($alias);

        $setting = $this->findAccount($integrationId, $alias);
        if (! $setting) {
            return false;
        }

        $wasDefault = $setting->is_default;
        $setting->delete();

        if ($wasDefault) {
            $replacement = IntegrationSetting::forWorkspace()
                ->where('integration_id', $integrationId)
                ->where('account_alias', '')
                ->first()
                ?: IntegrationSetting::forWorkspace()
                    ->where('integration_id', $integrationId)
                    ->orderBy('account_alias')
                    ->first();

            $replacement?->update(['is_default' => true]);
        }

        return true;
    }

    public function setDefaultAccount(string $integrationId, string $alias): bool
    {
        $integrationId = IntegrationIdentity::rawId($integrationId);
        $alias = app(IntegrationAccountResolver::class)->normalizeAlias($alias);
        $setting = $this->findAccount($integrationId, $alias);

        if (! $setting) {
            return false;
        }

        IntegrationSetting::forWorkspace()
            ->where('integration_id', $integrationId)
            ->update(['is_default' => false]);

        $setting->update(['is_default' => true]);

        return true;
    }

    /**
     * Link a provider identity to a local user, merging Telegram shadow history.
     *
     * @param  array{userId: string, provider: string, externalId: string, displayName?: ?string}  $data
     * @return array{identity?: UserExternalIdentity, user?: User, conflict?: string}
     */
    public function linkExternalIdentity(array $data): array
    {
        $user = User::findOrFail($data['userId']);
        $existing = UserExternalIdentity::where('provider', $data['provider'])
            ->where('external_id', $data['externalId'])
            ->first();

        if ($existing && $existing->user_id !== $user->id) {
            return ['conflict' => "This {$data['provider']} ID is already linked to user: {$existing->user?->name}"];
        }

        $identity = UserExternalIdentity::updateOrCreate(
            [
                'provider' => $data['provider'],
                'external_id' => $data['externalId'],
            ],
            [
                'id' => $existing->id ?? Str::uuid()->toString(),
                'user_id' => $user->id,
                'display_name' => $data['displayName'] ?? null,
            ]
        );

        if ($data['provider'] === 'telegram') {
            $this->mergeTelegramShadowUser($user, $data['externalId']);
        }

        return ['identity' => $identity, 'user' => $user->fresh()];
    }

    public function unlinkExternalIdentity(string $identityId): void
    {
        UserExternalIdentity::findOrFail($identityId)->delete();
    }

    /**
     * @return Collection<int, UserExternalIdentity>
     */
    public function externalIdentities(?string $provider = null): Collection
    {
        return UserExternalIdentity::with('user')
            ->when($provider, fn ($query) => $query->where('provider', $provider))
            ->get();
    }

    private function findAccount(string $integrationId, string $alias): ?IntegrationSetting
    {
        return IntegrationSetting::forWorkspace()
            ->where('integration_id', $integrationId)
            ->where('account_alias', $alias)
            ->first();
    }

    private function mergeTelegramShadowUser(User $user, string $externalId): void
    {
        $shadowEmail = "telegram-{$externalId}@external.opencompany";
        $shadow = User::where('email', $shadowEmail)
            ->where('id', '!=', $user->id)
            ->first();

        if (! $shadow) {
            return;
        }

        $existingChannelIds = ChannelMember::where('user_id', $user->id)->pluck('channel_id');
        ChannelMember::where('user_id', $shadow->id)
            ->whereIn('channel_id', $existingChannelIds)
            ->delete();

        ChannelMember::where('user_id', $shadow->id)->update(['user_id' => $user->id]);
        Message::where('author_id', $shadow->id)->update(['author_id' => $user->id]);
        ApprovalRequest::where('responded_by_id', $shadow->id)->update(['responded_by_id' => $user->id]);

        $shadow->delete();
    }
}
