<?php

namespace App\Ai\Gateways;

use App\Ai\Prompting\SystemPromptBag;
use App\Ai\TextGeneration\MergedTextGenerationOptions;
use Closure;
use Generator;
use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Responses\TextResponse;

class CachingTextGateway implements TextGateway
{
    public function __construct(
        private readonly TextGateway $inner,
        private readonly PromptCachePolicy $cachePolicy = new PromptCachePolicy,
    ) {}

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
        return $this->inner->generateText(
            $provider,
            $model,
            $this->instructions($instructions),
            $messages,
            $tools,
            $schema,
            $this->options($provider, $options),
            $timeout,
        );
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
        yield from $this->inner->streamText(
            $invocationId,
            $provider,
            $model,
            $this->instructions($instructions),
            $messages,
            $tools,
            $schema,
            $this->options($provider, $options),
            $timeout,
        );
    }

    public function onToolInvocation(Closure $invoking, Closure $invoked): self
    {
        $this->inner->onToolInvocation($invoking, $invoked);

        return $this;
    }

    public function inner(): TextGateway
    {
        return $this->inner;
    }

    private function instructions(?string $fallback): ?string
    {
        if (! app()->bound(SystemPromptBag::class)) {
            return $fallback;
        }

        return app(SystemPromptBag::class)->toInstructions($fallback);
    }

    private function options(TextProvider $provider, ?TextGenerationOptions $options): ?TextGenerationOptions
    {
        $providerKey = $this->providerKey($provider);
        $providerOptions = $this->cachePolicy->providerOptions($providerKey);

        if ($providerOptions === []) {
            return $options;
        }

        return new MergedTextGenerationOptions($options, [
            $providerKey => $providerOptions,
        ]);
    }

    private function providerKey(TextProvider $provider): string
    {
        if (method_exists($provider, 'driver')) {
            return (string) $provider->driver();
        }

        if (method_exists($provider, 'name')) {
            return (string) $provider->name();
        }

        return class_basename($provider);
    }
}
