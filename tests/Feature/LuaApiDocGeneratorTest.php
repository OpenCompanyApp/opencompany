<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LuaApiDocGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LuaApiDocGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private LuaApiDocGenerator $generator;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(LuaApiDocGenerator::class);
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
    }

    public function test_build_function_map_excludes_system_namespaces(): void
    {
        $map = $this->generator->buildFunctionMap($this->agent);

        foreach (array_keys($map) as $path) {
            $this->assertFalse(str_starts_with($path, 'lua.'), "Should not contain lua namespace: {$path}");
            $this->assertFalse(str_starts_with($path, 'tasks.'), "Should not contain tasks namespace: {$path}");
            $this->assertFalse(str_starts_with($path, 'system.'), "Should not contain system namespace: {$path}");
        }
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

    public function test_generate_namespace_index_shows_param_signatures(): void
    {
        $index = $this->generator->generateNamespaceIndex($this->agent);

        // Required params without ?, optional params with ?
        $this->assertStringContainsString('send_channel_message(channelId, content)', $index);
    }

    // ── generateNamespaceDocs ────────────────────────────────────

    public function test_generate_namespace_docs_includes_parameter_table(): void
    {
        $docs = $this->generator->generateNamespaceDocs('calendar', $this->agent);

        $this->assertStringContainsString('| Parameter | Type | Required | Description |', $docs);
        $this->assertStringContainsString('eventId', $docs);
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

        // The eventId parameter should be listed as required on get/update/delete tools
        $this->assertMatchesRegularExpression('/\| eventId \| string \| yes \|/', $docs);
    }

    public function test_all_function_names_are_valid_lua_identifiers(): void
    {
        $map = $this->generator->buildFunctionMap($this->agent);

        foreach ($map as $path => $slug) {
            $parts = explode('.', $path);
            $fnName = end($parts);
            $this->assertMatchesRegularExpression(
                '/^[a-zA-Z_][a-zA-Z0-9_]*$/',
                $fnName,
                "Invalid Lua identifier '{$fnName}' in path '{$path}' (tool: {$slug})"
            );
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
        $method = new \ReflectionMethod(LuaApiDocGenerator::class, 'deriveFunctionName');

        $result = $method->invoke($this->generator, $toolName, $appName);

        $this->assertEquals($expected, $result, "deriveFunctionName('{$toolName}', '{$appName}') should be '{$expected}', got '{$result}'");
    }
}
