<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListAutomationRule;
use App\Models\ListTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * REST CRUD for list automation rules in the active workspace.
 *
 * Tenancy invariant: every query starts from `ListAutomationRule::forWorkspace()`
 * so a rule id from another workspace resolves to 404, never to a read or
 * mutation. Template ids supplied by the client are validated against the same
 * workspace before they are linked. This controller owns HTTP shape only; rule
 * execution lives elsewhere.
 */
class AutomationRuleController extends Controller
{
    /** @return Collection<int, ListAutomationRule> */
    public function index(Request $request): Collection
    {
        $query = ListAutomationRule::forWorkspace()->with(['template', 'createdBy']);

        if ($request->input('activeOnly', true)) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')->get();
    }

    public function store(Request $request): ListAutomationRule
    {
        $rule = ListAutomationRule::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'trigger_type' => $request->input('triggerType'),
            'trigger_conditions' => $request->input('triggerConditions'),
            'action_type' => $request->input('actionType'),
            'action_config' => $request->input('actionConfig'),
            'list_template_id' => $this->scopedTemplateId($request->input('templateId')),
            'created_by_id' => auth()->id(),
            'is_active' => true,
        ]);

        return $rule->load(['template', 'createdBy']);
    }

    public function update(Request $request, string $id): ListAutomationRule
    {
        $rule = ListAutomationRule::forWorkspace()->findOrFail($id);

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
            $data['list_template_id'] = $this->scopedTemplateId($request->input('templateId'));
        }
        if ($request->has('isActive')) {
            $data['is_active'] = $request->input('isActive');
        }

        $rule->update($data);

        return $rule->load(['template', 'createdBy']);
    }

    public function destroy(string $id): JsonResponse
    {
        ListAutomationRule::forWorkspace()->findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Accept a template id only when it belongs to the active workspace.
     *
     * Returns null for an empty value; a foreign or unknown id is a 404 so the
     * response does not reveal whether another tenant's template exists.
     */
    private function scopedTemplateId(mixed $templateId): ?string
    {
        if ($templateId === null || $templateId === '') {
            return null;
        }

        return ListTemplate::forWorkspace()->findOrFail((string) $templateId)->id;
    }
}
