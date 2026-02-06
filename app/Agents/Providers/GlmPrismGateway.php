<?php

namespace App\Agents\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Gateway\Prism\PrismGateway;
use Laravel\Ai\Gateway\TextGenerationOptions;
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

    /**
     * Override to use config-based timeout (instead of hardcoded 60s)
     * and add HTTP-level retries for transient failures.
     */
    protected function createPrismTextRequest(
        Provider $provider,
        string $model,
        ?array $schema,
        ?TextGenerationOptions $options = null,
        ?int $timeout = null,
    ) {
        $resolvedTimeout = $timeout ?? (int) config('prism.request_timeout', 600);

        Log::info('GlmPrismGateway timeout debug', [
            'incoming_timeout' => $timeout,
            'config_value' => config('prism.request_timeout'),
            'resolved_timeout' => $resolvedTimeout,
        ]);

        return parent::createPrismTextRequest(
            $provider,
            $model,
            $schema,
            $options,
            $resolvedTimeout,
        )->withClientOptions(['timeout' => $resolvedTimeout])
        ->withClientRetry(
            times: 2,
            sleepMilliseconds: 1000,
            when: fn ($exception) => $exception instanceof ConnectionException
                || ($exception instanceof RequestException
                    && in_array($exception->response?->status(), [408, 429, 500, 502, 503, 504])),
            throw: true,
        );
    }
}
