<?php

namespace App\Ai\Gateways;

use App\Ai\Providers\RelayAiProviderFactory;
use Closure;
use Generator;
use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Responses\TextResponse;
use OpenCompany\PrismRelay\Registry\RelayRegistry;

class ModelRouterTextGateway implements TextGateway
{
    private Closure $invokingToolCallback;

    private Closure $toolInvokedCallback;

    /**
     * @param  array<string, mixed>  $providerDefinition
     * @param  array<string, mixed>  $baseConfig
     */
    public function __construct(
        private readonly RelayAiProviderFactory $factory,
        private readonly string $providerName,
        private readonly array $providerDefinition,
        private readonly array $baseConfig,
        private readonly ?RelayRegistry $registry = null,
    ) {
        $this->invokingToolCallback = fn () => true;
        $this->toolInvokedCallback = fn () => true;
    }

    public function generateText(
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages = [],
        array $tools = [],
        ?array $schema = null,
        ?TextGenerationOptions $options = null,
        ?int $timeout = null,
    ): TextResponse {
        $routed = $this->providerForModel($model);

        return $routed->textGateway()
            ->onToolInvocation($this->invokingToolCallback, $this->toolInvokedCallback)
            ->generateText($routed, $model, $instructions, $messages, $tools, $schema, $options, $timeout);
    }

    public function streamText(
        string $invocationId,
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages = [],
        array $tools = [],
        ?array $schema = null,
        ?TextGenerationOptions $options = null,
        ?int $timeout = null,
    ): Generator {
        $routed = $this->providerForModel($model);

        yield from $routed->textGateway()
            ->onToolInvocation($this->invokingToolCallback, $this->toolInvokedCallback)
            ->streamText($invocationId, $routed, $model, $instructions, $messages, $tools, $schema, $options, $timeout);
    }

    public function onToolInvocation(Closure $invoking, Closure $invoked): self
    {
        $this->invokingToolCallback = $invoking;
        $this->toolInvokedCallback = $invoked;

        return $this;
    }

    private function providerForModel(string $model): TextProvider
    {
        foreach ($this->providerDefinition['model_routes'] ?? [] as $route) {
            if (! is_array($route)) {
                continue;
            }

            foreach (($route['match'] ?? []) as $pattern) {
                if (is_string($pattern) && str_starts_with($model, $pattern)) {
                    return $this->factory->createAnonymous(
                        providerName: $this->providerName,
                        driver: $this->registry()->laravelAiDriverForTransport(
                            (string) ($route['driver'] ?? $this->registry()->defaultTransport())
                        ),
                        providerDefinition: $this->providerDefinition,
                        config: array_merge($this->baseConfig, is_array($route['config'] ?? null) ? $route['config'] : []),
                    );
                }
            }
        }

        return $this->factory->createAnonymous(
            providerName: $this->providerName,
            driver: $this->registry()->laravelAiDriverForTransport(
                (string) ($this->providerDefinition['fallback_driver'] ?? $this->registry()->defaultTransport())
            ),
            providerDefinition: $this->providerDefinition,
            config: $this->baseConfig,
        );
    }

    private function registry(): RelayRegistry
    {
        return $this->registry ?? app(RelayRegistry::class);
    }
}
