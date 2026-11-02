<?php

namespace Tests\Evals\Vfs\Support;

use App\Domain\Ai\Catalog\AiCatalog;
use Illuminate\Support\Facades\Http;

/**
 * Minimal OpenAI-compatible chat client for the VFS evals (GLM on Z.AI).
 *
 * Owns: one non-streaming `POST {base}/chat/completions` per call, the
 * per-run request budget, and token accounting. It does NOT own tool
 * execution (EvalAgentLoop) or tool schemas (EvalToolbox maps the real tools
 * with the same gateway mapping production uses).
 *
 * Secret handling (invariant): the API key is read from the environment
 * (`ZAI_API_KEY`, else the legacy `GLM_API_KEY` from `.env`) and only ever
 * placed in the Authorization header. It is never returned, logged, written
 * to transcripts or included in exception messages; HTTP errors are reported
 * by status code and a body excerpt from which the key cannot appear.
 *
 * Network boundary (invariant): the constructor turns on
 * `Http::preventStrayRequests()` and allows only the model host, so a tool,
 * fixture or listener that tries to reach anything else fails loudly instead
 * of silently calling out.
 *
 * Why plain HTTP instead of the app provider layer: the eval drives the loop
 * itself so it can interleave scripted writers (mixed tasks), count requests
 * against the quota and stop at a turn cap. The model and URL still come from
 * the app's provider config (`config('ai.providers.z')`).
 */
final class GlmChatClient
{
    private string $baseUrl;

    private string $model;

    public function __construct(
        private readonly string $apiKey,
        private readonly RequestBudget $budget,
        ?string $model = null,
        ?string $baseUrl = null,
        private readonly float $temperature = 0.2,
    ) {
        // Provider/model come from the app catalog and config, not literals:
        // EVAL_PROVIDER (default the agents' default provider) and EVAL_MODEL
        // (default that provider's catalog default model).
        $provider = (string) (env('EVAL_PROVIDER') ?: config('ai.default_for_agents'));
        $this->baseUrl = rtrim($baseUrl ?? (string) config("ai.providers.{$provider}.url"), '/');
        $this->model = $model ?? app(AiCatalog::class)->defaultModel($provider);

        $host = parse_url($this->baseUrl, PHP_URL_SCHEME).'://'.parse_url($this->baseUrl, PHP_URL_HOST);
        Http::preventStrayRequests();
        Http::allowStrayRequests([$host.'/*']);
    }

    /**
     * Resolve the key from the environment without exposing it.
     */
    public static function keyFromEnv(): ?string
    {
        foreach (['ZAI_API_KEY', 'GLM_API_KEY'] as $name) {
            $value = env($name);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * One chat completion.
     *
     * @param  list<array<string, mixed>>  $messages  OpenAI chat messages
     * @param  list<array<string, mixed>>  $tools  OpenAI function tools
     * @return array{message: array<string, mixed>, usage: array{prompt_tokens: int, completion_tokens: int}, finish_reason: ?string}
     *
     * @throws EvalBudgetExhausted when the run's request budget is used up
     * @throws \RuntimeException on a non-2xx response after one retry
     */
    public function chat(array $messages, array $tools): array
    {
        $body = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => 4096,
            // GLM-5.1 reasons by default; the pilot ran with thinking off so
            // tool-use behavior, not hidden reasoning, is what we measure.
            'thinking' => ['type' => 'disabled'],
            'stream' => false,
        ];
        if ($tools !== []) {
            $body['tools'] = $tools;
            $body['tool_choice'] = 'auto';
        }

        $attempt = 0;
        while (true) {
            $attempt++;
            $this->budget->consume();
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout(240)
                ->post($this->baseUrl.'/chat/completions', $body);

            if ($response->successful()) {
                break;
            }
            // One retry for rate limits and transient upstream errors; each
            // attempt counts against the budget.
            if ($attempt < 2 && in_array($response->status(), [429, 500, 502, 503, 504], true)) {
                sleep($response->status() === 429 ? 20 : 5);

                continue;
            }

            throw new \RuntimeException(sprintf('GLM HTTP %d: %s', $response->status(), mb_substr((string) $response->body(), 0, 300)));
        }

        $json = $response->json();
        $choice = $json['choices'][0] ?? [];

        return [
            'message' => is_array($choice['message'] ?? null) ? $choice['message'] : ['role' => 'assistant', 'content' => ''],
            'usage' => [
                'prompt_tokens' => (int) ($json['usage']['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($json['usage']['completion_tokens'] ?? 0),
            ],
            'finish_reason' => $choice['finish_reason'] ?? null,
        ];
    }
}
