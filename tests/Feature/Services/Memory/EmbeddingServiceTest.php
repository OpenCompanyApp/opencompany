<?php

namespace Tests\Feature\Services\Memory;

use App\Domain\Ai\Embeddings\EmbeddingClient;
use App\Models\AppSetting;
use App\Models\EmbeddingCache;
use App\Services\Memory\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmbeddingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EmbeddingService::class);
    }

    public function test_embed_calls_embedding_client_and_returns_vector(): void
    {
        $fakeEmbedding = array_fill(0, 1536, 0.1);

        config(['memory.embedding.testing_fallback' => false]);
        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);

        $client = Mockery::mock(EmbeddingClient::class);
        $client->shouldReceive('embed')
            ->once()
            ->with('openai', 'text-embedding-3-small', 'Hello world', $this->workspace->id)
            ->andReturn($fakeEmbedding);
        $this->app->instance(EmbeddingClient::class, $client);
        $this->service = app(EmbeddingService::class);

        $result = $this->service->embed('Hello world');

        $this->assertCount(1536, $result);
        $this->assertEquals(0.1, $result[0]);

        // Should be cached now
        $cacheKey = EmbeddingCache::cacheKey('openai', 'text-embedding-3-small', 'Hello world');
        $this->assertNotNull(EmbeddingCache::find($cacheKey));
    }

    public function test_cache_hit_returns_without_api_call(): void
    {
        $fakeEmbedding = array_fill(0, 1536, 0.5);

        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);

        // Pre-populate cache
        EmbeddingCache::create([
            'id' => EmbeddingCache::cacheKey('openai', 'text-embedding-3-small', 'cached text'),
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'embedding' => $fakeEmbedding,
            'workspace_id' => $this->workspace->id,
        ]);

        $client = Mockery::mock(EmbeddingClient::class);
        $client->shouldNotReceive('embed');
        $this->app->instance(EmbeddingClient::class, $client);
        $this->service = app(EmbeddingService::class);

        $result = $this->service->embed('cached text');

        $this->assertCount(1536, $result);
        $this->assertEquals(0.5, $result[0]);
    }

    public function test_batch_embedding_checks_cache_per_text(): void
    {
        $cachedEmbedding = array_fill(0, 1536, 0.3);
        $freshEmbedding = array_fill(0, 1536, 0.7);

        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);

        // Cache one of the texts
        EmbeddingCache::create([
            'id' => EmbeddingCache::cacheKey('openai', 'text-embedding-3-small', 'already cached'),
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'embedding' => $cachedEmbedding,
            'workspace_id' => $this->workspace->id,
        ]);

        config(['memory.embedding.testing_fallback' => false]);

        $client = Mockery::mock(EmbeddingClient::class);
        $client->shouldReceive('embedMany')
            ->once()
            ->with('openai', 'text-embedding-3-small', ['new text'], $this->workspace->id)
            ->andReturn([$freshEmbedding]);
        $this->app->instance(EmbeddingClient::class, $client);
        $this->service = app(EmbeddingService::class);

        $results = $this->service->embedBatch(['already cached', 'new text']);

        $this->assertCount(2, $results);
        $this->assertEquals(0.3, $results[0][0]); // cached
        $this->assertEquals(0.7, $results[1][0]); // fresh from API
    }

    public function test_provider_resolution_reads_app_setting_first(): void
    {
        // Set via AppSetting (should take precedence over config)
        AppSetting::setValue('memory_embedding_model', 'ollama:snowflake-arctic-embed2', 'memory');

        $result = $this->service->embed('test text');

        $this->assertCount(1536, $result);

        // Verify it cached with ollama provider
        $cacheKey = EmbeddingCache::cacheKey('ollama', 'snowflake-arctic-embed2', 'test text');
        $cached = EmbeddingCache::find($cacheKey);
        $this->assertNotNull($cached);
        $this->assertEquals('ollama', $cached->provider);
        $this->assertEquals('snowflake-arctic-embed2', $cached->model);
    }

    public function test_empty_batch_returns_empty_array(): void
    {
        $result = $this->service->embedBatch([]);

        $this->assertEmpty($result);
    }

    public function test_embed_propagates_exception_on_api_failure(): void
    {
        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);
        config(['memory.embedding.testing_fallback' => false]);

        $client = Mockery::mock(EmbeddingClient::class);
        $client->shouldReceive('embed')
            ->once()
            ->andThrow(new \RuntimeException('Embedding failed'));
        $this->app->instance(EmbeddingClient::class, $client);
        $this->service = app(EmbeddingService::class);

        $this->expectException(\RuntimeException::class);

        $this->service->embed('should fail');
    }
}
