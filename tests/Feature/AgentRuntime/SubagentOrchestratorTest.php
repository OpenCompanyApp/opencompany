<?php

namespace Tests\Feature\AgentRuntime;

use App\Agents\Runtime\Subagents\GenericSubagent;
use App\Agents\Runtime\Subagents\SubagentDependencyGraph;
use App\Agents\Runtime\Subagents\SubagentOrchestrator;
use App\Agents\Runtime\Subagents\SubagentRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Tests\TestCase;

/**
 * Regressions for the audit finding that the dependency orchestrator ignored
 * upstream outputs, ran dependents of failed steps, and silently broke cycles.
 * Subagents are replaced by an in-memory fake; no provider is called.
 */
class SubagentOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> agent id => prompt received */
    public static array $prompts = [];

    /** @var array<string, string> agent id => failure message */
    public static array $failures = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::$prompts = [];
        self::$failures = [];

        $this->app->bind(GenericSubagent::class, fn ($app, array $params) => new class($params['agent']) extends GenericSubagent
        {
            public function __construct(private User $fakeAgent) {}

            public function prompt(string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null, ?int $timeout = null): AgentResponse
            {
                SubagentOrchestratorTest::$prompts[$this->fakeAgent->id] = $prompt;
                if (isset(SubagentOrchestratorTest::$failures[$this->fakeAgent->id])) {
                    throw new \RuntimeException(SubagentOrchestratorTest::$failures[$this->fakeAgent->id]);
                }

                return new AgentResponse('inv', "output of {$this->fakeAgent->name}", new Usage(3, 4), new Meta);
            }
        });
    }

    private function agent(string $name): User
    {
        return User::factory()->agent()->create(['name' => $name, 'status' => 'idle']);
    }

    // A dependent sees its dependency's final output; independent runs do not.
    public function test_dependents_receive_upstream_outputs(): void
    {
        [$research, $writer] = [$this->agent('Researcher'), $this->agent('Writer')];

        $stats = app(SubagentOrchestrator::class)->runSequential([
            ['id' => 'write', 'agent' => $writer, 'task' => 'Write the summary', 'dependsOn' => ['research']],
            ['id' => 'research', 'agent' => $research, 'task' => 'Collect facts'],
        ], 'channel-1', 'task-1');

        $this->assertSame('completed', $stats['write']->status);
        $this->assertSame('Collect facts', self::$prompts[$research->id]);
        $this->assertStringContainsString('Write the summary', self::$prompts[$writer->id]);
        $this->assertStringContainsString('Result of "research"', self::$prompts[$writer->id]);
        $this->assertStringContainsString('output of Researcher', self::$prompts[$writer->id]);
    }

    // A failed step skips its dependents (transitively) without prompting them.
    public function test_failed_dependency_skips_downstream_runs(): void
    {
        [$a, $b, $c, $d] = [$this->agent('A'), $this->agent('B'), $this->agent('C'), $this->agent('D')];
        self::$failures[$a->id] = 'provider down';

        $stats = app(SubagentOrchestrator::class)->runSequential([
            ['id' => 'a', 'agent' => $a, 'task' => 'step a'],
            ['id' => 'b', 'agent' => $b, 'task' => 'step b', 'dependsOn' => ['a']],
            ['id' => 'c', 'agent' => $c, 'task' => 'step c', 'dependsOn' => ['b']],
            ['id' => 'd', 'agent' => $d, 'task' => 'independent'],
        ], 'channel-1', 'task-1');

        $this->assertSame('failed', $stats['a']->status);
        $this->assertSame('skipped', $stats['b']->status);
        $this->assertSame('skipped', $stats['c']->status);
        $this->assertSame('completed', $stats['d']->status);
        $this->assertArrayNotHasKey($b->id, self::$prompts);
        $this->assertArrayNotHasKey($c->id, self::$prompts);
    }

    // Cycles and unknown dependencies reject the whole plan before any run.
    public function test_invalid_plans_fail_before_any_subagent_runs(): void
    {
        $graph = app(SubagentDependencyGraph::class);

        try {
            $graph->order([new SubagentRun('a', 'x', 'a', ['b']), new SubagentRun('b', 'y', 'b', ['a'])]);
            $this->fail('A cycle must be rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('cycle', $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown run');
        app(SubagentOrchestrator::class)->runSequential([
            ['id' => 'a', 'agent' => $this->agent('A'), 'task' => 'a', 'dependsOn' => ['ghost']],
        ], 'channel-1');
    }
}
