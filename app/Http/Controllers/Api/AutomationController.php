<?php

namespace App\Http\Controllers\Api;

use App\Domain\Automations\Application\ManageAutomations;
use App\Http\Controllers\Controller;
use App\Http\Resources\AutomationResource;
use App\Models\Automation;
use Cron\CronExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * API adapter for workspace automations.
 *
 * The Automations domain context owns schedule validation, CRUD semantics,
 * manual dispatch, run history, and schedule previews. This controller keeps
 * HTTP validation and response details close to the route boundary.
 */
class AutomationController extends Controller
{
    public function __construct(private ManageAutomations $automations) {}

    /**
     * @return array<int, mixed>
     */
    public function index(): array
    {
        return AutomationResource::collection($this->automations->list())->resolve();
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    public function store(Request $request): array|JsonResponse
    {
        $executionType = $request->input('executionType', 'prompt');
        $rules = [
            'name' => 'required|string|max:255',
            'agentId' => 'required|exists:users,id',
            'executionType' => 'nullable|string|in:prompt,script',
            'cronExpression' => 'required|string',
            'timezone' => 'nullable|string|timezone',
        ];
        $rules[$executionType === 'script' ? 'script' : 'prompt'] = $executionType === 'script'
            ? 'required|string|max:524288'
            : 'required|string|max:10000';

        $request->validate($rules);

        if (! CronExpression::isValidExpression($request->input('cronExpression'))) {
            return response()->json(['message' => 'Invalid cron expression'], 422);
        }

        return $this->automationResource($this->automations->create([
            ...$request->all(),
            ...$this->automationBooleans($request),
            'createdById' => $request->user()->id,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function show(string $id): array
    {
        $automation = $this->automations->show($id);
        $data = $this->automationResource($automation);
        $data['nextRuns'] = collect($automation->getNextRuns(5))
            ->map(fn ($run) => $run->toIso8601String());

        return $data;
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    public function update(Request $request, string $id): array|JsonResponse
    {
        if ($request->has('executionType') && ! in_array($request->input('executionType'), ['prompt', 'script'], true)) {
            return response()->json(['message' => "executionType must be 'prompt' or 'script'"], 422);
        }

        if ($request->has('cronExpression') && ! CronExpression::isValidExpression($request->input('cronExpression'))) {
            return response()->json(['message' => 'Invalid cron expression'], 422);
        }

        return $this->automationResource($this->automations->update($id, [
            ...$request->all(),
            ...$this->automationBooleans($request),
        ]));
    }

    public function destroy(string $id): JsonResponse
    {
        $this->automations->delete($id);

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
            $this->automations->bulkDelete($request->input('ids')),
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
            $this->automations->bulkRun($request->input('ids')),
        ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function runs(string $id): Collection
    {
        return $this->automations->runs($id);
    }

    public function triggerRun(string $id): JsonResponse
    {
        $this->automations->run($id);

        return response()->json(['message' => 'Run dispatched']);
    }

    /**
     * Page immutable revision metadata without sending every historical body.
     * Resolve the parent in the active workspace before touching its history;
     * archived source is user data, never a global developer catalog resource.
     */
    public function scriptRevisions(string $id): LengthAwarePaginator
    {
        $automation = $this->automations->show($id);

        return DB::table('automation_script_revisions')
            ->where('workspace_id', $automation->workspace_id)
            ->where('automation_id', $automation->id)
            ->select(['id', 'runtime', 'source_digest', 'engine_digest', 'status', 'created_at'])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
    }

    /** Read exactly one preserved source; selecting it never admits or executes it. */
    public function scriptRevision(string $id, string $revision): object
    {
        $automation = $this->automations->show($id);
        $row = DB::table('automation_script_revisions')
            ->where('workspace_id', $automation->workspace_id)
            ->where('automation_id', $automation->id)
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
            'runs' => $this->automations->previewSchedule($cronExpr, $request->input('timezone', 'UTC')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function automationResource(Automation $automation): array
    {
        return (new AutomationResource($automation))->resolve();
    }

    /**
     * Preserve Laravel request boolean semantics before handing input to the
     * application service, especially for form-style strings like "false".
     *
     * @return array<string, bool>
     */
    private function automationBooleans(Request $request): array
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
