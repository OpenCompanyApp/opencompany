<?php

namespace App\Domain\Vfs\Events;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * One VFS event in the CER envelope of the future workspace event log
 * (docs/planning/connections-events-routines.md §2, governance model
 * "Event log, audit and replay").
 *
 * Envelope: type, source (`vfs`), subject (canonical VFS path plus backend
 * ref), actor, occurred_at, idempotency_key, payload (untrusted, never object
 * content), causality_id, depth. The remaining typed fields (surface, run,
 * operation, effect, decision, approval, action class, versions, bytes, error,
 * duration) are the VFS audit dimensions; when the event log lands they map
 * into its payload and VfsEventSink is rebound, nothing else changes.
 *
 * Types in use:
 *   vfs.read                 aggregated read/list/search of one path (per run or agent-minute)
 *   vfs.denied               permission_denied at the VFS boundary
 *   vfs.approval_requested   a gated operation recorded an approval
 *   vfs.file.created|written|deleted|moved   text/binary objects (docs, files, agent docs)
 *   vfs.directory.created    folders
 *   vfs.record.created|updated|deleted       structured records (rows, tasks, list items/issues)
 *   vfs.operation.failed     a write that was allowed but failed (conflict, not_found, ...)
 *   vfs.tool_call            one VFS primitive tool invocation (VfsOperationLog)
 *
 * Effects: read | write | destructive | export (export is reserved for data
 * leaving the governed boundary, e.g. a computer pull; always one event each).
 * Decisions: allowed | denied | approval_required.
 */
final class VfsEvent
{
    public const EFFECTS = ['read', 'write', 'destructive', 'export'];

    public const DECISIONS = ['allowed', 'denied', 'approval_required'];

    /**
     * @param  array<string, mixed>  $payload  Untrusted metadata; never object content or secrets
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $workspaceId,
        public readonly string $actorType,
        public readonly ?string $actorId,
        public readonly string $surface,
        public readonly string $operation,
        public readonly ?string $effect,
        public readonly string $decision,
        public readonly ?string $path,
        public readonly ?string $canonicalPath,
        public readonly CarbonImmutable $occurredAt,
        public readonly ?string $backendType = null,
        public readonly ?string $backendId = null,
        public readonly ?string $runId = null,
        public readonly ?string $causalityId = null,
        public readonly int $depth = 0,
        public readonly ?string $approvalId = null,
        public readonly ?string $actionClass = null,
        public readonly ?string $beforeVersion = null,
        public readonly ?string $afterVersion = null,
        public readonly ?int $bytes = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $errorCode = null,
        public readonly int $durationMs = 0,
        public readonly array $payload = [],
        public readonly string $source = 'vfs',
    ) {}

    public static function newId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * A committed change to company data: what the change feed and realtime
     * refresh are built from.
     */
    public function committedWrite(): bool
    {
        return $this->decision === 'allowed'
            && $this->errorCode === null
            && in_array($this->effect, ['write', 'destructive'], true)
            && (str_starts_with($this->type, 'vfs.file.')
                || str_starts_with($this->type, 'vfs.record.')
                || str_starts_with($this->type, 'vfs.directory.'));
    }

    /**
     * Envelope view for consumers that speak the event-log shape.
     *
     * @return array<string, mixed>
     */
    public function envelope(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'source' => $this->source,
            'subject' => [
                'path' => $this->canonicalPath ?? $this->path,
                'backend_type' => $this->backendType,
                'backend_id' => $this->backendId,
            ],
            'actor' => ['type' => $this->actorType, 'id' => $this->actorId],
            'occurred_at' => $this->occurredAt->toIso8601ZuluString('microsecond'),
            'idempotency_key' => $this->idempotencyKey,
            'causality_id' => $this->causalityId,
            'depth' => $this->depth,
            'payload' => [
                ...$this->payload,
                'surface' => $this->surface,
                'run_id' => $this->runId,
                'operation' => $this->operation,
                'effect' => $this->effect,
                'decision' => $this->decision,
                'approval_id' => $this->approvalId,
                'action_class' => $this->actionClass,
                'before_version' => $this->beforeVersion,
                'after_version' => $this->afterVersion,
            ],
        ];
    }
}
