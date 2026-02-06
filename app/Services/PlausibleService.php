<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlausibleService
{
    private ?string $apiKey;
    private string $baseUrl;
    private ?IntegrationSetting $setting;

    public function __construct()
    {
        $this->setting = IntegrationSetting::where('integration_id', 'plausible')->first();
        $this->apiKey = $this->setting?->getConfigValue('api_key');
        $this->baseUrl = rtrim($this->setting?->getConfigValue('url') ?? 'https://plausible.io', '/');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get the list of site domains configured in the integration settings.
     */
    public function getConfiguredSites(): array
    {
        return $this->setting?->getConfigValue('sites', []) ?? [];
    }

    /**
     * Query stats via the v2 API.
     */
    public function query(array $body): array
    {
        return $this->request('POST', '/api/v2/query', $body);
    }

    /**
     * Get realtime visitor count for a site.
     */
    public function realtimeVisitors(string $siteId): int
    {
        $response = $this->rawRequest('GET', '/api/v1/stats/realtime/visitors', [
            'site_id' => $siteId,
        ]);

        $body = trim($response->body());
        if (!is_numeric($body)) {
            throw new \RuntimeException('Unexpected response from realtime visitors endpoint.');
        }

        return (int) $body;
    }

    /**
     * List all sites.
     */
    public function listSites(int $limit = 100, ?string $after = null): array
    {
        $params = ['limit' => $limit];
        if ($after) {
            $params['after'] = $after;
        }

        return $this->request('GET', '/api/v1/sites', $params);
    }

    /**
     * Create a new site.
     */
    public function createSite(string $domain, string $timezone = 'Etc/UTC'): array
    {
        return $this->request('POST', '/api/v1/sites', [
            'domain' => $domain,
            'timezone' => $timezone,
        ]);
    }

    /**
     * Delete a site.
     */
    public function deleteSite(string $siteId): void
    {
        $this->request('DELETE', '/api/v1/sites/' . urlencode($siteId));
    }

    /**
     * List goals for a site.
     */
    public function listGoals(string $siteId): array
    {
        return $this->request('GET', '/api/v1/sites/' . urlencode($siteId) . '/goals');
    }

    /**
     * Create a goal for a site.
     */
    public function createGoal(string $siteId, array $goal): array
    {
        return $this->request('POST', '/api/v1/sites/' . urlencode($siteId) . '/goals', [
            'goal' => $goal,
        ]);
    }

    /**
     * Delete a goal.
     */
    public function deleteGoal(string $siteId, int $goalId): void
    {
        $this->request('DELETE', '/api/v1/sites/' . urlencode($siteId) . '/goals/' . $goalId);
    }

    /**
     * Make an API request and return parsed JSON.
     */
    private function request(string $method, string $path, array $data = []): array
    {
        $response = $this->rawRequest($method, $path, $data);
        return $response->json() ?? [];
    }

    /**
     * Make a raw HTTP request to the Plausible API.
     */
    private function rawRequest(string $method, string $path, array $data = []): \Illuminate\Http\Client\Response
    {
        if (!$this->apiKey) {
            throw new \RuntimeException('Plausible API key is not configured.');
        }

        $url = $this->baseUrl . $path;

        try {
            $http = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30);

            $response = match (strtoupper($method)) {
                'GET' => $http->get($url, $data),
                'POST' => $http->post($url, $data),
                'PUT' => $http->put($url, $data),
                'DELETE' => $http->delete($url, $data),
                default => throw new \RuntimeException("Unsupported HTTP method: {$method}"),
            };

            if (!$response->successful()) {
                $contentType = $response->header('Content-Type') ?? '';
                $body = $response->body();

                // Detect HTML responses (wrong URL or enterprise-only endpoint)
                if (str_contains($contentType, 'text/html') || str_starts_with(trim($body), '<!DOCTYPE')) {
                    Log::warning("Plausible API returned HTML for {$method} {$path}", [
                        'status' => $response->status(),
                    ]);
                    throw new \RuntimeException("Plausible API endpoint not available (HTTP {$response->status()}). The {$path} endpoint may require an enterprise plan or the URL may be incorrect.");
                }

                $error = $response->json('error') ?? $body;
                Log::error("Plausible API error: {$method} {$path}", [
                    'status' => $response->status(),
                    'error' => $error,
                ]);
                throw new \RuntimeException("Plausible API error ({$response->status()}): " . (is_string($error) ? $error : json_encode($error)));
            }

            return $response;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("Plausible API connection error: {$method} {$path}", [
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException("Failed to connect to Plausible API: {$e->getMessage()}");
        }
    }
}
