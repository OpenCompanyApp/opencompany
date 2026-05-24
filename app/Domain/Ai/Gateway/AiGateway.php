<?php

namespace App\Domain\Ai\Gateway;

use App\Agents\Providers\DynamicProviderResolver;
use App\Domain\Ai\Embeddings\EmbeddingClient;
use App\Domain\Ai\Usage\CostCalculator;
use App\Domain\Ai\Usage\LlmUsageEvent;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\AiManager;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;

/**
 * Executes OpenAI-compatible AI Gateway calls through OpenCompany runtime.
 *
 * The gateway owns protocol translation and accounting. Provider execution
 * remains delegated to Laravel AI gateways and OpenCompany embedding clients so
 * external API access cannot fork a separate provider stack.
 */
class AiGateway
{
    public function __construct(
        private readonly AiGatewayModelRegistry $registry,
        private readonly DynamicProviderResolver $resolver,
        private readonly AiManager $ai,
        private readonly EmbeddingClient $embeddings,
        private readonly CostCalculator $costs,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    public function chatCompletion(string $requestedModel, array $messages, ?int $maxTokens = null, ?float $temperature = null): TextResponse
    {
        $resolved = $this->registry->resolve($requestedModel);
        $runtime = $this->resolveRuntime($resolved['provider'], $resolved['model']);
        [$instructions, $conversation] = $this->messages($messages);
        $provider = $this->ai->textProvider($runtime['provider']);
        $options = new TextGenerationOptions(
            temperature: $temperature,
            maxTokens: $maxTokens,
        );

        $started = hrtime(true);
        $response = $provider->textGateway()->generateText(
            provider: $provider,
            model: $runtime['model'],
            instructions: $instructions,
            messages: $conversation,
            tools: [],
            schema: null,
            options: $options,
            timeout: (int) config('ai.request_timeout', 30),
        );

        $this->recordUsage(
            purpose: 'ai_gateway.chat_completions',
            requestedProvider: $resolved['provider'],
            requestedModel: $resolved['model'],
            resolvedProvider: $response->meta->provider ?: $runtime['provider'],
            resolvedModel: $response->meta->model ?: $runtime['model'],
            usage: $response->usage,
            generationTimeMs: (int) round((hrtime(true) - $started) / 1_000_000),
            raw: ['gateway_model' => $resolved['gateway_model']],
        );

        return $response;
    }

    /**
     * @param  list<string>  $inputs
     * @return list<array<int, float>>
     */
    public function embeddings(string $requestedModel, array $inputs): array
    {
        $resolved = $this->registry->resolve($requestedModel);
        $started = hrtime(true);
        $vectors = $this->embeddings->embedMany($resolved['provider'], $resolved['model'], $inputs, workspace()->id);

        $this->recordUsage(
            purpose: 'ai_gateway.embeddings',
            requestedProvider: $resolved['provider'],
            requestedModel: $resolved['model'],
            resolvedProvider: $resolved['provider'],
            resolvedModel: $resolved['model'],
            usage: new Usage(promptTokens: $this->roughInputTokens($inputs)),
            generationTimeMs: (int) round((hrtime(true) - $started) / 1_000_000),
            raw: ['gateway_model' => $resolved['gateway_model'], 'input_count' => count($inputs)],
        );

        return $vectors;
    }

    /**
     * @return array{provider: string, model: string}
     */
    private function resolveRuntime(string $provider, string $model): array
    {
        $resolver = clone $this->resolver;

        return $resolver
            ->setWorkspaceId(workspace()->id)
            ->resolveFromParts($provider, $model);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     * @return array{0: string, 1: list<Message>}
     */
    private function messages(array $payload): array
    {
        $instructions = [];
        $messages = [];

        foreach ($payload as $message) {
            $role = (string) ($message['role'] ?? 'user');
            $content = $this->stringContent($message['content'] ?? '');

            if ($role === 'system' || $role === 'developer') {
                $instructions[] = $content;

                continue;
            }

            $messages[] = match ($role) {
                'assistant' => new AssistantMessage($content),
                default => new UserMessage($content),
            };
        }

        return [trim(implode("\n\n", array_filter($instructions))) ?: 'You are a helpful assistant.', $messages];
    }

    private function stringContent(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            return collect($content)
                ->map(fn (mixed $part): string => is_array($part) ? (string) ($part['text'] ?? '') : (string) $part)
                ->filter()
                ->implode("\n");
        }

        return (string) $content;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function recordUsage(
        string $purpose,
        string $requestedProvider,
        string $requestedModel,
        string $resolvedProvider,
        string $resolvedModel,
        Usage $usage,
        int $generationTimeMs,
        array $raw = [],
    ): void {
        if (! Schema::hasTable('llm_usage_events')) {
            return;
        }

        $estimatedCost = $this->costs->estimate($resolvedProvider, $resolvedModel, $usage);

        LlmUsageEvent::create([
            'workspace_id' => workspace()->id,
            'purpose' => $purpose,
            'status' => 'completed',
            'requested_provider' => $requestedProvider,
            'requested_model' => $requestedModel,
            'resolved_provider' => $resolvedProvider,
            'resolved_model' => $resolvedModel,
            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
            'cache_read_tokens' => $usage->cacheReadInputTokens,
            'cache_write_tokens' => $usage->cacheWriteInputTokens,
            'reasoning_tokens' => $usage->reasoningTokens,
            'generation_time_ms' => $generationTimeMs,
            'estimated_cost_usd' => $estimatedCost,
            'cost_source' => $estimatedCost === null ? 'unknown' : 'catalog_estimate',
            'raw_usage' => ['usage' => $usage->toArray(), ...$raw],
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $inputs
     */
    private function roughInputTokens(array $inputs): int
    {
        return (int) ceil(strlen(implode("\n", $inputs)) / 4);
    }
}
