<?php

namespace App\Domain\AgentRuntime\Application;

use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Runtime\AmbiguousAgentToolExecution;
use App\Models\AgentToolExecution;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use JsonSerializable;
use Stringable;

/**
 * Executes agent tools through a durable pre-call idempotency ledger.
 *
 * The database can prove that a call was claimed before entering external or
 * mutating code, but it cannot atomically commit with a third-party system.
 * Consequently, a call left running or one that throws is marked ambiguous and
 * is never repeated automatically. A completed identical retry reuses the
 * encrypted result. This is at-most-once safety, not a claim of distributed
 * exactly-once execution.
 */
class IdempotentToolExecutor
{
    /**
     * @param  array<string|int, mixed>  $arguments
     * @param  callable(): mixed  $callback
     */
    public function execute(
        ActiveAgentRun $activeRun,
        string $surface,
        int $sequence,
        string $toolName,
        array $arguments,
        callable $callback,
        ?string $toolInvocationId = null,
    ): mixed {
        $activeRun->checkpoint(force: true);
        $run = $activeRun->record();
        $signature = $this->signature($toolName, $arguments);
        $now = now();

        // insertOrIgnore makes the unique run/surface/sequence key the arbiter
        // during a duplicate delivery. Raw arguments are intentionally not
        // persisted because tool inputs can contain workspace secrets.
        $inserted = AgentToolExecution::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'workspace_id' => $run->workspace_id,
            'agent_run_id' => $run->id,
            'surface' => $surface,
            'sequence' => $sequence,
            'tool_name' => $toolName,
            'signature' => $signature,
            'tool_invocation_id' => $toolInvocationId,
            'status' => AgentToolExecution::STATUS_RUNNING,
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $execution = AgentToolExecution::query()
            ->where('agent_run_id', $run->id)
            ->where('surface', $surface)
            ->where('sequence', $sequence)
            ->firstOrFail();

        if ($inserted === 0) {
            return $this->resolveExisting(
                $execution,
                $signature,
                $toolName,
                $sequence,
                $toolInvocationId,
            );
        }

        try {
            $result = $callback();

            // Cancellation after an external response still suppresses later
            // calls. If this checkpoint throws, the just-finished call remains
            // ambiguous rather than being advertised as safely replayable.
            $activeRun->checkpoint(force: true);
            $execution->markCompleted($this->encodeResult($result));

            return $result;
        } catch (\Throwable $exception) {
            try {
                $execution->markAmbiguous($exception);
            } catch (\Throwable) {
                // Preserve the execution exception. A database outage may also
                // prevent the ambiguity update, but the pre-call running record
                // remains a replay blocker once storage is available again.
            }

            throw $exception;
        }
    }

    private function resolveExisting(
        AgentToolExecution $execution,
        string $signature,
        string $toolName,
        int $sequence,
        ?string $toolInvocationId,
    ): mixed {
        if ($execution->signature !== $signature) {
            $execution->markAmbiguous('A retry produced a different tool call at the same logical sequence.');

            throw $this->ambiguous($toolName, $sequence, 'retry diverged');
        }

        if ($toolInvocationId !== null && $execution->tool_invocation_id !== $toolInvocationId) {
            // The SDK creates a new diagnostic invocation ID on each provider
            // retry. Keep the latest value so the post-call TaskStep can refer
            // back to the replayed ledger entry.
            $execution->update(['tool_invocation_id' => $toolInvocationId]);
        }

        if ($execution->status === AgentToolExecution::STATUS_COMPLETED) {
            return $this->decodeResult($execution->result_payload);
        }

        if ($execution->status === AgentToolExecution::STATUS_RUNNING) {
            $execution->markAmbiguous('The prior worker stopped before recording the tool result.');
        }

        throw $this->ambiguous($toolName, $sequence, 'prior outcome is unknown');
    }

    /** @param array<string|int, mixed> $arguments */
    private function signature(string $toolName, array $arguments): string
    {
        return hash('sha256', json_encode([
            'tool' => $toolName,
            'arguments' => $this->normalize($arguments),
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
            }

            ksort($value, SORT_STRING);

            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        if ($value instanceof \BackedEnum) {
            return ['__enum' => $value::class, 'value' => $value->value];
        }

        if ($value instanceof \UnitEnum) {
            return ['__enum' => $value::class, 'name' => $value->name];
        }

        if ($value instanceof \DateTimeInterface) {
            return ['__datetime' => $value->format(DATE_ATOM)];
        }

        if ($value instanceof JsonSerializable) {
            return $this->normalize($value->jsonSerialize());
        }

        if ($value instanceof Arrayable) {
            return $this->normalize($value->toArray());
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_object($value)) {
            return [
                '__class' => $value::class,
                'properties' => $this->normalize(get_object_vars($value)),
            ];
        }

        if (is_resource($value)) {
            return ['__resource' => get_resource_type($value)];
        }

        if (is_float($value) && ! is_finite($value)) {
            return ['__float' => (string) $value];
        }

        return $value;
    }

    /**
     * The payload is encrypted by the model cast before storage. PHP's native
     * serializer preserves the scalar, array, and Stringable values accepted by
     * tool and Lua surfaces without lossy type conversion.
     */
    private function encodeResult(mixed $result): string
    {
        return base64_encode(serialize($result));
    }

    private function decodeResult(?string $payload): mixed
    {
        if ($payload === null || ($decoded = base64_decode($payload, true)) === false) {
            throw new \RuntimeException('Completed tool execution has no valid replay payload.');
        }

        $result = @unserialize($decoded, ['allowed_classes' => true]);
        if ($result === false && $decoded !== 'b:0;') {
            throw new \RuntimeException('Completed tool execution replay payload is invalid.');
        }

        return $result;
    }

    private function ambiguous(string $toolName, int $sequence, string $reason): AmbiguousAgentToolExecution
    {
        return new AmbiguousAgentToolExecution(
            "Tool [{$toolName}] at logical sequence [{$sequence}] was not repeated because its {$reason}.",
        );
    }
}
