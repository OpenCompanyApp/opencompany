<?php

namespace OpenCompany\Integrations\Google;

use Illuminate\Support\Facades\Http;
use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\Integrations\Google\Services\GoogleSearchConsoleService;
use OpenCompany\Integrations\Google\Tools\GoogleSearchConsoleAddSite;
use OpenCompany\Integrations\Google\Tools\GoogleSearchConsoleDeleteSite;
use OpenCompany\Integrations\Google\Tools\GoogleSearchConsoleDeleteSitemap;
use OpenCompany\Integrations\Google\Tools\GoogleSearchConsoleGetSitemap;
use OpenCompany\Integrations\Google\Tools\GoogleSearchConsoleInspectUrl;
use OpenCompany\Integrations\Google\Tools\GoogleSearchConsoleListSitemaps;
use OpenCompany\Integrations\Google\Tools\GoogleSearchConsoleListSites;
use OpenCompany\Integrations\Google\Tools\GoogleSearchConsolePerformance;
use OpenCompany\Integrations\Google\Tools\GoogleSearchConsoleSubmitSitemap;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

use OpenCompany\IntegrationCore\Contracts\HasIntegrationCapabilities;
class GoogleSearchConsoleToolProvider implements ToolProvider, ConfigurableIntegration, HasIntegrationCapabilities
{

/**
     * Describe host and authentication capabilities for catalog and setup flows.
     *
     * @return array<string, mixed>
     */
    public function integrationCapabilities(): array
    {
        return [
          'auth' => [
            'strategy' => 'oauth2_authorization_code',
            'legacy_auth_type' => 'oauth',
            'credential_mode' => 'stored_token',
            'setup_flows' =>
            [
              0 => 'web_redirect',
              1 => 'local_redirect',
              2 => 'device_code',
            ],
            'requires_browser_for_setup' => true,
            'refreshable' => true,
            'token_keys' =>
            [
              0 => 'access_token',
              1 => 'refresh_token',
              2 => 'expires_at',
            ],
            'notes' =>
            [
              0 => 'Web hosts use the registered OAuth redirect callback.',
              1 => 'CLI hosts can support Google OAuth with a desktop loopback redirect; device-code setup is possible where scopes allow it.',
              2 => 'CLI runtime works with stored access and refresh tokens.',
            ],
          ],
          'host_availability' => [
            'web' =>
            [
              'setup_supported' => true,
              'runtime_supported' => true,
              'setup_mode' => 'web_redirect',
            ],
            'cli' =>
            [
              'setup_supported' => true,
              'runtime_supported' => true,
              'setup_mode' => 'local_redirect_or_device_code',
              'runtime_mode' => 'normal',
            ],
          ],
          'runtime_requirements' => [
          ],
          'compatibility' => [
            'web_setup_supported' => true,
            'web_runtime_supported' => true,
            'cli_setup_supported' => true,
            'cli_runtime_supported' => true,
          ],
          'shared_credentials' => [
            'group' => 'google-workspace-oauth-client',
            'keys' => ['client_id', 'client_secret'],
          ],
        ];
    }

    public function appName(): string
    {
        return 'google-search-console';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'Google Search Console',
            'description' => 'Search performance and indexing',
            'icon' => 'ph:chart-line-up',
            'logo' => 'simple-icons:googlesearchconsole',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Search Console',
            'description' => 'Search performance, URL indexing, and sitemap management',
            'icon' => 'ph:chart-line-up',
            'logo' => 'simple-icons:googlesearchconsole',
            'category' => 'analytics',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/searchconsole.googleapis.com',
            'catalog_visibility' => 'hidden',
            'replaced_by' => 'google-search-console',
        ];
    }    public function configSchema(): array
    {
        return [
            [
                'key' => 'client_id',
                'type' => 'text',
                'label' => 'Client ID',
                'placeholder' => 'Your Google Cloud OAuth Client ID',
                'hint' => 'From <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> &rarr; Credentials &rarr; OAuth 2.0 Client IDs. Shared across all Google integrations &mdash; only needs to be entered once.',
                'required' => true,
            ],
            [
                'key' => 'client_secret',
                'type' => 'secret',
                'label' => 'Client Secret',
                'placeholder' => 'Your Google Cloud OAuth Client Secret',
                'required' => true,
            ],
            [
                'key' => 'access_token',
                'type' => 'oauth_connect',
                'label' => 'Google Account',
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_search_console',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Search Console" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://www.googleapis.com/webmasters/v3/sites');

            if ($response->successful()) {
                $sites = $response->json('siteEntry') ?? [];
                $count = count($sites);
                $emailInfo = $connectedEmail ? " ({$connectedEmail})" : '';

                return [
                    'success' => true,
                    'message' => "Search Console connected{$emailInfo}. {$count} verified " . ($count === 1 ? 'property' : 'properties') . '.',
                ];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Search Console API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public function validationRules(): array
    {
        return [
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'access_token' => 'nullable|string',
        ];
    }

    public function tools(): array
    {
        return [
            'google_search_console_add_site' => [
                'class' => GoogleSearchConsoleAddSite::class,
                'type' => 'write',
                'name' => 'Google Search Console Add Site',
                'description' => 'Add a new site property to Google Search Console.',
                'icon' => 'ph:wrench',
            ],
            'google_search_console_delete_site' => [
                'class' => GoogleSearchConsoleDeleteSite::class,
                'type' => 'write',
                'name' => 'Google Search Console Delete Site',
                'description' => 'Remove a site property from Google Search Console.',
                'icon' => 'ph:wrench',
            ],
            'google_search_console_delete_sitemap' => [
                'class' => GoogleSearchConsoleDeleteSitemap::class,
                'type' => 'write',
                'name' => 'Google Search Console Delete Sitemap',
                'description' => 'Remove a sitemap from Google Search Console.',
                'icon' => 'ph:wrench',
            ],
            'google_search_console_get_sitemap' => [
                'class' => GoogleSearchConsoleGetSitemap::class,
                'type' => 'read',
                'name' => 'Google Search Console Get Sitemap',
                'description' => 'Get details of a specific sitemap in Google Search Console. Returns the sitemap\'s path, last submitted/downloaded dates, whether it\'s a sitemap index, and content type breakdown with submitted vs indexed URL counts.',
                'icon' => 'ph:wrench',
            ],
            'google_search_console_inspect_url' => [
                'class' => GoogleSearchConsoleInspectUrl::class,
                'type' => 'read',
                'name' => 'Google Search Console Inspect URL',
                'description' => 'Check a URL\'s indexing status in Google Search Console. Returns: index verdict, coverage state, last crawl time, robots.txt state, indexing state, rich results, mobile usability, and AMP status.',
                'icon' => 'ph:wrench',
            ],
            'google_search_console_list_sitemaps' => [
                'class' => GoogleSearchConsoleListSitemaps::class,
                'type' => 'read',
                'name' => 'Google Search Console List Sitemaps',
                'description' => 'List all submitted sitemaps for a Google Search Console property. Returns each sitemap\'s path, last submitted/downloaded dates, whether it\'s a sitemap index, and content type counts (submitted vs indexed URLs).',
                'icon' => 'ph:wrench',
            ],
            'google_search_console_list_sites' => [
                'class' => GoogleSearchConsoleListSites::class,
                'type' => 'read',
                'name' => 'Google Search Console List Sites',
                'description' => 'List all verified Google Search Console sites/properties with their permission levels. Use this first to discover available properties before querying performance data or inspecting URLs. Returns each site\'s URL (e.g., "sc-domain:example.com" or "https://www.example.com/") and your permission level.',
                'icon' => 'ph:wrench',
            ],
            'google_search_console_performance' => [
                'class' => GoogleSearchConsolePerformance::class,
                'type' => 'read',
                'name' => 'Google Search Console Performance',
                'description' => 'Query Google Search Console search performance data (clicks, impressions, CTR, position). Common queries: "top pages by clicks" → dimensions=["page"]. "Top search queries" → dimensions=["query"]. "Traffic trend" → dimensions=["date"]. "Mobile vs desktop" → dimensions=["device"]. "Blog section" → dimensions=["page"], filters=[{dimension:"page", operator:"contains", value:"/blog/"}]. Combine dimensions: dimensions=["query","device"] for queries by device.',
                'icon' => 'ph:wrench',
            ],
            'google_search_console_submit_sitemap' => [
                'class' => GoogleSearchConsoleSubmitSitemap::class,
                'type' => 'write',
                'name' => 'Google Search Console Submit Sitemap',
                'description' => 'Submit a new sitemap to Google Search Console.',
                'icon' => 'ph:wrench',
            ],
        ];
    }


    public function scriptDocsPath(): ?string
    {
        return dirname(__DIR__) . '/script-docs/google.md';
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'access_token', 'type' => 'oauth', 'label' => 'Google Account', 'required' => true],
        ];
    }

    public function isIntegration(): bool
    {
        return true;
    }

    /** @param  array<string, mixed>  $context */
    public function createTool(string $class, array $context = []): Tool
    {
        $account = $context['account'] ?? null;
        $service = $account !== null
            ? new GoogleSearchConsoleService(GoogleServiceProvider::makeClient(app(), $this->appName(), (string) $account))
            : app(GoogleSearchConsoleService::class);

        return new $class($service);
    }
}
