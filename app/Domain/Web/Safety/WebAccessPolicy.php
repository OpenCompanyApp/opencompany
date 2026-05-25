<?php

namespace App\Domain\Web\Safety;

use App\Domain\Web\Exceptions\WebFetchPermanentException;
use App\Models\AppSetting;

/**
 * Enforces workspace-level web access policy independently from provider APIs.
 *
 * Provider-side filters are useful for cost and relevance, but OpenCompany must
 * still enforce allow/block domains after search results return and before any
 * fetch URL is requested. Tool-call domain filters may narrow the workspace
 * policy; they must never widen it.
 */
class WebAccessPolicy
{
    /**
     * @param  list<string>  $requestAllowedDomains
     * @param  list<string>  $requestBlockedDomains
     */
    public function assertUrlAllowed(string $url, array $requestAllowedDomains = [], array $requestBlockedDomains = []): void
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            throw new WebFetchPermanentException('URL host is required.');
        }

        if (! $this->allowsHost($host, $requestAllowedDomains, $requestBlockedDomains)) {
            throw new WebFetchPermanentException("URL host [{$host}] is blocked by workspace web access policy.");
        }
    }

    /**
     * @param  list<string>  $requestAllowedDomains
     * @param  list<string>  $requestBlockedDomains
     */
    public function allowsUrl(string $url, array $requestAllowedDomains = [], array $requestBlockedDomains = []): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host !== '' && $this->allowsHost($host, $requestAllowedDomains, $requestBlockedDomains);
    }

    /**
     * @param  list<string>  $requestAllowedDomains
     * @param  list<string>  $requestBlockedDomains
     */
    public function allowsHost(string $host, array $requestAllowedDomains = [], array $requestBlockedDomains = []): bool
    {
        $host = strtolower(trim($host));
        $workspaceAllowed = $this->allowedDomains();
        $workspaceBlocked = $this->blockedDomains();

        if ($workspaceAllowed !== [] && ! $this->hostMatchesAny($host, $workspaceAllowed)) {
            return false;
        }

        if ($requestAllowedDomains !== [] && ! $this->hostMatchesAny($host, $this->normalizeDomains($requestAllowedDomains))) {
            return false;
        }

        return ! $this->hostMatchesAny($host, array_values(array_unique(array_merge(
            $workspaceBlocked,
            $this->normalizeDomains($requestBlockedDomains),
        ))));
    }

    /**
     * @return list<string>
     */
    public function allowedDomains(): array
    {
        return $this->configuredDomains('web_allowed_domains', config('web.policy.allowed_domains', []));
    }

    /**
     * @return list<string>
     */
    public function blockedDomains(): array
    {
        return $this->configuredDomains('web_blocked_domains', config('web.policy.blocked_domains', []));
    }

    /**
     * @param  list<string>  $domains
     */
    private function hostMatchesAny(string $host, array $domains): bool
    {
        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function configuredDomains(string $key, mixed $default): array
    {
        $value = app()->bound('currentWorkspace') ? AppSetting::getValue($key, $default) : $default;

        return is_array($value) ? $this->normalizeDomains($value) : [];
    }

    /**
     * @param  array<int|string, mixed>  $domains
     * @return list<string>
     */
    private function normalizeDomains(array $domains): array
    {
        $normalized = [];
        foreach ($domains as $domain) {
            if (! is_string($domain)) {
                continue;
            }

            $domain = strtolower(trim($domain));
            $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
            $domain = trim(explode('/', $domain)[0]);
            if ($domain !== '') {
                $normalized[] = $domain;
            }
        }

        return array_values(array_unique($normalized));
    }
}
