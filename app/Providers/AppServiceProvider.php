<?php

namespace App\Providers;

use App\Agents\Providers\CodexPrismGateway;
use App\Agents\Providers\GlmPrismGateway;
use App\Models\ApprovalRequest;
use App\Models\Document;
use App\Services\Mcp\McpServerRegistrar;
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
use Prism\Prism\Providers\DeepSeek\DeepSeek;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Override the default config-based credential resolver with DB-backed one
        $this->app->singleton(
            \OpenCompany\IntegrationCore\Contracts\CredentialResolver::class,
            \App\Services\IntegrationSettingCredentialResolver::class
        );
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

        // Register enabled models with Prism Server
        if (config('prism.prism_server.enabled')) {
            $this->app->booted(function () {
                app(PrismServerService::class)->registerModels();
            });
        }

        // Register GLM (Zhipu AI) as custom Prism providers
        // GLM uses OpenAI-compatible chat/completions API (same as DeepSeek)
        $prismManager = $this->app->make(PrismManager::class);
        $glmPrismFactory = function ($app, array $config) {
            return new DeepSeek(
                apiKey: $config['api_key'] ?? '',
                url: $config['url'] ?? 'https://api.z.ai/api/coding/paas/v4',
            );
        };
        $prismManager->extend('glm', $glmPrismFactory);
        $prismManager->extend('glm-coding', $glmPrismFactory);

        // Register 'glm' and 'glm-coding' as custom AI SDK drivers.
        // These use GlmPrismGateway which routes to our custom 'glm' Prism provider
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

            $aiManager->extend('glm', $createGlmDriver);
            $aiManager->extend('glm-coding', $createGlmDriver);

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
}
