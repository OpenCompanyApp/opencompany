<?php

namespace Tests\Feature\AgentRuntime;

use App\Agents\Tools\Agents\ContactAgent;
use App\Jobs\AgentRespondJob;
use App\Models\Task;
use App\Models\User;
use App\Services\AgentCommunicationService;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\Concerns\GrantsAgentPermissions;
use Tests\TestCase;

/**
 * Regression for the audit finding that the delegation depth guard was dead,
 * in-process code: depth and the agent chain must be read from persisted tasks
 * so a chain spread across separately queued AgentRespondJobs is still bounded.
 */
class DelegationGuardTest extends TestCase
{
    use GrantsAgentPermissions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Bus::fake([AgentRespondJob::class]);
        config(['agents.delegation.max_depth' => 2]);
    }

    private function agent(): User
    {
        $agent = User::factory()->agent()->create(['status' => 'idle']);
        // Mechanics test: the policy model requires approval for writes by
        // default (peer contact); grant explicitly so the depth guard is hit.
        $this->allowAgentContact($agent);

        return $agent;
    }

    private function contact(User $from, User $to, ?string $currentTaskId, string $action = 'delegate'): string
    {
        $tool = new ContactAgent($from, app(AgentPermissionService::class), app(AgentCommunicationService::class), $currentTaskId);

        return $tool->handle(new Request(['action' => $action, 'agentId' => $to->id, 'message' => 'Please handle this']));
    }

    private function rootTask(User $agent): Task
    {
        return Task::create([
            'id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'title' => 'root',
            'type' => Task::TYPE_CUSTOM, 'status' => Task::STATUS_ACTIVE, 'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_CHAT, 'agent_id' => $agent->id, 'requester_id' => $agent->id, 'started_at' => now(),
        ]);
    }

    // Each hop is a separate queued job in production; only persisted lineage
    // links them. A -> B -> C reaches max depth 2, so C cannot delegate on.
    public function test_depth_limit_holds_across_persisted_tasks(): void
    {
        [$a, $b, $c, $d] = [$this->agent(), $this->agent(), $this->agent(), $this->agent()];

        $this->assertStringContainsString('Delegated to', $this->contact($a, $b, $this->rootTask($a)->id));
        $hop1 = Task::where('agent_id', $b->id)->sole();
        $this->assertSame(1, $hop1->context['delegation_depth']);

        $this->assertStringContainsString('Delegated to', $this->contact($b, $c, $hop1->id));
        $hop2 = Task::where('agent_id', $c->id)->sole();
        $this->assertSame(2, $hop2->context['delegation_depth']);
        $this->assertSame([$a->id, $b->id], $hop2->context['delegation_chain']);

        $refused = $this->contact($c, $d, $hop2->id);
        $this->assertStringContainsString('Delegation depth limit reached', $refused);
        $this->assertSame(0, Task::where('agent_id', $d->id)->count());
    }

    // A -> B -> A ping-pong is refused before any message, task or job exists.
    public function test_delegating_back_into_the_chain_is_refused_without_side_effects(): void
    {
        [$a, $b] = [$this->agent(), $this->agent()];
        $this->contact($a, $b, $this->rootTask($a)->id);
        $hop1 = Task::where('agent_id', $b->id)->sole();
        Bus::fake([AgentRespondJob::class]);

        $refused = $this->contact($b, $a, $hop1->id, 'ask');

        $this->assertStringContainsString('already part of this delegation chain', $refused);
        $this->assertSame(0, Task::where('agent_id', $a->id)->where('source', Task::SOURCE_AGENT_ASK)->count());
        Bus::assertNotDispatched(AgentRespondJob::class);
    }

    // Notifications have no parent task, but still carry lineage so a notify
    // chain cannot grow without bound.
    public function test_notify_chains_are_depth_bounded(): void
    {
        [$a, $b, $c, $d] = [$this->agent(), $this->agent(), $this->agent(), $this->agent()];
        $this->contact($a, $b, $this->rootTask($a)->id, 'notify');
        $n1 = Task::where('agent_id', $b->id)->sole();
        $this->assertNull($n1->parent_task_id);
        $this->contact($b, $c, $n1->id, 'notify');
        $n2 = Task::where('agent_id', $c->id)->sole();

        $this->assertStringContainsString('Delegation depth limit reached', $this->contact($c, $d, $n2->id, 'notify'));
    }
}
