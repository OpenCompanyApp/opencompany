<?php

namespace Tests\Feature\Vfs;

use App\Agents\Tools\Vfs\VfsExec;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\ListItem;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFile;
use App\Services\FileSystemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Cross-workspace isolation matrix for every VFS mount.
 *
 * A second workspace is filled with records that would be visible to the
 * local agent if any mount query lost its workspace scope (docs and channels
 * default to "read all", files are granted '*', the foreign agent reports to
 * the local agent, and every name carries the QZFOREIGN marker). The local
 * agent must not see any of it through listing, reading, stat, search or
 * find, must get not_found when guessing foreign ids, must not reuse a
 * foreign cursor, and the facade must refuse a mismatched workspace binding.
 */
class VfsWorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const MARKER = 'qzforeign';

    private User $agent;

    private Workspace $foreign;

    private User $foreignAgent;

    /** @var array<string, string> mount => canonical path of one foreign record */
    private array $foreignPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent('manager')->create(['name' => 'Atlas', 'status' => 'idle']);
        $this->disk($this->workspace, 'disk-local');
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);
        // Local control data so listings are not trivially empty.
        Document::factory()->create(['title' => 'Local Note', 'content' => 'local body', 'author_id' => $this->agent->id]);

        $this->seedForeignWorkspace();

        // Seeding foreign documents runs IndexDocumentJob synchronously, and
        // that job binds the document's workspace without restoring it.
        // Restore the local binding the agent runtime would have.
        app()->instance('currentWorkspace', $this->workspace);
    }

    // Every mount root, plus a recursive find and search across all data
    // mounts, must not expose foreign names or content.
    public function test_foreign_records_are_invisible_through_ls_cat_find_and_search(): void
    {
        $executor = app(VfsCommandExecutor::class);
        $budget = new VfsBudget(maxEntries: 500, maxDepth: 3, maxBytes: 200_000, maxFiles: 300, maxMatches: 500);

        foreach (['/docs', '/files', '/tasks', '/lists', '/tables', '/channels', '/agents', '/automations', '/approvals', '/workspace'] as $mount) {
            $listing = $executor->execute($this->agent, "ls {$mount}", '/', $budget)['stdout'];
            $this->assertStringNotContainsStringIgnoringCase(self::MARKER, $listing, "ls {$mount} leaked foreign data");
        }

        foreach (['/workspace/members.json', '/tasks/counts.json', '/lists/counts.json'] as $file) {
            $content = $executor->execute($this->agent, "cat {$file}", '/', $budget)['stdout'];
            $this->assertStringNotContainsStringIgnoringCase(self::MARKER, $content, "cat {$file} leaked foreign data");
        }

        // Data mounts only: walking /tools (a large generated catalog) makes
        // find/rg over / slow without adding isolation coverage.
        $mounts = '/docs /files /tasks /lists /tables /channels /agents /automations /approvals /workspace';

        foreach (explode(' ', $mounts) as $mount) {
            $found = $executor->execute($this->agent, "find {$mount} -maxdepth 3", '/', $budget)['stdout'];
            $this->assertStringNotContainsStringIgnoringCase(self::MARKER, $found, "find {$mount} leaked foreign data");
        }

        foreach (['rg -i '.self::MARKER." {$mounts}", 'grep -r -i '.self::MARKER." {$mounts}"] as $search) {
            $result = $executor->execute($this->agent, $search.' || true', '/', $budget);
            $this->assertStringNotContainsStringIgnoringCase(self::MARKER, (string) $result['stdout'], "{$search} leaked foreign data");
        }

        // Positive control: the same search surface does find local content.
        $this->assertStringContainsString('local body', $executor->execute($this->agent, "rg -i 'local body' {$mounts}", '/', $budget)['stdout']);

        $hits = app(OpenCompanyVfs::class)->searchWithMetadata($this->agent, self::MARKER, explode(' ', $mounts), $budget, false, true);
        $this->assertSame([], $hits['matches']);
    }

    // Guessing a foreign record's canonical id must look exactly like a
    // missing record: not_found, never permission_denied (which would confirm
    // the id exists) and never content.
    public function test_by_id_guessing_of_foreign_records_returns_not_found(): void
    {
        $vfs = app(OpenCompanyVfs::class);

        foreach ($this->foreignPaths as $mount => $path) {
            foreach (['stat' => fn () => $vfs->stat($this->agent, $path), 'read' => fn () => $vfs->read($this->agent, $path)] as $operation => $call) {
                try {
                    $call();
                    $this->fail("{$operation} {$path} ({$mount}) should not resolve a foreign record.");
                } catch (VfsError $e) {
                    $this->assertSame('not_found', $e->errorCode, "{$operation} {$path} ({$mount}) returned {$e->errorCode}");
                }
            }
        }
    }

    // Cursors are bound to workspace + agent; one minted in another
    // workspace must be rejected rather than resuming its listing.
    public function test_cursor_minted_in_another_workspace_is_rejected(): void
    {
        $vfs = app(OpenCompanyVfs::class);

        app()->instance('currentWorkspace', $this->foreign);
        $page = $vfs->listPage($this->foreignAgent, '/docs', new VfsBudget(maxEntries: 1));
        $this->assertNotNull($page->nextCursor);
        app()->instance('currentWorkspace', $this->workspace);

        try {
            $vfs->listPage($this->agent, '/docs', new VfsBudget(maxEntries: 1), $page->nextCursor);
            $this->fail('A foreign cursor must be rejected.');
        } catch (VfsError $e) {
            $this->assertSame('invalid_request', $e->errorCode);
            $this->assertSame('cursor', $e->context['field'] ?? null);
        }
    }

    // The facade and the shell executor refuse to run when the bound
    // workspace is not the agent's (or nothing is bound), before any query.
    public function test_mismatched_or_missing_workspace_binding_fails_closed(): void
    {
        $vfs = app(OpenCompanyVfs::class);
        $executor = app(VfsCommandExecutor::class);
        $calls = [
            'list' => fn () => $vfs->list($this->agent, '/docs'),
            'read' => fn () => $vfs->read($this->agent, '/workspace/settings.json'),
            'readContent' => fn () => $vfs->readContent($this->agent, '/workspace/settings.json'),
            'stat' => fn () => $vfs->stat($this->agent, '/docs'),
            'write' => fn () => $vfs->write($this->agent, '/files/x.txt', 'x'),
            'patch' => fn () => $vfs->patch($this->agent, '/files/x.txt', '{"search":"a","replace":"b"}'),
            'search' => fn () => $vfs->search($this->agent, 'x', ['/']),
            'exec' => fn () => $executor->execute($this->agent, 'ls /'),
        ];

        foreach (['foreign binding' => fn () => app()->instance('currentWorkspace', $this->foreign), 'no binding' => fn () => app()->forgetInstance('currentWorkspace')] as $case => $bind) {
            $bind();
            foreach ($calls as $name => $call) {
                try {
                    $call();
                    $this->fail("{$name} must fail with a {$case}.");
                } catch (VfsError $e) {
                    $this->assertSame('permission_denied', $e->errorCode, "{$name} with {$case}");
                    $this->assertSame('workspace_binding_mismatch', $e->context['reason']);
                }
            }
        }

        app()->instance('currentWorkspace', $this->foreign);
        $envelope = json_decode(app(VfsExec::class, ['agent' => $this->agent, 'executor' => $executor])->handle(new Request(['command' => 'ls /'])), true);
        $this->assertFalse($envelope['ok']);
        $this->assertSame('permission_denied', $envelope['error']['code']);
        app()->instance('currentWorkspace', $this->workspace);
    }

    // vfs:atlas resolves the agent only inside the selected workspace, never
    // rewrites workspace_id, and is unavailable outside local/testing.
    public function test_atlas_resolves_agents_only_inside_the_selected_workspace(): void
    {
        $this->assertSame(1, Artisan::call('vfs:atlas', ['vfsCommand' => ['pwd'], '--agent' => $this->foreignAgent->name]));

        $this->assertSame(0, Artisan::call('vfs:atlas', ['vfsCommand' => ['pwd'], '--agent' => $this->foreignAgent->name, '--workspace' => $this->foreign->slug, '--json' => true]));
        $this->assertStringContainsString($this->foreignAgent->id, Artisan::output());

        // The agent factory assigns the bound workspace, so detach explicitly.
        $orphan = User::factory()->agent()->create(['name' => 'Orphan Agent']);
        User::query()->whereKey($orphan->id)->update(['workspace_id' => null]);
        $this->assertSame(1, Artisan::call('vfs:atlas', ['vfsCommand' => ['pwd'], '--agent' => $orphan->name]));
        $this->assertNull($orphan->fresh()->workspace_id);

        $this->app['env'] = 'production';
        try {
            $this->assertSame(1, Artisan::call('vfs:atlas', ['vfsCommand' => ['pwd']]));
            $this->assertStringContainsString('local or testing', Artisan::output());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    // vfs:atlas goes through the runtime's approval wrapping: an approval-
    // gated primitive records an approval and does not execute.
    public function test_atlas_routes_primitives_through_approval_wrapping(): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'vfs_write',
            'permission' => 'allow',
            'requires_approval' => true,
        ]);

        $exit = Artisan::call('vfs:atlas', ['--primitive' => 'write', '--arg' => ['path=/files/atlas-gated.txt', 'content=x']]);

        $this->assertSame(1, $exit);
        // Phase 2: gated vfs_* primitives answer with the pending_approval JSON.
        $this->assertStringContainsString('pending_approval', Artisan::output());
        $approval = ApprovalRequest::query()->where('requester_id', $this->agent->id)->sole();
        $this->assertSame('vfs_write', $approval->tool_execution_context['tool_slug']);
        $this->assertFalse(WorkspaceFile::forWorkspace()->where('name', 'atlas-gated.txt')->exists());
    }

    private function seedForeignWorkspace(): void
    {
        $this->foreign = Workspace::create(['name' => 'Qzforeign Co', 'slug' => 'qzforeign-co']);
        $this->disk($this->foreign, 'disk-foreign');
        $foreignId = $this->foreign->id;

        // Reports to the local agent: a relationship-gated mount that forgot
        // the workspace would list it.
        $this->foreignAgent = User::factory()->agent()->create([
            'workspace_id' => $foreignId,
            'name' => 'Qzforeign Agent',
            'manager_id' => $this->agent->id,
        ]);

        $docs = [];
        foreach (['Qzforeign Doc One', 'Qzforeign Doc Two'] as $title) {
            $docs[] = Document::factory()->create([
                'workspace_id' => $foreignId,
                'title' => $title,
                'content' => 'qzforeign secret body',
                'author_id' => $this->foreignAgent->id,
            ]);
        }

        $file = app(FileSystemService::class)->writeFile($foreignId, null, 'qzforeign-secret.txt', 'qzforeign file body', $this->foreignAgent->id);

        $task = Task::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $foreignId,
            'title' => 'Qzforeign Task',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'agent_id' => $this->foreignAgent->id,
            'requester_id' => $this->foreignAgent->id,
            'source' => Task::SOURCE_MANUAL,
        ]);

        $project = ListItem::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $foreignId,
            'title' => 'Qzforeign Project',
            'description' => 'qzforeign project',
            'is_folder' => true,
            'status' => 'active',
            'creator_id' => $this->foreignAgent->id,
        ]);
        $item = ListItem::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $foreignId,
            'parent_id' => $project->id,
            'title' => 'Qzforeign Item',
            'description' => 'qzforeign item body',
            'is_folder' => false,
            'status' => 'todo',
            'creator_id' => $this->foreignAgent->id,
        ]);

        $channel = Channel::factory()->public()->create([
            'workspace_id' => $foreignId,
            'name' => 'qzforeign-channel',
            'creator_id' => $this->foreignAgent->id,
        ]);
        Message::factory()->create([
            'channel_id' => $channel->id,
            'author_id' => $this->foreignAgent->id,
            'content' => 'qzforeign message',
            'timestamp' => now(),
        ]);

        $table = DataTable::create(['workspace_id' => $foreignId, 'name' => 'Qzforeign Table', 'created_by' => $this->foreignAgent->id]);
        $column = DataTableColumn::create(['table_id' => $table->id, 'name' => 'Qzforeign Col', 'type' => 'text', 'order' => 0, 'required' => false]);
        $row = DataTableRow::create(['table_id' => $table->id, 'data' => [$column->id => 'qzforeign cell'], 'created_by' => $this->foreignAgent->id]);

        $automation = Automation::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $foreignId,
            'name' => 'Qzforeign Automation',
            'execution_type' => 'prompt',
            'agent_id' => $this->foreignAgent->id,
            'prompt' => 'qzforeign prompt',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'created_by_id' => $this->foreignAgent->id,
            'is_active' => true,
            'keep_history' => true,
        ]);

        $approval = ApprovalRequest::factory()->create([
            'requester_id' => $this->foreignAgent->id,
            'title' => 'Qzforeign approval',
            'status' => 'pending',
        ]);

        $human = User::factory()->create(['name' => 'Qzforeign Human', 'email' => 'qzforeign@example.com']);
        $this->foreign->members()->attach($human->id, ['id' => (string) Str::uuid(), 'role' => 'admin']);

        $this->foreignPaths = [
            'docs' => "/docs/by-id/{$docs[0]->id}",
            'files' => "/files/by-id/{$file->id}",
            'tasks' => "/tasks/by-id/{$task->id}",
            'lists' => "/lists/by-id/{$item->id}",
            'channels' => "/channels/by-id/{$channel->id}",
            'agents' => "/agents/by-id/{$this->foreignAgent->id}",
            'tables' => "/tables/qzforeign-table/rows/by-id/{$row->id}.json",
            'automations' => "/automations/{$automation->id}.json",
            'approvals' => "/approvals/{$approval->id}.json",
        ];
    }

    private function disk(Workspace $workspace, string $id): void
    {
        WorkspaceDisk::create([
            'id' => $id,
            'workspace_id' => $workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
    }
}
