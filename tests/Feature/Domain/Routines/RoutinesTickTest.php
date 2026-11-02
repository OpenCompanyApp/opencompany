<?php

namespace Tests\Feature\Domain\Routines;

use App\Console\Commands\RoutinesTick;
use App\Domain\Routines\Application\ExecuteScriptRoutine;
use App\Domain\Routines\Application\ResolveRoutineRun;
use App\Events\AgentStatusUpdated;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Jobs\RunRoutineJob;
use App\Models\AgentRunRecord;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceEvent;
use App\Services\ScriptAdmission;
use App\Services\ScriptRoutineInvocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Protects the Routine model that replaced Automations
 * (docs/planning/connections-events-routines.md):
 *  - `routines:tick` records exactly one `routine.triggered` event per
 *    scheduled slot (idempotency key `routine:{id}:{scheduledAt}`) and
 *    dispatches exactly one run for it;
 *  - the run's task and AgentRunRecord carry the event's causality chain one
 *    hop deeper;
 *  - the notify policy gates the routine's own output message;
 *  - editing instructions bumps the version;
 *  - the deleted Automation, automation-rule and integration-webhook
 *    endpoints are gone.
 */
class RoutinesTickTest extends TestCase
{
    use RefreshDatabase;

    private function agentRoutine(array $overrides = []): Routine
    {
        $creator = User::factory()->create();
        $agent = User::factory()->agent()->create();
        $channel = Channel::factory()->dm()->create(['workspace_id' => $this->workspace->id]);

        return Routine::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Morning report',
            'kind' => Routine::KIND_AGENT,
            'agent_id' => $agent->id,
            'instructions' => 'Summarize the night.',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'is_active' => true,
            'channel_id' => $channel->id,
            'keep_history' => true,
            'created_by_id' => $creator->id,
            ...$overrides,
        ]);
    }

    // A scheduler that runs twice in the same minute, or a slot replayed after
    // next_run_at was reset to it, must not produce a second event or run.
    public function test_tick_records_one_idempotent_event_and_dispatches_once_per_slot(): void
    {
        Bus::fake([RunRoutineJob::class]);
        Carbon::setTestNow('2026-11-02 09:00:30 UTC');
        $routine = $this->agentRoutine();
        $slot = Carbon::parse('2026-11-02 09:00:00 UTC');
        $routine->update(['next_run_at' => $slot]);

        $this->artisan('routines:tick')->assertSuccessful();
        $this->artisan('routines:tick')->assertSuccessful();

        $event = WorkspaceEvent::query()->where('type', RoutinesTick::EVENT_TYPE)->sole();
        $this->assertSame("routine:{$routine->id}:{$slot->toIso8601String()}", $event->idempotency_key);
        $this->assertSame($this->workspace->id, $event->workspace_id);
        $this->assertSame('scheduler', $event->source);
        $this->assertSame('system', $event->actor_type);
        $this->assertSame(0, $event->depth);
        $this->assertSame($routine->id, $event->payload['routine_id']);
        Bus::assertDispatchedTimes(RunRoutineJob::class, 1);
        $this->assertSame(1, AgentRunRecord::query()->count());
        $this->assertTrue($routine->fresh()->next_run_at->isFuture());

        // Replay the same slot: the event already exists, so nothing runs.
        $routine->updateQuietly(['next_run_at' => $slot]);
        $this->artisan('routines:tick')->assertSuccessful();

        $this->assertSame(1, WorkspaceEvent::query()->where('type', RoutinesTick::EVENT_TYPE)->count());
        Bus::assertDispatchedTimes(RunRoutineJob::class, 1);
        $this->assertSame(1, AgentRunRecord::query()->count());
        $this->assertTrue($routine->fresh()->next_run_at->isFuture());
    }

    // The trigger event is the root of a chain; the run is the next hop.
    public function test_routine_run_task_carries_causality_from_the_trigger_event(): void
    {
        Bus::fake([RunRoutineJob::class]);
        $routine = $this->agentRoutine(['next_run_at' => now()->subMinute()]);

        $this->artisan('routines:tick')->assertSuccessful();

        $event = WorkspaceEvent::query()->where('type', RoutinesTick::EVENT_TYPE)->sole();
        $run = AgentRunRecord::query()->sole();
        $task = $run->task;

        $this->assertSame(AgentRunRecord::KIND_ROUTINE_AGENT, $run->kind);
        $this->assertSame($event->causality_id, $task->causality_id);
        $this->assertSame($event->depth + 1, $task->causality_depth);
        $this->assertSame($event->causality_id, $run->causality_id);
        $this->assertSame($event->depth + 1, $run->causality_depth);
        $this->assertSame($event->id, $task->context['trigger_event_id']);
        $this->assertSame($routine->version, $task->context['routine_version']);
        $this->assertSame(Task::SOURCE_ROUTINE, $task->source);

        Bus::assertDispatched(RunRoutineJob::class, function (RunRoutineJob $job) use ($event, $run): bool {
            $causality = (new \ReflectionProperty($job, 'causality'))->getValue($job);
            $dispatchKey = (new \ReflectionProperty($job, 'dispatchKey'))->getValue($job);

            return $causality?->id === $event->causality_id
                && $causality->depth === 1
                && $dispatchKey === $run->dispatch_key;
        });
    }

    // A manual run is a human action and starts its own root chain.
    public function test_manual_run_starts_a_root_causality_chain(): void
    {
        Event::fake([MessageSent::class, TaskUpdated::class, AgentStatusUpdated::class]);
        $routine = $this->agentRoutine();

        $context = app(ResolveRoutineRun::class)
            ->handle($routine, (string) Str::uuid(), AgentRunRecord::KIND_ROUTINE_AGENT, 2);

        $this->assertNotNull($context->task->causality_id);
        $this->assertSame(0, $context->task->causality_depth);
        $this->assertSame(0, WorkspaceEvent::query()->count());
    }

    // Only content edits create a new version; schedule edits do not.
    public function test_instruction_edits_bump_the_version(): void
    {
        $routine = $this->agentRoutine();
        $this->assertSame(1, $routine->fresh()->version);

        $routine->update(['cron_expression' => '0 10 * * *']);
        $this->assertSame(1, $routine->fresh()->version);

        $routine->update(['instructions' => 'Summarize the week.']);
        $this->assertSame(2, $routine->fresh()->version);
    }

    public function test_notify_policy_decisions(): void
    {
        $routine = new Routine(['notify' => Routine::NOTIFY_ON_FAILURE]);
        $this->assertFalse($routine->shouldNotify('done', failed: false));
        $this->assertTrue($routine->shouldNotify('boom', failed: true));

        $routine = new Routine(['notify' => Routine::NOTIFY_IF_NEW, 'last_output_digest' => Routine::outputDigest('same')]);
        $this->assertFalse($routine->shouldNotify("same\n", failed: false));
        $this->assertTrue($routine->shouldNotify('different', failed: false));
        $this->assertTrue($routine->shouldNotify('same', failed: true));

        $routine = new Routine(['notify' => Routine::NOTIFY_ALWAYS]);
        $this->assertTrue($routine->shouldNotify('anything', failed: false));
    }

    // notify=if_new: a repeated identical script output is posted once.
    public function test_if_new_script_routine_skips_identical_output(): void
    {
        $this->requireEngine();
        $routine = $this->scriptRoutine('puts("same every run")', Routine::NOTIFY_IF_NEW);

        $this->runScript($routine, 'dispatch-1');
        $this->runScript($routine->fresh(), 'dispatch-2');

        $this->assertSame(2, AgentRunRecord::query()->where('status', AgentRunRecord::STATUS_COMPLETED)->count());
        $this->assertSame(1, Message::query()->where('channel_id', $routine->fresh()->channel_id)->count());
        $this->assertSame(Routine::outputDigest('same every run'), $routine->fresh()->last_output_digest);
    }

    // notify=on_failure: successful output stays quiet, failures are posted.
    public function test_on_failure_script_routine_posts_only_failures(): void
    {
        $this->requireEngine();
        $quiet = $this->scriptRoutine('puts("all good")', Routine::NOTIFY_ON_FAILURE);
        $this->runScript($quiet, 'dispatch-ok');
        $this->assertSame(Task::STATUS_COMPLETED, AgentRunRecord::query()->sole()->task->status);
        $this->assertSame(0, Message::query()->count());

        $loud = $this->scriptRoutine('raise "boom"', Routine::NOTIFY_ON_FAILURE, 'script-on-failure-loud');
        $this->runScript($loud, 'dispatch-fail');

        $failed = AgentRunRecord::query()->where('dispatch_key', 'dispatch-fail')->sole();
        $this->assertSame(Task::STATUS_FAILED, $failed->task->status);
        $this->assertSame(1, Message::query()->where('agent_run_id', $failed->id)->count());
        $this->assertStringContainsString('boom', (string) Message::query()->where('agent_run_id', $failed->id)->value('content'));
    }

    // Phase 0 deletions: Automations API, list automation rules and
    // incoming integration webhooks no longer exist.
    public function test_deleted_automation_rule_and_webhook_endpoints_are_gone(): void
    {
        $user = User::factory()->create(['type' => 'human']);
        $this->actingAs($user)->withHeader('X-Workspace-Id', $this->workspace->id);

        $this->getJson('/api/automations')->assertNotFound();
        $this->postJson('/api/automations', [])->assertNotFound();
        $this->getJson('/api/automation-rules')->assertNotFound();
        $this->getJson('/api/integration-webhooks')->assertNotFound();
        $this->postJson('/api/webhooks/'.Str::uuid(), ['event' => 'x'])->assertNotFound();
        $this->getJson('/api/routines')->assertOk();
    }

    private function requireEngine(): void
    {
        if (! is_executable((string) config('code.engine_binary'))) {
            $this->markTestSkipped('Requires the pinned Ruby engine (RUBY_ENGINE_BINARY).');
        }
        Event::fake([MessageSent::class, TaskUpdated::class, AgentStatusUpdated::class]);
    }

    private function scriptRoutine(string $source, string $notify, string $id = 'script-notify'): Routine
    {
        return Routine::create([
            'id' => $id, 'workspace_id' => $this->workspace->id,
            'name' => 'Notify '.$id, 'kind' => Routine::KIND_SCRIPT, 'notify' => $notify,
            'agent_id' => User::factory()->agent()->create(['status' => 'idle'])->id,
            'created_by_id' => User::factory()->create()->id,
            'script' => $source, ...app(ScriptAdmission::class)->admit($source),
            'cron_expression' => '0 9 * * *', 'timezone' => 'UTC', 'is_active' => true, 'keep_history' => true,
        ]);
    }

    private function runScript(Routine $routine, string $dispatchKey): void
    {
        app(ExecuteScriptRoutine::class)->handle(
            $routine,
            $dispatchKey,
            'job-'.$dispatchKey,
            ScriptRoutineInvocation::capture($routine),
        );
    }
}
