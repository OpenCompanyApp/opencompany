<?php

namespace App\Agents\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Providers\Provider;
use OpenCompany\PrismRelay\Bridge\CachingPrismGateway;

/**
 * Custom gateway that routes Codex requests to the registered 'codex' Prism provider.
 *
 * Extends CachingPrismGateway for prompt cache support.
 */
class CodexPrismGateway extends CachingPrismGateway
{
    public function __construct(Dispatcher $events)
    {
        parent::__construct($events);
    }

    protected function configure(mixed $prism, Provider $provider, string $model): mixed
    {
        return $prism->using('codex', $model);
    }

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
