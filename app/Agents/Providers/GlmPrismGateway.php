<?php

namespace App\Agents\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Gateway\Prism\PrismGateway;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Providers\Provider;

/**
 * Custom PrismGateway that routes requests to custom Prism providers
 * registered via PrismManager::extend() (GLM, Kimi, MiniMax, etc.).
 *
 * The base PrismGateway maps driver names to PrismProvider enums, which only
 * works for native Prism providers. Custom providers need their string key
 * passed directly to Prism's using() method.
 */
class GlmPrismGateway extends PrismGateway
{
    public function __construct(Dispatcher $events)
    {
        parent::__construct($events);
    }

    /**
     * Override configure to use the provider's driver name as the Prism provider key
     * instead of a PrismProvider enum.
     *
     * Prism's using() accepts string|ProviderEnum, so passing the driver name
     * resolves to our custom provider registered via PrismManager::extend().
     */
    protected function configure(mixed $prism, Provider $provider, string $model): mixed
    {
        return $prism->using(
            $provider->driver(),
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
    /**
     * @param  array<string, mixed>|null  $schema
     */
    protected function createPrismTextRequest(
        Provider $provider,
        string $model,
        ?array $schema,
        ?TextGenerationOptions $options = null,
        ?int $timeout = null,
    ): mixed {
        $resolvedTimeout = $timeout ?? (int) config('prism.request_timeout', 600);

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
                    && in_array($exception->response->status(), [408, 429, 500, 502, 503, 504])),
            throw: true,
        );
    }
}
