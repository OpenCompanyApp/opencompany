<?php

namespace App\Services\Memory;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;

class RerankingService
{
    /**
     * Rerank documents by relevance to a query.
     *
     * When reranking is disabled, returns documents in original order
     * with synthetic descending scores so callers never need conditional logic.
     *
     * @param  string  $query
     * @param  array<int, string>  $documents
     * @param  int|null  $topK
     * @return array<int, array{index: int, document: string, score: float}>
     */
    public function rerank(string $query, array $documents, ?int $topK = null): array
    {
        if (empty($documents)) {
            return [];
        }

        $enabled = AppSetting::getValue('memory_reranking_enabled')
            ?? config('memory.reranking.enabled', true);

        if (! $enabled) {
            return $this->passThrough($documents);
        }

        $topK ??= config('memory.reranking.top_k', 10);

        [$provider, $model] = $this->resolveProviderModel();

        if ($provider === 'ollama') {
            return $this->rerankWithOllama($query, $documents, $model, $topK);
        }

        $response = Reranking::of($documents)
            ->limit($topK)
            ->rerank($query, $provider, $model);

        return collect($response->results)->map(fn (RankedDocument $doc) => [
            'index' => $doc->index,
            'document' => $doc->document,
            'score' => $doc->score,
        ])->all();
    }

    /**
     * Rerank using Ollama's chat API with Qwen3-Reranker prompt format.
     *
     * Qwen3-Reranker outputs "yes"/"no" relevance judgments via a specialized
     * system prompt. Scoring is binary: yes=1.0, no=0.0, error=0.5.
     *
     * @return array<int, array{index: int, document: string, score: float}>
     */
    private function rerankWithOllama(string $query, array $documents, string $model, int $topK): array
    {
        $url = config('prism.providers.ollama.url', 'http://localhost:11434');

        // Quick connectivity check to avoid N slow timeouts
        try {
            $check = Http::timeout(2)->get($url.'/api/tags');
            if (! $check->successful()) {
                return $this->passThrough($documents);
            }
        } catch (\Throwable) {
            return $this->passThrough($documents);
        }

        $systemPrompt = 'Judge whether the Document meets the requirements based on the Query and the Instruct provided. Note that the answer can only be "yes" or "no".';

        $scored = [];

        foreach ($documents as $i => $doc) {
            $userMessage = "<Instruct>: Given a web search query, retrieve relevant passages that answer the query\n<Query>: {$query}\n<Document>: {$doc}";

            try {
                $response = Http::timeout(30)->post($url.'/api/chat', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                    'stream' => false,
                    'options' => [
                        'temperature' => 0,
                        'num_predict' => 2,
                    ],
                ]);

                $answer = strtolower(trim($response->json('message.content', '')));
                $score = str_starts_with($answer, 'yes') ? 1.0 : (str_starts_with($answer, 'no') ? 0.0 : 0.5);
            } catch (\Throwable) {
                $score = 0.5;
            }

            $scored[] = [
                'index' => $i,
                'document' => $doc,
                'score' => $score,
            ];
        }

        // Sort by score descending, preserving original order for ties
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score'] ?: $a['index'] <=> $b['index']);

        return array_slice($scored, 0, $topK);
    }

    /**
     * Pass-through: return documents in original order with synthetic scores.
     *
     * @return array<int, array{index: int, document: string, score: float}>
     */
    private function passThrough(array $documents): array
    {
        return array_map(fn (string $doc, int $i) => [
            'index' => $i,
            'document' => $doc,
            'score' => 1.0 - ($i * 0.01),
        ], $documents, array_keys($documents));
    }

    /**
     * Resolve the reranking provider and model from AppSetting or config fallback.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveProviderModel(): array
    {
        return AppSetting::resolveProviderModel(
            'memory_reranking_model', 'memory.reranking.provider', 'memory.reranking.model'
        );
    }
}
