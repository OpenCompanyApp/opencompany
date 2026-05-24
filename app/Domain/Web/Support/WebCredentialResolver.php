<?php

namespace App\Domain\Web\Support;

use App\Models\IntegrationSetting;

/**
 * Resolves web-provider credentials from workspace settings, then env fallback.
 *
 * Provider adapters use this service instead of reading IntegrationSetting
 * directly so workspace scoping, account aliases, and local-development env
 * fallback stay centralized.
 */
class WebCredentialResolver
{
    public function apiKey(string $provider): string
    {
        $provider = $this->normalize($provider);
        $catalog = $this->providerConfig($provider);
        $integrationId = $catalog['integration_id'] ?? "web.{$provider}";

        foreach (array_filter([$integrationId, $provider]) as $id) {
            $setting = $this->setting((string) $id);
            $key = $setting?->getConfigValue('api_key')
                ?? $setting?->getConfigValue('apiKey')
                ?? $setting?->getConfigValue('token')
                ?? $setting?->getConfigValue('bearer_token');

            if (is_string($key) && trim($key) !== '') {
                return trim($key);
            }
        }

        $env = $catalog['api_key_env'] ?? null;
        $value = is_string($env) ? env($env) : null;

        return is_string($value) ? trim($value) : '';
    }

    public function baseUrl(string $provider, ?string $key = 'base_url'): ?string
    {
        $provider = $this->normalize($provider);
        $catalog = $this->providerConfig($provider);
        $integrationId = $catalog['integration_id'] ?? "web.{$provider}";

        foreach (array_filter([$integrationId, $provider]) as $id) {
            $setting = $this->setting((string) $id);
            $value = $setting?->getConfigValue((string) $key)
                ?? ($key === 'base_url' ? $setting?->getConfigValue('url') : null);

            if (is_string($value) && trim($value) !== '') {
                return rtrim(trim($value), '/');
            }
        }

        $value = $catalog[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? rtrim(trim($value), '/') : null;
    }

    public function isConfigured(string $provider): bool
    {
        $provider = $this->normalize($provider);

        if ($provider === 'direct') {
            return true;
        }

        if ($provider === 'jina') {
            return true;
        }

        if ($provider === 'searxng') {
            return $this->baseUrl($provider) !== null;
        }

        return $this->apiKey($provider) !== '';
    }

    public function providerConfig(string $provider): array
    {
        return config('web.providers.'.$this->normalize($provider), []);
    }

    private function setting(string $integrationId): ?IntegrationSetting
    {
        $query = app()->bound('currentWorkspace')
            ? IntegrationSetting::forWorkspace()
            : IntegrationSetting::query();

        return $query
            ->where('integration_id', $integrationId)
            ->default()
            ->first();
    }

    private function normalize(string $provider): string
    {
        return str_replace('-', '_', strtolower(trim($provider)));
    }
}
