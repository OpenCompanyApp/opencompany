<?php

namespace App\Http\Controllers\Api;

use App\Domain\Work\Application\ManageTasks;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskStep;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API adapter for workspace tasks.
 *
 * Task query shape, lifecycle transitions, step mutations, and agent dispatch
 * decisions live in the Work domain context. This controller only translates
 * HTTP request/response details for the task UI.
 */
class TaskController extends Controller
{
    public function __construct(private ManageTasks $tasks) {}

    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        return $this->tasks->list($request->all());
    }

    public function show(string $id): Task
    {
        return $this->tasks->show($id);
    }

    public function store(Request $request): Task
    {
        return $this->tasks->create($request->all());
    }

    public function update(Request $request, string $id): Task
    {
        return $this->tasks->update($id, $request->all());
    }

    public function destroy(string $id): JsonResponse
    {
        $this->tasks->delete($id);

        return response()->json(['success' => true]);
    }

    public function start(string $id): Task
    {
        return $this->tasks->start($id);
    }

    public function pause(string $id): Task
    {
        return $this->tasks->transition($id, 'pause');
    }

    public function resume(string $id): Task
    {
        return $this->tasks->transition($id, 'resume');
    }

    public function complete(Request $request, string $id): Task
    {
        return $this->tasks->transition($id, 'complete', $request->input('result'));
    }

    public function fail(Request $request, string $id): Task
    {
        return $this->tasks->transition($id, 'fail', $request->input('reason'));
    }

    public function cancel(string $id): Task
    {
        return $this->tasks->transition($id, 'cancel');
    }

    /**
     * @return Collection<int, TaskStep>
     */
    public function steps(string $id)
    {
        return $this->tasks->steps($id);
    }

    public function addStep(Request $request, string $id): TaskStep
    {
        return $this->tasks->addStep($id, $request->all());
    }

    public function updateStep(Request $request, string $taskId, string $stepId): TaskStep
    {
        return $this->tasks->updateStep($taskId, $stepId, $request->all());
    }

    public function completeStep(string $taskId, string $stepId): TaskStep
    {
        return $this->tasks->completeStep($taskId, $stepId);
    }
}
