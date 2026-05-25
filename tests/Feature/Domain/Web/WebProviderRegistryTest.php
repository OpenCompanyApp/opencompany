<?php

namespace Tests\Feature\Domain\Web;

use App\Domain\Web\Contracts\WebFetchProvider;
use App\Domain\Web\Contracts\WebSearchProvider;
use App\Domain\Web\Registry\WebProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebProviderRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_lists_and_resolves_search_and_fetch_providers(): void
    {
        config([
            'web.providers.tavily.api_key' => 'test-key',
            'web.providers.zai.api_key' => 'test-key',
        ]);

        $registry = app(WebProviderRegistry::class);

        $this->assertContains('tavily', $registry->names());
        $this->assertInstanceOf(WebSearchProvider::class, $registry->searchProvider('tavily'));
        $this->assertInstanceOf(WebFetchProvider::class, $registry->fetchProvider('direct'));
        $this->assertContains('tavily', $registry->availableSearchProviderIds());
        $this->assertContains('direct', $registry->availableFetchProviderIds());
    }

    public function test_registry_statuses_include_configuration_metadata(): void
    {
        config(['web.providers.tavily.api_key' => 'test-key']);

        $status = collect(app(WebProviderRegistry::class)->statuses())->firstWhere('id', 'tavily');

        $this->assertSame('Tavily', $status['label']);
        $this->assertTrue($status['enabled']);
        $this->assertTrue($status['configured']);
        $this->assertContains('search', $status['capabilities']);
    }
}
