<?php

namespace Tests\Feature;

use App\Models\AgentPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentPermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create([
            'type' => 'agent',
            'agent_type' => 'coder',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
        ]);
    }

    public function test_index_returns_tools_channels_folders_behavior_mode(): void
    {
        $response = $this->getJson("/api/agents/{$this->agent->id}/permissions");

        $response->assertOk()
            ->assertJsonStructure([
                'tools',
                'channelIds',
                'folderIds',
                'behaviorMode',
            ]);

        $data = $response->json();

        $this->assertCount(4, $data['tools']);
        $this->assertIsArray($data['channelIds']);
        $this->assertIsArray($data['folderIds']);
        $this->assertIsString($data['behaviorMode']);
    }

    public function test_index_reflects_existing_permissions(): void
    {
        // Create a deny permission for a tool
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'send_channel_message',
            'permission' => 'deny',
            'requires_approval' => false,
        ]);

        // Create a channel permission
        $channelId = Str::uuid()->toString();
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'channel',
            'scope_key' => $channelId,
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $response = $this->getJson("/api/agents/{$this->agent->id}/permissions");

        $response->assertOk();
        $data = $response->json();

        // The denied tool should show as disabled
        $sendMessageTool = collect($data['tools'])->firstWhere('id', 'send_channel_message');
        $this->assertFalse($sendMessageTool['enabled']);

        // The channel should appear in channelIds
        $this->assertContains($channelId, $data['channelIds']);
    }

    public function test_index_returns_404_for_non_agent(): void
    {
        $human = User::factory()->create(['type' => 'human']);

        $response = $this->getJson("/api/agents/{$human->id}/permissions");

        $response->assertNotFound();
    }

    public function test_update_tools_replaces_all_permissions(): void
    {
        // Create an existing tool permission
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'send_channel_message',
            'permission' => 'deny',
            'requires_approval' => false,
        ]);

        $response = $this->putJson("/api/agents/{$this->agent->id}/permissions/tools", [
            'tools' => [
                [
                    'scopeKey' => 'search_documents',
                    'permission' => 'allow',
                    'requiresApproval' => true,
                ],
                [
                    'scopeKey' => 'create_task_step',
                    'permission' => 'deny',
                    'requiresApproval' => false,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonStructure(['tools']);

        // DB should have exactly 2 tool permissions, old one deleted
        $toolPerms = AgentPermission::forAgent($this->agent->id)->tools()->get();
        $this->assertCount(2, $toolPerms);

        $scopeKeys = $toolPerms->pluck('scope_key')->sort()->values()->toArray();
        $this->assertEquals(['create_task_step', 'search_documents'], $scopeKeys);

        // The old send_channel_message permission should be gone
        $this->assertNull(
            AgentPermission::forAgent($this->agent->id)->tools()->where('scope_key', 'send_channel_message')->first()
        );
    }

    public function test_update_tools_validates_input(): void
    {
        $response = $this->putJson("/api/agents/{$this->agent->id}/permissions/tools", [
            'tools' => [
                [
                    'scopeKey' => 'search_documents',
                    // missing permission and requiresApproval
                ],
            ],
        ]);

        $response->assertUnprocessable();
    }

    public function test_update_channels_sets_whitelist(): void
    {
        $channelId1 = Str::uuid()->toString();
        $channelId2 = Str::uuid()->toString();

        $response = $this->putJson("/api/agents/{$this->agent->id}/permissions/channels", [
            'channels' => [$channelId1, $channelId2],
        ]);

        $response->assertOk()
            ->assertJsonStructure(['channelIds']);

        $channelPerms = AgentPermission::forAgent($this->agent->id)->channels()->get();
        $this->assertCount(2, $channelPerms);

        $returnedIds = $response->json('channelIds');
        $this->assertContains($channelId1, $returnedIds);
        $this->assertContains($channelId2, $returnedIds);
    }

    public function test_update_channels_empty_array_clears_restrictions(): void
    {
        // Create existing channel permissions
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'channel',
            'scope_key' => Str::uuid()->toString(),
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'channel',
            'scope_key' => Str::uuid()->toString(),
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $this->assertCount(2, AgentPermission::forAgent($this->agent->id)->channels()->get());

        $response = $this->putJson("/api/agents/{$this->agent->id}/permissions/channels", [
            'channels' => [],
        ]);

        $response->assertOk();

        $channelPerms = AgentPermission::forAgent($this->agent->id)->channels()->get();
        $this->assertCount(0, $channelPerms);
    }

    public function test_update_folders_sets_whitelist(): void
    {
        $folderId = Str::uuid()->toString();

        $response = $this->putJson("/api/agents/{$this->agent->id}/permissions/folders", [
            'folders' => [$folderId],
        ]);

        $response->assertOk()
            ->assertJsonStructure(['folderIds']);

        $folderPerms = AgentPermission::forAgent($this->agent->id)->folders()->get();
        $this->assertCount(1, $folderPerms);

        $this->assertContains($folderId, $response->json('folderIds'));
    }

    public function test_update_folders_replaces_previous(): void
    {
        $oldFolderId = Str::uuid()->toString();
        $newFolderId = Str::uuid()->toString();

        // Create old folder permission
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'folder',
            'scope_key' => $oldFolderId,
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $response = $this->putJson("/api/agents/{$this->agent->id}/permissions/folders", [
            'folders' => [$newFolderId],
        ]);

        $response->assertOk();

        $folderPerms = AgentPermission::forAgent($this->agent->id)->folders()->get();
        $this->assertCount(1, $folderPerms);
        $this->assertEquals($newFolderId, $folderPerms->first()->scope_key);

        // Old folder should be gone
        $this->assertNull(
            AgentPermission::forAgent($this->agent->id)->folders()->where('scope_key', $oldFolderId)->first()
        );
    }
}
