<?php

namespace Tests\Unit\Domain\Web;

use App\Domain\Web\Cache\WebResultCache;
use App\Domain\Web\ValueObjects\WebSearchResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebResultCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_keys_are_workspace_scoped(): void
    {
        $cache = app(WebResultCache::class);
        $response = new WebSearchResponse('tavily', 'same query', []);

        $cache->put('search', 'tavily', ['query' => 'same query'], 'workspace-a', $response);

        $this->assertInstanceOf(WebSearchResponse::class, $cache->get('search', 'tavily', ['query' => 'same query'], 'workspace-a'));
        $this->assertNull($cache->get('search', 'tavily', ['query' => 'same query'], 'workspace-b'));
    }
}
