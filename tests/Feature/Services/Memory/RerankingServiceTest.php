<?php

namespace Tests\Feature\Services\Memory;

use App\Models\AppSetting;
use App\Services\Memory\RerankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;
use Laravel\Ai\Responses\RerankingResponse;
use Tests\TestCase;

class RerankingServiceTest extends TestCase
{
    use RefreshDatabase;

    private RerankingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RerankingService;
    }

    public function test_disabled_returns_passthrough_in_original_order(): void
    {
        // Explicitly disable since default is now true
        AppSetting::setValue('memory_reranking_enabled', false, 'memory');
        config(['memory.reranking.enabled' => false]);

        $documents = ['first doc', 'second doc', 'third doc'];
        $results = $this->service->rerank('some query', $documents);

        $this->assertCount(3, $results);
        $this->assertEquals(0, $results[0]['index']);
        $this->assertEquals('first doc', $results[0]['document']);
        $this->assertEquals(1, $results[1]['index']);
        $this->assertEquals('second doc', $results[1]['document']);
        $this->assertEquals(2, $results[2]['index']);
        $this->assertEquals('third doc', $results[2]['document']);

        // Scores should be descending
        $this->assertGreaterThan($results[1]['score'], $results[0]['score']);
        $this->assertGreaterThan($results[2]['score'], $results[1]['score']);
    }

    public function test_empty_documents_returns_empty_array(): void
    {
        $results = $this->service->rerank('query', []);

        $this->assertEmpty($results);
    }

    public function test_enabled_calls_laravel_ai_reranking(): void
    {
        config(['memory.reranking.enabled' => true]);
        config(['memory.reranking.provider' => 'cohere']);
        config(['memory.reranking.model' => 'rerank-v3.5']);
        config(['memory.reranking.top_k' => 5]);

        Reranking::fake([
            new RerankingResponse(
                results: [
                    new RankedDocument(index: 1, document: 'second doc', score: 0.95),
                    new RankedDocument(index: 0, document: 'first doc', score: 0.72),
                ],
                meta: new \Laravel\Ai\Responses\Data\Meta(provider: 'cohere', model: 'rerank-v3.5'),
            ),
        ]);

        $documents = ['first doc', 'second doc'];
        $results = $this->service->rerank('relevant query', $documents);

        $this->assertCount(2, $results);
        $this->assertEquals(1, $results[0]['index']);
        $this->assertEquals('second doc', $results[0]['document']);
        $this->assertEquals(0.95, $results[0]['score']);
        $this->assertEquals(0, $results[1]['index']);
        $this->assertEquals('first doc', $results[1]['document']);
        $this->assertEquals(0.72, $results[1]['score']);
    }

    public function test_provider_resolution_from_app_setting(): void
    {
        AppSetting::setValue('memory_reranking_enabled', true, 'memory');
        AppSetting::setValue('memory_reranking_model', 'jina:jina-reranker-v2-base-multilingual', 'memory');

        Reranking::fake([
            new RerankingResponse(
                results: [
                    new RankedDocument(index: 0, document: 'only doc', score: 0.88),
                ],
                meta: new \Laravel\Ai\Responses\Data\Meta(provider: 'jina', model: 'jina-reranker-v2-base-multilingual'),
            ),
        ]);

        $results = $this->service->rerank('test', ['only doc']);

        $this->assertCount(1, $results);
        $this->assertEquals(0.88, $results[0]['score']);
    }

    public function test_config_fallback_when_no_app_setting(): void
    {
        config(['memory.reranking.enabled' => true]);
        config(['memory.reranking.provider' => 'cohere']);
        config(['memory.reranking.model' => 'rerank-v3.5']);

        Reranking::fake([
            new RerankingResponse(
                results: [
                    new RankedDocument(index: 0, document: 'doc', score: 0.5),
                ],
                meta: new \Laravel\Ai\Responses\Data\Meta(provider: 'cohere', model: 'rerank-v3.5'),
            ),
        ]);

        // No AppSetting set — should use config values
        $results = $this->service->rerank('query', ['doc']);

        $this->assertCount(1, $results);
        $this->assertEquals(0.5, $results[0]['score']);
    }

    public function test_app_setting_enabled_overrides_config(): void
    {
        // Config says disabled, but AppSetting says enabled
        config(['memory.reranking.enabled' => false]);
        AppSetting::setValue('memory_reranking_enabled', true, 'memory');

        config(['memory.reranking.provider' => 'cohere']);
        config(['memory.reranking.model' => 'rerank-v3.5']);

        Reranking::fake([
            new RerankingResponse(
                results: [
                    new RankedDocument(index: 0, document: 'doc', score: 0.9),
                ],
                meta: new \Laravel\Ai\Responses\Data\Meta(provider: 'cohere', model: 'rerank-v3.5'),
            ),
        ]);

        $results = $this->service->rerank('query', ['doc']);

        // Should have called the API (not pass-through) because AppSetting overrides
        $this->assertEquals(0.9, $results[0]['score']);
    }

    // --- Ollama-specific tests ---

    public function test_ollama_calls_chat_api_and_scores_binary(): void
    {
        AppSetting::setValue('memory_reranking_enabled', true, 'memory');
        AppSetting::setValue('memory_reranking_model', 'ollama:dengcao/Qwen3-Reranker-0.6B:Q8_0', 'memory');

        Http::fake([
            '*/api/tags' => Http::response(['models' => []], 200),
            '*/api/chat' => Http::sequence()
                ->push(['message' => ['content' => 'yes']], 200)
                ->push(['message' => ['content' => 'no']], 200)
                ->push(['message' => ['content' => 'yes']], 200),
        ]);

        $documents = ['relevant doc', 'irrelevant doc', 'another relevant'];
        $results = $this->service->rerank('test query', $documents);

        $this->assertCount(3, $results);

        // "yes" docs (score 1.0) should come first, "no" docs (score 0.0) last
        $this->assertEquals(1.0, $results[0]['score']);
        $this->assertEquals(0, $results[0]['index']); // first "yes" preserves original order
        $this->assertEquals(1.0, $results[1]['score']);
        $this->assertEquals(2, $results[1]['index']); // second "yes"
        $this->assertEquals(0.0, $results[2]['score']);
        $this->assertEquals(1, $results[2]['index']); // the "no"
    }

    public function test_ollama_handles_api_failure_gracefully(): void
    {
        AppSetting::setValue('memory_reranking_enabled', true, 'memory');
        AppSetting::setValue('memory_reranking_model', 'ollama:dengcao/Qwen3-Reranker-0.6B:Q8_0', 'memory');

        Http::fake([
            '*/api/tags' => Http::response(['models' => []], 200),
            '*/api/chat' => Http::response('Internal Server Error', 500),
        ]);

        $results = $this->service->rerank('query', ['doc one', 'doc two']);

        $this->assertCount(2, $results);
        // Both should get fallback score of 0.5
        $this->assertEquals(0.5, $results[0]['score']);
        $this->assertEquals(0.5, $results[1]['score']);
    }

    public function test_ollama_respects_top_k(): void
    {
        AppSetting::setValue('memory_reranking_enabled', true, 'memory');
        AppSetting::setValue('memory_reranking_model', 'ollama:dengcao/Qwen3-Reranker-0.6B:Q8_0', 'memory');

        $sequence = Http::sequence();
        for ($i = 0; $i < 10; $i++) {
            $sequence->push(['message' => ['content' => $i < 3 ? 'yes' : 'no']], 200);
        }

        Http::fake([
            '*/api/tags' => Http::response(['models' => []], 200),
            '*/api/chat' => $sequence,
        ]);

        $documents = array_map(fn ($i) => "document {$i}", range(0, 9));
        $results = $this->service->rerank('query', $documents, topK: 5);

        $this->assertCount(5, $results);
    }

    public function test_ollama_skips_when_unreachable(): void
    {
        AppSetting::setValue('memory_reranking_enabled', true, 'memory');
        AppSetting::setValue('memory_reranking_model', 'ollama:dengcao/Qwen3-Reranker-0.6B:Q8_0', 'memory');

        Http::fake([
            '*/api/tags' => Http::response('', 500),
        ]);

        $documents = ['doc one', 'doc two', 'doc three'];
        $results = $this->service->rerank('query', $documents);

        // Should return passthrough (original order, synthetic scores)
        $this->assertCount(3, $results);
        $this->assertEquals(0, $results[0]['index']);
        $this->assertEquals(1, $results[1]['index']);
        $this->assertEquals(2, $results[2]['index']);
        $this->assertGreaterThan($results[1]['score'], $results[0]['score']);
    }
}
