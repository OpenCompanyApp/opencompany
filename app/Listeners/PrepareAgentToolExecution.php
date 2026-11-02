<?php

namespace App\Listeners;

use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Tools\System\RunGuardedTool;
use Laravel\Ai\Events\InvokingTool;

/**
 * Assigns provider tool calls their durable position before execution starts.
 *
 * Laravel AI dispatches this event immediately before calling Tool::handle().
 * Counting every provider tool (including agent-as-tool delegations that own a
 * child run instead of a ledger row) keeps retry sequence aligned with the
 * checkpointed TaskStep history injected into a resumed model conversation.
 */
class PrepareAgentToolExecution
{
    public function handle(InvokingTool $event): void
    {
        if (! app()->bound(ActiveAgentRun::class)) {
            return;
        }

        $activeRun = app(ActiveAgentRun::class);
        $sequence = $activeRun->nextProviderToolSequence();

        if ($event->tool instanceof RunGuardedTool) {
            $event->tool->prepareInvocation($event->toolInvocationId, $sequence);
        }
    }
}
