<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListAutomationRule;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AutomationRuleController extends Controller
{
    public function index(Request $request)
    {
        $query = ListAutomationRule::with(['template', 'createdBy']);

        if ($request->input('activeOnly', true)) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $rule = ListAutomationRule::create([
            'id' => Str::uuid()->toString(),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'trigger_type' => $request->input('triggerType'),
            'trigger_conditions' => $request->input('triggerConditions'),
            'action_type' => $request->input('actionType'),
            'action_config' => $request->input('actionConfig'),
            'list_template_id' => $request->input('templateId'),
            'created_by_id' => $request->input('createdById'),
            'is_active' => true,
        ]);

        return $rule->load(['template', 'createdBy']);
    }

    public function update(Request $request, string $id)
    {
        $rule = ListAutomationRule::findOrFail($id);

        $data = $request->only([
            'name',
            'description',
        ]);

        if ($request->has('triggerType')) {
            $data['trigger_type'] = $request->input('triggerType');
        }
        if ($request->has('triggerConditions')) {
            $data['trigger_conditions'] = $request->input('triggerConditions');
        }
        if ($request->has('actionType')) {
            $data['action_type'] = $request->input('actionType');
        }
        if ($request->has('actionConfig')) {
            $data['action_config'] = $request->input('actionConfig');
        }
        if ($request->has('templateId')) {
            $data['list_template_id'] = $request->input('templateId');
        }
        if ($request->has('isActive')) {
            $data['is_active'] = $request->input('isActive');
        }

        $rule->update($data);

        return $rule->load(['template', 'createdBy']);
    }

    public function destroy(string $id)
    {
        ListAutomationRule::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }
}
