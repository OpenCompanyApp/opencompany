<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Prism\Prism\PrismManager;
use Prism\Prism\Providers\DeepSeek\DeepSeek;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Disable JSON wrapping for API resources
        JsonResource::withoutWrapping();

        // Register GLM (Zhipu AI) as a custom Prism provider
        // GLM uses OpenAI-compatible chat/completions API (same as DeepSeek)
        $this->app->make(PrismManager::class)->extend('glm', function ($app, array $config) {
            return new DeepSeek(
                apiKey: $config['api_key'] ?? '',
                url: $config['url'] ?? 'https://api.z.ai/api/coding/paas/v4',
            );
        });
    }
}
