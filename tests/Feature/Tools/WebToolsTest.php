<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\ToolRegistry;
use App\Agents\Tools\Web\WebFetchTool;
use App\Agents\Tools\Web\WebSearchTool;
use App\Domain\Web\Safety\WebRequestGuard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class WebToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tool_registry_exposes_web_tools_directly(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $registry = app(ToolRegistry::class);

        $this->assertContains('web_search', $registry->getToolSlugsForAgent($agent));
        $this->assertContains('web_fetch', $registry->getToolSlugsForAgent($agent));
        $this->assertInstanceOf(WebSearchTool::class, $registry->instantiateToolBySlug('web_search', $agent));
        $this->assertInstanceOf(WebFetchTool::class, $registry->instantiateToolBySlug('web_fetch', $agent));
    }

    public function test_web_search_tool_returns_formatted_and_structured_data(): void
    {
        config([
            'web.search.default_provider' => 'tavily',
            'web.providers.tavily.api_key' => 'test-key',
        ]);

        Http::fake([
            'https://api.tavily.com/search' => Http::response([
                'answer' => 'Laravel queues process jobs.',
                'results' => [[
                    'title' => 'Queues - Laravel',
                    'url' => 'https://laravel.com/docs/12.x/queues',
                    'content' => 'Queue batching documentation.',
                    'score' => 0.9,
                ]],
            ]),
        ]);

        $tool = app()->make(WebSearchTool::class, ['agent' => User::factory()->create(['type' => 'agent'])]);
        $output = $tool->handle(new Request(['query' => 'Laravel queues', 'include_answer' => true]));

        $this->assertStringContainsString('Provider: tavily', $output);
        $this->assertStringContainsString('Queues - Laravel', $output);
        $this->assertStringContainsString('Structured data:', $output);
    }

    public function test_web_fetch_tool_supports_outline_section_and_chunk_modes(): void
    {
        $this->app->bind(WebRequestGuard::class, fn () => new class extends WebRequestGuard {
            protected function resolveIpAddresses(string $host): array
            {
                return ['93.184.216.34'];
            }
        });

        Http::fake([
            'https://example.com/guide' => Http::response('<html><head><title>Guide</title></head><body><main><h1>Intro</h1><p>First section.</p><h2>Deep Dive</h2><p>'.str_repeat('Important details. ', 80).'</p></main></body></html>', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $tool = app()->make(WebFetchTool::class, ['agent' => User::factory()->create(['type' => 'agent'])]);

        $outline = $tool->handle(new Request(['url' => 'https://example.com/guide', 'mode' => 'outline']));
        $this->assertStringContainsString('Deep Dive [id: deep-dive]', $outline);

        $section = $tool->handle(new Request(['url' => 'https://example.com/guide', 'mode' => 'section', 'section_id' => 'deep-dive', 'max_chars' => 120]));
        $this->assertStringContainsString('Important details.', $section);
        $this->assertStringContainsString('Next chunk token:', $section);
    }
}
