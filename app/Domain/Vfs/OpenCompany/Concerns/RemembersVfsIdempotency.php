<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\User;
use App\Models\VfsIdempotencyKey;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Optional `idempotency_key` on VFS mutations (write, patch, rm, mv, mkdir).
 *
 * Contract:
 * - The key comes from the tool parameters the tools already pass
 *   (`$toolParameters['idempotency_key']`), scoped to the acting agent and
 *   remembered for 24 hours.
 * - The first call claims the key before mutating (a row with a null result),
 *   so a concurrent duplicate gets `conflict` ("still in progress") instead of
 *   mutating twice. On success the result is stored; on any failure
 *   (including approval_required) the claim is released so a retry runs
 *   normally (pending approvals dedupe on their own).
 * - A repeat with the same key and the same operation + path returns the
 *   stored result with `idempotent_replay: true` and does not mutate.
 *   Reusing a key for a different operation or path is `invalid_request`.
 */
trait RemembersVfsIdempotency
{
    /**
     * @param  array<string, mixed>|null  $toolParameters
     * @param  callable(): array<string, mixed>  $run
     * @return array<string, mixed>
     */
    private function withIdempotency(User $agent, ?array $toolParameters, string $operation, string $subject, callable $run): array
    {
        $key = $toolParameters['idempotency_key'] ?? null;
        if ($key === null || $key === '') {
            return $run();
        }
        if (! is_string($key) || strlen($key) > 191) {
            throw VfsError::invalid('idempotency_key must be a string of at most 191 characters.', ['field' => 'idempotency_key']);
        }

        $claim = $this->claimIdempotencyKey($agent, $key, $operation, $subject);
        if (is_array($claim)) {
            return $claim;
        }

        try {
            $result = $run();
        } catch (\Throwable $e) {
            $claim->delete();

            throw $e;
        }

        $claim->forceFill(['result' => $result])->save();

        return $result;
    }

    /**
     * Claim the key, or return the stored result of an earlier identical call.
     *
     * @return VfsIdempotencyKey|array<string, mixed>
     */
    private function claimIdempotencyKey(User $agent, string $key, string $operation, string $subject): VfsIdempotencyKey|array
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $existing = VfsIdempotencyKey::query()
                ->where('agent_id', $agent->id)
                ->where('idempotency_key', $key)
                ->first();

            if ($existing !== null && $existing->expires_at->isPast()) {
                $existing->delete();
                $existing = null;
            }

            if ($existing !== null) {
                if ($existing->operation !== $operation || $existing->path !== $subject) {
                    throw VfsError::invalid('idempotency_key was already used for a different operation.', [
                        'field' => 'idempotency_key',
                        'used_for' => ['operation' => $existing->operation, 'path' => $existing->path],
                        'hint' => 'Use a fresh key for each distinct operation.',
                    ]);
                }
                if ($existing->result === null) {
                    throw VfsError::conflict($subject, null, null, 'An operation with this idempotency_key is still running. Retry shortly with the same key.');
                }

                return [...$existing->result, 'idempotent_replay' => true];
            }

            try {
                return VfsIdempotencyKey::create([
                    'workspace_id' => (string) $agent->workspace_id,
                    'agent_id' => (string) $agent->id,
                    'idempotency_key' => $key,
                    'operation' => $operation,
                    'path' => $subject,
                    'result' => null,
                    'expires_at' => now()->addDay(),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Lost the race to a concurrent identical call: re-read it.
            }
        }

        throw VfsError::conflict($subject, null, null, 'An operation with this idempotency_key is still running. Retry shortly with the same key.');
    }
}
