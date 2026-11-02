<?php

namespace Tests\Feature\AgentSafety;

use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Planning-session finding #5: tool metadata defaults unknown types to
 * 'action', and `supervised` used to gate only 'write' — so untyped tools ran
 * unsupervised. Supervision now gates every type except a declared 'read'.
 */
class SupervisedModeTypingTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervised_gates_every_non_read_type(): void
    {
        $agent = User::factory()->agent()->create(['behavior_mode' => 'supervised']);
        $permissions = app(AgentPermissionService::class);

        foreach (['action', 'write', 'unknown', ''] as $type) {
            $this->assertTrue(
                $permissions->resolveToolPermission($agent, 'internal_tool_'.$type, $type)['requires_approval'],
                "type '{$type}' must require approval under supervision",
            );
        }
        $this->assertFalse($permissions->resolveToolPermission($agent, 'internal_read_tool', 'read')['requires_approval']);
    }
}
