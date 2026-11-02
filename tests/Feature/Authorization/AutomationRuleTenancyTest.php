<?php

namespace Tests\Feature\Authorization;

use App\Models\ListAutomationRule;
use App\Models\ListTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression for the cross-workspace IDOR on /api/automation-rules: index
 * returned every tenant's rules and update/destroy used a bare findOrFail.
 * Rules now carry workspace_id and every CRUD path is forWorkspace()-scoped.
 */
class AutomationRuleTenancyTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $otherWorkspace;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->otherWorkspace = Workspace::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Workspace B',
            'slug' => 'workspace-b',
        ]);

        // Admin of workspace B only. Authenticate with be() (not actingAs) so
        // the base TestCase does not also grant membership in workspace A.
        $this->outsider = User::factory()->create(['type' => 'human']);
        WorkspaceMember::create([
            'workspace_id' => $this->otherWorkspace->id,
            'user_id' => $this->outsider->id,
            'role' => 'admin',
        ]);
    }

    private function ruleInWorkspaceA(): ListAutomationRule
    {
        $creator = User::factory()->create(['type' => 'human']);

        // Template-less on purpose: the old template-based scoping could not
        // see these at all, and the REST API exposed them to everyone.
        return ListAutomationRule::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'A secret rule',
            'trigger_type' => 'task_created',
            'action_type' => 'send_notification',
            'created_by_id' => $creator->id,
            'is_active' => true,
        ]);
    }

    public function test_member_of_other_workspace_cannot_list_update_or_delete_rules(): void
    {
        $rule = $this->ruleInWorkspaceA();

        $this->be($this->outsider);

        $this->getJson('/api/automation-rules')
            ->assertOk()
            ->assertJsonMissing(['id' => $rule->id])
            ->assertJsonCount(0);

        $this->patchJson("/api/automation-rules/{$rule->id}", ['name' => 'pwned'])->assertNotFound();
        $this->deleteJson("/api/automation-rules/{$rule->id}")->assertNotFound();

        $this->assertDatabaseHas('list_automation_rules', ['id' => $rule->id, 'name' => 'A secret rule']);
    }

    public function test_owner_workspace_sees_and_mutates_its_rules_and_new_rules_are_stamped(): void
    {
        $rule = $this->ruleInWorkspaceA();
        $admin = User::factory()->create(['type' => 'human']);

        $this->actingAs($admin)->getJson('/api/automation-rules')
            ->assertOk()
            ->assertJsonFragment(['id' => $rule->id]);

        $this->actingAs($admin)->patchJson("/api/automation-rules/{$rule->id}", ['name' => 'Renamed'])->assertOk();
        $this->assertSame('Renamed', $rule->fresh()->name);

        $created = $this->actingAs($admin)->postJson('/api/automation-rules', [
            'name' => 'New rule',
            'triggerType' => 'task_created',
            'actionType' => 'send_notification',
        ])->assertSuccessful()->json('id');

        $this->assertSame($this->workspace->id, ListAutomationRule::findOrFail($created)->workspace_id);

        $this->actingAs($admin)->deleteJson("/api/automation-rules/{$rule->id}")->assertOk();
        $this->assertDatabaseMissing('list_automation_rules', ['id' => $rule->id]);
    }

    public function test_rule_cannot_link_a_template_from_another_workspace(): void
    {
        // A client-supplied template id must not smuggle a rule into, or link
        // it to, another tenant's template.
        $foreignTemplate = ListTemplate::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->otherWorkspace->id,
            'name' => 'Foreign template',
            'default_title' => 'Foreign item',
            'created_by_id' => $this->outsider->id,
        ]);

        $admin = User::factory()->create(['type' => 'human']);

        $this->actingAs($admin)->postJson('/api/automation-rules', [
            'name' => 'Sneaky',
            'triggerType' => 'task_created',
            'actionType' => 'send_notification',
            'templateId' => $foreignTemplate->id,
        ])->assertNotFound();

        $this->assertDatabaseMissing('list_automation_rules', ['name' => 'Sneaky']);
    }
}
