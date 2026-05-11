<?php

namespace App\Ai\Gateways;

use Closure;
use Generator;
use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Responses\TextResponse;
use RuntimeException;

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
