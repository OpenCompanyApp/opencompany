<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use Illuminate\Support\Str;
use OpenCompany\IntegrationCore\Script\ScriptDispatchException;

/**
 * Persists the minimal intent and outcome evidence for one Code Mode write.
 *
 * TaskStep is the existing task authority: this service deliberately does not
 * create a second journal or retain credentials, request bodies, provider
 * payloads, or exception text. It records only stable identifiers and digests
 * needed to correlate a callback with a task and determine that an ambiguous
 * provider failure must not be replayed automatically.
 *
 * CodeExec and ExecuteScriptAutomation invoke the bridge after their task
 * creation transactions have committed. This service cannot make an external
 * provider call atomic with the application database; future callers must not
 * wrap callback dispatch in a transaction that can roll this intent back.
 */
final class ScriptCallbackReceiptService
{
    private const MAX_CANONICAL_DEPTH = 64;

    /**
     * Create the durable intent before the provider-facing tool is invoked.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @throws ScriptDispatchException when the executing task/source identity is absent or out of scope
     */
    public function begin(
        User $agent,
        ?string $taskId,
        ?string $sourceDigest,
        ?string $codeInvocationId,
        int $sequence,
        string $toolSlug,
        array $arguments,
        ?string $account,
    ): TaskStep {
        if ($taskId === null || $taskId === '') {
            throw ScriptDispatchException::denied('A task receipt is required before a write callback can run.');
        }

        if (! is_string($sourceDigest) || ! preg_match('/^[a-f0-9]{64}$/', $sourceDigest)
            || ! is_string($codeInvocationId) || $codeInvocationId === '' || $sequence < 1) {
            throw ScriptDispatchException::denied('This write callback has no bound source receipt. Start a new code execution.');
        }

        // The registry task ID is untrusted correlation context. Match both the
        // agent and workspace before persisting any write intent under the task.
        $task = Task::query()
            ->whereKey($taskId)
            ->where('workspace_id', $agent->workspace_id)
            ->where('agent_id', $agent->id)
            ->where('status', Task::STATUS_ACTIVE)
            ->first();

        if ($task === null) {
            throw ScriptDispatchException::denied('The current task is not active for this agent in its workspace.');
        }

        $requestDigest = $this->canonicalDigest([
            'tool_slug' => $toolSlug,
            'arguments' => $arguments,
            // Account aliases can identify an operator account. Include only a
            // digest in the durable record, never the alias itself.
            'account' => $account,
        ]);

        return TaskStep::create([
            'id' => Str::uuid()->toString(),
            'task_id' => $task->id,
            'description' => "Code callback intent: {$toolSlug}",
            'status' => TaskStep::STATUS_IN_PROGRESS,
            'step_type' => TaskStep::TYPE_ACTION,
            'started_at' => now(),
            'metadata' => [
                'script_callback' => [
                    'schema_version' => 1,
                    'code_invocation_id' => $codeInvocationId,
                    'sequence' => $sequence,
                    'source_digest' => $sourceDigest,
                    'canonical_request_digest' => $requestDigest,
                    'tool_slug' => $toolSlug,
                    'disposition' => 'intent_recorded',
                ],
            ],
        ]);
    }

    /** Record a successful provider result by digest only. */
    public function succeeded(TaskStep $step, mixed $result): void
    {
        $receiptDigest = $this->safeDigest($result);

        $this->finish($step, 'succeeded', $receiptDigest, $receiptDigest === null);
    }

    /**
     * Record an ambiguous result without provider error text, then let the
     * original exception propagate. Callers must inspect state, not replay it.
     */
    public function unknown(TaskStep $step): void
    {
        $this->finish($step, 'unknown', null, false);
    }

    private function finish(TaskStep $step, string $disposition, ?string $receiptDigest, bool $receiptDigestUnavailable): void
    {
        $metadata = $step->metadata ?? [];
        $callback = $metadata['script_callback'] ?? [];
        $callback['disposition'] = $disposition;
        $callback['receipt_digest'] = $receiptDigest;
        $callback['receipt_digest_unavailable'] = $receiptDigestUnavailable;
        $callback['automatic_replay'] = false;
        $metadata['script_callback'] = $callback;

        $step->update([
            'metadata' => $metadata,
            'status' => TaskStep::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $value */
    private function canonicalDigest(array $value): string
    {
        return hash('sha256', json_encode($this->canonicalize($value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function safeDigest(mixed $value): ?string
    {
        try {
            return hash('sha256', json_encode($this->canonicalize($value, seen: new \SplObjectStorage), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        } catch (\Throwable) {
            // A digest is evidence, not an execution dependency. Do not invent
            // a type-derived value that could be mistaken for a result receipt.
            return null;
        }
    }

    /** Convert associative maps into a deterministic, non-persisted hash input. */
    private function canonicalize(mixed $value, int $depth = 0, ?\SplObjectStorage $seen = null): mixed
    {
        // Recursive PHP arrays can be produced by host-side callers. Refuse a
        // bounded digest rather than walking an untrusted graph indefinitely.
        if ($depth > self::MAX_CANONICAL_DEPTH) {
            throw new \InvalidArgumentException('Receipt value exceeds the supported nesting depth.');
        }

        $seen ??= new \SplObjectStorage;

        if ($value instanceof \stdClass) {
            if ($seen->contains($value)) {
                throw new \InvalidArgumentException('Receipt value contains a cyclic object.');
            }
            $seen->attach($value);

            $properties = get_object_vars($value);
            $keys = array_keys($properties);
            sort($keys, SORT_STRING);
            $normalized = [];
            foreach ($keys as $key) {
                $normalized[(string) $key] = $this->canonicalize($properties[$key], $depth + 1, $seen);
            }

            // Tagging keeps {} and {"0": ...} distinct from PHP lists.
            return ['receipt_type' => 'object', 'properties' => $normalized];
        }

        if (! is_array($value)) {
            if (is_object($value) || is_resource($value)) {
                throw new \InvalidArgumentException('Unsupported receipt value.');
            }

            return $value;
        }

        if (array_is_list($value)) {
            return [
                'receipt_type' => 'list',
                'items' => array_map(fn (mixed $item): mixed => $this->canonicalize($item, $depth + 1, $seen), $value),
            ];
        }

        $normalized = [];
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        foreach ($keys as $key) {
            $normalized[(string) $key] = $this->canonicalize($value[$key], $depth + 1, $seen);
        }

        return ['receipt_type' => 'map', 'entries' => $normalized];
    }
}
