<?php

namespace App\Providers;

use App\Agents\Tools\Providers as ToolProviders;
use App\Agents\Tools\ToolRegistry;
use App\Models\ApprovalRequest;
use App\Models\Document;
use App\Observers\ApprovalRequestObserver;
use App\Observers\DocumentObserver;
use App\Services\AgentPermissionService;
use App\Services\Chat\ChatBridge;
use App\Services\Chat\ChatManager;
use App\Services\Mcp\McpServerRegistrar;
use App\Services\PrismServerService;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;
use Laravel\Ai\Providers\OpenRouterProvider;
use OpenCompany\PrismRelay\Bridge\LaravelAi\RelayTextGateway;
use OpenCompany\PrismRelay\Relay;
use Prism\Prism\PrismManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class);

        if (class_exists(\OpenCompany\IntegrationCore\Lua\LuaCatalogBuilder::class)) {
            $this->app->singleton(\OpenCompany\IntegrationCore\Lua\LuaCatalogBuilder::class);
        }

        if (class_exists(\OpenCompany\IntegrationCore\Lua\LuaDocRenderer::class)) {
            $this->app->singleton(\OpenCompany\IntegrationCore\Lua\LuaDocRenderer::class);
        }

        // Override the default config-based credential resolver with DB-backed one
        $this->app->singleton(
            \OpenCompany\IntegrationCore\Contracts\CredentialResolver::class,
            \App\Services\IntegrationSettingCredentialResolver::class
        );

        // Agent file storage — allows vendor tool packages to save files into workspace file system
        $this->app->singleton(
            \OpenCompany\IntegrationCore\Contracts\AgentFileStorage::class,
            \App\Services\AgentFileStorageService::class,
        );

        // Chat integration manager (workspace-scoped Chat instances)
        $this->app->singleton(ChatManager::class);
        $this->app->singleton(ChatBridge::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Observers
        ApprovalRequest::observe(ApprovalRequestObserver::class);
        Document::observe(DocumentObserver::class);

        // Disable JSON wrapping for API resources
        JsonResource::withoutWrapping();

        // Register MCP servers as tool providers
        if ($this->app->bound(\OpenCompany\IntegrationCore\Support\ToolProviderRegistry::class)) {
            McpServerRegistrar::registerAll(
                $this->app->make(\OpenCompany\IntegrationCore\Support\ToolProviderRegistry::class)
            );
        }

        // Register built-in tool providers
        $this->registerBuiltInToolProviders();

        // Register enabled models with Prism Server
        if (config('prism.prism_server.enabled')) {
            $this->app->booted(function () {
                app(PrismServerService::class)->registerModels();
            });
        }

        // Custom Prism providers (Z.AI, Kimi, MiniMax) are registered by
        // PrismRelayServiceProvider via afterResolving(PrismManager::class).

        // Laravel AI 0.6 has native gateways for standard providers. Keep those
        // intact, and register relay-backed drivers only for custom providers
        // where prism-relay owns prompt caching and provider/runtime quirks.
        $this->app->afterResolving(AiManager::class, function (AiManager $aiManager, $app) {
            $dispatcher = $app->make(Dispatcher::class);

            $createRelayDriver = function ($app, array $config) use ($dispatcher) {
                $config['name'] ??= $config['driver'] ?? 'relay';
                $config['key'] ??= '';

                return (new OpenRouterProvider($config, $dispatcher))->useTextGateway(
                    new RelayTextGateway(
                        relay: $app->bound(Relay::class) ? $app->make(Relay::class) : null,
                        forcedProvider: $config['driver'] ?? null,
                    ),
                );
            };

            foreach (['z', 'z-api', 'kimi', 'kimi-coding', 'minimax', 'minimax-cn', 'codex'] as $driver) {
                $aiManager->extend($driver, $createRelayDriver);
            }
        });
    }

    /**
     * Register all built-in tool providers with the ToolRegistry.
     */
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
        $registry->registerBuiltIn(new ToolProviders\AutomationsToolProvider);
        $registry->registerBuiltIn(new ToolProviders\SvgToolProvider);
        $registry->registerBuiltIn(new ToolProviders\LuaToolProvider);
    }
}
