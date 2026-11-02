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
 */
trait MountsVfsRoutines
{
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

        return json_encode($routine->toArray(), JSON_PRETTY_PRINT);
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
                ->where('id', 'like', $parts[2].'%')
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
