<?php

namespace Tests\Feature\Events;

use App\Domain\Events\Causality;
use App\Domain\Events\CausalityDepthExceeded;
use App\Domain\Events\EventLog;
use App\Models\WorkspaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The workspace event log is append-only, idempotent for redelivered inputs,
 * and carries the causality chain used for loop protection.
 */
class EventLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_the_envelope_and_dedupes_by_idempotency_key(): void
    {
        $log = app(EventLog::class);
        $first = $log->record($this->workspace->id, 'webhook.received', 'connection', ['type' => 'connection', 'id' => 'c1'],
            payload: ['x' => 1], subject: '/files/a', idempotencyKey: 'delivery-1');
        $again = $log->record($this->workspace->id, 'webhook.received', 'connection', ['type' => 'connection', 'id' => 'c1'],
            payload: ['x' => 2], idempotencyKey: 'delivery-1');

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, WorkspaceEvent::count());
        $this->assertSame(0, $first->depth);
        $this->assertSame(['x' => 1], $first->fresh()->payload);
    }

    public function test_events_are_append_only(): void
    {
        $event = app(EventLog::class)->record($this->workspace->id, 'task.assigned', 'internal', ['type' => 'system']);

        $this->expectException(\LogicException::class);
        $event->update(['type' => 'changed']);
    }

    // Loop protection: each hop increments depth on the same chain and the
    // configured maximum stops the chain.
    public function test_causality_chain_increments_depth_and_stops_at_the_limit(): void
    {
        config(['events.max_depth' => 2]);
        $log = app(EventLog::class);
        $root = $log->record($this->workspace->id, 'message.posted', 'channel', ['type' => 'human', 'id' => 'u1']);
        $hop1 = $log->record($this->workspace->id, 'run.completed', 'internal', ['type' => 'agent'], causality: $log->causalityOf($root)->next());
        $hop2 = $log->causalityOf($hop1)->next();

        $this->assertSame($root->causality_id, $hop1->causality_id);
        $this->assertSame(1, $hop1->depth);
        $this->assertSame($root->id, $hop1->parent_event_id);
        $this->assertSame(2, $hop2->depth);
        $this->expectException(CausalityDepthExceeded::class);
        $hop2->next();
    }

    public function test_a_new_chain_starts_at_depth_zero(): void
    {
        $chain = Causality::root();
        $this->assertSame(0, $chain->depth);
        $this->assertNotSame($chain->id, Causality::root()->id);
    }
}
