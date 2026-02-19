<?php

namespace Tests\Feature;

use App\Models\AgentPermission;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentPermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private AgentPermissionService $service;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AgentPermissionService::class);
        $this->agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);
    }

    private function createPermission(array $attributes): AgentPermission
    {
        return AgentPermission::create(array_merge([
            'id' => Str::uuid()->toString(),
            'agent_id' => $this->agent->id,
            'requires_approval' => false,
        ], $attributes));
    }

    private function enableWorkspaceIntegration(string $integrationId, bool $enabled = true): IntegrationSetting
    {
        return IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => $integrationId,
            'config' => [],
            'enabled' => $enabled,
        ]);
    }

    // ── Tool Permission Tests ──────────────────────────────────────────

    public function test_tool_allowed_by_default_when_no_permissions(): void
    {
        $result = $this->service->resolveToolPermission($this->agent, 'send_message', 'write');

        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['requires_approval']);
    }

    public function test_tool_denied_with_explicit_deny_record(): void
    {
        $this->createPermission([
            'scope_type' => 'tool',
            'scope_key' => 'delete_channel',
            'permission' => 'deny',
        ]);

        $result = $this->service->resolveToolPermission($this->agent, 'delete_channel', 'write');

        $this->assertFalse($result['allowed']);
    }

    public function test_tool_requires_approval_from_db_record(): void
    {
        $this->createPermission([
            'scope_type' => 'tool',
            'scope_key' => 'send_message',
            'permission' => 'allow',
            'requires_approval' => true,
        ]);

        $result = $this->service->resolveToolPermission($this->agent, 'send_message', 'write');

        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['requires_approval']);
    }

    public function test_tool_allowed_without_approval_from_db(): void
    {
        $this->createPermission([
            'scope_type' => 'tool',
            'scope_key' => 'send_message',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $result = $this->service->resolveToolPermission($this->agent, 'send_message', 'write');

        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['requires_approval']);
    }

    // ── Behavior Mode Tests ────────────────────────────────────────────

    public function test_supervised_mode_requires_approval_for_write_tools(): void
    {
        $this->agent->update(['behavior_mode' => 'supervised']);

        $result = $this->service->resolveToolPermission($this->agent, 'send_message', 'write');

        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['requires_approval']);
    }

    public function test_supervised_mode_no_approval_for_read_tools(): void
    {
        $this->agent->update(['behavior_mode' => 'supervised']);

        $result = $this->service->resolveToolPermission($this->agent, 'list_channels', 'read');

        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['requires_approval']);
    }

    public function test_strict_mode_requires_approval_for_all_tools(): void
    {
        $this->agent->update(['behavior_mode' => 'strict']);

        $readResult = $this->service->resolveToolPermission($this->agent, 'list_channels', 'read');
        $writeResult = $this->service->resolveToolPermission($this->agent, 'send_message', 'write');

        $this->assertTrue($readResult['requires_approval']);
        $this->assertTrue($writeResult['requires_approval']);
    }

    public function test_autonomous_mode_no_extra_approval(): void
    {
        $this->agent->update(['behavior_mode' => 'autonomous']);

        $result = $this->service->resolveToolPermission($this->agent, 'send_message', 'write');

        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['requires_approval']);
    }

    public function test_explicit_db_record_overrides_behavior_mode(): void
    {
        // Case 1: DB says no approval — overrides supervised mode
        $this->agent->update(['behavior_mode' => 'supervised']);
        $this->createPermission([
            'scope_type' => 'tool',
            'scope_key' => 'send_message',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $result = $this->service->resolveToolPermission($this->agent, 'send_message', 'write');
        $this->assertFalse($result['requires_approval']);

        // Case 2: DB says approval required — respected even in autonomous mode
        $this->agent->update(['behavior_mode' => 'autonomous']);
        $this->createPermission([
            'scope_type' => 'tool',
            'scope_key' => 'create_document',
            'permission' => 'allow',
            'requires_approval' => true,
        ]);

        $result = $this->service->resolveToolPermission($this->agent, 'create_document', 'write');
        $this->assertTrue($result['requires_approval']);
    }

    public function test_system_tools_never_require_approval(): void
    {
        $this->agent->update(['behavior_mode' => 'strict']);

        $exemptTools = [
            'wait_for_approval',
            'wait',
            'update_current_task',
            'create_task_step',
            'get_tool_info',
        ];

        foreach ($exemptTools as $tool) {
            $result = $this->service->resolveToolPermission($this->agent, $tool, 'write');
            $this->assertTrue($result['allowed'], "Tool {$tool} should be allowed");
            $this->assertFalse($result['requires_approval'], "Tool {$tool} should never require approval, even in strict mode");
        }
    }

    public function test_deny_overrides_behavior_mode(): void
    {
        $this->agent->update(['behavior_mode' => 'autonomous']);
        $this->createPermission([
            'scope_type' => 'tool',
            'scope_key' => 'delete_channel',
            'permission' => 'deny',
        ]);

        $result = $this->service->resolveToolPermission($this->agent, 'delete_channel', 'write');

        $this->assertFalse($result['allowed']);
    }

    // ── Integration Access Tests ─────────────────────────────────────────

    public function test_no_integrations_when_none_workspace_enabled(): void
    {
        $result = $this->service->getEnabledIntegrations($this->agent);

        // No IntegrationSetting rows — only MCP-prefixed apps (if any) should be present
        $nonMcp = array_filter($result, fn ($app) => !str_starts_with($app, 'mcp_'));
        $this->assertEmpty($nonMcp, 'Non-MCP integrations should not be returned without workspace enablement');
    }

    public function test_only_workspace_enabled_integrations_returned(): void
    {
        $this->enableWorkspaceIntegration('telegram');

        $result = $this->service->getEnabledIntegrations($this->agent);

        $this->assertContains('telegram', $result);
    }

    public function test_workspace_disabled_integration_excluded(): void
    {
        $this->enableWorkspaceIntegration('telegram', enabled: false);

        $result = $this->service->getEnabledIntegrations($this->agent);

        $this->assertNotContains('telegram', $result);
    }

    public function test_denied_integration_excluded(): void
    {
        $this->enableWorkspaceIntegration('telegram');

        $this->createPermission([
            'scope_type' => 'integration',
            'scope_key' => 'telegram',
            'permission' => 'deny',
        ]);

        $result = $this->service->getEnabledIntegrations($this->agent);

        $this->assertNotContains('telegram', $result);
    }

    public function test_new_integration_enabled_by_default_when_records_exist(): void
    {
        // Enable both integrations at workspace level
        $this->enableWorkspaceIntegration('telegram');
        $this->enableWorkspaceIntegration('plausible');

        // When an agent has integration records for known apps,
        // new workspace-enabled integrations without records should still be enabled.
        $this->createPermission([
            'scope_type' => 'integration',
            'scope_key' => 'telegram',
            'permission' => 'allow',
        ]);

        $result = $this->service->getEnabledIntegrations($this->agent);

        $this->assertContains('telegram', $result);
        // plausible has no agent-level record but is workspace-enabled, so it should be included
        $this->assertContains('plausible', $result);
    }

    public function test_explicitly_allowed_integration_enabled(): void
    {
        $this->enableWorkspaceIntegration('telegram');

        $this->createPermission([
            'scope_type' => 'integration',
            'scope_key' => 'telegram',
            'permission' => 'allow',
        ]);

        $result = $this->service->getEnabledIntegrations($this->agent);

        $this->assertContains('telegram', $result);
    }

    public function test_agent_deny_overrides_workspace_enabled(): void
    {
        $this->enableWorkspaceIntegration('telegram');

        $this->createPermission([
            'scope_type' => 'integration',
            'scope_key' => 'telegram',
            'permission' => 'deny',
        ]);

        $result = $this->service->getEnabledIntegrations($this->agent);

        $this->assertNotContains('telegram', $result);
    }

    // ── Channel Access Tests ───────────────────────────────────────────

    public function test_channel_unrestricted_when_no_permissions(): void
    {
        $result = $this->service->canAccessChannel($this->agent, 'any-channel-id');

        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['can_request']);
    }

    public function test_channel_allowed_when_whitelisted(): void
    {
        $this->createPermission([
            'scope_type' => 'channel',
            'scope_key' => 'chan-1',
            'permission' => 'allow',
        ]);

        $result = $this->service->canAccessChannel($this->agent, 'chan-1');

        $this->assertTrue($result['allowed']);
    }

    public function test_channel_denied_when_not_in_whitelist(): void
    {
        $this->createPermission([
            'scope_type' => 'channel',
            'scope_key' => 'chan-1',
            'permission' => 'allow',
        ]);

        $result = $this->service->canAccessChannel($this->agent, 'chan-2');

        $this->assertFalse($result['allowed']);
        $this->assertTrue($result['can_request']);
    }

    // ── Folder Access Tests ────────────────────────────────────────────

    public function test_folders_null_when_unrestricted(): void
    {
        $result = $this->service->getAllowedFolderIds($this->agent);

        $this->assertNull($result);
    }

    public function test_folders_returns_array_when_restricted(): void
    {
        $this->createPermission([
            'scope_type' => 'folder',
            'scope_key' => 'folder-aaa',
            'permission' => 'allow',
        ]);
        $this->createPermission([
            'scope_type' => 'folder',
            'scope_key' => 'folder-bbb',
            'permission' => 'allow',
        ]);

        $result = $this->service->getAllowedFolderIds($this->agent);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContains('folder-aaa', $result);
        $this->assertContains('folder-bbb', $result);
    }

    // ── canContactAgent Tests ─────────────────────────────────────────

    public function test_can_contact_agent_manager_bypass_caller_manages(): void
    {
        $target = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'manager_id' => $this->agent->id,
        ]);

        $result = $this->service->canContactAgent($this->agent, $target);

        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['requires_approval']);
    }

    public function test_can_contact_agent_manager_bypass_target_manages(): void
    {
        $target = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);
        $this->agent->update(['manager_id' => $target->id]);

        $result = $this->service->canContactAgent($this->agent, $target);

        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['requires_approval']);
    }

    public function test_can_contact_agent_explicit_allow(): void
    {
        $target = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);

        $this->createPermission([
            'scope_type' => 'agent',
            'scope_key' => $target->id,
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $result = $this->service->canContactAgent($this->agent, $target);

        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['requires_approval']);
    }

    public function test_can_contact_agent_explicit_deny(): void
    {
        $target = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);

        $this->createPermission([
            'scope_type' => 'agent',
            'scope_key' => $target->id,
            'permission' => 'deny',
        ]);

        $result = $this->service->canContactAgent($this->agent, $target);

        $this->assertFalse($result['allowed']);
    }

    public function test_can_contact_agent_wildcard_allow(): void
    {
        $target = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);

        $this->createPermission([
            'scope_type' => 'agent',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $result = $this->service->canContactAgent($this->agent, $target);

        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['requires_approval']);
    }

    public function test_can_contact_agent_wildcard_deny(): void
    {
        $target = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);

        $this->createPermission([
            'scope_type' => 'agent',
            'scope_key' => '*',
            'permission' => 'deny',
        ]);

        $result = $this->service->canContactAgent($this->agent, $target);

        $this->assertFalse($result['allowed']);
    }

    public function test_can_contact_agent_default_autonomous(): void
    {
        $target = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);
        $this->agent->update(['behavior_mode' => 'autonomous']);

        $result = $this->service->canContactAgent($this->agent, $target);

        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['requires_approval']);
    }

    public function test_can_contact_agent_default_supervised(): void
    {
        $target = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);
        $this->agent->update(['behavior_mode' => 'supervised']);

        $result = $this->service->canContactAgent($this->agent, $target);

        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['requires_approval']);
    }

    public function test_can_contact_agent_default_strict(): void
    {
        $target = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);
        $this->agent->update(['behavior_mode' => 'strict']);

        $result = $this->service->canContactAgent($this->agent, $target);

        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['requires_approval']);
    }

    public function test_can_contact_agent_explicit_with_approval(): void
    {
        $target = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);

        $this->createPermission([
            'scope_type' => 'agent',
            'scope_key' => $target->id,
            'permission' => 'allow',
            'requires_approval' => true,
        ]);

        $result = $this->service->canContactAgent($this->agent, $target);

        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['requires_approval']);
    }
}
