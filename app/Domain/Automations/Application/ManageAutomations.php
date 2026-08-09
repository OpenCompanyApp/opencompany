<?php

namespace App\Domain\Automations\Application;

use App\Domain\Automations\Domain\AutomationSchedule;
use App\Jobs\RunAutomationJob;
use App\Models\Automation;
use App\Models\Task;
use App\Services\QuickJsSandboxService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Workspace automation management use cases.
 *
 * This service owns schedule validation, CRUD, manual dispatch, run history,
 * and schedule previews. Actual prompt/script execution remains in the queued
 * automation runtime so web requests never run user automations inline.
 */
class ManageAutomations
{
    public function __construct(
        private QuickJsSandboxService $sandbox,
    ) {}

    /** @return Collection<int, Automation> */
    public function list(): Collection
    {
        return Automation::forWorkspace()
            ->with(['agent', 'channel', 'createdBy'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Automation
    {
        $executionType = $data['executionType'] ?? 'prompt';
        if ($executionType === 'script') {
            $this->validateScript((string) ($data['script'] ?? ''));
        }

        return Automation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'execution_type' => $executionType,
            'agent_id' => $data['agentId'],
            'prompt' => $data['prompt'] ?? null,
            'script' => $data['script'] ?? null,
            'script_runtime' => $executionType === 'script' ? config('code.runtime') : null,
            'cron_expression' => $data['cronExpression'],
            'timezone' => $data['timezone'] ?? 'UTC',
            'channel_id' => $data['channelId'] ?? null,
            'keep_history' => $this->booleanValue($data['keepHistory'] ?? true),
            'created_by_id' => $data['createdById'],
            'is_active' => true,
        ])->load(['agent', 'channel', 'createdBy']);
    }

    public function show(string $id): Automation
    {
        return Automation::forWorkspace()
            ->with(['agent', 'channel', 'createdBy'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(string $id, array $input): Automation
    {
        $automation = Automation::forWorkspace()->findOrFail($id);
        $data = [];

        foreach (['name', 'description', 'prompt', 'script', 'timezone'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field];
            }
        }

        foreach (['agentId' => 'agent_id', 'channelId' => 'channel_id', 'cronExpression' => 'cron_expression'] as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $data[$column] = $input[$inputKey];
            }
        }

        if (array_key_exists('executionType', $input)) {
            $data['execution_type'] = $input['executionType'];
        }

        $targetType = $data['execution_type'] ?? $automation->execution_type;
        if ($targetType === 'script'
            && (array_key_exists('script', $input) || $automation->script_runtime !== config('code.runtime'))) {
            $this->validateScript((string) ($data['script'] ?? $automation->script ?? ''));
            $data['script_runtime'] = config('code.runtime');
        } elseif ($targetType === 'prompt') {
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

        $automation->update($data);

        if (array_key_exists('cronExpression', $input) || array_key_exists('timezone', $input)) {
            $automation->refreshNextRunAt();
        }

        return $automation->load(['agent', 'channel', 'createdBy']);
    }

    public function delete(string $id): void
    {
        Automation::forWorkspace()->findOrFail($id)->delete();
    }

    /**
     * @param  array<int, string>  $ids
     * @return array{deleted: int}
     */
    public function bulkDelete(array $ids): array
    {
        return [
            'deleted' => Automation::forWorkspace()->whereIn('id', $ids)->delete(),
        ];
    }

    /**
     * @param  array<int, string>  $ids
     * @return array{triggered: int, skipped: int}
     */
    public function bulkRun(array $ids): array
    {
        $automations = Automation::forWorkspace()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get();

        foreach ($automations as $automation) {
            RunAutomationJob::dispatch($automation);
        }

        return [
            'triggered' => $automations->count(),
            'skipped' => count($ids) - $automations->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function runs(string $id): Collection
    {
        return Task::forWorkspace()
            ->with(['agent'])
            ->where('source', Task::SOURCE_AUTOMATION)
            ->where(function ($query) use ($id) {
                $query->whereJsonContains('context->automation_id', $id)
                    ->orWhereJsonContains('context->scheduled_automation_id', $id);
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'runNumber' => $task->context['run_number'] ?? null,
                'result' => $task->result,
                'agentName' => $task->agent?->name,
                'startedAt' => $task->started_at,
                'completedAt' => $task->completed_at,
                'createdAt' => $task->created_at,
            ]);
    }

    public function run(string $id): void
    {
        RunAutomationJob::dispatch(Automation::forWorkspace()->findOrFail($id));
    }

    /**
     * @return array<int, string>
     */
    public function previewSchedule(string $cronExpression, string $timezone = 'UTC'): array
    {
        return array_map(
            fn ($run) => $run->toIso8601String(),
            (new AutomationSchedule($cronExpression, $timezone))->nextRuns(5),
        );
    }

    private function booleanValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Compile before persistence so syntax failures cannot become scheduled
     * incidents. Validation is capability-empty and therefore side-effect free.
     *
     * @throws ValidationException
     */
    private function validateScript(string $script): void
    {
        if (trim($script) === '') {
            throw ValidationException::withMessages([
                'script' => 'JavaScript is required for a script automation.',
            ]);
        }

        $result = $this->sandbox->execute(
            code: $script,
            profile: 'automation',
            validateOnly: true,
            sourceName: 'automation-code.js',
        );

        if ($result->succeeded()) {
            return;
        }

        $error = $result->error ?? [];
        $location = isset($error['line']) ? ' at line '.$error['line'] : '';

        throw ValidationException::withMessages([
            'script' => '['.($error['type'] ?? 'syntax_error').']'.$location.': '.($error['message'] ?? 'JavaScript validation failed.'),
        ]);
    }
}
