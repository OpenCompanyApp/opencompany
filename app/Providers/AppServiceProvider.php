<?php

namespace App\Providers;

use App\Agents\Tools\Providers as ToolProviders;
use App\Agents\Tools\ToolRegistry;
use App\Domain\Ai\Codex\CodexOAuthService;
use App\Domain\Ai\Codex\Contracts\CodexTokenStore as CodexTokenStoreContract;
use App\Domain\Ai\Codex\Stores\EloquentCodexTokenStore;
use App\Domain\Ai\Runtime\OpenCompanyAiProviderFactory;
use App\Domain\Ai\Runtime\OpenCompanyAiProviderRegistrar;
use App\Domain\Ai\Usage\OpenRouterGenerationStore;
use App\Domain\Chat\Telegram\Application\TelegramNotificationRouter;
use App\Domain\Vfs\Core\VfsOperationLog;
use App\Domain\Vfs\Events\DatabaseVfsEventSink;
use App\Domain\Vfs\Events\VfsAuditor;
use App\Domain\Vfs\Events\VfsEventSink;
use App\Domain\Vfs\OpenCompany\AuditedOpenCompanyVfs;
use App\Domain\Vfs\OpenCompany\Mounts\VfsMountRegistry;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Events\TaskUpdated;
use App\Http\Middleware\AuthenticateAiGateway;
use App\Models\ApprovalRequest;
use App\Models\Document;
use App\Observers\ApprovalRequestObserver;
use App\Observers\DocumentObserver;
use App\Services\AgentPermissionService;
use App\Services\Chat\ChatBridge;
use App\Services\Chat\ChatManager;
use App\Services\CodeExecutionBudget;
use App\Services\Mcp\McpServerRegistrar;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;
use App\Domain\CodeMode\Script\ScriptCatalogBuilder;
use App\Domain\CodeMode\Script\ScriptDocRenderer;
use App\Agents\Tools\External\ToolProviderRegistry;

class AppServiceProvider extends ServiceProvider
{
    /** The HTTP factory is shared, so install the dynamic budget wrapper once. */
    private ?\WeakMap $codeExecutionBudgetFactories = null;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class);
        // Holds the approval-replay principal override for the request/job.
        $this->app->scoped(\App\Domain\Authorization\Application\OnBehalfOf::class);
        $this->app->scoped(CodeExecutionBudget::class);

        // Code Mode catalog/doc rendering and the external (MCP) tool provider
        // registry are app-owned since the integration package system was
        // deleted (Product Direction, nuclear decisions, 2026-11-02).
        $this->app->singleton(ScriptCatalogBuilder::class);
        $this->app->singleton(ScriptDocRenderer::class);
        $this->app->singleton(ToolProviderRegistry::class);

        // Chat integration manager (workspace-scoped Chat instances)
        $this->app->singleton(ChatManager::class);
        $this->app->singleton(ChatBridge::class);

        // VFS audit: every container-resolved facade is the audited one, and
        // events go through one sink (table-backed until the workspace event
        // log exists; rebinding VfsEventSink is the whole migration). The sink
        // is a singleton because it buffers aggregated read events.
        $this->app->bind(OpenCompanyVfs::class, AuditedOpenCompanyVfs::class);
        $this->app->singleton(VfsEventSink::class, DatabaseVfsEventSink::class);
        // Additional VFS mounts (future connection, skills and app mounts;
        // tests). Built-in mounts are declared by OpenCompanyVfs itself
        // (RegistersVfsBuiltinMounts); governance wraps every mount.
        $this->app->singleton(VfsMountRegistry::class);

        $this->app->singleton(OpenCompanyAiProviderFactory::class);
        $this->app->singleton(OpenCompanyAiProviderRegistrar::class);
        $this->app->singleton(OpenRouterGenerationStore::class);
        $this->app->singleton(CodexTokenStoreContract::class, EloquentCodexTokenStore::class);
        $this->app->singleton(CodexOAuthService::class, fn ($app) => new CodexOAuthService(
            $app->make(CodexTokenStoreContract::class),
            $app->make(HttpFactory::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->configureRateLimiting();

        $this->app->afterResolving(HttpFactory::class, function (HttpFactory $http): void {
            $this->codeExecutionBudgetFactories ??= new \WeakMap;
            if (isset($this->codeExecutionBudgetFactories[$http])) {
                return;
            }

            $this->codeExecutionBudgetFactories[$http] = true;

            // Resolve the scoped budget at transport time: capturing one
            // instance here would leak a completed worker/request scope into
            // later host calls.
            $http->globalMiddleware(function (callable $handler): Closure {
                return function ($request, array $options) use ($handler) {
                    return $this->app->make(CodeExecutionBudget::class)->dispatch($handler, $request, $options);
                };
            });
        });

        // Observers
        ApprovalRequest::observe(ApprovalRequestObserver::class);
        Document::observe(DocumentObserver::class);

        Event::listen(TaskUpdated::class, [TelegramNotificationRouter::class, 'handleTaskUpdated']);
        foreach ([JobProcessing::class, JobProcessed::class, JobExceptionOccurred::class, JobFailed::class] as $queueEvent) {
            Event::listen($queueEvent, function (object $event): void {
                $job = $event->job ?? null;
                Cache::put('agent-runtime:queue-worker-heartbeat', [
                    'at' => now()->toIso8601String(),
                    'event' => class_basename($event),
                    'job' => $job && method_exists($job, 'resolveName') ? class_basename($job->resolveName()) : null,
                    'connection' => $event->connectionName ?? null,
                ], now()->addDay());
            });
        }
        // Tool-call telemetry from the vfs_* tool classes; object-level VFS
        // events are recorded by AuditedOpenCompanyVfs. No per-event
        // Schema::hasTable() probe: the table is part of the schema.
        Event::listen(VfsOperationLog::class, function (VfsOperationLog $event): void {
            $this->app->make(VfsAuditor::class)->toolCall($event);
        });
        // Aggregated VFS read events are buffered; persist them at the end of
        // every request and queue job.
        $this->app->terminating(fn () => $this->app->make(VfsEventSink::class)->flush());
        foreach ([JobProcessed::class, JobFailed::class] as $jobFinished) {
            Event::listen($jobFinished, fn () => $this->app->make(VfsEventSink::class)->flush());
        }

        // Disable JSON wrapping for API resources
        JsonResource::withoutWrapping();

        // Register MCP servers as tool providers
        McpServerRegistrar::registerAll($this->app->make(ToolProviderRegistry::class));

        // Register built-in tool providers
        $this->registerBuiltInToolProviders();

        // Register all app-catalog providers with Laravel AI. Provider metadata,
        // aliases, and transport mapping are intentionally owned by OpenCompany
        // so agent execution is not coupled to package-level registries.
        $this->app->afterResolving(AiManager::class, function (AiManager $aiManager, $app) {
            $app->make(OpenCompanyAiProviderRegistrar::class)->register($aiManager);
        });
    }

    /**
     * Register all built-in tool providers with the ToolRegistry.
     */
    /**
     * Register the named edge limiters used as `throttle:<name>` in routes/api.php.
     *
     * Budgets come from config/rate_limits.php (env-driven). Closures read
     * config per request, so tests and runtime config changes apply without
     * re-registration. Keys are prefixed per limiter. The framework also
     * namespaces by limiter name, but explicit prefixes keep the guest and
     * user buckets from ever colliding.
     *
     * Breadcrumb: these are app-local edge controls, not package-owned. Keep
     * them here (or move them to a dedicated provider) rather than inlining
     * numeric `throttle:60,1` strings in routes.
     */
    private function configureRateLimiting(): void
    {
        // Authenticated first-party API: one bucket per user, so NAT'd teams
        // do not starve each other. Guests fall back to a tighter IP bucket.
        RateLimiter::for('api', function (Request $request): Limit {
            $user = $request->user();

            return $user
                ? Limit::perMinute(max(1, (int) config('rate_limits.api.per_minute')))->by('user:'.$user->getAuthIdentifier())
                : Limit::perMinute(max(1, (int) config('rate_limits.api.guest_per_minute')))->by('ip:'.$request->ip());
        });

        // Chat provider webhooks: IP + adapter, so a noisy Slack source cannot
        // exhaust Telegram's budget (and vice versa). Deliberately not keyed by
        // the presented secret: attackers could rotate fake secrets to get
        // fresh buckets.
        RateLimiter::for('chat-webhooks', function (Request $request): Limit {
            $adapter = (string) ($request->route('adapter') ?? 'telegram-legacy');

            return Limit::perMinute(max(1, (int) config('rate_limits.chat_webhooks.per_minute')))
                ->by('chat-webhook:'.$adapter.'|'.$request->ip());
        });

        // Telegram Mini App: the Telegram user id here is parsed from
        // *unverified* init_data (signature checks happen in the controller).
        // It is therefore always combined with the IP, so forging another
        // user's id cannot drain that user's bucket, and the per-IP ceiling
        // stops id rotation from bypassing the limit.
        RateLimiter::for('telegram-mini-app', function (Request $request): array {
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(max(1, (int) config('rate_limits.telegram_mini_app.per_user_per_minute')))
                    ->by('tg-mini-app:'.$ip.'|'.$this->claimedTelegramUserId($request)),
                Limit::perMinute(max(1, (int) config('rate_limits.telegram_mini_app.per_ip_per_minute')))
                    ->by('tg-mini-app-ip:'.$ip),
            ];
        });

        // AI Gateway: one budget per gateway API key (a cost guard on paid
        // provider fan-out), falling back to IP for unauthenticated callers.
        // ThrottleRequests runs before AuthenticateAiGateway (middleware
        // priority), so resolve via the shared memoized lookup instead of
        // relying on state set in handle(). Expired keys still get their own
        // bucket here; the middleware rejects them right after.
        RateLimiter::for('ai-gateway', function (Request $request): Limit {
            $key = AuthenticateAiGateway::resolveKey($request);

            $limit = $key
                ? Limit::perMinute(max(1, (int) config('rate_limits.ai_gateway.per_minute')))->by('ai-gateway-key:'.$key->getKey())
                : Limit::perMinute(max(1, (int) config('rate_limits.ai_gateway.guest_per_minute')))->by('ai-gateway-ip:'.$request->ip());

            // OpenAI-compatible clients parse error.code/type, so match that
            // envelope rather than Laravel's default HTML/JSON 429.
            return $limit->response(fn (Request $request, array $headers) => response()->json([
                'error' => [
                    'message' => 'Rate limit exceeded for this API key. Retry later.',
                    'type' => 'rate_limit_error',
                    'code' => 'rate_limit_exceeded',
                ],
            ], 429, $headers));
        });
    }

    /**
     * Best-effort, UNVERIFIED Telegram user id from Mini App init_data.
     *
     * Used only to subdivide an IP's rate-limit bucket. Never use it for
     * authorization. Returns 'anon' when init_data is absent or malformed.
     */
    private function claimedTelegramUserId(Request $request): string
    {
        $initData = $request->input('init_data');
        if (! is_string($initData) || $initData === '' || strlen($initData) > 8192) {
            return 'anon';
        }

        parse_str($initData, $fields);
        $user = is_string($fields['user'] ?? null) ? json_decode($fields['user'], true) : null;
        $id = is_array($user) ? ($user['id'] ?? null) : null;

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (string) $id : 'anon';
    }

    private function registerBuiltInToolProviders(): void
    {
        $registry = $this->app->make(ToolRegistry::class);
        $permissions = $this->app->make(AgentPermissionService::class);

        $registry->registerBuiltIn(new ToolProviders\TasksToolProvider);
        $registry->registerBuiltIn(new ToolProviders\SystemToolProvider);
        $registry->registerBuiltIn(new ToolProviders\AgentsToolProvider($permissions));
        $registry->registerBuiltIn(new ToolProviders\MemoryToolProvider);
        $registry->registerBuiltIn(new ToolProviders\ChatToolProvider($permissions));
        $registry->registerBuiltIn(new ToolProviders\DocsToolProvider($permissions));
        $registry->registerBuiltIn(new ToolProviders\FilesToolProvider($permissions));
        $registry->registerBuiltIn(new ToolProviders\TablesToolProvider);
        $registry->registerBuiltIn(new ToolProviders\CalendarToolProvider);
        $registry->registerBuiltIn(new ToolProviders\ListsToolProvider);
        $registry->registerBuiltIn(new ToolProviders\WorkspaceToolProvider($permissions));
        $registry->registerBuiltIn(new ToolProviders\RoutinesToolProvider);
        $registry->registerBuiltIn(new ToolProviders\SvgToolProvider);
        $registry->registerBuiltIn(new ToolProviders\CodeToolProvider);
        $registry->registerBuiltIn(new ToolProviders\WebToolProvider);
        $registry->registerBuiltIn(new ToolProviders\VfsToolProvider);
    }
}
