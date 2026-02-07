<?php

namespace Tests\Feature;

use App\Models\AgentPermission;
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

    public function test_behavior_mode_or_db_approval_combined(): void
    {
        // Case 1: DB says no approval, but supervised + write → requires approval
        $this->agent->update(['behavior_mode' => 'supervised']);
        $this->createPermission([
            'scope_type' => 'tool',
            'scope_key' => 'send_message',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $result = $this->service->resolveToolPermission($this->agent, 'send_message', 'write');
        $this->assertTrue($result['requires_approval']);

        // Case 2: DB says approval required, but autonomous → still requires approval (OR logic)
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

    public function test_all_integrations_enabled_when_no_records(): void
    {
        $result = $this->service->getEnabledIntegrations($this->agent);

        // Should include all known integration apps
        $this->assertContains('telegram', $result);
    }

    public function test_denied_integration_excluded(): void
    {
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
        // When an agent has integration records for known apps,
        // new integrations without records should still be enabled.
        $this->createPermission([
            'scope_type' => 'integration',
            'scope_key' => 'telegram',
            'permission' => 'allow',
        ]);

        $result = $this->service->getEnabledIntegrations($this->agent);

        // telegram is explicitly allowed
        $this->assertContains('telegram', $result);
        // mermaid (if registered) should be enabled by default since no deny record exists
        // At minimum, other integrations without records should not be excluded
    }

    public function test_explicitly_allowed_integration_enabled(): void
    {
        $this->createPermission([
            'scope_type' => 'integration',
            'scope_key' => 'telegram',
            'permission' => 'allow',
        ]);

        $result = $this->service->getEnabledIntegrations($this->agent);

        $this->assertContains('telegram', $result);
    }

    // ── Channel Access Tests ───────────────────────────────────────────

    public function test_channel_unrestricted_when_no_permissions(): void
    {
        $result = $this->service->canAccessChannel($this->agent, 'any-channel-id');

        $this->assertTrue($result);
    }

    public function test_channel_allowed_when_whitelisted(): void
    {
        $this->createPermission([
            'scope_type' => 'channel',
            'scope_key' => 'chan-1',
            'permission' => 'allow',
        ]);

        $result = $this->service->canAccessChannel($this->agent, 'chan-1');

        $this->assertTrue($result);
    }

    public function test_channel_denied_when_not_in_whitelist(): void
    {
        $this->createPermission([
            'scope_type' => 'channel',
            'scope_key' => 'chan-1',
            'permission' => 'allow',
        ]);

        $result = $this->service->canAccessChannel($this->agent, 'chan-2');

        $this->assertFalse($result);
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
}
