<?php

namespace Tests\Feature;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\CodeApiDocGenerator;
use App\Services\CodeBridge;
use App\Services\MrubySandboxService;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Mockery;
use OpenCompany\IntegrationCore\Script\ScriptBridgeException;
use Tests\TestCase;

/**
 * Deterministic functional workflows for Code Mode's supported Ruby surface.
 *
 * The corpus executes real mruby against ScriptBridge and fake, scoped PHP
 * tools. It is deliberately not an LLM benchmark, a QuickJS comparison, or a
 * proof of model quality; every provider response and side effect is local.
 */
class MrubyAgentExperienceTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $binary = getenv('RUBY_ENGINE_BINARY');
        if (! $binary || ! is_executable($binary)) {
            throw new \RuntimeException('This corpus requires the real pinned Ruby engine via RUBY_ENGINE_BINARY.');
        }

        config([
            'code.engine_binary' => $binary,
            'code.engine_sha256' => hash_file('sha256', $binary),
        ]);
        $this->agent = User::factory()->agent()->create();
    }

    /**
     * @param  array<string, string>  $paths
     * @param  array<string, list<array<string, mixed>>>  $parameters
     * @param  array<string, Tool>  $tools
     */
    private function bridge(array $paths, array $parameters, array $tools): CodeBridge
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('getToolMetaBySlug')->andReturnUsing(
            static fn (string $slug): array => ['name' => $slug, 'type' => str_contains($slug, 'write') ? 'write' : 'read'],
        );
        $registry->shouldReceive('resolveScriptToolForDispatch')->andReturnUsing(
            function (string $slug, User $agent, ?string $account = null) use ($tools): array {
                $this->assertSame($this->agent->id, $agent->id);
                $this->assertNull($account);
                $this->assertArrayHasKey($slug, $tools);

                return ['decision' => 'allow', 'reason' => 'Fake scoped tool', 'tool' => $tools[$slug]];
            },
        );

        $docs = Mockery::mock(CodeApiDocGenerator::class);
        $docs->shouldReceive('buildFunctionMap')->andReturn($paths);
        $docs->shouldReceive('buildParameterMap')->andReturn($parameters);
        $docs->shouldReceive('buildAccountMap')->andReturn([]);

        return new CodeBridge($this->agent, $registry, $docs, [
            'callback_limit' => 12,
            'callback_wall_limit' => 2.0,
            'callback_total_wall_limit' => 5.0,
            'callback_result_limit' => 32 * 1024,
        ]);
    }

    /** @param Closure(Request): string $handler */
    private function tool(Closure $handler): Tool
    {
        return new class($handler) implements Tool
        {
            public function __construct(private Closure $handler) {}

            public function description(): string
            {
                return 'AX corpus fake tool';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function handle(Request $request): string
            {
                return ($this->handler)($request);
            }
        };
    }

    private function execute(string $source, CodeBridge $bridge): mixed
    {
        $result = app(MrubySandboxService::class)->execute($source, 'agent', $bridge, sourceName: 'mruby-ax-corpus.rb');
        $this->assertNull($result->error, json_encode($result->error, JSON_PRETTY_PRINT));

        return $result;
    }

    public function test_joined_filtered_records_preserve_false_empty_and_large_numeric_ids(): void
    {
        $tool = $this->tool(static fn (Request $request): string => json_encode([
            ['id' => 9_007_199_254_740_993, 'enabled' => false, 'label' => '', 'team_id' => 2],
            ['id' => 7, 'enabled' => true, 'label' => 'skip', 'team_id' => 9],
        ], JSON_THROW_ON_ERROR));
        $bridge = $this->bridge(
            ['integrations.ax.list_records' => 'ax_list_records'],
            ['integrations.ax.list_records' => [['name' => 'active', 'type' => 'boolean', 'required' => true]]],
            ['ax_list_records' => $tool],
        );

        $result = $this->execute(<<<'RUBY'
records = app.integrations.ax.list_records(active: false)
selected = records.select { |row| row["team_id"] == 2 && row["enabled"] == false }
{id: selected[0]["id"], enabled: selected[0]["enabled"], label: selected[0]["label"], count: selected.length}
RUBY, $bridge);

        $this->assertSame(9_007_199_254_740_993, $result->result->id);
        $this->assertFalse($result->result->enabled);
        $this->assertSame('', $result->result->label);
        $this->assertSame(1, $result->result->count);
    }

    public function test_bounded_pagination_stops_after_the_published_limit(): void
    {
        $calls = [];
        $tool = $this->tool(function (Request $request) use (&$calls): string {
            $calls[] = $request['cursor'] ?? null;

            return json_encode(($request['cursor'] ?? null) === null
                ? ['items' => [['id' => 1]], 'next_cursor' => 'page-2']
                : ['items' => [['id' => 2]], 'next_cursor' => 'page-3'], JSON_THROW_ON_ERROR);
        });
        $bridge = $this->bridge(
            ['integrations.ax.list_page' => 'ax_list_page'],
            ['integrations.ax.list_page' => [['name' => 'cursor', 'type' => 'string', 'required' => false]]],
            ['ax_list_page' => $tool],
        );

        $result = $this->execute(<<<'RUBY'
cursor = nil
ids = []
2.times do
  page = app.integrations.ax.list_page(cursor: cursor)
  page["items"].each { |item| ids << item["id"] }
  cursor = page["next_cursor"]
  break if cursor.nil?
end
{ids: ids, next_cursor: cursor}
RUBY, $bridge);

        $this->assertSame([null, 'page-2'], $calls);
        $this->assertSame([1, 2], $result->result->ids);
        $this->assertSame('page-3', $result->result->next_cursor);
    }

    public function test_invalid_keywords_fail_before_dispatch_then_a_repaired_call_runs(): void
    {
        $calls = 0;
        $tool = $this->tool(function (Request $request) use (&$calls): string {
            $calls++;
            $this->assertSame(3, $request['limit']);

            return json_encode(['items' => [['id' => 1]]], JSON_THROW_ON_ERROR);
        });
        $bridge = $this->bridge(
            ['integrations.ax.list_records' => 'ax_list_records'],
            ['integrations.ax.list_records' => [['name' => 'limit', 'type' => 'integer', 'required' => true]]],
            ['ax_list_records' => $tool],
        );

        $result = $this->execute(<<<'RUBY'
repaired = false
begin
  app.integrations.ax.list_records(limit: "three")
rescue
  repaired = true
end
page = app.integrations.ax.list_records(limit: 3)
{repaired: repaired, count: page["items"].length}
RUBY, $bridge);

        $this->assertSame(1, $calls);
        $this->assertTrue($result->result->repaired);
        $this->assertSame(1, $result->result->count);
    }

    public function test_completed_fake_write_followed_by_ruby_error_is_non_retryable(): void
    {
        $writes = [];
        $tool = $this->tool(function (Request $request) use (&$writes): string {
            $writes[] = ['id' => $request['id'], 'enabled' => $request['enabled']];

            return json_encode(['accepted' => true], JSON_THROW_ON_ERROR);
        });
        $bridge = $this->bridge(
            ['integrations.ax.write_record' => 'ax_write_record'],
            ['integrations.ax.write_record' => [
                ['name' => 'id', 'type' => 'string', 'required' => true],
                ['name' => 'enabled', 'type' => 'boolean', 'required' => true],
            ]],
            ['ax_write_record' => $tool],
        );

        $result = app(MrubySandboxService::class)->execute(<<<'RUBY'
app.integrations.ax.write_record(id: "record-7", enabled: false)
raise "later local failure"
RUBY, 'agent', $bridge, sourceName: 'mruby-ax-corpus.rb');

        $this->assertNotNull($result->error);
        $this->assertSame([['id' => 'record-7', 'enabled' => false]], $writes);
        $this->assertSame(1, $result->effects['writesSucceeded']);
        $this->assertFalse($result->effects['retryable']);
        $this->assertFalse($result->error['retryable']);
        $this->assertSame('succeeded', $result->error['effectStatus']);
    }

    public function test_discovery_contract_exposes_only_the_published_fake_capability_paths(): void
    {
        $bridge = $this->bridge(
            ['integrations.ax.list_records' => 'ax_list_records'],
            ['integrations.ax.list_records' => [['name' => 'limit', 'type' => 'integer', 'required' => true]]],
            ['ax_list_records' => $this->tool(static fn (Request $request): string => '{}')],
        );

        $this->assertSame(['integrations.ax.list_records'], array_keys($bridge->capabilities()));

        try {
            $bridge->call('integrations.ax.unpublished');
            $this->fail('Expected an unpublished capability to be rejected.');
        } catch (ScriptBridgeException $exception) {
            $this->assertSame('unknown_function', $exception->errorType);
        }
    }
}
