<?php

namespace App\Domain\Vfs\Events;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsOperationLog;
use App\Domain\Vfs\OpenCompany\Policy\VfsActionClass;
use App\Events\VfsObjectChanged;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Turns VFS facade calls into VfsEvents.
 *
 * Called only by AuditedOpenCompanyVfs, which wraps every public read and
 * mutation of the facade, so every surface (direct tools, Code Mode helpers
 * such as cp/mv/rm/mkdir, the shell, approval replay, future bridges) is
 * covered without each tool emitting its own log.
 *
 * Rules:
 * - Only the outermost facade call of one logical operation is recorded
 *   (VfsOperationContext::enter); nested reads are part of their parent.
 * - Reads: one aggregated `vfs.read` per (actor, run or agent-minute,
 *   operation, path). Not-found and other non-permission read failures are
 *   not recorded; permission denials always are (`vfs.denied`, one row
 *   each: the sink never aggregates or drops a denial).
 * - Writes: every attempt that reached a decision is recorded: committed
 *   (`vfs.file.*`, `vfs.record.*`, `vfs.directory.*`, with before/after
 *   versions and the backend id; parent folders a write created implicitly
 *   get their own `vfs.directory.created`), denied, approval_required (`vfs.approval_requested`) or failed
 *   (`vfs.operation.failed` with the error code). A committed write also
 *   broadcasts VfsObjectChanged.
 * - Auditing never changes the outcome: sink failures are logged by the sink
 *   and exceptions from the operation are rethrown unchanged.
 */
class VfsAuditor
{
    public function __construct(
        private readonly VfsEventSink $sink,
        private readonly VfsOperationContext $context,
    ) {}

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function read(User $agent, string $operationName, string $path, callable $operation, ?string $normalizedPath = null): mixed
    {
        if (! $this->context->enter()) {
            try {
                return $operation();
            } finally {
                $this->context->leave();
            }
        }

        $started = microtime(true);
        $subject = $normalizedPath ?? $path;

        try {
            $result = $operation();
        } catch (VfsError $e) {
            $this->context->leave();
            if ($e->errorCode === 'permission_denied') {
                $this->sink->record($this->event($agent, 'vfs.denied', $operationName, 'read', 'denied', $subject, null, $started, errorCode: $e->errorCode, actionClass: VfsActionClass::READ));
            }

            throw $e;
        } catch (\Throwable $e) {
            $this->context->leave();

            throw $e;
        }

        $this->context->leave();
        $run = $this->context->run();
        $bucket = $run['run_id'] ?? 'm'.now()->format('YmdHi');
        $key = 'read:'.hash('sha256', implode('|', [$agent->workspace_id, $agent->id, $bucket, $operationName, $subject]));

        $this->sink->record($this->event(
            $agent,
            'vfs.read',
            $operationName,
            'read',
            'allowed',
            $subject,
            null,
            $started,
            bytes: is_string($result) ? strlen($result) : null,
            idempotencyKey: $key,
            actionClass: VfsActionClass::READ,
        ));

        return $result;
    }

    /**
     * Record one mutation of the facade.
     *
     * @template T of array<string, mixed>
     *
     * @param  callable(): T  $operation
     * @param  callable(string): (array<string, mixed>|null)  $statQuietly  Stat without auditing; null when the path does not resolve
     * @param  array<string, mixed>|null  $toolParameters  For idempotency_key passthrough
     * @return T
     */
    public function write(
        User $agent,
        string $operationName,
        string $path,
        ?string $destination,
        callable $operation,
        callable $statQuietly,
        ?array $toolParameters = null,
        ?int $bytes = null,
    ): array {
        if (! $this->context->enter()) {
            try {
                return $operation();
            } finally {
                $this->context->leave();
            }
        }

        $started = microtime(true);
        $before = null;
        $missingParents = [];

        try {
            $before = $statQuietly($path);
            $missingParents = $this->missingParents($operationName, $path, $destination, $before, $statQuietly);
            $result = $operation();
        } catch (VfsError $e) {
            $this->context->leave();
            $this->recordFailedWrite($agent, $operationName, $path, $before, $e, $started);

            throw $e;
        } catch (\Throwable $e) {
            $this->context->leave();
            $this->sink->record($this->event($agent, 'vfs.operation.failed', $operationName, $this->writeEffect($operationName), 'allowed', $path, $before['canonical_path'] ?? null, $started, errorCode: 'internal_error', actionClass: VfsActionClass::classify($operationName, $path)));

            throw $e;
        }

        try {
            // Parents first: they were created before the object inside them.
            foreach ($missingParents as $parent) {
                $this->recordImplicitDirectory($agent, $parent, $destination ?? $path, $statQuietly, $started);
            }

            $target = $destination ?? $path;
            // The result is enough only when it already identifies the object
            // (canonical path, version AND backend). Create results carry no
            // backend_type/backend_id, and a created event without them cannot
            // be joined to its object by change-set or revert builders.
            $after = $operationName === 'remove' ? null : (
                isset($result['canonical_path'], $result['version'], $result['backend_type'], $result['backend_id'])
                    ? $result
                    : ($statQuietly((string) ($result['canonical_path'] ?? $result['path'] ?? $target)) ?? $result)
            );
            $canonical = (string) ($after['canonical_path'] ?? $before['canonical_path'] ?? $result['canonical_path'] ?? $target);
            $key = $this->writeIdempotencyKey($agent, $operationName, $path, $toolParameters);

            $event = $this->event(
                $agent,
                $this->writeType($operationName, $canonical, $before, $after, $result),
                $operationName,
                $this->writeEffect($operationName),
                'allowed',
                $path,
                $canonical,
                $started,
                backendType: (string) ($before['backend_type'] ?? $after['backend_type'] ?? '') ?: null,
                backendId: (string) ($before['backend_id'] ?? $after['backend_id'] ?? '') ?: null,
                beforeVersion: is_string($before['version'] ?? null) ? $before['version'] : null,
                afterVersion: is_string($after['version'] ?? null) ? $after['version'] : null,
                bytes: $bytes,
                idempotencyKey: $key,
                actionClass: VfsActionClass::classify($operationName, $canonical),
                payload: array_filter([
                    'destination' => $destination,
                    'status' => is_string($result['status'] ?? null) ? $result['status'] : null,
                    'stale_base' => ($result['stale_base'] ?? false) === true ? true : null,
                ], fn (mixed $value): bool => $value !== null),
            );
            $this->sink->record($event);

            if ($event->committedWrite() && ($result['status'] ?? null) !== 'unchanged' && ($result['status'] ?? null) !== 'exists') {
                event(VfsObjectChanged::fromEvent($event));
            }
        } catch (\Throwable $e) {
            // Auditing must never turn a committed write into a failure.
            Log::warning('VFS write audit failed after commit.', ['operation' => $operationName, 'exception_type' => get_class($e)]);
        } finally {
            $this->context->leave();
        }

        return $result;
    }

    /**
     * Ancestors of a not-yet-existing target that do not exist either, the
     * outermost first. Writes create them implicitly (files: ensureFolderPath;
     * docs: mkdir -p), and each one is a committed object the audit trail
     * and change sets must see.
     *
     * Only computed when the target is new (one extra stat of its parent per
     * create; none for edits of existing objects). Mount roots and
     * top-level views (`/files`, `/docs`, `/docs/by-id`) are never "created".
     *
     * @param  array<string, mixed>|null  $before
     * @param  callable(string): (array<string, mixed>|null)  $statQuietly
     * @return list<string>
     */
    private function missingParents(string $operationName, string $path, ?string $destination, ?array $before, callable $statQuietly): array
    {
        if (! in_array($operationName, ['write', 'mkdir', 'touch', 'copy', 'move'], true)) {
            return [];
        }

        $target = $destination ?? $path;
        $targetExists = $destination === null ? $before !== null : $statQuietly($destination) !== null;
        if ($targetExists) {
            return [];
        }

        $missing = [];
        $segments = array_values(array_filter(explode('/', $target), fn (string $segment): bool => $segment !== ''));
        // Bounded walk: deep implicit trees are rare and each step is a stat.
        for ($depth = count($segments) - 1; $depth >= 2 && count($missing) < 10; $depth--) {
            $parent = '/'.implode('/', array_slice($segments, 0, $depth));
            if ($statQuietly($parent) !== null) {
                break;
            }
            array_unshift($missing, $parent);
        }

        return $missing;
    }

    /**
     * Record `vfs.directory.created` for a parent a write created
     * implicitly; skipped when the parent still does not exist (the
     * operation took another route, e.g. a mount without folders).
     *
     * @param  callable(string): (array<string, mixed>|null)  $statQuietly
     */
    private function recordImplicitDirectory(User $agent, string $parent, string $target, callable $statQuietly, float $started): void
    {
        $stat = $statQuietly($parent);
        if ($stat === null) {
            return;
        }

        $canonical = (string) ($stat['canonical_path'] ?? $parent);
        $event = $this->event(
            $agent,
            'vfs.directory.created',
            'mkdir',
            'write',
            'allowed',
            $parent,
            $canonical,
            $started,
            backendType: is_string($stat['backend_type'] ?? null) ? $stat['backend_type'] : null,
            backendId: is_string($stat['backend_id'] ?? null) ? $stat['backend_id'] : null,
            actionClass: VfsActionClass::classify('mkdir', $canonical),
            payload: ['implicit' => true, 'created_for' => $target],
        );
        $this->sink->record($event);
        event(VfsObjectChanged::fromEvent($event));
    }

    /**
     * Persist one tool-call telemetry record (VfsOperationLog, emitted by the
     * vfs_patch / vfs_write / vfs_exec tool classes). Object-level effects are
     * recorded separately by the facade; this row keeps the redacted command
     * line and the tool-level outcome.
     */
    public function toolCall(VfsOperationLog $log): void
    {
        $this->sink->record(new VfsEvent(
            id: $log->operationId,
            type: 'vfs.tool_call',
            workspaceId: $log->workspaceId,
            actorType: 'agent',
            actorId: $log->agentId,
            surface: in_array($log->surface, VfsOperationContext::SURFACES, true) ? $log->surface : 'tool',
            operation: $log->operation,
            effect: null,
            decision: match ($log->errorCode) {
                'permission_denied' => 'denied',
                'approval_required' => 'approval_required',
                default => 'allowed',
            },
            path: $log->path,
            canonicalPath: null,
            occurredAt: CarbonImmutable::now(),
            runId: $this->context->run()['run_id'],
            causalityId: $this->context->run()['causality_id'],
            depth: $this->context->run()['depth'],
            approvalId: $this->context->approvalId(),
            errorCode: $log->errorCode,
            durationMs: $log->durationMs,
            payload: array_filter([
                'cwd' => $log->cwd,
                'ok' => $log->ok,
                ...$log->metadata,
            ], fn (mixed $value): bool => $value !== null),
        ));
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    private function recordFailedWrite(User $agent, string $operationName, string $path, ?array $before, VfsError $e, float $started): void
    {
        $canonical = is_string($before['canonical_path'] ?? null) ? $before['canonical_path'] : null;
        $actionClass = is_string($e->context['action_class'] ?? null) ? $e->context['action_class'] : VfsActionClass::classify($operationName, $canonical ?? $path);

        [$type, $decision] = match ($e->errorCode) {
            'permission_denied' => ['vfs.denied', 'denied'],
            'approval_required' => ['vfs.approval_requested', 'approval_required'],
            default => ['vfs.operation.failed', 'allowed'],
        };
        $approvalId = is_string($e->context['approval_id'] ?? null) ? $e->context['approval_id'] : null;

        $this->sink->record($this->event(
            $agent,
            $type,
            $operationName,
            $this->writeEffect($operationName),
            $decision,
            $path,
            $canonical,
            $started,
            backendType: is_string($before['backend_type'] ?? null) ? $before['backend_type'] : null,
            backendId: is_string($before['backend_id'] ?? null) ? $before['backend_id'] : null,
            beforeVersion: is_string($before['version'] ?? null) ? $before['version'] : null,
            errorCode: $e->errorCode,
            // Repeated identical requests return the same pending approval;
            // record that request once.
            idempotencyKey: $approvalId !== null ? 'approval:'.$approvalId : null,
            actionClass: $actionClass,
            approvalId: $approvalId,
            payload: array_filter([
                'denied_tool' => $e->context['denied_tool'] ?? null,
                'required_tools' => $e->context['required_tools'] ?? null,
            ], fn (mixed $value): bool => $value !== null),
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function event(
        User $agent,
        string $type,
        string $operation,
        ?string $effect,
        string $decision,
        ?string $path,
        ?string $canonicalPath,
        float $started,
        ?string $backendType = null,
        ?string $backendId = null,
        ?string $beforeVersion = null,
        ?string $afterVersion = null,
        ?int $bytes = null,
        ?string $idempotencyKey = null,
        ?string $errorCode = null,
        ?string $actionClass = null,
        ?string $approvalId = null,
        array $payload = [],
    ): VfsEvent {
        $run = $this->context->run();

        return new VfsEvent(
            id: VfsEvent::newId(),
            type: $type,
            workspaceId: (string) $agent->workspace_id,
            actorType: $agent->type === 'agent' ? 'agent' : 'human',
            actorId: (string) $agent->id,
            surface: $this->context->surface(),
            operation: $operation,
            effect: $effect,
            decision: $decision,
            path: $path,
            canonicalPath: $canonicalPath,
            occurredAt: CarbonImmutable::now(),
            backendType: $backendType,
            backendId: $backendId,
            runId: $run['run_id'],
            causalityId: $run['causality_id'],
            depth: $run['depth'],
            approvalId: $approvalId ?? $this->context->approvalId(),
            actionClass: $actionClass,
            beforeVersion: $beforeVersion,
            afterVersion: $afterVersion,
            bytes: $bytes,
            idempotencyKey: $idempotencyKey,
            errorCode: $errorCode,
            durationMs: (int) round((microtime(true) - $started) * 1000),
            payload: $payload,
        );
    }

    private function writeEffect(string $operationName): string
    {
        return in_array($operationName, ['remove', 'delete', 'trash'], true) ? 'destructive' : 'write';
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $result
     */
    private function writeType(string $operationName, string $canonical, ?array $before, ?array $after, array $result): string
    {
        $isDirectory = $operationName === 'mkdir'
            || ($before['type'] ?? $after['type'] ?? null) === 'directory';
        $isRecord = preg_match('#^/(?:tables/.+/rows/|tasks/|lists/|issues/)#', $canonical) === 1
            || in_array($before['backend_type'] ?? $after['backend_type'] ?? null, ['data_table_row', 'task', 'list_item'], true);
        $family = $isDirectory ? 'vfs.directory' : ($isRecord ? 'vfs.record' : 'vfs.file');

        $verb = match (true) {
            in_array($operationName, ['remove', 'delete', 'trash'], true) => 'deleted',
            $operationName === 'move' => 'moved',
            $before === null || ($result['status'] ?? null) === 'created' || $operationName === 'copy' => 'created',
            default => $family === 'vfs.record' ? 'updated' : 'written',
        };

        if ($family === 'vfs.directory' && ! in_array($verb, ['created', 'deleted', 'moved'], true)) {
            $verb = 'created';
        }

        return "{$family}.{$verb}";
    }

    /**
     * Scoped per actor so two agents reusing a key never collide.
     *
     * @param  array<string, mixed>|null  $toolParameters
     */
    private function writeIdempotencyKey(User $agent, string $operationName, string $path, ?array $toolParameters): ?string
    {
        $key = $toolParameters['idempotency_key'] ?? $toolParameters['idempotencyKey'] ?? null;
        if (! is_string($key) || $key === '') {
            return null;
        }

        return 'write:'.hash('sha256', implode('|', [$agent->workspace_id, $agent->id, $operationName, $path, $key]));
    }
}
