<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunScheduledAutomationJob;
use App\Models\ScheduledAutomation;
use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ScheduledAutomationController extends Controller
{
    public function index()
    {
        return ScheduledAutomation::with(['agent', 'channel', 'createdBy'])
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'agentId' => 'required|exists:users,id',
            'prompt' => 'required|string|max:10000',
            'cronExpression' => 'required|string',
            'timezone' => 'nullable|string|timezone',
        ]);

        if (! CronExpression::isValidExpression($request->input('cronExpression'))) {
            return response()->json(['message' => 'Invalid cron expression'], 422);
        }

        $automation = ScheduledAutomation::create([
            'id' => Str::uuid()->toString(),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'agent_id' => $request->input('agentId'),
            'prompt' => $request->input('prompt'),
            'cron_expression' => $request->input('cronExpression'),
            'timezone' => $request->input('timezone', 'UTC'),
            'channel_id' => $request->input('channelId'),
            'keep_history' => $request->boolean('keepHistory', true),
            'created_by_id' => $request->input('createdById', auth()->id()),
            'is_active' => true,
        ]);

        return $automation->load(['agent', 'channel', 'createdBy']);
    }

    public function show(string $id)
    {
        $automation = ScheduledAutomation::with(['agent', 'channel', 'createdBy'])
            ->findOrFail($id);

        $data = $automation->toArray();
        $data['nextRuns'] = collect($automation->getNextRuns(5))
            ->map(fn (Carbon $run) => $run->toIso8601String());

        return $data;
    }

    public function update(Request $request, string $id)
    {
        $automation = ScheduledAutomation::findOrFail($id);

        $data = [];

        if ($request->has('name')) {
            $data['name'] = $request->input('name');
        }
        if ($request->has('description')) {
            $data['description'] = $request->input('description');
        }
        if ($request->has('agentId')) {
            $data['agent_id'] = $request->input('agentId');
        }
        if ($request->has('prompt')) {
            $data['prompt'] = $request->input('prompt');
        }
        if ($request->has('channelId')) {
            $data['channel_id'] = $request->input('channelId');
        }
        if ($request->has('timezone')) {
            $data['timezone'] = $request->input('timezone');
        }
        if ($request->has('isActive')) {
            $data['is_active'] = $request->boolean('isActive');
            if ($data['is_active']) {
                $data['consecutive_failures'] = 0;
            }
        }
        if ($request->has('cronExpression')) {
            $cronExpr = $request->input('cronExpression');
            if (! CronExpression::isValidExpression($cronExpr)) {
                return response()->json(['message' => 'Invalid cron expression'], 422);
            }
            $data['cron_expression'] = $cronExpr;
        }
        if ($request->has('keepHistory')) {
            $data['keep_history'] = $request->boolean('keepHistory');
        }

        $automation->update($data);

        if ($request->has('cronExpression') || $request->has('timezone')) {
            $automation->refreshNextRunAt();
        }

        return $automation->load(['agent', 'channel', 'createdBy']);
    }

    public function destroy(string $id)
    {
        ScheduledAutomation::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    public function triggerRun(string $id)
    {
        $automation = ScheduledAutomation::findOrFail($id);
        RunScheduledAutomationJob::dispatch($automation);

        return response()->json(['message' => 'Run dispatched']);
    }

    public function previewSchedule(Request $request)
    {
        $cronExpr = $request->input('cronExpression');

        if (! $cronExpr || ! CronExpression::isValidExpression($cronExpr)) {
            return response()->json(['message' => 'Invalid cron expression'], 422);
        }

        $cron = new CronExpression($cronExpr);
        $tz = $request->input('timezone', 'UTC');
        $reference = now()->timezone($tz);
        $runs = [];

        for ($i = 0; $i < 5; $i++) {
            $reference = Carbon::instance($cron->getNextRunDate($reference->toDateTime()));
            $runs[] = $reference->copy()->toIso8601String();
        }

        return response()->json(['runs' => $runs]);
    }
}
