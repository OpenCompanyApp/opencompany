<?php

namespace Tests;

use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected ?Workspace $workspace = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test workspace for tests that use RefreshDatabase
        if (in_array(\Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive($this))) {
            $this->workspace = Workspace::create([
                'name' => 'Test Workspace',
                'slug' => 'test',
            ]);

            app()->instance('currentWorkspace', $this->workspace);
        }
    }

    /**
     * Override actingAs to automatically add the user as a workspace member.
     * This ensures the ResolveWorkspace middleware passes membership checks.
     */
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        if ($this->workspace && ($user->type ?? null) !== 'agent') {
            WorkspaceMember::firstOrCreate([
                'workspace_id' => $this->workspace->id,
                'user_id' => $user->id,
            ], ['role' => 'admin']);
        }

        return parent::actingAs($user, $guard);
    }
}
