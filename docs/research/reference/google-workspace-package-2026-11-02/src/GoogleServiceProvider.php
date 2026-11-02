<?php

namespace OpenCompany\Integrations\Google;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use OpenCompany\Integrations\Google\Services\GmailService;
use OpenCompany\Integrations\Google\Services\GoogleCalendarService;
use OpenCompany\Integrations\Google\Services\GoogleContactsService;
use OpenCompany\Integrations\Google\Services\GoogleDriveService;
use OpenCompany\Integrations\Google\Services\GoogleSearchConsoleService;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;
use OpenCompany\Integrations\Google\Services\GoogleAnalyticsService;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;
use OpenCompany\Integrations\Google\Services\GoogleFormsService;
use OpenCompany\Integrations\Google\Services\GoogleTasksService;
use OpenCompany\IntegrationCore\Contracts\CredentialResolver;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;
use Illuminate\Contracts\Container\Container;

class GoogleServiceProvider extends ServiceProvider
{
    /** @var array<int, string> */
    private const GOOGLE_INTEGRATIONS = [
        'google-calendar', 'gmail', 'google-drive',
        'google-contacts', 'google-sheets', 'google-search-console', 'google-tasks', 'google-analytics',
        'google-docs', 'google-forms',
    ];

    /** @var array<string, string> */
    private const LEGACY_INTEGRATION_IDS = [
        'google-calendar' => 'google_calendar',
        'google-drive' => 'google_drive',
        'google-contacts' => 'google_contacts',
        'google-sheets' => 'google_sheets',
        'google-search-console' => 'google_search_console',
        'google-tasks' => 'google_tasks',
        'google-analytics' => 'google_analytics',
        'google-docs' => 'google_docs',
        'google-forms' => 'google_forms',
    ];

    public function register(): void
    {
        $this->app->singleton(GoogleCalendarService::class, function ($app) {
            return new GoogleCalendarService(self::makeClient($app, 'google-calendar'));
        });

        $this->app->singleton(GmailService::class, function ($app) {
            return new GmailService(self::makeClient($app, 'gmail'));
        });

        $this->app->singleton(GoogleDriveService::class, function ($app) {
            return new GoogleDriveService(self::makeClient($app, 'google-drive'));
        });

        $this->app->singleton(GoogleContactsService::class, function ($app) {
            return new GoogleContactsService(self::makeClient($app, 'google-contacts'));
        });

        $this->app->singleton(GoogleSheetsService::class, function ($app) {
            return new GoogleSheetsService(self::makeClient($app, 'google-sheets'));
        });

        $this->app->singleton(GoogleSearchConsoleService::class, function ($app) {
            return new GoogleSearchConsoleService(self::makeClient($app, 'google-search-console'));
        });

        $this->app->singleton(GoogleTasksService::class, function ($app) {
            return new GoogleTasksService(self::makeClient($app, 'google-tasks'));
        });

        $this->app->singleton(GoogleAnalyticsService::class, function ($app) {
            return new GoogleAnalyticsService(self::makeClient($app, 'google-analytics'));
        });

        $this->app->singleton(GoogleDocsService::class, function ($app) {
            return new GoogleDocsService(self::makeClient($app, 'google-docs'));
        });

        $this->app->singleton(GoogleFormsService::class, function ($app) {
            return new GoogleFormsService(self::makeClient($app, 'google-forms'));
        });
    }

    public static function makeClient(Container $app, string $integration, ?string $account = null): GoogleClient
    {
        $creds = $app->make(CredentialResolver::class);
        $accessToken = self::resolveCredentialWithSource($creds, $integration, 'access_token', '', $account);
        $refreshToken = self::resolveCredentialWithSource($creds, $integration, 'refresh_token', '', $account);
        $expiresAt = self::resolveCredentialWithSource($creds, $integration, 'expires_at', null, $account);
        $tokenSource = $accessToken['source'] ?? $refreshToken['source'] ?? $integration;

        return new GoogleClient(
            clientId: self::resolveSharedCredential($creds, $integration, 'client_id', $account),
            clientSecret: self::resolveSharedCredential($creds, $integration, 'client_secret', $account),
            accessToken: (string) $accessToken['value'],
            refreshToken: (string) $refreshToken['value'],
            expiresAt: $expiresAt['value'] !== null ? (int) $expiresAt['value'] : null,
            integrationId: $tokenSource,
            accountAlias: $account,
        );
    }

    /**
     * Resolve a shared credential (client_id/client_secret) across all Google integrations.
     * Tries the target integration first, then falls back to any sibling that has it configured.
     */
    private static function resolveSharedCredential(CredentialResolver $creds, string $integration, string $key, ?string $account = null): string
    {
        foreach (self::credentialIds($integration) as $id) {
            $value = $creds->get($id, $key, '', $account);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        foreach (self::GOOGLE_INTEGRATIONS as $sibling) {
            if ($sibling === $integration) {
                continue;
            }
            foreach (self::credentialIds($sibling) as $id) {
                $value = $creds->get($id, $key, '', $account);
                if ($value !== null && $value !== '') {
                    return (string) $value;
                }
            }
        }

        return '';
    }

    /**
     * @return array{value: mixed, source?: string}
     */
    private static function resolveCredentialWithSource(
        CredentialResolver $creds,
        string $integration,
        string $key,
        mixed $default = '',
        ?string $account = null,
    ): array {
        foreach (self::credentialIds($integration) as $id) {
            $value = $creds->get($id, $key, null, $account);
            if ($value !== null && $value !== '') {
                return ['value' => $value, 'source' => $id];
            }
        }

        return ['value' => $default];
    }

    /**
     * @return array<int, string>
     */
    private static function credentialIds(string $integration): array
    {
        $ids = [$integration];
        if (isset(self::LEGACY_INTEGRATION_IDS[$integration])) {
            $ids[] = self::LEGACY_INTEGRATION_IDS[$integration];
        }

        return array_values(array_unique($ids));
    }

    public function boot(): void
    {
        if ($this->app->bound(ToolProviderRegistry::class)) {
            $registry = $this->app->make(ToolProviderRegistry::class);
            $registry->register(new GoogleCalendarToolProvider());
            $registry->register(new GmailToolProvider());
            $registry->register(new GoogleDriveToolProvider());
            $registry->register(new GoogleContactsToolProvider());
            $registry->register(new GoogleSheetsToolProvider());
            $registry->register(new GoogleSearchConsoleToolProvider());
            $registry->register(new GoogleTasksToolProvider());
            $registry->register(new GoogleAnalyticsToolProvider());
            $registry->register(new GoogleDocsToolProvider());
            $registry->register(new GoogleFormsToolProvider());
        }

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::prefix('api/integrations/google/oauth')
            ->middleware(['web', 'auth', 'resolve.workspace', 'workspace.admin'])
            ->group(function () {
                Route::get('authorize', [GoogleOAuthController::class, 'authorize']);
                Route::get('callback', [GoogleOAuthController::class, 'callback']);
            });
    }
}
