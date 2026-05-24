<?php

namespace App\Domain\Ai\Runtime;

use App\Domain\Ai\Usage\OpenRouterGenerationStore;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\OpenRouter\OpenRouterGateway;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Responses\TextResponse;

/**
 * OpenRouter gateway variant that preserves raw generation metadata.
 *
 * The upstream Laravel AI gateway correctly normalizes text responses but does
 * not expose OpenRouter generation IDs or billing fields. This subclass keeps
 * the upstream request/parse flow and only records the raw JSON before parsing.
 */
class OpenRouterBillingGateway extends OpenRouterGateway
{
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
        $body = $this->buildTextRequestBody(
            $provider,
            $model,
            $instructions,
            $messages,
            $tools,
            $schema,
            $options,
        );

        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider, $timeout)->post('chat/completions', $body),
        );

        $data = $response->json();
        if (is_array($data)) {
            app(OpenRouterGenerationStore::class)->push($data);
        }

        $this->validateTextResponse($data);

        return $this->parseTextResponse(
            $data,
            $provider,
            filled($schema),
            $tools,
            $schema,
            $options,
            $instructions,
            $messages,
            $timeout,
        );
    }
}
