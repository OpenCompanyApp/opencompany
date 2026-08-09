<?php

namespace Tests\Feature;

use App\Domain\Collaboration\Application\ProvisionWorkspace;
use App\Models\Channel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WorkspaceCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_api_requires_an_authenticated_verified_user(): void
    {
        $this->postJson('/api/workspaces', [
            'name' => 'Unauthorized Workspace',
            'slug' => 'unauthorized-workspace',
        ])->assertUnauthorized();

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->postJson('/api/workspaces', [
            'name' => 'Unverified Workspace',
            'slug' => 'unverified-workspace',
        ])->assertForbidden();

        $this->assertDatabaseMissing('workspaces', ['slug' => 'unauthorized-workspace']);
        $this->assertDatabaseMissing('workspaces', ['slug' => 'unverified-workspace']);
    }

    public function test_workspace_api_provisions_the_complete_tenant_record_set(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->postJson('/api/workspaces', [
            'name' => 'Operations',
            'slug' => 'operations',
            'icon' => 'ph:briefcase',
            'color' => 'blue',
        ])->assertCreated();

        $workspace = Workspace::query()->where('slug', 'operations')->firstOrFail();

        $response->assertJsonPath('id', $workspace->id)
            ->assertJsonPath('slug', 'operations');
        $this->assertSame($owner->id, $workspace->owner_id);
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'admin',
        ]);
        $this->assertDatabaseHas('users', [
            'workspace_id' => $workspace->id,
            'type' => 'agent',
            'agent_type' => 'system',
        ]);
        $general = Channel::query()
            ->where('workspace_id', $workspace->id)
            ->where('name', 'general')
            ->firstOrFail();
        $this->assertDatabaseHas('channel_members', [
            'channel_id' => $general->id,
            'user_id' => $owner->id,
            'role' => 'admin',
        ]);
    }

    public function test_workspace_provisioning_rolls_back_every_record_when_bootstrap_fails(): void
    {
        $owner = User::factory()->create();
        $systemAgentCount = User::query()->where('agent_type', 'system')->count();

        Event::listen('eloquent.creating: '.Channel::class, function (): never {
            throw new \RuntimeException('Simulated channel bootstrap failure.');
        });

        try {
            app(ProvisionWorkspace::class)->create($owner, 'Broken Workspace', 'broken-workspace');
            $this->fail('Workspace provisioning should have failed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated channel bootstrap failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('workspaces', ['slug' => 'broken-workspace']);
        $this->assertSame($systemAgentCount, User::query()->where('agent_type', 'system')->count());
    }

    public function test_initial_setup_rolls_back_the_new_user_when_workspace_bootstrap_fails(): void
    {
        Workspace::query()->delete();
        Event::listen('eloquent.creating: '.Channel::class, function (): never {
            throw new \RuntimeException('Simulated setup failure.');
        });

        $this->withoutExceptionHandling();

        try {
            $this->post('/setup', [
                'name' => 'Initial Owner',
                'email' => 'owner@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'workspace_name' => 'Initial Workspace',
            ]);
            $this->fail('Initial setup should have failed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated setup failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
        $this->assertDatabaseMissing('workspaces', ['slug' => 'initial-workspace']);
    }
}
