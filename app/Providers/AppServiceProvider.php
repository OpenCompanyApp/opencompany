<?php

namespace App\Providers;

use App\Agents\Providers\CodexPrismGateway;
use App\Agents\Providers\GlmPrismGateway;
use App\Agents\Tools\Providers as ToolProviders;
use App\Agents\Tools\ToolRegistry;
use App\Models\ApprovalRequest;
use App\Models\Document;
use App\Services\AgentPermissionService;
use App\Services\Mcp\McpServerRegistrar;
use App\Services\Chat\ChatBridge;
use App\Services\Chat\ChatManager;
use App\Services\PrismServerService;
use App\Observers\ApprovalRequestObserver;
use App\Observers\DocumentObserver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;
use Laravel\Ai\Providers\OpenAiProvider;
use Prism\Prism\PrismManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class);

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

        // Register custom AI SDK drivers.
        // These use GlmPrismGateway which routes to the matching Prism provider
        // (chat/completions) instead of the default OpenAI provider (/responses).
        // Use afterResolving because AiManager is scoped (recreated per job in queue workers).
        $this->app->afterResolving(AiManager::class, function (AiManager $aiManager, $app) {
            $createGlmDriver = function ($app, array $config) {
                return new OpenAiProvider(
                    new GlmPrismGateway($app['events']),
                    $config,
                    $app->make(Dispatcher::class)
                );
            };

            $aiManager->extend('z', $createGlmDriver);
            $aiManager->extend('z-api', $createGlmDriver);
            $aiManager->extend('kimi', $createGlmDriver);
            $aiManager->extend('kimi-coding', $createGlmDriver);
            $aiManager->extend('minimax', $createGlmDriver);
            $aiManager->extend('minimax-cn', $createGlmDriver);

            // Register Codex driver (ChatGPT subscription via OAuth)
            $aiManager->extend('codex', function ($app, array $config) {
                return new OpenAiProvider(
                    new CodexPrismGateway($app['events']),
                    $config,
                    $app->make(Dispatcher::class)
                );
            });
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
