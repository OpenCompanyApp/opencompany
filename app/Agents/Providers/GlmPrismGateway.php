<?php

namespace App\Agents\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Gateway\Prism\PrismGateway;
use Laravel\Ai\Providers\Provider;

/**
 * Custom PrismGateway that routes GLM requests to the registered 'glm' Prism provider
 * instead of the default OpenAI provider.
 *
 * The base PrismGateway hardcodes driver:'openai' → PrismProvider::OpenAI which uses
 * OpenAI's /responses endpoint. GLM needs the chat/completions endpoint via our custom
 * 'glm' Prism provider (registered as DeepSeek-compatible in AppServiceProvider).
 */
class GlmPrismGateway extends PrismGateway
{
    public function __construct(Dispatcher $events)
    {
        parent::__construct($events);
    }

    /**
     * Override configure to use the string 'glm' as the Prism provider key
     * instead of PrismProvider::OpenAI enum.
     *
     * Prism's using() accepts string|ProviderEnum, so passing 'glm' resolves
     * to our custom provider registered via PrismManager::extend('glm', ...).
     */
    protected function configure($prism, Provider $provider, string $model): mixed
    {
        return $prism->using(
            'glm',
            $model,
            array_filter([
                'api_key' => $provider->providerCredentials()['key'],
            ]),
        );
    }
}
