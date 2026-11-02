<?php

namespace OpenCompany\Integrations\Google;

use App\Models\IntegrationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleClient
{
    public function __construct(
        private string $clientId = '',
        private string $clientSecret = '',
        private string $accessToken = '',
        private string $refreshToken = '',
        private ?int $expiresAt = null,
        private string $integrationId = '',
        private ?string $accountAlias = null,
    ) {}

    public function isConfigured(): bool
    {
        return $this->accessToken !== '';
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $url, array $query = []): array
    {
        return $this->request('GET', $url, query: $query);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function post(string $url, array $data = []): array
    {
        return $this->request('POST', $url, data: $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function put(string $url, array $data = []): array
    {
        return $this->request('PUT', $url, data: $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function patch(string $url, array $data = []): array
    {
        return $this->request('PATCH', $url, data: $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function delete(string $url, array $data = []): void
    {
        $this->request('DELETE', $url, data: $data);
    }

    /**
     * GET that returns the raw response body (for non-JSON endpoints like Drive export).
     *
     * @param  array<string, mixed>  $query
     */
    public function getRaw(string $url, array $query = []): string
    {
        $this->ensureValidToken();

        $http = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
        ])->timeout(30);

        if (! empty($query)) {
            $http = $http->withQueryParameters($query);
        }

        $response = $http->get($url);

        if (! $response->successful()) {
            $this->handleError('GET', $url, $response);
        }

        return $response->body();
    }

    /**
     * POST with a raw body string (for Gmail send).
     *
     * @return array<string, mixed>
     */
    public function postRaw(string $url, string $body, string $contentType = 'message/rfc822'): array
    {
        $this->ensureValidToken();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => $contentType,
        ])->timeout(30)->withBody($body, $contentType)->post($url);

        if (! $response->successful()) {
            $this->handleError('POST', $url, $response);
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $data = [], array $query = []): array
    {
        $this->ensureValidToken();

        $http = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
        ])->timeout(30)->acceptJson();

        if (! empty($query)) {
            $http = $http->withQueryParameters($query);
        }

        $response = match ($method) {
            'GET' => $http->get($url),
            'POST' => $http->post($url, $data),
            'PUT' => $http->put($url, $data),
            'PATCH' => $http->patch($url, $data),
            'DELETE' => $http->delete($url, $data),
            default => throw new \RuntimeException("Unsupported HTTP method: {$method}"),
        };

        if (! $response->successful()) {
            $this->handleError($method, $url, $response);
        }

        return $response->json() ?? [];
    }

    private function ensureValidToken(): void
    {
        if (! $this->accessToken) {
            throw new \RuntimeException('Google access token is not configured.');
        }

        // Token is still valid (more than 60 seconds remaining)
        if ($this->expiresAt && $this->expiresAt > (time() + 60)) {
            return;
        }

        // No refresh token — cannot auto-refresh
        if (! $this->refreshToken) {
            throw new \RuntimeException('Google access token expired and no refresh token available. Please reconnect.');
        }

        $this->refreshAccessToken();
    }

    private function refreshAccessToken(): void
    {
        $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            $error = $response->json('error_description') ?? $response->json('error') ?? $response->body();
            Log::error('Google token refresh failed', [
                'integration' => $this->integrationId,
                'error' => $error,
            ]);

            throw new \RuntimeException('Failed to refresh Google access token: ' . (is_string($error) ? $error : json_encode($error)));
        }

        $data = $response->json() ?? [];
        $this->accessToken = $data['access_token'] ?? '';
        $this->expiresAt = time() + (int) ($data['expires_in'] ?? 3600);

        // Google may return a new refresh token (rare)
        if (isset($data['refresh_token'])) {
            $this->refreshToken = $data['refresh_token'];
        }

        // Persist updated tokens to database.
        // Invariant: tokens are persisted only into the bound workspace's
        // setting. Without a workspace there is no safe target — an unscoped
        // query could write this account's token into another tenant's row —
        // so the refreshed token stays in memory for this call only.
        if (! app()->bound('currentWorkspace')) {
            Log::warning('Google token refreshed without a bound workspace; not persisted.', [
                'integration' => $this->integrationId,
            ]);

            return;
        }

        $setting = IntegrationSetting::forWorkspace()
            ->where('integration_id', $this->integrationId)
            ->forAccount($this->accountAlias)
            ->first();
        if ($setting) {
            /** @var array<string, mixed> $config */
            $config = $setting->config ?? [];
            $config['access_token'] = $this->accessToken;
            $config['expires_at'] = $this->expiresAt;
            if (isset($data['refresh_token'])) {
                $config['refresh_token'] = $this->refreshToken;
            }
            $setting->config = $config;
            $setting->save();
        }
    }

    /**
     * @param  \Illuminate\Http\Client\Response  $response
     */
    private function handleError(string $method, string $url, $response): void
    {
        $body = $response->json() ?? [];
        $errorMsg = $body['error']['message'] ?? $body['error_description'] ?? $response->body();
        $errorCode = $body['error']['code'] ?? $response->status();

        Log::error("Google API error: {$method} {$url}", [
            'integration' => $this->integrationId,
            'status' => $response->status(),
            'error' => $errorMsg,
        ]);

        throw new \RuntimeException("Google API error ({$errorCode}): " . (is_string($errorMsg) ? $errorMsg : json_encode($errorMsg)));
    }
}
