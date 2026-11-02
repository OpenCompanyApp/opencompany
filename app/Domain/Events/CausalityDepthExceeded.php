<?php

namespace App\Domain\Events;

/**
 * A chain of events, routines or delegations reached the configured maximum
 * depth (`config('events.max_depth')`). Carries an agent-readable message.
 */
class CausalityDepthExceeded extends \DomainException
{
    public function __construct(public readonly int $maxDepth)
    {
        parent::__construct("Causality depth limit reached ({$maxDepth}). This chain of delegations, events or routines stops here; report back instead of starting further work.");
    }
}
