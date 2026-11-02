<?php

namespace App\Domain\Vfs\Events;

/**
 * Destination of VFS events.
 *
 * Today bound to DatabaseVfsEventSink (the `vfs_operation_events` table).
 * When the workspace event log exists, bind an implementation that appends
 * VfsEvent::envelope() to it; callers (VfsAuditor) do not change.
 *
 * Contract:
 * - record() must not throw for persistence problems that are not the
 *   caller's fault; an audit hiccup must never turn a committed write into a
 *   reported failure. Implementations log and continue.
 * - Read events (effect `read`) may be aggregated and buffered; every other
 *   event is durable when record() returns.
 * - flush() persists any buffered events (end of request/job, tests).
 */
interface VfsEventSink
{
    public function record(VfsEvent $event): void;

    public function flush(): void;
}
