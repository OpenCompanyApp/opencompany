<?php

namespace App\Services\Ai;

use App\Domain\Ai\Codex\CodexOAuthService;
use App\Models\IntegrationSetting;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModelRuntimeCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public function embeddingModels(): array
    {
        $result = [];
        $integrationSettings = IntegrationSetting::forWorkspace()->default()->get()->keyBy('integration_id');

        foreach ($this->embeddingProviders() as $providerId => $provider) {
            $integration = $integrationSettings->get($providerId);
            $configured = $integration?->hasValidConfig()
                || ! empty(config("ai.providers.{$providerId}.api_key", ''))
                || ! empty(config("ai.providers.{$providerId}.key", ''));

            foreach ($provider['models'] as $modelId => $modelName) {
                $result[] = [
                    'id' => "{$providerId}:{$modelId}",
                    'provider' => $providerId,
                    'providerName' => $provider['name'],
                    'model' => $modelId,
                    'name' => $modelName,
                    'configured' => $configured,
                    'type' => 'cloud',
                ];
            }
        }

        $ollamaStatus = $this->ollamaStatus();
        $downloadedModels = $ollamaStatus['models'];

        foreach ($this->ollamaEmbeddingModels() as $modelId => $info) {
            $baseName = explode(':', $modelId)[0];

            $result[] = [
                'id' => "ollama:{$modelId}",
                'provider' => 'ollama',
                'providerName' => 'Ollama (self-hosted)',
                'model' => $modelId,
                'name' => $info['name'],
                'configured' => $ollamaStatus['online'],
                'type' => 'local',
                'downloaded' => in_array($baseName, $downloadedModels) || in_array($modelId, $downloadedModels),
                'size' => $info['size'],
                'parameters' => $info['parameters'],
                'memory' => $info['memory'],
                'dimensions' => $info['dimensions'],
                'context' => $info['context'],
                'description' => $info['description'],
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rerankingModels(): array
    {
        $result = [];
        $ollamaStatus = $this->ollamaStatus();
        $downloadedModels = $ollamaStatus['models'];

        foreach ($this->ollamaRerankingModels() as $modelId => $info) {
            $baseName = explode(':', $modelId)[0];

            $result[] = [
                'id' => "ollama:{$modelId}",
                'provider' => 'ollama',
                'providerName' => 'Ollama (self-hosted)',
                'model' => $modelId,
                'name' => $info['name'],
                'configured' => $ollamaStatus['online'],
                'type' => 'local',
                'downloaded' => in_array($baseName, $downloadedModels) || in_array($modelId, $downloadedModels),
                'size' => $info['size'],
                'parameters' => $info['parameters'],
                'memory' => $info['memory'],
                'description' => $info['description'],
            ];
        }

        foreach ($this->cloudRerankingProviders() as $providerId => $provider) {
            $configured = ! empty(config("ai.providers.{$providerId}.key", ''));

            foreach ($provider['models'] as $modelId => $modelName) {
                $result[] = [
                    'id' => "{$providerId}:{$modelId}",
                    'provider' => $providerId,
                    'providerName' => $provider['name'],
                    'model' => $modelId,
                    'name' => $modelName,
                    'configured' => $configured,
                    'type' => 'cloud',
                ];
            }
        }

        $listedProviders = array_merge(['ollama'], array_keys($this->cloudRerankingProviders()));
        $available = IntegrationSetting::getAvailableIntegrations();
        $integrationSettings = IntegrationSetting::forWorkspace()->default()->get()->keyBy('integration_id');

        foreach ($available as $id => $info) {
            if (! isset($info['api_format']) || in_array($id, $listedProviders, true)) {
                continue;
            }

            $integration = $integrationSettings->get($id);
            $configured = $integration?->hasValidConfig()
                || ! empty(config("ai.providers.{$id}.api_key", ''))
                || ! empty(config("ai.providers.{$id}.key", ''));

            $models = $info['models'] ?? [];
            if (! is_array($models) || $models === []) {
                continue;
            }

            foreach ($models as $modelId => $modelName) {
                $result[] = [
                    'id' => "{$id}:{$modelId}",
                    'provider' => $id,
                    'providerName' => ($info['name'] ?? $id).' (LLM)',
                    'model' => $modelId,
                    'name' => $modelName,
                    'configured' => (bool) $configured,
                    'type' => 'llm',
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{online: bool, models: array<int, mixed>, url: mixed}
     */
    public function ollamaStatus(): array
    {
        $url = config('ai.providers.ollama.url', 'http://localhost:11434');

        try {
            $response = Http::timeout(3)->get($url.'/api/tags');

            if (! $response->successful()) {
                return ['online' => false, 'models' => [], 'url' => $url];
            }

            $models = collect($response->json('models', []))
                ->pluck('name')
                ->map(fn (string $name) => explode(':', $name)[0])
                ->unique()
                ->values()
                ->toArray();

            return ['online' => true, 'models' => $models, 'url' => $url];
        } catch (\Throwable) {
            return ['online' => false, 'models' => [], 'url' => $url];
        }
    }

    public function pullOllamaModel(string $model): StreamedResponse
    {
        $url = config('ai.providers.ollama.url', 'http://localhost:11434');

        return response()->stream(function () use ($model, $url) {
            try {
                $response = (new Client)->post($url.'/api/pull', [
                    'json' => ['model' => $model, 'stream' => true],
                    'stream' => true,
                    'timeout' => 600,
                    'connect_timeout' => 10,
                ]);

                $body = $response->getBody();
                $buffer = '';

                while (! $body->eof()) {
                    $buffer .= $body->read(8192);

                    while (($newlinePos = strpos($buffer, "\n")) !== false) {
                        $line = trim(substr($buffer, 0, $newlinePos));
                        $buffer = substr($buffer, $newlinePos + 1);

                        if ($line === '') {
                            continue;
                        }

                        $data = json_decode($line, true);
                        if (! is_array($data)) {
                            continue;
                        }

                        $event = ['status' => $data['status'] ?? ''];
                        if (isset($data['completed'], $data['total']) && $data['total'] > 0) {
                            $event['completed'] = $data['completed'];
                            $event['total'] = $data['total'];
                            $event['percent'] = min(100, (int) round($data['completed'] / $data['total'] * 100));
                        }

                        if (($data['status'] ?? '') === 'success') {
                            $event['done'] = true;
                            $event['success'] = true;
                        }

                        echo 'data: '.json_encode($event)."\n\n";
                        ob_flush();
                        flush();
                    }
                }

                echo 'data: '.json_encode(['done' => true, 'success' => true])."\n\n";
                ob_flush();
                flush();
            } catch (\Throwable $e) {
                echo 'data: '.json_encode(['done' => true, 'success' => false, 'error' => $e->getMessage()])."\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function fetchProviderModels(string $id): array
    {
        if ($id === 'codex') {
            return $this->fetchCodexModels();
        }

        $available = config('integrations', []);
        $format = $available[$id]['api_format'] ?? null;

        return match ($format) {
            'anthropic' => $this->fetchAnthropicModels($id),
            'gemini' => $this->fetchGeminiModels($id),
            'ollama' => $this->fetchOllamaModels($id),
            'openai', 'openai_compat' => $this->fetchOpenAiCompatModels($id),
            default => throw new \InvalidArgumentException("Model fetching not supported for: {$id}"),
        };
    }

    /**
     * @return array<string, string>
     */
    private function fetchOpenAiCompatModels(string $id): array
    {
        [$apiKey, $baseUrl] = $this->providerCredentials($id);

        if (! $apiKey) {
            throw new \RuntimeException('API key not configured. Save your API key first.');
        }

        $response = Http::withHeaders(['Authorization' => 'Bearer '.$apiKey])
            ->timeout(15)
            ->get($baseUrl.'/models');

        if (! $response->successful()) {
            throw new \RuntimeException('API returned '.$response->status().': '.$response->body());
        }

        $models = [];
        foreach ($response->json('data', []) as $model) {
            $modelId = $model['id'] ?? null;
            if ($modelId) {
                $models[$modelId] = $this->formatModelName($modelId);
            }
        }

        if ($id === 'z' || $id === 'z-api') {
            $models = $this->probeGlmVariants($models, $apiKey, $baseUrl);
        }

        ksort($models);

        return $models;
    }

    /**
     * @return array<string, string>
     */
    private function fetchAnthropicModels(string $id): array
    {
        [$apiKey, $baseUrl] = $this->providerCredentials($id);

        if (! $apiKey) {
            throw new \RuntimeException('API key not configured. Save your API key first.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(15)->get($baseUrl.'/models');

        if (! $response->successful()) {
            throw new \RuntimeException('API returned '.$response->status().': '.$response->body());
        }

        $models = [];
        foreach ($response->json('data', []) as $model) {
            $modelId = $model['id'] ?? null;
            if ($modelId) {
                $models[$modelId] = $model['display_name'] ?? $this->formatModelName($modelId);
            }
        }

        ksort($models);

        return $models;
    }

    /**
     * @return array<string, string>
     */
    private function fetchGeminiModels(string $id): array
    {
        [$apiKey, $baseUrl] = $this->providerCredentials($id);

        if (! $apiKey) {
            throw new \RuntimeException('API key not configured. Save your API key first.');
        }

        $response = Http::timeout(15)->get($baseUrl.'/models', ['key' => $apiKey]);

        if (! $response->successful()) {
            throw new \RuntimeException('API returned '.$response->status().': '.$response->body());
        }

        $models = [];
        foreach ($response->json('models', []) as $model) {
            $name = $model['name'] ?? null;
            if (! $name || ! in_array('generateContent', $model['supportedGenerationMethods'] ?? [], true)) {
                continue;
            }

            $modelId = str_replace('models/', '', $name);
            $models[$modelId] = $model['displayName'] ?? $this->formatModelName($modelId);
        }

        ksort($models);

        return $models;
    }

    /**
     * @return array<string, string>
     */
    private function fetchOllamaModels(string $id): array
    {
        $setting = $this->integrationSetting($id);
        $available = config('integrations', []);
        $baseUrl = $setting?->getConfigValue('url') ?: ($available[$id]['default_url'] ?? 'http://localhost:11434/v1');

        $response = Http::timeout(10)->get($baseUrl.'/models');

        if (! $response->successful()) {
            throw new \RuntimeException('Could not connect to Ollama at '.$baseUrl);
        }

        $models = [];
        foreach ($response->json('data', $response->json('models', [])) as $model) {
            $modelId = $model['id'] ?? $model['name'] ?? null;
            if ($modelId) {
                $models[$modelId] = $this->formatModelName($modelId);
            }
        }

        ksort($models);

        return $models;
    }

    /**
     * @return array<string, string>
     */
    private function fetchCodexModels(): array
    {
        $oauthService = app(CodexOAuthService::class);
        $token = $oauthService->getAccessToken();

        if (! $token) {
            throw new \RuntimeException('Not authenticated. Please connect your Codex account first.');
        }

        $response = Http::withToken($token)
            ->withHeaders(array_filter([
                'ChatGPT-Account-Id' => $oauthService->getAccountId(),
            ]))
            ->timeout(15)
            ->get(config('codex.url', 'https://chatgpt.com/backend-api/codex').'/models');

        if (! $response->successful()) {
            throw new \RuntimeException('API returned '.$response->status().': '.$response->body());
        }

        $models = [];
        foreach ($response->json('models', $response->json('data', [])) as $model) {
            $modelId = is_string($model) ? $model : ($model['id'] ?? $model['slug'] ?? null);
            if ($modelId) {
                $models[$modelId] = ucwords(strtolower(strtoupper(str_replace(['-', '_'], [' ', ' '], $modelId))));
            }
        }

        return $models;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function providerCredentials(string $id): array
    {
        $setting = $this->integrationSetting($id);
        $available = config('integrations', []);

        return [
            $setting?->getConfigValue('api_key')
                ?: config("ai.providers.{$id}.api_key", '')
                ?: config("ai.providers.{$id}.key", '')
                ?: null,
            $setting?->getConfigValue('url')
                ?: config("ai.providers.{$id}.url")
                ?: ($available[$id]['default_url'] ?? ''),
        ];
    }

    private function integrationSetting(string $id): ?IntegrationSetting
    {
        return IntegrationSetting::forWorkspace()
            ->where('integration_id', $id)
            ->default()
            ->first();
    }

    /**
     * @param  array<string, string>  $models
     * @return array<string, string>
     */
    private function probeGlmVariants(array $models, string $apiKey, string $baseUrl): array
    {
        $variants = [];
        foreach (array_keys($models) as $modelId) {
            $variants[] = $modelId.'-flash';
            $variants[] = $modelId.'-plus';
        }

        foreach ($variants as $variant) {
            if (isset($models[$variant])) {
                continue;
            }

            try {
                $probe = Http::withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ])->timeout(8)->post($baseUrl.'/chat/completions', [
                    'model' => $variant,
                    'messages' => [['role' => 'user', 'content' => 'hi']],
                    'max_tokens' => 1,
                ]);

                if ($probe->status() === 200 || $probe->status() === 429) {
                    $models[$variant] = $this->formatModelName($variant);
                }
            } catch (\Throwable) {
                //
            }
        }

        return $models;
    }

    private function formatModelName(string $modelId): string
    {
        $name = preg_replace('/-\d{8}$/', '', $modelId) ?: $modelId;
        $name = str_replace(['-', '_', '/'], [' ', ' ', ' / '], $name);

        return ucwords($name);
    }

    /**
     * @return array<string, array{name: string, models: array<string, string>}>
     */
    private function embeddingProviders(): array
    {
        return [
            'openai' => [
                'name' => 'OpenAI',
                'models' => [
                    'text-embedding-3-small' => 'text-embedding-3-small (1536d)',
                    'text-embedding-3-large' => 'text-embedding-3-large (3072d)',
                    'text-embedding-ada-002' => 'text-embedding-ada-002 (1536d)',
                ],
            ],
            'voyageai' => [
                'name' => 'VoyageAI',
                'models' => [
                    'voyage-3' => 'voyage-3',
                    'voyage-3-lite' => 'voyage-3-lite',
                    'voyage-code-3' => 'voyage-code-3',
                ],
            ],
            'gemini' => [
                'name' => 'Gemini',
                'models' => [
                    'text-embedding-004' => 'text-embedding-004 (768d)',
                ],
            ],
            'openrouter' => [
                'name' => 'OpenRouter',
                'models' => [
                    'openai/text-embedding-3-small' => 'text-embedding-3-small (1536d)',
                    'openai/text-embedding-3-large' => 'text-embedding-3-large (3072d)',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function ollamaEmbeddingModels(): array
    {
        return [
            'snowflake-arctic-embed2' => [
                'name' => 'Snowflake Arctic Embed 2',
                'size' => '568M',
                'parameters' => '568M',
                'memory' => '~1.2 GB',
                'dimensions' => 1024,
                'context' => 8192,
                'description' => 'State-of-the-art multilingual embedding model with 8K context. Best quality for retrieval across multiple languages.',
            ],
            'nomic-embed-text' => [
                'name' => 'Nomic Embed Text v1.5',
                'size' => '137M',
                'parameters' => '137M',
                'memory' => '~0.5 GB',
                'dimensions' => 768,
                'context' => 8192,
                'description' => 'Efficient general-purpose model with Matryoshka dimension support (64-768d). Good balance of quality and speed.',
            ],
            'snowflake-arctic-embed:s' => [
                'name' => 'Snowflake Arctic Embed S',
                'size' => '33M',
                'parameters' => '33M',
                'memory' => '~0.2 GB',
                'dimensions' => 384,
                'context' => 512,
                'description' => 'Ultra-lightweight model for large datasets. Smallest footprint with competitive accuracy. Best for constrained environments.',
            ],
            'mxbai-embed-large' => [
                'name' => 'MxBAI Embed Large',
                'size' => '335M',
                'parameters' => '335M',
                'memory' => '~1.2 GB',
                'dimensions' => 1024,
                'context' => 512,
                'description' => 'High-quality model from Mixedbread AI. Strong benchmark performance, outperforms OpenAI ada-002.',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function ollamaRerankingModels(): array
    {
        return [
            'dengcao/Qwen3-Reranker-0.6B:Q8_0' => [
                'name' => 'Qwen3 Reranker 0.6B',
                'size' => '639 MB',
                'parameters' => '0.6B',
                'memory' => '~1 GB',
                'description' => 'Lightweight reranker based on Qwen3 (Q8_0 quantization). Fast inference on CPU, good accuracy for most use cases.',
            ],
            'dengcao/Qwen3-Reranker-4B:Q4_K_M' => [
                'name' => 'Qwen3 Reranker 4B',
                'size' => '2.5 GB',
                'parameters' => '4B',
                'memory' => '~4 GB',
                'description' => 'Mid-size reranker with stronger relevance judgments (Q4_K_M quantization). Good balance of quality and speed.',
            ],
            'dengcao/Qwen3-Reranker-8B:Q4_K_M' => [
                'name' => 'Qwen3 Reranker 8B',
                'size' => '5.0 GB',
                'parameters' => '8B',
                'memory' => '~7 GB',
                'description' => 'Largest Qwen3 Reranker variant (Q4_K_M quantization). State-of-the-art accuracy, best for high-stakes retrieval.',
            ],
        ];
    }

    /**
     * @return array<string, array{name: string, models: array<string, string>}>
     */
    private function cloudRerankingProviders(): array
    {
        return [
            'cohere' => [
                'name' => 'Cohere',
                'models' => [
                    'rerank-v3.5' => 'Rerank v3.5',
                    'rerank-english-v3.0' => 'Rerank English v3.0',
                    'rerank-multilingual-v3.0' => 'Rerank Multilingual v3.0',
                ],
            ],
            'jina' => [
                'name' => 'Jina AI',
                'models' => [
                    'jina-reranker-v2-base-multilingual' => 'Jina Reranker v2 Multilingual',
                ],
            ],
        ];
    }
}
