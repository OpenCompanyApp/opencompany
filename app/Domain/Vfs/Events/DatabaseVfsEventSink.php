<?php

namespace App\Domain\Vfs\Events;

use App\Models\VfsOperationEvent;
use Illuminate\Support\Facades\Log;

/**
 * Table-backed VfsEventSink (`vfs_operation_events`).
 *
 * Cost model:
 * - Writes, denials (including read-path denials), approval requests and
 *   failures: one INSERT each, at record() time.
 * - Allowed reads: aggregated to one row per (actor, run — or the agent-minute when
 *   there is no run —, operation, path). The aggregation key is the row's
 *   idempotency_key (unique index), duplicates are dropped in memory first and
 *   with INSERT OR IGNORE semantics at flush, and buffered reads are written
 *   in one bulk statement at the end of the request or queue job (registered
 *   in AppServiceProvider) or when the buffer fills. A `find /` therefore
 *   costs one statement, not one per directory.
 *
 * Singleton: the buffer lives for the process; flush() empties it.
 */
class DatabaseVfsEventSink implements VfsEventSink
{
    private const READ_BUFFER_LIMIT = 200;

    /** @var array<string, array<string, mixed>> */
    private array $readBuffer = [];

    /** @var array<string, true> Keys already persisted by this process (bounded). */
    private array $seenReadKeys = [];

    public function record(VfsEvent $event): void
    {
        try {
            // Invariant: only allowed reads aggregate. A denial is an
            // authorization decision and always gets its own row, even on the
            // read path (read denials carry no idempotency key, and the read
            // buffer used to drop keyless events, so no denial was stored).
            if ($event->effect === 'read' && $event->decision !== 'denied') {
                $this->bufferRead($event);

                return;
            }

            VfsOperationEvent::query()->insertOrIgnore([$this->row($event)]);
        } catch (\Throwable $e) {
            Log::warning('VFS audit event could not be persisted.', [
                'type' => $event->type,
                'workspace_id' => $event->workspaceId,
                'exception_type' => get_class($e),
            ]);
        }
    }

    public function flush(): void
    {
        if ($this->readBuffer === []) {
            return;
        }

        $rows = array_values($this->readBuffer);
        $this->readBuffer = [];

        try {
            foreach (array_chunk($rows, 100) as $chunk) {
                VfsOperationEvent::query()->insertOrIgnore($chunk);
            }
        } catch (\Throwable $e) {
            Log::warning('VFS read audit events could not be persisted.', [
                'count' => count($rows),
                'exception_type' => get_class($e),
            ]);
        }
    }

    private function bufferRead(VfsEvent $event): void
    {
        $key = (string) $event->idempotencyKey;
        if ($key === '' || isset($this->seenReadKeys[$key]) || isset($this->readBuffer[$key])) {
            return;
        }

        if (count($this->seenReadKeys) > 5_000) {
            $this->seenReadKeys = [];
        }
        $this->seenReadKeys[$key] = true;
        $this->readBuffer[$key] = $this->row($event);

        if (count($this->readBuffer) >= self::READ_BUFFER_LIMIT) {
            $this->flush();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function row(VfsEvent $event): array
    {
        $now = now();

        return [
            'event_id' => $event->id,
            'workspace_id' => $event->workspaceId,
            'type' => $event->type,
            'source' => $event->source,
            'actor_type' => $event->actorType,
            'agent_id' => $event->actorId,
            'surface' => $event->surface,
            'run_id' => $event->runId,
            'causality_id' => $event->causalityId,
            'depth' => $event->depth,
            'operation' => $event->operation,
            'effect' => $event->effect,
            'decision' => $event->decision,
            'action_class' => $event->actionClass,
            'path' => $event->path,
            'canonical_path' => $event->canonicalPath,
            'backend_type' => $event->backendType,
            'backend_id' => $event->backendId,
            'approval_id' => $event->approvalId,
            'before_version' => $event->beforeVersion,
            'after_version' => $event->afterVersion,
            'bytes' => $event->bytes,
            'idempotency_key' => $event->idempotencyKey,
            'error_code' => $event->errorCode,
            'duration_ms' => max(0, $event->durationMs),
            'committed' => $event->committedWrite(),
            'payload' => $event->payload === [] ? null : json_encode($event->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'occurred_at' => $event->occurredAt->format('Y-m-d H:i:s.u'),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
