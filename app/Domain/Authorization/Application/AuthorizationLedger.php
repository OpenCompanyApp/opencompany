<?php

namespace App\Domain\Authorization\Application;

use App\Agents\Runtime\ActiveAgentRun;
use App\Domain\Authorization\Engine\Decision;
use App\Domain\Authorization\Engine\PolicyEngine;
use App\Models\AuthorizationLedgerEntry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Appends to and verifies the authorization ledger (GIN-1204; ROADMAP
 * Phase 0 P0 item 9).
 *
 * Owns: the per-workspace sequence and hash chain, redaction of `details`,
 * correlation ids (request id or the bound agent run), and chain
 * verification. Two kinds of entries:
 * - `decision`: what AuthorizationService decided (security-relevant
 *   actions per PolicyEngine::auditMode());
 * - `security_change`: a change that was made (role changed, grants
 *   replaced, key created or revoked, settings command run), recorded by
 *   the code that made it, after it succeeded.
 *
 * Secrets: `details` passes through redact() — keys that look like secrets,
 * tokens, passwords or API keys are replaced, long strings truncated. Never
 * pass a plaintext credential here regardless.
 *
 * Failure: a ledger write that fails throws. Decision entries are written
 * before the action runs, so a failed write stops the action (fail closed).
 *
 * Workspace boundary: the caller passes the workspace id explicitly.
 */
class AuthorizationLedger
{
    private const REDACT_KEY = '/(secret|token|password|passwd|api[_-]?key|authorization|credential|plain_?text|private[_-]?key|cookie)/i';

    /** Keys that match REDACT_KEY by name but are safe, non-secret identifiers. */
    private const SAFE_KEYS = ['key_id', 'key_prefix', 'masked_key', 'key_name'];

    private const MAX_STRING = 500;

    /** Hashed columns, in hash order. Changing this breaks verification of old rows. */
    private const HASHED = [
        'workspace_id', 'sequence', 'kind', 'action', 'effect', 'reason_code', 'reason', 'actor_type', 'actor_id',
        'on_behalf_of_id', 'resource_type', 'resource_id', 'policy_version', 'policy_hash', 'correlation_id',
        'run_id', 'details', 'trace', 'occurred_at', 'prev_hash',
    ];

    /**
     * Record one decision.
     *
     * @param  array{type: string, id: string|null}  $actor
     * @param  array{type: string, id: string|null}|null  $resource
     * @param  array<string, mixed>  $details
     */
    public function recordDecision(string $workspaceId, string $action, Decision $decision, array $actor, ?string $onBehalfOfId, ?array $resource, array $details = []): AuthorizationLedgerEntry
    {
        return $this->append($workspaceId, [
            'kind' => AuthorizationLedgerEntry::KIND_DECISION,
            'action' => $action,
            'effect' => $decision->effect,
            'reason_code' => $decision->reasonCode,
            'reason' => $decision->reason,
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'on_behalf_of_id' => $onBehalfOfId,
            'resource_type' => $resource['type'] ?? null,
            'resource_id' => $resource['id'] ?? null,
            'policy_version' => $decision->policyVersion,
            'details' => $details,
            'trace' => $decision->trace,
        ]);
    }

    /**
     * Record a security change that has been made.
     *
     * @param  array{type: string, id: string|null}  $actor
     * @param  array{type: string, id: string|null}|null  $resource
     * @param  array<string, mixed>  $details  before/after summaries; no secrets
     */
    public function recordChange(string $workspaceId, string $action, array $actor, ?array $resource, array $details = [], string $reasonCode = 'changed'): AuthorizationLedgerEntry
    {
        return $this->append($workspaceId, [
            'kind' => AuthorizationLedgerEntry::KIND_SECURITY_CHANGE,
            'action' => $action,
            'effect' => Decision::ALLOW,
            'reason_code' => $reasonCode,
            'reason' => null,
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'on_behalf_of_id' => null,
            'resource_type' => $resource['type'] ?? null,
            'resource_id' => $resource['id'] ?? null,
            'policy_version' => PolicyEngine::VERSION,
            'details' => $details,
            'trace' => [],
        ]);
    }

    /**
     * Recompute the workspace's hash chain.
     *
     * @return array{ok: bool, checked: int, broken_at: int|null}
     */
    public function verify(string $workspaceId): array
    {
        $previous = null;
        $expectedSequence = 1;
        $checked = 0;

        foreach (AuthorizationLedgerEntry::query()->where('workspace_id', $workspaceId)->orderBy('sequence')->cursor() as $entry) {
            $row = $this->rowFor($entry);
            if ($entry->sequence !== $expectedSequence || $entry->prev_hash !== $previous || ! hash_equals($this->hash($row), $entry->entry_hash)) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => $entry->sequence];
            }
            $previous = $entry->entry_hash;
            $expectedSequence++;
            $checked++;
        }

        return ['ok' => true, 'checked' => $checked, 'broken_at' => null];
    }

    /**
     * Correlation id for the current unit of work: the request's
     * X-Request-Id when it is a safe token, otherwise one generated id per
     * HTTP request (memoized on the request); a fresh id per entry in
     * queue workers and console commands.
     */
    public function correlationId(): string
    {
        // Queue workers and commands reuse one Request object for many jobs;
        // memoizing on it would correlate unrelated work. Their entries carry
        // the agent run id instead.
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return (string) Str::ulid();
        }

        $request = request();
        $memo = $request->attributes->get('opencompany.correlation_id');
        if (is_string($memo)) {
            return $memo;
        }

        $header = (string) $request->headers->get('X-Request-Id', '');
        $id = preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $header) === 1 ? $header : (string) Str::ulid();
        $request->attributes->set('opencompany.correlation_id', $id);

        return $id;
    }

    /**
     * Replace secret-looking keys and truncate long strings, recursively.
     *
     * @param  array<mixed>  $details
     * @return array<mixed>
     */
    public function redact(array $details): array
    {
        $out = [];
        foreach ($details as $key => $value) {
            if (is_string($key) && ! in_array($key, self::SAFE_KEYS, true) && preg_match(self::REDACT_KEY, $key) === 1) {
                $out[$key] = '[redacted]';

                continue;
            }
            $out[$key] = match (true) {
                is_array($value) => $this->redact($value),
                is_string($value) => Str::limit($value, self::MAX_STRING),
                is_scalar($value) || $value === null => $value,
                default => '[unsupported]',
            };
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function append(string $workspaceId, array $fields): AuthorizationLedgerEntry
    {
        $fields['details'] = $this->redact($fields['details'] ?? []);
        $fields['policy_hash'] = PolicyEngine::policyHash();
        $fields['correlation_id'] = $this->correlationId();
        $fields['run_id'] = $this->runId();
        $fields['workspace_id'] = $workspaceId;

        // Invariant: (workspace_id, sequence) is unique, so two concurrent
        // appends cannot both claim the same predecessor. The loser retries
        // against the new tail inside its own savepoint.
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($workspaceId, $fields): AuthorizationLedgerEntry {
                    $tail = AuthorizationLedgerEntry::query()
                        ->where('workspace_id', $workspaceId)
                        ->orderByDesc('sequence')
                        ->first(['sequence', 'entry_hash']);

                    $row = $fields;
                    $row['sequence'] = ($tail === null ? 0 : $tail->sequence) + 1;
                    $row['prev_hash'] = $tail?->entry_hash;
                    $row['occurred_at'] = now()->utc()->startOfSecond();
                    $row['entry_hash'] = $this->hash($row);

                    return AuthorizationLedgerEntry::query()->create($row);
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 5) {
                    throw $exception;
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function rowFor(AuthorizationLedgerEntry $entry): array
    {
        $row = [];
        foreach (self::HASHED as $column) {
            $row[$column] = $entry->getAttribute($column);
        }

        return $row;
    }

    /** @param  array<string, mixed>  $row */
    private function hash(array $row): string
    {
        $canonical = [];
        foreach (self::HASHED as $column) {
            $value = $row[$column] ?? null;
            if ($value instanceof \DateTimeInterface) {
                $value = \Illuminate\Support\Carbon::instance($value)->utc()->format('Y-m-d\TH:i:s\Z');
            } elseif (is_array($value)) {
                $value = $this->canonicalize($value);
            } elseif ($column === 'sequence') {
                $value = (int) $value;
            }
            $canonical[$column] = $value;
        }

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Sort associative keys recursively so database JSON round trips
     * (PostgreSQL jsonb reorders keys) hash identically.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function canonicalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => is_array($item) ? $this->canonicalize($item) : $item, $value);
    }

    private function runId(): ?string
    {
        if (! app()->bound(ActiveAgentRun::class)) {
            return null;
        }
        $id = app(ActiveAgentRun::class)->recordOrNull()?->id;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
