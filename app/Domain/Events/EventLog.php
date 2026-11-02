<?php

namespace App\Domain\Events;

use App\Models\WorkspaceEvent;
use Illuminate\Database\QueryException;

/**
 * Appends events to the durable workspace event log.
 *
 * Owns: the envelope, idempotent recording (webhooks/polling may redeliver),
 * and stamping the causality chain. Does not own: trigger matching or routine
 * dispatch (Phase 1+), or prompt rendering (payloads are always wrapped as
 * untrusted content there).
 *
 * Workspace boundary: the caller passes the workspace explicitly; the log
 * never infers it from ambient state.
 */
class EventLog
{
    /**
     * @param  array{type: 'human'|'agent'|'connection'|'system', id?: string|null}  $actor
     * @param  array<string, mixed>|null  $payload
     */
    public function record(
        string $workspaceId,
        string $type,
        string $source,
        array $actor,
        ?array $payload = null,
        ?string $subject = null,
        ?Causality $causality = null,
        ?string $idempotencyKey = null,
        ?string $sourceRef = null,
        ?string $runId = null,
        ?\DateTimeInterface $occurredAt = null,
    ): WorkspaceEvent {
        if ($idempotencyKey !== null) {
            $existing = WorkspaceEvent::query()
                ->where('workspace_id', $workspaceId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $causality ??= Causality::root();
        $attributes = [
            'workspace_id' => $workspaceId,
            'type' => $type,
            'source' => $source,
            'source_ref' => $sourceRef,
            'subject' => $subject,
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'] ?? null,
            'occurred_at' => $occurredAt ?? now(),
            'recorded_at' => now(),
            'idempotency_key' => $idempotencyKey,
            'payload' => $payload,
            'causality_id' => $causality->id,
            'parent_event_id' => $causality->parentEventId,
            'depth' => $causality->depth,
            'run_id' => $runId,
        ];

        try {
            return WorkspaceEvent::query()->create($attributes);
        } catch (QueryException $exception) {
            // Concurrent redelivery raced the lookup: the unique key wins.
            if ($idempotencyKey !== null) {
                $existing = WorkspaceEvent::query()->where('workspace_id', $workspaceId)
                    ->where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $exception;
        }
    }

    /** The causality to hand to work started by this event. */
    public function causalityOf(WorkspaceEvent $event): Causality
    {
        return new Causality($event->causality_id, $event->depth, $event->id);
    }
}
