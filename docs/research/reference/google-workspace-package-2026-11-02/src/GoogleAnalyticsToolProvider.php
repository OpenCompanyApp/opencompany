<?php

namespace OpenCompany\Integrations\Google;

use Illuminate\Support\Facades\Http;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\HasIntegrationCapabilities;
use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;
use OpenCompany\Integrations\Google\Services\GoogleAnalyticsService;
use OpenCompany\Integrations\Google\Tools\GoogleAnalyticsBatchRunPivotReports;
use OpenCompany\Integrations\Google\Tools\GoogleAnalyticsBatchRunReports;
use OpenCompany\Integrations\Google\Tools\GoogleAnalyticsCheckCompatibility;
use OpenCompany\Integrations\Google\Tools\GoogleAnalyticsListProperties;
use OpenCompany\Integrations\Google\Tools\GoogleAnalyticsMetadata;
use OpenCompany\Integrations\Google\Tools\GoogleAnalyticsPivotReport;
use OpenCompany\Integrations\Google\Tools\GoogleAnalyticsRealtime;
use OpenCompany\Integrations\Google\Tools\GoogleAnalyticsReport;

/**
 * Exposes Google Analytics GA4 Admin and Data API tools.
 */
class GoogleAnalyticsToolProvider implements ToolProvider, ConfigurableIntegration, HasIntegrationCapabilities
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
        return 'google-analytics';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'Google Analytics',
            'description' => 'Website analytics and reporting',
            'icon' => 'ph:chart-bar',
            'logo' => 'simple-icons:googleanalytics',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Analytics',
            'description' => 'Website traffic, audience insights, and conversion reporting',
            'icon' => 'ph:chart-bar',
            'logo' => 'simple-icons:googleanalytics',
            'category' => 'analytics',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com',
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_analytics',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Analytics" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries', [
                'pageSize' => '200',
            ]);

            if ($response->successful()) {
                $accounts = $response->json('accountSummaries') ?? [];
                $propertyCount = 0;
                foreach ($accounts as $account) {
                    $propertyCount += count($account['propertySummaries'] ?? []);
                }
                $accountCount = count($accounts);
                $emailInfo = $connectedEmail ? " ({$connectedEmail})" : '';

                return [
                    'success' => true,
                    'message' => "Google Analytics connected{$emailInfo}. {$accountCount} " . ($accountCount === 1 ? 'account' : 'accounts') . ", {$propertyCount} " . ($propertyCount === 1 ? 'property' : 'properties') . '.',
                ];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Analytics API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
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
            'google_analytics_list_properties' => [
                'class' => GoogleAnalyticsListProperties::class,
                'type' => 'read',
                'name' => 'Google Analytics List Properties',
                'description' => 'List all accessible GA4 properties with their IDs and names. Use this first to discover the propertyId needed for other Analytics tools.',
                'icon' => 'ph:wrench',
            ],
            'google_analytics_metadata' => [
                'class' => GoogleAnalyticsMetadata::class,
                'type' => 'read',
                'name' => 'Google Analytics Metadata',
                'description' => 'List all available dimensions and metrics for a GA4 property, including custom ones. Use this to discover what data can be queried in reports.',
                'icon' => 'ph:wrench',
            ],
            'google_analytics_realtime' => [
                'class' => GoogleAnalyticsRealtime::class,
                'type' => 'read',
                'name' => 'Google Analytics Realtime',
                'description' => 'Run a GA4 realtime report showing activity in the last 30 minutes. Common dimensions: country, city, deviceCategory, unifiedScreenName (page/screen), platform. Common metrics: activeUsers, screenPageViews, eventCount, conversions.',
                'icon' => 'ph:wrench',
            ],
            'google_analytics_report' => [
                'class' => GoogleAnalyticsReport::class,
                'type' => 'read',
                'name' => 'Google Analytics Report',
                'description' => 'Run a GA4 analytics report. Returns rows of dimension/metric data for the specified date range. Common dimensions: sessionSource, sessionMedium, sessionDefaultChannelGroup (traffic source); pagePath, pageTitle, landingPage (pages); country, city (geo); deviceCategory, browser, operatingSystem (device); date, dateHour, month (time); newVsReturning (user); eventName (events). Common metrics: sessions, totalUsers, newUsers, activeUsers (traffic); screenPageViews, bounceRate, averageSessionDuration, engagementRate, sessionsPerUser (engagement); eventCount, conversions (events); purchaseRevenue, totalRevenue (e-commerce). Dates: YYYY-MM-DD or relative: "today", "yesterday", "7daysAgo", "28daysAgo", "30daysAgo", "90daysAgo", "365daysAgo". Filter operators: exact, contains, begins_with, ends_with, regex, in_list. Metric filter operators: equal, less_than, greater_than, less_than_or_equal, greater_than_or_equal.',
                'icon' => 'ph:wrench',
            ],
            'google_analytics_check_compatibility' => [
                'class' => GoogleAnalyticsCheckCompatibility::class,
                'type' => 'read',
                'name' => 'Check Compatibility',
                'description' => 'Check whether GA4 dimensions and metrics can be combined in a Data API report.',
                'icon' => 'ph:check-circle',
            ],
            'google_analytics_pivot_report' => [
                'class' => GoogleAnalyticsPivotReport::class,
                'type' => 'read',
                'name' => 'Pivot Report',
                'description' => 'Run an advanced GA4 pivot report.',
                'icon' => 'ph:table',
            ],
            'google_analytics_batch_run_reports' => [
                'class' => GoogleAnalyticsBatchRunReports::class,
                'type' => 'read',
                'name' => 'Batch Run Reports',
                'description' => 'Run multiple GA4 standard reports in one Data API request.',
                'icon' => 'ph:stack',
            ],
            'google_analytics_batch_run_pivot_reports' => [
                'class' => GoogleAnalyticsBatchRunPivotReports::class,
                'type' => 'read',
                'name' => 'Batch Run Pivot Reports',
                'description' => 'Run multiple GA4 pivot reports in one Data API request.',
                'icon' => 'ph:stack',
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
            ? new GoogleAnalyticsService(GoogleServiceProvider::makeClient(app(), $this->appName(), (string) $account))
            : app(GoogleAnalyticsService::class);

        return new $class($service);
    }
}
