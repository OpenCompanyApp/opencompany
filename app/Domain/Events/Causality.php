<?php

namespace App\Domain\Events;

use Illuminate\Support\Str;

/**
 * The causality chain carried by every event and run: the root chain id and
 * the number of hops from the originating event or human action.
 *
 * Owns the single loop-protection rule (`config('events.max_depth')`): an
 * event, routine trigger or agent delegation whose next hop would exceed the
 * limit is refused. This replaces the old in-process delegation counter.
 */
final class Causality
{
    public function __construct(
        public readonly string $id,
        public readonly int $depth,
        public readonly ?string $parentEventId = null,
    ) {}

    /** A new chain, started by a human, a schedule tick or an external input. */
    public static function root(): self
    {
        return new self((string) Str::uuid(), 0);
    }

    public static function maxDepth(): int
    {
        return max(1, (int) config('events.max_depth', 3));
    }

    /**
     * The next hop in this chain.
     *
     * @throws CausalityDepthExceeded when the hop would exceed the limit
     */
    public function next(?string $parentEventId = null): self
    {
        if ($this->depth + 1 > self::maxDepth()) {
            throw new CausalityDepthExceeded(self::maxDepth());
        }

        return new self($this->id, $this->depth + 1, $parentEventId ?? $this->parentEventId);
    }
}
