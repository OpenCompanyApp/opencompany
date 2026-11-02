<?php

namespace Tests\Feature\Vfs;

use App\Agents\Runtime\ActiveAgentRun;
use App\Domain\Authorization\Application\OnBehalfOf;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\AgentPermission;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PostgreSQL transaction safety for untrusted identifiers.
 *
 * On PostgreSQL a non-uuid literal compared with a uuid column is an ERROR,
 * and an error inside a transaction aborts it: every later statement fails
 * with "current transaction is aborted" even when the first exception was
 * caught. VFS paths, run ids and CLI options are untrusted strings, so the
 * code must never send a malformed id to a uuid column. Each scenario below
 * ends with an ordinary query, which fails on PostgreSQL if the transaction
 * was poisoned (on SQLite these pass trivially; CI runs them on both).
 */
class VfsPostgresSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent('manager')->create(['name' => 'Atlas', 'status' => 'idle']);
    }

    // A malformed run id (tests, replays, foreign runtimes) has no ledger
    // row; resolving the on-behalf-of principal must not query with it.
    public function test_malformed_run_id_does_not_abort_the_transaction(): void
    {
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun('not-a-uuid'));

        $this->assertNull(app(ActiveAgentRun::class)->recordOrNull());
        $this->assertNull(app(OnBehalfOf::class)->currentPrincipalId());
        $this->assertSame(1, User::query()->whereKey($this->agent->id)->count());
    }

    // Agents choose by-id tokens; a non-uuid token is simply not found.
    public function test_non_uuid_by_id_paths_are_not_found_without_aborting(): void
    {
        $folder = Document::factory()->create(['title' => 'Shared', 'is_folder' => true, 'author_id' => $this->agent->id]);
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'folder',
            'scope_key' => $folder->id,
            'permission' => 'allow',
        ]);
        $vfs = app(OpenCompanyVfs::class);

        foreach (['/docs/by-id/not-a-uuid', '/files/by-id/not-a-uuid', '/tasks/by-id/not-a-uuid', '/lists/by-id/not-a-uuid', '/agents/by-id/not-a-uuid', '/approvals/not-a-uuid.json'] as $path) {
            try {
                $vfs->stat($this->agent, $path);
                $this->fail("{$path} should not resolve.");
            } catch (VfsError $e) {
                $this->assertContains($e->errorCode, ['not_found', 'permission_denied'], $path);
            }
        }

        $this->assertSame(1, User::query()->whereKey($this->agent->id)->count());
    }

    // `vfs:atlas --workspace=<slug>` must look up a slug without comparing
    // the slug to the uuid primary key.
    public function test_atlas_workspace_slug_lookup_does_not_abort(): void
    {
        $this->artisan('vfs:atlas', ['vfsCommand' => ['pwd'], '--workspace' => 'no-such-workspace-slug'])->run();

        $this->assertSame(1, User::query()->whereKey($this->agent->id)->count());
    }
}
