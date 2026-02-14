<?php

namespace Tests\Feature\Services\Memory;

use App\Models\Channel;
use App\Models\User;
use App\Services\Memory\MemoryScopeGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryScopeGuardTest extends TestCase
{
    use RefreshDatabase;

    private MemoryScopeGuard $guard;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new MemoryScopeGuard;
        $this->agent = User::factory()->agent()->create();
    }

    public function test_dm_only_allows_in_dm_channel(): void
    {
        config(['memory.scope.default' => 'dm_only']);
        $channel = Channel::factory()->create(['type' => 'dm']);

        $this->assertTrue($this->guard->canUseMemoryTools($this->agent, $channel->id));
    }

    public function test_dm_only_allows_in_agent_channel(): void
    {
        config(['memory.scope.default' => 'dm_only']);
        $channel = Channel::factory()->create(['type' => 'agent']);

        $this->assertTrue($this->guard->canUseMemoryTools($this->agent, $channel->id));
    }

    public function test_dm_only_denies_in_public_channel(): void
    {
        config(['memory.scope.default' => 'dm_only']);
        $channel = Channel::factory()->create(['type' => 'public']);

        $this->assertFalse($this->guard->canUseMemoryTools($this->agent, $channel->id));
    }

    public function test_dm_only_denies_in_external_channel(): void
    {
        config(['memory.scope.default' => 'dm_only']);
        $channel = Channel::factory()->create(['type' => 'external']);

        $this->assertFalse($this->guard->canUseMemoryTools($this->agent, $channel->id));
    }

    public function test_all_mode_allows_in_any_channel(): void
    {
        config(['memory.scope.default' => 'all']);

        foreach (['dm', 'agent', 'public', 'external'] as $type) {
            $channel = Channel::factory()->create(['type' => $type]);
            $this->assertTrue(
                $this->guard->canUseMemoryTools($this->agent, $channel->id),
                "Expected 'all' mode to allow in '{$type}' channel"
            );
        }
    }

    public function test_none_mode_denies_everywhere(): void
    {
        config(['memory.scope.default' => 'none']);

        foreach (['dm', 'agent', 'public', 'external'] as $type) {
            $channel = Channel::factory()->create(['type' => $type]);
            $this->assertFalse(
                $this->guard->canUseMemoryTools($this->agent, $channel->id),
                "Expected 'none' mode to deny in '{$type}' channel"
            );
        }
    }

    public function test_denies_when_channel_not_found(): void
    {
        config(['memory.scope.default' => 'dm_only']);

        $this->assertFalse($this->guard->canUseMemoryTools($this->agent, 'nonexistent-id'));
    }

    public function test_denies_when_channel_id_is_null(): void
    {
        config(['memory.scope.default' => 'dm_only']);

        $this->assertFalse($this->guard->canUseMemoryTools($this->agent, null));
    }

    public function test_denial_message_includes_tool_name(): void
    {
        $message = $this->guard->denialMessage('recall_memory');

        $this->assertStringContainsString('recall_memory', $message);
        $this->assertStringContainsString('group channels', $message);
    }
}
