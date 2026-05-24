<?php

namespace App\Domain\Web\Providers;

use App\Domain\Web\Enums\WebCapability;
use App\Domain\Web\Exceptions\WebProviderException;
use App\Domain\Web\Support\WebCredentialResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class AbstractWebProvider
{
    public function __construct(protected WebCredentialResolver $credentials) {}

    public function label(): string
    {
        return (string) ($this->providerConfig()['label'] ?? $this->id());
    }

    public function isAvailable(): bool
    {
        return $this->credentials->isConfigured($this->id());
    }

    public function supports(WebCapability $capability): bool
    {
        return in_array($capability->value, $this->providerConfig()['capabilities'] ?? [], true);
    }

    protected function apiKey(): string
    {
        $key = $this->credentials->apiKey($this->id());
        if ($key === '') {
            throw new WebProviderException($this->label().' API key is not configured.');
        }

        return $key;
    }

    protected function baseUrl(?string $key = 'base_url', ?string $fallback = null): string
    {
        return rtrim($this->credentials->baseUrl($this->id(), $key) ?? $fallback ?? '', '/');
    }

    protected function providerConfig(): array
    {
        return $this->credentials->providerConfig($this->id());
    }

    protected function getJson(string $url, array $headers = [], int $timeout = 30): array
    {
        return $this->decodeJson(Http::withHeaders($headers)->timeout($timeout)->acceptJson()->get($url));
    }

    protected function postJson(string $url, array $payload, array $headers = [], int $timeout = 30): array
    {
        return $this->decodeJson(Http::withHeaders($headers)->timeout($timeout)->acceptJson()->asJson()->post($url, $payload));
    }

    protected function getText(string $url, array $headers = [], int $timeout = 30): string
    {
        $response = Http::withHeaders($headers)->timeout($timeout)->get($url);
        if (! $response->successful()) {
            throw new WebProviderException($this->label()." request failed with HTTP {$response->status()}: ".substr($response->body(), 0, 1000));
        }

        return mb_convert_encoding($response->body(), 'UTF-8', 'UTF-8');
    }

    protected function decodeJson(Response $response): array
    {
        if (! $response->successful()) {
            throw new WebProviderException($this->label()." request failed with HTTP {$response->status()}: ".substr($response->body(), 0, 1000));
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new WebProviderException($this->label().' returned invalid JSON.');
        }

        return $json;
    }

    protected function string(array $data, string $key, string $fallback = ''): string
    {
        $value = $data[$key] ?? $fallback;

        return is_scalar($value) || $value === null ? (string) $value : $fallback;
    }

    protected function limit(string $value, int $limit): string
    {
        if ($limit <= 0 || mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit))."\n\n[truncated to {$limit} characters]";
    }

    protected function sourceUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
