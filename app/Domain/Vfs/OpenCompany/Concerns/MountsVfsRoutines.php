<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Read-only routine mount.
 *
 * Listing metadata (name, active) stays visible to every agent. The body
 * fields that carry instructions, code or external data
 * ($routinePrivateFields) are only served to agents allowed the routine read
 * tool (`get_routine`) without approval, so the VFS never shows more than the
 * domain tool would without a human. Routines replace Automations
 * (docs/planning/connections-events-routines.md).
 */
trait MountsVfsRoutines
{
    /**
     * Fields hidden unless the agent may use get_routine.
     *
     * @var list<string>
     */
    private static array $routinePrivateFields = ['instructions', 'script', 'last_result', 'content', 'config'];

    /**
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    private function listRoutines(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        return $this->pageRoutines($agent, $segments, $path, $budget, [])['entries'];
    }

    /**
     * Page workspace-owned routines by creation time and id. Updated runtime
     * fields such as next_run_at must not move records across a browse cursor.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $state
     * @return array{entries: list<VfsEntry>, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function pageRoutines(User $agent, array $segments, string $path, VfsBudget $budget, array $state): array
    {
        if (count($segments) > 1) {
            throw VfsError::notReadable($path);
        }

        return $this->paginateVfsTimestampQuery(
            Routine::forWorkspace(),
            'created_at',
            'desc',
            $budget->maxEntries,
            $budget->maxFiles,
            $state,
            fn (Routine $routine): VfsEntry => $this->routineEntry($routine, "/routines/{$routine->id}.json"),
        );
    }

    /**
     * @param  list<string>  $segments
     */
    private function readRoutines(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1) {
            return $this->entriesToText($this->listRoutines($agent, $segments, $path, $budget));
        }
        if (count($segments) !== 2) {
            throw VfsError::notFound($path);
        }

        $routine = $this->resolveRoutineToken(Str::before($segments[1], '.json'), $path);
        $payload = $routine->toArray();

        // Invariant: approval-gated means "not without a human". Serving the
        // body when get_routine is allowed only with approval would bypass that
        // gate; redact and point at the gated tool instead (a read never
        // auto-creates an approval request).
        $decision = $this->permissions->resolveToolPermission($agent, 'get_routine', 'read');
        if (! $decision['allowed'] || $decision['requires_approval']) {
            $hidden = [];
            foreach (self::$routinePrivateFields as $field) {
                if (array_key_exists($field, $payload)) {
                    unset($payload[$field]);
                    $hidden[] = $field;
                }
            }
            $payload['redacted_fields'] = $hidden;
            $payload['redaction_reason'] = $decision['allowed']
                ? 'Routine instructions, code and results need human approval for this agent: call the get_routine tool, which asks for approval, instead of reading this file.'
                : 'Routine instructions, code and results require the get_routine tool permission.';
        }

        return json_encode($payload, JSON_PRETTY_PRINT);
    }

    private function routineEntry(Routine $routine, string $path): VfsEntry
    {
        return new VfsEntry(
            $this->slug($routine->name).'--'.substr($routine->id, 0, 8).'.json',
            $path,
            'file',
            "/routines/{$routine->id}.json",
            'routine',
            $routine->id,
            capabilities: ['read'],
            metadata: [
                'name' => $routine->name,
                'active' => $routine->is_active,
                ...$this->readOnlyMetadata(
                    'Routine JSON is a generated routine record.',
                    'routine tools',
                    'Use routine tools to create, update, run, pause, or delete routines.',
                ),
            ],
        );
    }

    private function resolveRoutineToken(string $token, string $path): Routine
    {
        if (preg_match('/^(.*)--([a-f0-9]{8})$/i', $token, $parts) === 1) {
            return Routine::forWorkspace()
                ->tap(fn ($query) => $this->whereIdLike($query, 'id', $parts[2].'%'))
                ->get()
                ->first(fn (Routine $candidate): bool => $this->slug($candidate->name) === $parts[1])
                ?? throw VfsError::notFound($path);
        }

        $byId = Routine::forWorkspace()->find($token);
        if ($byId) {
            return $byId;
        }

        $matches = Routine::forWorkspace()
            ->lazy(100)
            ->filter(fn (Routine $candidate): bool => $this->slug($candidate->name) === $token)
            ->take(2);
        if ($matches->count() === 1) {
            return $matches->first();
        }
        if ($matches->count() > 1) {
            throw VfsError::invalid("Routine slug is ambiguous: {$token}. Use /routines/{uuid}.json.");
        }

        throw VfsError::notFound($path);
    }
}
