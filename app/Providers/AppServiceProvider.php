<?php

namespace App\Providers;

use App\Agents\Providers\CodexPrismGateway;
use App\Agents\Providers\GlmPrismGateway;
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
use Laravel\Ai\Providers\AnthropicProvider;
use Laravel\Ai\Providers\AzureOpenAiProvider;
use Laravel\Ai\Providers\DeepSeekProvider;
use Laravel\Ai\Providers\GeminiProvider;
use Laravel\Ai\Providers\GroqProvider;
use Laravel\Ai\Providers\MistralProvider;
use Laravel\Ai\Providers\OllamaProvider;
use Laravel\Ai\Providers\OpenAiProvider;
use Laravel\Ai\Providers\OpenRouterProvider;
use Laravel\Ai\Providers\VoyageAiProvider;
use Laravel\Ai\Providers\XaiProvider;
use OpenCompany\PrismRelay\Bridge\CachingPrismGateway;
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

        // Override all AI SDK drivers to use CachingPrismGateway for provider-aware
        // prompt caching (Anthropic ephemeral, Gemini dedicated, OpenAI auto).
        // Use afterResolving because AiManager is scoped (recreated per job in queue workers).
        $this->app->afterResolving(AiManager::class, function (AiManager $aiManager, $app) {
            $gateway = new CachingPrismGateway($app['events']);
            $dispatcher = $app->make(Dispatcher::class);

            // Standard drivers — replace PrismGateway with CachingPrismGateway
            $standardDrivers = [
                'anthropic' => AnthropicProvider::class,
                'azure' => AzureOpenAiProvider::class,
                'deepseek' => DeepSeekProvider::class,
                'gemini' => GeminiProvider::class,
                'groq' => GroqProvider::class,
                'mistral' => MistralProvider::class,
                'ollama' => OllamaProvider::class,
                'openai' => OpenAiProvider::class,
                'openrouter' => OpenRouterProvider::class,
                'voyageai' => VoyageAiProvider::class,
                'xai' => XaiProvider::class,
            ];

            foreach ($standardDrivers as $driver => $providerClass) {
                $aiManager->extend($driver, fn ($app, array $config) => new $providerClass(
                    $gateway, $config, $dispatcher,
                ));
            }

            // Custom relay-backed drivers — use GlmPrismGateway (extends
            // CachingPrismGateway) so non-native Prism providers still work.
            $glmGateway = new GlmPrismGateway($app['events']);
            $createGlmDriver = fn ($app, array $config) => new OpenAiProvider(
                $glmGateway, $config, $dispatcher,
            );

            $aiManager->extend('z', $createGlmDriver);
            $aiManager->extend('z-api', $createGlmDriver);
            $aiManager->extend('kimi', $createGlmDriver);
            $aiManager->extend('kimi-coding', $createGlmDriver);
            $aiManager->extend('minimax', $createGlmDriver);
            $aiManager->extend('minimax-cn', $createGlmDriver);

            // Codex driver (ChatGPT subscription via OAuth)
            $aiManager->extend('codex', fn ($app, array $config) => new OpenAiProvider(
                new CodexPrismGateway($app['events']), $config, $dispatcher,
            ));
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
