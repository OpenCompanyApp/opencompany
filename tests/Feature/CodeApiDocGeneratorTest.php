<?php

namespace Tests\Feature;

use App\Agents\Tools\ToolRegistry;
use App\Models\AgentPermission;
use App\Models\User;
use App\Services\CodeApiDocGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CodeApiDocGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private CodeApiDocGenerator $generator;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(CodeApiDocGenerator::class);
        $this->agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);
    }

    // ── buildFunctionMap ─────────────────────────────────────────

    public function test_build_function_map_returns_non_empty_map(): void
    {
        $map = $this->generator->buildFunctionMap($this->agent);

        $this->assertNotEmpty($map);

        foreach ($map as $path => $slug) {
            $this->assertIsString($path);
            $this->assertIsString($slug);
            $this->assertStringContainsString('.', $path, "Path should be namespace.function: {$path}");
        }
    }

    public function test_build_function_map_maps_known_tools(): void
    {
        $map = $this->generator->buildFunctionMap($this->agent);

        $this->assertArrayHasKey('calendar.list_events', $map);
        $this->assertEquals('list_calendar_events', $map['calendar.list_events']);

        $this->assertArrayHasKey('calendar.create_event', $map);
        $this->assertEquals('create_calendar_event', $map['calendar.create_event']);

        $this->assertArrayHasKey('chat.send_channel_message', $map);
        $this->assertEquals('send_channel_message', $map['chat.send_channel_message']);

        $this->assertArrayHasKey('web.search', $map);
        $this->assertEquals('web_search', $map['web.search']);

        $this->assertArrayHasKey('web.fetch', $map);
        $this->assertEquals('web_fetch', $map['web.fetch']);
    }

    public function test_build_function_map_excludes_system_namespaces(): void
    {
        $map = $this->generator->buildFunctionMap($this->agent);

        foreach (array_keys($map) as $path) {
            $this->assertFalse(str_starts_with($path, 'code.'), "Should not contain code namespace: {$path}");
            $this->assertFalse(str_starts_with($path, 'tasks.'), "Should not contain tasks namespace: {$path}");
            $this->assertFalse(str_starts_with($path, 'system.'), "Should not contain system namespace: {$path}");
        }
    }

    public function test_agent_discovery_excludes_disabled_integrations_while_developer_catalog_keeps_them(): void
    {
        // The developer inventory remains a configuration surface. Agent Code
        // Mode must instead use only the enabled integration capability set.
        $scriptNamespaces = $this->generator->buildFunctionMap($this->agent);
        $developerNamespaces = $this->generator->getNamespacesForCatalog($this->agent);

        $this->assertArrayHasKey('integrations.coingecko', $developerNamespaces);
        $this->assertSame([], array_filter(
            array_keys($scriptNamespaces),
            static fn (string $path): bool => str_starts_with($path, 'integrations.coingecko.'),
        ));
        $this->assertLessThan(5_000, count($scriptNamespaces));
    }

    public function test_permission_change_rebuilds_cached_agent_capabilities(): void
    {
        $before = $this->generator->buildFunctionMap($this->agent);
        $this->assertArrayHasKey('web.search', $before);

        // Regression: the old cache key was only the actor id, so a newly
        // denied tool stayed visible until the PHP process was restarted.
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'web_search',
            'permission' => 'deny',
            'requires_approval' => false,
        ]);

        $after = $this->generator->buildFunctionMap($this->agent);

        $this->assertArrayNotHasKey('web.search', $after);
    }

    public function test_approval_required_tool_remains_discoverable_with_its_approval_contract(): void
    {
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'web_search',
            'permission' => 'allow',
            'requires_approval' => true,
        ]);

        $catalog = app(ToolRegistry::class)->getScriptToolCatalog($this->agent);
        $web = collect($catalog)->firstWhere('name', 'web');
        $tool = collect($web['tools'] ?? [])->firstWhere('slug', 'web_search');

        // Approval is not a discovery denial; runtime still owns approval at
        // dispatch, while the generated documentation reflects visibility.
        $this->assertIsArray($tool);
        $this->assertTrue($tool['requiresApproval']);
        $this->assertArrayHasKey('web.search', $this->generator->buildFunctionMap($this->agent));
    }

    // ── deriveFunctionName (via reflection) ──────────────────────

    public function test_derive_function_name_strips_app_words(): void
    {
        $this->assertDerivesName('query', 'Query Documents', 'docs');
        $this->assertDerivesName('query', 'Query Calendar', 'calendar');
        // Special characters like & should be sanitized
        $this->assertDerivesName('list_events', 'List Calendars & Events', 'google_calendar');
    }

    public function test_derive_function_name_strips_prepositions(): void
    {
        $this->assertDerivesName('comment', 'Comment on Document', 'docs');
    }

    public function test_derive_function_name_preserves_unrelated_words(): void
    {
        $this->assertDerivesName('manage_rows', 'Manage Table Rows', 'tables');
        $this->assertDerivesName('send_channel_message', 'Send Channel Message', 'chat');
    }

    public function test_derive_function_name_falls_back_on_full_strip(): void
    {
        // If stripping removes all words, return original snake_case
        $this->assertDerivesName('docs', 'Docs', 'docs');
    }

    // ── generateNamespaceIndex ───────────────────────────────────

    public function test_generate_namespace_index_contains_expected_namespaces(): void
    {
        $index = $this->generator->generateNamespaceIndex($this->agent);

        $this->assertStringContainsString('app.calendar', $index);
        $this->assertStringContainsString('app.chat', $index);
        $this->assertStringContainsString('app.docs', $index);
        $this->assertStringContainsString('app.memory', $index);
        $this->assertStringContainsString('app.web', $index);
    }

    public function test_generate_namespace_index_with_filter(): void
    {
        $index = $this->generator->generateNamespaceIndex($this->agent, 'chat');

        $this->assertStringContainsString('app.chat', $index);
        $this->assertStringNotContainsString('app.docs', $index);
        $this->assertStringNotContainsString('app.calendar', $index);
    }

    public function test_generate_namespace_index_with_unknown_filter(): void
    {
        $index = $this->generator->generateNamespaceIndex($this->agent, 'nonexistent');

        $this->assertStringContainsString("Namespace 'nonexistent' not found", $index);
    }

    public function test_generate_namespace_index_points_to_code_read_doc_for_details(): void
    {
        $index = $this->generator->generateNamespaceIndex($this->agent);

        $this->assertStringContainsString('Use `code_read_doc` to inspect a namespace before calling its functions.', $index);
        $this->assertStringContainsString('code_read_doc(page: "integrations.coingecko")', $index);
    }

    public function test_generate_namespace_index_hides_redundant_default_aliases(): void
    {
        $this->primeNamespaceCache([
            'integrations.coingecko' => [
                'description' => 'Cryptocurrency market data',
                'functions' => [
                    [
                        'name' => 'search',
                        'description' => 'Search coins',
                        'fullDescription' => 'Search coins',
                        'parameters' => [],
                        'sourceToolSlug' => 'coingecko_search',
                    ],
                ],
            ],
            'integrations.coingecko.default' => [
                'description' => 'Cryptocurrency market data',
                'functions' => [
                    [
                        'name' => 'search',
                        'description' => 'Search coins',
                        'fullDescription' => 'Search coins',
                        'parameters' => [],
                        'sourceToolSlug' => 'coingecko_search',
                    ],
                ],
            ],
            'integrations.coingecko.work' => [
                'description' => 'Cryptocurrency market data',
                'functions' => [
                    [
                        'name' => 'search',
                        'description' => 'Search coins',
                        'fullDescription' => 'Search coins',
                        'parameters' => [],
                        'sourceToolSlug' => 'coingecko_search',
                    ],
                ],
            ],
        ]);

        $index = $this->generator->generateNamespaceIndex($this->agent);

        $this->assertStringContainsString('**app.integrations.coingecko** — Cryptocurrency market data', $index);
        $this->assertStringContainsString('**app.integrations.coingecko.work** — Cryptocurrency market data', $index);
        $this->assertStringNotContainsString('app.integrations.coingecko.default.search({})', $index);
    }

    public function test_generate_namespace_index_with_root_filter_hides_default_alias(): void
    {
        $this->primeNamespaceCache([
            'integrations.coingecko' => [
                'description' => 'Cryptocurrency market data',
                'functions' => [
                    [
                        'name' => 'search',
                        'description' => 'Search coins',
                        'fullDescription' => 'Search coins',
                        'parameters' => [],
                        'sourceToolSlug' => 'coingecko_search',
                    ],
                ],
            ],
            'integrations.coingecko.default' => [
                'description' => 'Cryptocurrency market data',
                'functions' => [
                    [
                        'name' => 'search',
                        'description' => 'Search coins',
                        'fullDescription' => 'Search coins',
                        'parameters' => [],
                        'sourceToolSlug' => 'coingecko_search',
                    ],
                ],
            ],
        ]);

        $index = $this->generator->generateNamespaceIndex($this->agent, 'integrations.coingecko');

        $this->assertStringContainsString('**app.integrations.coingecko** — Cryptocurrency market data', $index);
        $this->assertStringNotContainsString('app.integrations.coingecko.default.search({})', $index);
    }

    public function test_generate_namespace_index_with_default_filter_keeps_default_alias(): void
    {
        $this->primeNamespaceCache([
            'integrations.coingecko' => [
                'description' => 'Cryptocurrency market data',
                'functions' => [
                    [
                        'name' => 'search',
                        'description' => 'Search coins',
                        'fullDescription' => 'Search coins',
                        'parameters' => [],
                        'sourceToolSlug' => 'coingecko_search',
                    ],
                ],
            ],
            'integrations.coingecko.default' => [
                'description' => 'Cryptocurrency market data',
                'functions' => [
                    [
                        'name' => 'search',
                        'description' => 'Search coins',
                        'fullDescription' => 'Search coins',
                        'parameters' => [],
                        'sourceToolSlug' => 'coingecko_search',
                    ],
                ],
            ],
        ]);

        $index = $this->generator->generateNamespaceIndex($this->agent, 'integrations.coingecko.default');

        $this->assertStringContainsString('**app.integrations.coingecko.default** — Cryptocurrency market data', $index);
    }

    // ── generateNamespaceDocs ────────────────────────────────────

    public function test_generate_namespace_docs_includes_parameter_table(): void
    {
        $docs = $this->generator->generateNamespaceDocs('calendar', $this->agent);

        $this->assertStringContainsString('| Parameter | Type | Required | Description |', $docs);
        $this->assertStringContainsString('event_id', $docs);
    }

    public function test_generate_namespace_docs_unknown_namespace(): void
    {
        $docs = $this->generator->generateNamespaceDocs('bogus', $this->agent);

        $this->assertStringContainsString("Namespace 'bogus' not found", $docs);
    }

    // ── Atomic calendar tools ─────────────────────────────────

    public function test_atomic_calendar_tools_appear_separately(): void
    {
        $map = $this->generator->buildFunctionMap($this->agent);

        // Each atomic calendar tool should appear as its own entry
        $calendarSlugs = array_filter(
            $map,
            fn ($slug) => str_contains($slug, 'calendar'),
        );

        $this->assertContains('list_calendar_events', $calendarSlugs);
        $this->assertContains('get_calendar_event', $calendarSlugs);
        $this->assertContains('create_calendar_event', $calendarSlugs);
        $this->assertContains('update_calendar_event', $calendarSlugs);
        $this->assertContains('delete_calendar_event', $calendarSlugs);
    }

    public function test_calendar_docs_contain_event_id_param(): void
    {
        $docs = $this->generator->generateNamespaceDocs('calendar', $this->agent);

        // The event_id parameter should be listed as required on get/update/delete tools
        $this->assertMatchesRegularExpression('/\| event_id \| string \| yes \|/', $docs);
    }

    public function test_capability_paths_are_engine_safe_and_non_identifiers_are_exact_slugs(): void
    {
        $map = $this->generator->buildFunctionMap($this->agent);

        foreach ($map as $path => $slug) {
            $parts = explode('.', $path);
            $fnName = end($parts);
            $this->assertMatchesRegularExpression(
                '/^[a-zA-Z0-9_.-]+$/D',
                $path,
                "Invalid engine capability path '{$path}' (tool: {$slug})"
            );
            // Exact tool slugs such as ko-fi_list_supporters are intentionally
            // called through app.call; they are not Ruby dot-method identifiers.
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $fnName)) {
                $this->assertSame($slug, $fnName);
            }
        }
    }

    // ── search ───────────────────────────────────────────────────

    public function test_search_finds_by_function_name(): void
    {
        $results = $this->generator->search('send', $this->agent, 5);

        $this->assertStringContainsString('send_channel_message', $results);
    }

    public function test_search_returns_no_results_message(): void
    {
        $results = $this->generator->search('zzzznonexistent', $this->agent);

        $this->assertStringContainsString("No results found for 'zzzznonexistent'", $results);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function assertDerivesName(string $expected, string $toolName, string $appName): void
    {
        $method = new \ReflectionMethod(CodeApiDocGenerator::class, 'deriveFunctionName');

        $result = $method->invoke($this->generator, $toolName, $appName);

        $this->assertEquals($expected, $result, "deriveFunctionName('{$toolName}', '{$appName}') should be '{$expected}', got '{$result}'");
    }

    /**
     * @param  array<string, array{description: string, functions: array<int, array{name: string, description: string, fullDescription: string, parameters: array, sourceToolSlug: string}>}>  $namespaces
     */
    private function primeNamespaceCache(array $namespaces): void
    {
        // Establish a real current visibility fingerprint first. The injected
        // namespaces then isolate renderer behavior without restoring the old
        // unsafe actor-id-only cache contract.
        $this->generator->buildFunctionMap($this->agent);

        $generator = new \ReflectionObject($this->generator);

        $cachedNamespaces = $generator->getProperty('cachedNamespaces');
        $cachedNamespaces->setAccessible(true);
        $cachedNamespaces->setValue($this->generator, $namespaces);

    }
}
