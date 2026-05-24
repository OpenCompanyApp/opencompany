<?php

namespace App\Ai\Gateways;

use Closure;
use Generator;
use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Responses\TextResponse;
use RuntimeException;

/**
 * Gateway placeholder for catalog providers that cannot execute through Laravel AI.
 *
 * Registration still exposes the provider for catalog/config purposes, but any
 * runtime text call fails loudly with a transport-specific error.
 */
class UnsupportedTextGateway implements TextGateway
{
    public function __construct(
        private readonly string $provider,
        private readonly string $transport,
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
        throw $this->exception();
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
        throw $this->exception();
    }

    public function onToolInvocation(Closure $invoking, Closure $invoked): self
    {
        // No inner gateway exists, but returning $this keeps the TextGateway
        // contract chainable for callers that attach tool hooks unconditionally.
        return $this;
    }

    private function exception(): RuntimeException
    {
        return new RuntimeException(sprintf(
            'Provider [%s] uses unsupported transport [%s] for OpenCompany Laravel AI runtime execution.',
            $this->provider,
            $this->transport,
        ));
    }
}
