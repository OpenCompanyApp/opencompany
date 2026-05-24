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
use App\Models\ApprovalRequest;
use App\Models\Document;
use App\Observers\ApprovalRequestObserver;
use App\Observers\DocumentObserver;
use App\Services\AgentFileStorageService;
use App\Services\AgentPermissionService;
use App\Services\Chat\ChatBridge;
use App\Services\Chat\ChatManager;
use App\Services\IntegrationSettingCredentialResolver;
use App\Services\Mcp\McpServerRegistrar;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;
use OpenCompany\IntegrationCore\Contracts\AgentFileStorage;
use OpenCompany\IntegrationCore\Contracts\CredentialResolver;
use OpenCompany\IntegrationCore\Lua\LuaCatalogBuilder;
use OpenCompany\IntegrationCore\Lua\LuaDocRenderer;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class);

        if (class_exists(LuaCatalogBuilder::class)) {
            $this->app->singleton(LuaCatalogBuilder::class);
        }

        if (class_exists(LuaDocRenderer::class)) {
            $this->app->singleton(LuaDocRenderer::class);
        }

        // Override the default config-based credential resolver with DB-backed one
        $this->app->singleton(
            CredentialResolver::class,
            IntegrationSettingCredentialResolver::class
        );

        // Agent file storage — allows vendor tool packages to save files into workspace file system
        $this->app->singleton(
            AgentFileStorage::class,
            AgentFileStorageService::class,
        );

        // Chat integration manager (workspace-scoped Chat instances)
        $this->app->singleton(ChatManager::class);
        $this->app->singleton(ChatBridge::class);

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

        // Observers
        ApprovalRequest::observe(ApprovalRequestObserver::class);
        Document::observe(DocumentObserver::class);

        // Disable JSON wrapping for API resources
        JsonResource::withoutWrapping();

        // Register MCP servers as tool providers
        if ($this->app->bound(ToolProviderRegistry::class)) {
            McpServerRegistrar::registerAll(
                $this->app->make(ToolProviderRegistry::class)
            );
        }

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
        $registry->registerBuiltIn(new ToolProviders\WebToolProvider);
    }
}
