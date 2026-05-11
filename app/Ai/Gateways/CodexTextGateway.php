<?php

namespace App\Ai\Gateways;

use Generator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\TextEnd;
use Laravel\Ai\Streaming\Events\TextStart;
use OpenCompany\PrismCodex\CodexOAuthService;
use RuntimeException;

class CodexTextGateway extends OpenAiGateway
{
    public function __construct(
        Dispatcher $events,
        private readonly CodexOAuthService $oauthService,
    ) {
        parent::__construct($events);
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
        if (! $provider instanceof Provider) {
            throw new RuntimeException('Codex gateway requires a Laravel AI provider instance.');
        }

        $body = $this->codexRequestBody(
            $provider, $model, $instructions, $messages, $tools, $schema, $options,
        );

        $response = $this->client($provider, $timeout ?? 300)->post('responses', $body);
        $data = $this->completedResponseData($response);

        $this->validateTextResponse($data);

        return $this->parseTextResponse($data, $provider, filled($schema), $tools, $schema, $options, $timeout);
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
        $response = $this->generateText($provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout);
        $messageId = (string) Str::uuid7();
        $timestamp = now()->getTimestampMs();

        yield (new StreamStart((string) Str::uuid7(), $this->providerName($provider), $model, $timestamp))->withInvocationId($invocationId);
        yield (new TextStart((string) Str::uuid7(), $messageId, $timestamp))->withInvocationId($invocationId);

        if ($response->text !== '') {
            yield (new TextDelta((string) Str::uuid7(), $messageId, $response->text, now()->getTimestampMs()))->withInvocationId($invocationId);
        }

        yield (new TextEnd((string) Str::uuid7(), $messageId, now()->getTimestampMs()))->withInvocationId($invocationId);
        yield (new StreamEnd((string) Str::uuid7(), 'stop', $response->usage ?? new Usage, now()->getTimestampMs()))->withInvocationId($invocationId);
    }

    protected function client(Provider $provider, ?int $timeout = null): PendingRequest
    {
        $token = $this->oauthService->getAccessToken();

        if (! $token) {
            throw new RuntimeException('Codex not authenticated.');
        }

        $headers = array_filter([
            'ChatGPT-Account-Id' => $provider->additionalConfiguration()['account_id'] ?? $this->oauthService->getAccountId(),
            'originator' => config('codex.originator', 'opencompany'),
            'User-Agent' => config('codex.user_agent', 'opencompany'),
        ]);

        return Http::baseUrl(rtrim($provider->additionalConfiguration()['url'] ?? config('codex.url', 'https://chatgpt.com/backend-api/codex'), '/'))
            ->withHeaders($headers)
            ->withToken($token)
            ->timeout($timeout ?? 300)
            ->throw();
    }

    /**
     * @param  array<int, mixed>  $messages
     * @param  array<int, mixed>  $tools
     * @param  array<string, mixed>|null  $schema
     * @return array<string, mixed>
     */
    private function codexRequestBody(
        Provider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
    ): array {
        $body = $this->buildTextRequestBody($provider, $model, $instructions, $messages, $tools, $schema, $options);
        $input = [];
        $system = [];

        foreach ($body['input'] ?? [] as $item) {
            if (($item['role'] ?? null) === 'system') {
                $system[] = is_array($item['content'] ?? null)
                    ? json_encode($item['content'])
                    : (string) ($item['content'] ?? '');

                continue;
            }

            $input[] = $item;
        }

        $body['input'] = $input;
        $body['instructions'] = trim(implode("\n\n", array_filter([
            $instructions,
            ...$system,
        ]))) ?: 'You are a helpful assistant.';
        $body['stream'] = true;
        $body['store'] = false;

        return $body;
    }

    private function providerName(TextProvider $provider): string
    {
        return method_exists($provider, 'name')
            ? (string) $provider->name()
            : 'codex';
    }

    /**
     * @return array<string, mixed>
     */
    private function completedResponseData(ClientResponse $response): array
    {
        $completed = null;

        foreach (explode("\n", $response->body()) as $line) {
            $line = trim($line);

            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $payload = trim(substr($line, 5));
            if ($payload === '' || $payload === '[DONE]') {
                continue;
            }

            $data = json_decode($payload, true);
            if (! is_array($data)) {
                continue;
            }

            if (($data['type'] ?? '') === 'response.completed') {
                $completed = $data['response'] ?? $data;
            }
        }

        if (is_array($completed)) {
            return $completed;
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
