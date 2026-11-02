<?php

namespace App\Http\Controllers\Api;

use App\Domain\Routines\Application\ManageRoutines;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoutineResource;
use App\Models\Routine;
use Cron\CronExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * API adapter for workspace routines (`/api/routines*`).
 *
 * The Routines domain context (ManageRoutines) owns CRUD semantics, the
 * built-in guard, manual dispatch, run history, and schedule previews. This
 * controller keeps HTTP validation and response details close to the route
 * boundary. Every lookup goes through ManageRoutines, which scopes to the
 * current workspace; script revisions are read only after the parent routine
 * resolved in that workspace.
 */
class RoutineController extends Controller
{
    public function __construct(private ManageRoutines $routines) {}

    /**
     * @return array<int, mixed>
     */
    public function index(): array
    {
        return RoutineResource::collection($this->routines->list())->resolve();
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    public function store(Request $request): array|JsonResponse
    {
        $kind = $request->input('kind', Routine::KIND_AGENT);
        $rules = [
            'name' => 'required|string|max:255',
            'agentId' => 'required|exists:users,id',
            // Built-in routines are code-defined; users create agent or script routines.
            'kind' => 'nullable|string|in:agent,script',
            'mode' => 'nullable|string|in:'.implode(',', Routine::MODES),
            'notify' => 'nullable|string|in:'.implode(',', Routine::NOTIFIES),
            'cronExpression' => 'required|string',
            'timezone' => 'nullable|string|timezone',
        ];
        $rules[$kind === Routine::KIND_SCRIPT ? 'script' : 'instructions'] = $kind === Routine::KIND_SCRIPT
            ? 'required|string|max:524288'
            : 'required|string|max:10000';

        $request->validate($rules);

        if (! CronExpression::isValidExpression($request->input('cronExpression'))) {
            return response()->json(['message' => 'Invalid cron expression'], 422);
        }

        return $this->routineResource($this->routines->create([
            ...$request->all(),
            ...$this->routineBooleans($request),
            'createdById' => $request->user()->id,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function show(string $id): array
    {
        $routine = $this->routines->show($id);
        $data = $this->routineResource($routine);
        $data['nextRuns'] = collect($routine->getNextRuns(5))
            ->map(fn ($run) => $run->toIso8601String());

        return $data;
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    public function update(Request $request, string $id): array|JsonResponse
    {
        $request->validate([
            'kind' => 'sometimes|string|in:agent,script',
            'mode' => 'sometimes|string|in:'.implode(',', Routine::MODES),
            'notify' => 'sometimes|string|in:'.implode(',', Routine::NOTIFIES),
            'instructions' => 'sometimes|nullable|string|max:10000',
        ]);

        if ($request->has('cronExpression') && ! CronExpression::isValidExpression($request->input('cronExpression'))) {
            return response()->json(['message' => 'Invalid cron expression'], 422);
        }

        return $this->routineResource($this->routines->update($id, [
            ...$request->all(),
            ...$this->routineBooleans($request),
        ]));
    }

    public function destroy(string $id): JsonResponse
    {
        $this->routines->delete($id);

        return response()->json(['success' => true]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|string',
        ]);

        return response()->json(array_merge(
            ['success' => true],
            $this->routines->bulkDelete($request->input('ids')),
        ));
    }

    public function bulkTriggerRun(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|string',
        ]);

        return response()->json(array_merge(
            ['success' => true],
            $this->routines->bulkRun($request->input('ids')),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function runs(string $id): array
    {
        return $this->routines->runs($id);
    }

    public function triggerRun(string $id): JsonResponse
    {
        $this->routines->run($id);

        return response()->json(['message' => 'Run dispatched']);
    }

    /**
     * Page immutable revision metadata without sending every historical body.
     * Resolve the parent in the active workspace before touching its history;
     * archived source is user data, never a global developer catalog resource.
     *
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function scriptRevisions(string $id): LengthAwarePaginator
    {
        $routine = $this->routines->show($id);

        return DB::table('routine_script_revisions')
            ->where('workspace_id', $routine->workspace_id)
            ->where('routine_id', $routine->id)
            ->select(['id', 'runtime', 'source_digest', 'engine_digest', 'status', 'created_at'])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
    }

    /** Read exactly one preserved source; selecting it never admits or executes it. */
    public function scriptRevision(string $id, string $revision): object
    {
        $routine = $this->routines->show($id);
        $row = DB::table('routine_script_revisions')
            ->where('workspace_id', $routine->workspace_id)
            ->where('routine_id', $routine->id)
            ->where('id', $revision)
            ->first(['id', 'runtime', 'source', 'source_digest', 'engine_digest', 'status', 'created_at']);
        abort_if($row === null, 404);

        return $row;
    }

    public function previewSchedule(Request $request): JsonResponse
    {
        $cronExpr = $request->input('cronExpression');

        if (! $cronExpr || ! CronExpression::isValidExpression($cronExpr)) {
            return response()->json(['message' => 'Invalid cron expression'], 422);
        }

        return response()->json([
            'runs' => $this->routines->previewSchedule($cronExpr, $request->input('timezone', 'UTC')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function routineResource(Routine $routine): array
    {
        return (new RoutineResource($routine))->resolve();
    }

    /**
     * Preserve Laravel request boolean semantics before handing input to the
     * application service, especially for form-style strings like "false".
     *
     * @return array<string, bool>
     */
    private function routineBooleans(Request $request): array
    {
        $booleans = [];

        if ($request->has('isActive')) {
            $booleans['isActive'] = $request->boolean('isActive');
        }
        if ($request->has('keepHistory')) {
            $booleans['keepHistory'] = $request->boolean('keepHistory');
        }

        return $booleans;
    }
}
