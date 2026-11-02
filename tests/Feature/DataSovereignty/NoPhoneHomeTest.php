<?php

namespace Tests\Feature\DataSovereignty;

use App\Jobs\RefreshMcpToolsJob;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Pillar 11, "No phone-home", enforced in CI: a self-hosted install sends no
 * telemetry, checks for no updates, and fetches no remote flags or egress
 * switches. Outbound traffic only happens because an admin configured a
 * destination (an AI provider, MCP server, Telegram bot, web search/fetch
 * provider) or a user asked for it (a URL to fetch, a calendar feed to
 * import, an agent run).
 *
 * Two layers:
 * 1. Runtime: booting, rendering the public pages and running every
 *    scheduled task on a fresh install makes no HTTP request.
 * 2. Static: every app file that can open an outbound connection is listed
 *    below with the user action or admin configuration behind it. A new
 *    egress point fails this test until it is reviewed and added here.
 */
class NoPhoneHomeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The reviewed egress points. Each is user-initiated or reaches only a
     * destination an admin configured. Keep reasons precise.
     *
     * @var array<string, string>
     */
    private const ALLOWED_EGRESS = [
        'app/Ai/Gateways/CodexTextGateway.php' => 'Codex models, only when an admin connected a Codex account; called by agent runs',
        'app/Http/Controllers/Api/CodexAuthController.php' => 'Codex device sign-in, started by an admin',
        'app/Services/Ai/ModelConnectionTester.php' => 'admin presses "test connection" for an AI provider',
        'app/Services/Ai/ModelRuntimeCatalog.php' => 'model lists from configured providers / local Ollama, on admin request',
        'app/Services/Memory/RerankingService.php' => 'local Ollama reranker, only when configured',
        'app/Jobs/ReconcileOpenRouterUsage.php' => 'cost reconciliation, only after a run used OpenRouter with the admin key',
        'app/Services/Integrations/IntegrationConnectionTester.php' => 'admin presses "test connection"',
        'app/Services/Mcp/McpClient.php' => 'admin-added MCP servers (SSRF-guarded)',
        'app/Domain/Web/Support/StreamableMcpToolInvoker.php' => 'configured web search/fetch MCP provider, called by agent tools',
        'app/Domain/Web/Providers/AbstractWebProvider.php' => 'configured web search/fetch provider, called by agent tools',
        'app/Domain/Web/Providers/Fetch/DirectFetchProvider.php' => 'fetches a URL an agent or user asked for',
        'app/Domain/Web/Providers/Fetch/ZaiReaderFetchProvider.php' => 'configured Z.ai reader, called by agent tools',
        'app/Domain/Web/Providers/Search/ZaiMcpSearchProvider.php' => 'configured Z.ai search, called by agent tools',
        'app/Http/Controllers/Api/CalendarEventController.php' => 'imports a calendar feed URL a user submitted',
        'app/Domain/Chat/Telegram/Application/TelegramSetupService.php' => 'Telegram bot setup, started by an admin',
        'app/Services/TelegramService.php' => 'Telegram Bot API, only when an admin configured a bot',
    ];

    /** Primitives that open outbound network connections. */
    private const EGRESS_PATTERN = '/Http::(?!fake|assert|preventStrayRequests)|GuzzleHttp\\\\Client|curl_init|stream_socket_client|fsockopen|file_get_contents\(\s*[\'"]https?:/';

    public function test_every_outbound_call_site_is_reviewed(): void
    {
        $found = [];
        foreach (Finder::create()->files()->in(base_path('app'))->name('*.php') as $file) {
            if (preg_match(self::EGRESS_PATTERN, $file->getContents()) === 1) {
                $found[] = 'app/'.str_replace('\\', '/', $file->getRelativePathname());
            }
        }
        sort($found);
        $allowed = array_keys(self::ALLOWED_EGRESS);
        sort($allowed);

        $this->assertSame([], array_values(array_diff($found, $allowed)),
            'New outbound call site: make it user-initiated or admin-configured, then add it with its reason.');
        $this->assertSame([], array_values(array_diff($allowed, $found)), 'Stale allowlist entry: remove it.');
    }

    public function test_boot_public_pages_and_every_scheduled_task_make_no_outbound_requests(): void
    {
        Http::preventStrayRequests();

        $this->get('/login');
        $this->get('/setup');

        $events = app(Schedule::class)->events();
        $this->assertNotEmpty($events);
        foreach ($events as $event) {
            if ($event instanceof CallbackEvent) {
                $event->run(app());

                continue;
            }
            $command = trim((string) preg_replace('/^.*artisan[\'"]?\s+/', '', (string) $event->command));
            Artisan::call($command);
        }
        dispatch_sync(new RefreshMcpToolsJob);

        Http::assertNothingSent();
    }

    // Shipped defaults: Telescope (local-only) and mail delivery stay off
    // unless an operator turns them on, and no telemetry/error-reporting SDK
    // is installed at all.
    public function test_shipped_defaults_send_nothing_and_no_telemetry_sdk_is_installed(): void
    {
        $this->assertStringContainsString("env('TELESCOPE_ENABLED', false)", (string) file_get_contents(base_path('config/telescope.php')));
        $this->assertStringContainsString("env('MAIL_MAILER', 'log')", (string) file_get_contents(base_path('config/mail.php')));

        // Installed package names only (lockfiles also mention optional
        // "suggest" packages that are not installed).
        $composer = json_decode((string) file_get_contents(base_path('composer.lock')), true);
        $installed = array_column([...$composer['packages'], ...$composer['packages-dev']], 'name');
        $npm = json_decode((string) @file_get_contents(base_path('package-lock.json')), true);
        foreach (array_keys($npm['packages'] ?? []) as $path) {
            $installed[] = (string) preg_replace('#^.*node_modules/#', '', $path);
        }

        $sdks = ['sentry/', '@sentry/', 'laravel/nightwatch', 'bugsnag', 'posthog', 'segmentio', 'mixpanel', 'datadog',
            'dd-trace', 'newrelic', 'honeybadger', 'rollbar', 'flagsmith', 'launchdarkly', 'unleash', 'growthbook'];
        $offenders = array_values(array_filter($installed, function (string $name) use ($sdks): bool {
            foreach ($sdks as $sdk) {
                if (str_starts_with($name, $sdk) || str_starts_with($name, '@'.$sdk)) {
                    return true;
                }
            }

            return false;
        }));
        $this->assertSame([], $offenders, 'Telemetry or remote-flag SDK installed.');
    }
}
