<?php

namespace App\Domain\Routines\Application;

use App\Domain\Routines\Domain\RoutineSchedule;
use App\Jobs\RunBuiltinRoutineJob;
use App\Jobs\RunRoutineJob;
use App\Models\Routine;
use App\Models\Task;
use App\Services\ScriptAdmission;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Workspace routine management use cases (API and UI).
 *
 * Owns schedule/field validation, CRUD, manual dispatch, run history, and
 * schedule previews. Execution stays in the queued routine runtime so web
 * requests never run user routines inline.
 *
 * Input uses the API's camelCase keys: `kind` (`agent` | `script`),
 * `instructions`, `script`, `mode`, `notify`, `cronExpression`, `timezone`,
 * `agentId`, `channelId`, `keepHistory`, `isActive`.
 *
 * Invariants:
 *  - every query is scoped with `forWorkspace()`;
 *  - Ruby source is admitted (validated with the pinned engine) before it is
 *    stored, so a syntax failure never becomes a scheduled incident;
 *  - built-in routines are system-managed: they cannot be created, edited or
 *    deleted here (BuiltinRoutineRegistry owns their rows);
 *  - a manual run is a human action and starts its own causality chain.
 */
class ManageRoutines
{
    /** @return Collection<int, Routine> */
    public function list(): Collection
    {
        return Routine::forWorkspace()
            ->with(['agent', 'channel', 'createdBy'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the Ruby source is not admitted
     */
    public function create(array $data): Routine
    {
        $kind = $data['kind'] ?? Routine::KIND_AGENT;
        $this->assertUserKind($kind);
        $admission = $kind === Routine::KIND_SCRIPT
            ? app(ScriptAdmission::class)->admit((string) ($data['script'] ?? '')) : [];

        return Routine::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'kind' => $kind,
            'agent_id' => $data['agentId'],
            'instructions' => $kind === Routine::KIND_AGENT ? ($data['instructions'] ?? null) : null,
            'script' => $kind === Routine::KIND_SCRIPT ? ($data['script'] ?? null) : null,
            ...$admission,
            'mode' => $data['mode'] ?? Routine::MODE_ACT,
            'notify' => $data['notify'] ?? Routine::NOTIFY_ALWAYS,
            'cron_expression' => $data['cronExpression'],
            'timezone' => $data['timezone'] ?? 'UTC',
            'channel_id' => $data['channelId'] ?? null,
            'keep_history' => $this->booleanValue($data['keepHistory'] ?? true),
            'created_by_id' => $data['createdById'],
            'is_active' => true,
        ])->load(['agent', 'channel', 'createdBy']);
    }

    public function show(string $id): Routine
    {
        return Routine::forWorkspace()
            ->with(['agent', 'channel', 'createdBy'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException for built-ins, unknown kinds, or unadmitted Ruby
     */
    public function update(string $id, array $input): Routine
    {
        $routine = Routine::forWorkspace()->findOrFail($id);
        $this->assertEditable($routine);
        $data = [];

        foreach (['name', 'description', 'instructions', 'script', 'timezone', 'mode', 'notify'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field];
            }
        }

        foreach (['agentId' => 'agent_id', 'channelId' => 'channel_id', 'cronExpression' => 'cron_expression'] as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $data[$column] = $input[$inputKey];
            }
        }

        if (array_key_exists('kind', $input)) {
            $this->assertUserKind($input['kind']);
            $data['kind'] = $input['kind'];
        }

        $targetKind = $data['kind'] ?? $routine->kind;
        if ($targetKind === Routine::KIND_SCRIPT && array_key_exists('script', $input)) {
            $data = [...$data, ...app(ScriptAdmission::class)->admit((string) $data['script'])];
        } elseif ($targetKind === Routine::KIND_SCRIPT
            && $this->booleanValue($input['isActive'] ?? $routine->is_active)
            && ! app(ScriptAdmission::class)->matches($routine)) {
            throw ValidationException::withMessages(['script' => 'Submit and validate a Ruby body before enabling this stale routine.']);
        } elseif ($targetKind === Routine::KIND_AGENT) {
            $data['script_runtime'] = null;
        }
        if (array_key_exists('isActive', $input)) {
            $data['is_active'] = $this->booleanValue($input['isActive']);
            if ($data['is_active']) {
                $data['consecutive_failures'] = 0;
            }
        }
        if (array_key_exists('keepHistory', $input)) {
            $data['keep_history'] = $this->booleanValue($input['keepHistory']);
        }

        $routine->update($data);

        if (array_key_exists('cronExpression', $input) || array_key_exists('timezone', $input)) {
            $routine->refreshNextRunAt();
        }

        return $routine->load(['agent', 'channel', 'createdBy']);
    }

    /** @throws ValidationException for built-in routines */
    public function delete(string $id): void
    {
        $routine = Routine::forWorkspace()->findOrFail($id);
        $this->assertEditable($routine);
        $routine->delete();
    }

    /**
     * Built-in rows are skipped silently and reported as not deleted.
     *
     * @param  array<int, string>  $ids
     * @return array{deleted: int}
     */
    public function bulkDelete(array $ids): array
    {
        return [
            'deleted' => Routine::forWorkspace()
                ->whereIn('id', $ids)
                ->where('kind', '!=', Routine::KIND_BUILTIN)
                ->delete(),
        ];
    }

    /**
     * @param  array<int, string>  $ids
     * @return array{triggered: int, skipped: int}
     */
    public function bulkRun(array $ids): array
    {
        $routines = Routine::forWorkspace()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get();

        foreach ($routines as $routine) {
            $this->dispatch($routine);
        }

        return [
            'triggered' => $routines->count(),
            'skipped' => count($ids) - $routines->count(),
        ];
    }

    /**
     * Recent runs (newest first, at most 20) of one workspace routine.
     *
     * @return list<array<string, mixed>>
     */
    public function runs(string $id): array
    {
        return Task::forWorkspace()
            ->with(['agent'])
            ->where('source', Task::SOURCE_ROUTINE)
            ->whereJsonContains('context->routine_id', $id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'runNumber' => $task->context['run_number'] ?? null,
                'version' => $task->context['routine_version'] ?? null,
                'causalityId' => $task->causality_id,
                'causalityDepth' => $task->causality_depth,
                'result' => $task->result,
                'agentName' => $task->agent?->name,
                'startedAt' => $task->started_at,
                'completedAt' => $task->completed_at,
                'createdAt' => $task->created_at,
            ])
            ->values()
            ->all();
    }

    public function run(string $id): void
    {
        $this->dispatch(Routine::forWorkspace()->findOrFail($id));
    }

    /**
     * @return array<int, string>
     */
    public function previewSchedule(string $cronExpression, string $timezone = 'UTC'): array
    {
        return array_map(
            fn ($run) => $run->toIso8601String(),
            (new RoutineSchedule($cronExpression, $timezone))->nextRuns(5),
        );
    }

    /** Manual dispatch; no trigger event, so the run starts a root chain. */
    private function dispatch(Routine $routine): void
    {
        if ($routine->isBuiltin()) {
            RunBuiltinRoutineJob::dispatch($routine);

            return;
        }

        RunRoutineJob::dispatch($routine);
    }

    /** @throws ValidationException */
    private function assertUserKind(mixed $kind): void
    {
        if (! in_array($kind, [Routine::KIND_AGENT, Routine::KIND_SCRIPT], true)) {
            throw ValidationException::withMessages(['kind' => "kind must be 'agent' or 'script'."]);
        }
    }

    /** @throws ValidationException */
    private function assertEditable(Routine $routine): void
    {
        if ($routine->isBuiltin()) {
            throw ValidationException::withMessages(['routine' => 'Built-in routines are managed by OpenCompany and cannot be edited or deleted.']);
        }
    }

    private function booleanValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
