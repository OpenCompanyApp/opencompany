<?php

namespace Tests\Feature\Vfs;

use App\Agents\Tools\Vfs\VfsRm;
use App\Agents\Tools\Vfs\VfsWrite;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\ListItem;
use App\Models\ListStatus;
use App\Models\User;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceMember;
use App\Services\ApprovalExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Phase 2 (C1): a VFS-only agent can create, read, update, delete (trash),
 * rename/move and restore company records through the VFS alone, and every
 * such operation is at least as governed as the equivalent domain tool
 * (permission denial, approval creation, approved replay).
 */
class VfsRecordsLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private OpenCompanyVfs $vfs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent('manager')->create(['name' => 'Atlas', 'status' => 'idle']);
        WorkspaceDisk::create([
            'id' => 'disk-local',
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
        $this->permission('file_folder', '*');
        $this->vfs = app(OpenCompanyVfs::class);
    }

    // Docs: create inside a granted folder (with .md), read back, overwrite
    // only with the version, stale version is a conflict, missing version is
    // an explicit "read it first".
    public function test_document_create_read_overwrite_with_required_version(): void
    {
        $folder = $this->grantedFolder('Reports');

        $created = $this->vfs->write($this->agent, '/docs/reports/Weekly Report.md', "# Week 1\n", 'create');
        $this->assertSame('created', $created['status']);
        $this->assertSame('/docs/reports/weekly-report', $created['path']);
        $this->assertStringStartsWith('/docs/by-id/', $created['canonical_path']);
        $doc = Document::forWorkspace()->where('parent_id', $folder->id)->sole();
        $this->assertSame('Weekly Report', $doc->title);

        $read = $this->vfs->readContentWithVersion($this->agent, '/docs/reports/weekly-report.md');
        $this->assertSame("# Week 1\n", $read['content']);
        $this->assertSame($created['version'], $read['version']);
        $this->assertSame("/docs/by-id/{$doc->id}", $read['canonical_path']);

        $this->assertVfsError('invalid_request', fn () => $this->vfs->write($this->agent, '/docs/reports/weekly-report', 'x', 'overwrite'), 'read it first');
        $this->assertVfsError('invalid_request', fn () => $this->vfs->write($this->agent, '/docs/reports/weekly-report', 'x', 'create'));

        $updated = $this->vfs->write($this->agent, '/docs/reports/weekly-report', "# Week 2\n", 'overwrite', $read['version']);
        $this->assertSame('updated', $updated['status']);
        $this->assertSame("# Week 2\n", $doc->fresh()->content);

        $this->assertVfsError('conflict', fn () => $this->vfs->write($this->agent, '/docs/reports/weekly-report', 'stale', 'overwrite', $read['version']));
        $this->assertSame("# Week 2\n", $doc->fresh()->content);
    }

    // Docs: default (no folder grant) agents read everything and write
    // nothing — creating, deleting and moving are refused the same way.
    public function test_document_writes_require_a_folder_grant(): void
    {
        $folder = Document::factory()->create(['title' => 'Open', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $doc = Document::factory()->create(['title' => 'Readme', 'parent_id' => $folder->id, 'content' => 'x', 'author_id' => $this->agent->id]);

        $this->assertSame('x', $this->vfs->read($this->agent, '/docs/open/readme'));
        $this->assertVfsError('permission_denied', fn () => $this->vfs->write($this->agent, '/docs/open/new-doc', 'y'));
        $this->assertVfsError('permission_denied', fn () => $this->vfs->remove($this->agent, '/docs/open/readme'));
        $this->assertVfsError('permission_denied', fn () => $this->vfs->move($this->agent, '/docs/open/readme', '/docs/open/renamed'));
        $this->assertVfsError('permission_denied', fn () => $this->vfs->makeDirectory($this->agent, '/docs/open/sub'));
        $this->assertNull($doc->fresh()->deleted_at);
    }

    // Docs: rm moves to /docs/.trash (subtree included), the doc leaves
    // listings, and mv out of the trash restores it.
    public function test_document_trash_and_restore(): void
    {
        $folder = $this->grantedFolder('Projects');
        $sub = Document::factory()->create(['title' => 'Alpha', 'is_folder' => true, 'parent_id' => $folder->id, 'author_id' => $this->agent->id]);
        $child = Document::factory()->create(['title' => 'Plan', 'parent_id' => $sub->id, 'content' => 'plan', 'author_id' => $this->agent->id]);

        $trashed = $this->vfs->remove($this->agent, '/docs/projects/alpha');
        $this->assertSame('trashed', $trashed['status']);
        $this->assertSame(2, $trashed['trashed']);
        $this->assertTrue(Document::find($sub->id) === null && Document::find($child->id) === null);
        $this->assertNotNull(Document::withTrashed()->find($child->id)->deleted_at);
        $this->assertSame([], array_map(fn ($e) => $e->name, $this->vfs->list($this->agent, '/docs/projects')));
        $this->assertVfsError('not_found', fn () => $this->vfs->read($this->agent, '/docs/projects/alpha/plan'));

        $trash = $this->vfs->list($this->agent, '/docs/.trash');
        $this->assertCount(1, $trash);
        $this->assertSame("/docs/.trash/{$sub->id}", $trash[0]->path);

        $restored = $this->vfs->move($this->agent, "/docs/.trash/{$sub->id}", '/docs/projects');
        $this->assertSame('restored', $restored['status']);
        $this->assertSame('plan', $this->vfs->read($this->agent, '/docs/projects/alpha/plan'));
        $this->assertSame([], $this->vfs->list($this->agent, '/docs/.trash'));
    }

    // Docs: mv renames and moves between folders; both sides are authorized.
    public function test_document_rename_and_move_between_folders(): void
    {
        $from = $this->grantedFolder('Inbox');
        $to = $this->grantedFolder('Archive');
        $denied = Document::factory()->create(['title' => 'Private', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $doc = Document::factory()->create(['title' => 'Note', 'parent_id' => $from->id, 'content' => 'n', 'author_id' => $this->agent->id]);

        $renamed = $this->vfs->move($this->agent, '/docs/inbox/note', '/docs/inbox/Meeting Note');
        $this->assertSame('/docs/inbox/meeting-note', $renamed['path']);

        $moved = $this->vfs->move($this->agent, '/docs/inbox/meeting-note', '/docs/archive');
        $this->assertSame('/docs/archive/meeting-note', $moved['path']);
        $this->assertSame($to->id, $doc->fresh()->parent_id);

        $this->assertVfsError('permission_denied', fn () => $this->vfs->move($this->agent, '/docs/archive/meeting-note', "/docs/by-id/{$denied->id}"));

        $made = $this->vfs->makeDirectory($this->agent, '/docs/archive/2026/Q4');
        $this->assertSame('created', $made['status']);
        $this->assertSame('/docs/archive/2026/q4', $made['path']);
    }

    // Composition with domain tools: create needs create_document; when that
    // tool is approval-gated the write creates ONE approval and the approved
    // replay applies it.
    public function test_document_create_with_gated_domain_tool_applies_after_approval(): void
    {
        $folder = $this->grantedFolder('Gated');
        $this->gate('create_document');

        $response = json_decode($this->writeTool()->handle(new Request([
            'path' => "/docs/by-id/{$folder->id}/Approved Doc.md",
            'content' => 'approved body',
            'mode' => 'create',
        ])), true);
        $this->assertSame('approval_required', $response['error']['code']);
        $this->assertSame(['create_document'], $response['error']['context']['tools']);
        $this->assertFalse(Document::forWorkspace()->where('title', 'Approved Doc')->exists());

        app(ApprovalExecutionService::class)->resolve(ApprovalRequest::query()->sole(), 'approved', $this->approver());

        $this->assertSame('approved body', Document::forWorkspace()->where('title', 'Approved Doc')->sole()->content);
        $this->assertSame(1, ApprovalRequest::query()->count());
    }

    // A denied domain delete tool blocks rm even though vfs_write is allowed.
    public function test_document_rm_is_denied_when_delete_document_is_denied(): void
    {
        $folder = $this->grantedFolder('Keep');
        $doc = Document::factory()->create(['title' => 'Keep Me', 'parent_id' => $folder->id, 'content' => 'k', 'author_id' => $this->agent->id]);
        $this->permission('tool', 'delete_document', permission: 'deny');

        $this->assertVfsError('permission_denied', fn () => $this->vfs->remove($this->agent, '/docs/keep/keep-me'));
        $this->assertNotNull($doc->fresh());
    }

    // Tables: create via rows/new.json (name-keyed, typed), overwrite with
    // the version, trash and restore; values are validated per column type.
    public function test_table_row_create_overwrite_trash_restore(): void
    {
        [$table, $columns] = $this->customersTable();

        $created = $this->vfs->write($this->agent, '/tables/customers/rows/new.json', json_encode(['data' => ['Name' => 'Ada', 'Seats' => 3, 'Active' => true]]));
        $this->assertSame('created', $created['status']);
        $row = DataTableRow::where('table_id', $table->id)->sole();
        $this->assertSame("/tables/by-id/{$table->id}/rows/by-id/{$row->id}.json", $created['canonical_path']);
        $this->assertSame(['Name' => 'Ada', 'Seats' => 3, 'Active' => true], array_intersect_key(json_decode($this->vfs->read($this->agent, $created['path']), true)['data'], ['Name' => 1, 'Seats' => 1, 'Active' => 1]));

        $this->assertVfsError('invalid_request', fn () => $this->vfs->write($this->agent, '/tables/customers/rows/new.json', json_encode(['data' => ['Seats' => 'many']])), 'Seats must be a number');
        $this->assertVfsError('invalid_request', fn () => $this->vfs->write($this->agent, '/tables/customers/rows/new.json', json_encode(['data' => ['Seats' => 1]])), 'Name is required');
        $this->assertVfsError('invalid_request', fn () => $this->vfs->write($this->agent, '/tables/customers/rows/new.json', json_encode(['data' => ['Plan' => 'gold']])), 'Unknown table column');

        // Overwrite round-trips the payload read from rows/by-id.
        $payload = json_decode($this->vfs->read($this->agent, "/tables/customers/rows/{$row->id}"), true);
        $payload['data']['Seats'] = 5;
        $this->assertVfsError('invalid_request', fn () => $this->vfs->write($this->agent, "/tables/customers/rows/by-id/{$row->id}.json", json_encode($payload), 'overwrite'), 'read');
        $this->vfs->write($this->agent, "/tables/customers/rows/by-id/{$row->id}.json", json_encode($payload), 'overwrite', $payload['version']);
        $this->assertSame(5, $row->fresh()->data[$columns['Seats']->id]);
        $this->assertSame('Ada', $row->fresh()->data[$columns['Name']->id]);

        $this->assertSame('trashed', $this->vfs->remove($this->agent, "/tables/customers/rows/by-id/{$row->id}.json")['status']);
        $this->assertNull(DataTableRow::find($row->id));
        $this->assertStringNotContainsString($row->id, $this->vfs->read($this->agent, '/tables/customers/rows.ndjson'));
        $this->assertCount(1, $this->vfs->list($this->agent, "/tables/by-id/{$table->id}/rows/.trash"));

        $restored = $this->vfs->move($this->agent, "/tables/customers/rows/.trash/{$row->id}.json", '/tables/customers/rows/');
        $this->assertSame('restored', $restored['status']);
        $this->assertNotNull(DataTableRow::find($row->id));
    }

    // Row delete composes delete_table_row: gated → one approval through the
    // vfs_rm tool, approved replay trashes the row.
    public function test_table_row_rm_with_gated_delete_applies_after_approval(): void
    {
        [$table] = $this->customersTable();
        $row = DataTableRow::create(['table_id' => $table->id, 'data' => [], 'created_by' => $this->agent->id]);
        $this->gate('delete_table_row');

        $response = json_decode($this->rmTool()->handle(new Request(['path' => "/tables/customers/rows/{$row->id}.json"])), true);
        $this->assertFalse($response['ok']);
        $this->assertSame('approval_required', $response['error']['code']);
        $this->assertNotNull(DataTableRow::find($row->id));

        app(ApprovalExecutionService::class)->resolve(ApprovalRequest::query()->sole(), 'approved', $this->approver());

        $this->assertNull(DataTableRow::find($row->id));
        $this->assertNotNull(DataTableRow::onlyTrashed()->find($row->id));
    }

    // Lists: front-matter markdown round trip, create via new.md, unknown
    // status rejected with the allowed list, rm → trash → restore.
    public function test_list_item_front_matter_round_trip_create_and_trash(): void
    {
        $this->statuses();
        $project = ListItem::create([
            'id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'title' => 'Launch', 'description' => '', 'is_folder' => true,
            'status' => 'todo', 'priority' => 'medium', 'position' => 1, 'creator_id' => $this->agent->id,
        ]);

        $created = $this->vfs->write($this->agent, '/lists/projects/launch/new.md', "---\ntitle: Write announcement\nstatus: todo\npriority: high\n---\n\nDraft the post.\n");
        $item = ListItem::forWorkspace()->where('parent_id', $project->id)->sole();
        $this->assertSame('created', $created['status']);
        $this->assertSame(['high', $this->agent->id, $this->agent->id], [$item->priority, $item->creator_id, $item->assignee_id]);

        $markdown = $this->vfs->read($this->agent, "/lists/by-id/{$item->id}.md");
        $this->assertStringContainsString("title: 'Write announcement'", $markdown);
        $this->assertStringContainsString('project: Launch', $markdown);
        $this->assertStringContainsString('Draft the post.', $markdown);

        $this->assertVfsError('invalid_request', fn () => $this->vfs->write($this->agent, "/lists/by-id/{$item->id}", str_replace('status: todo', 'status: shipped', $markdown), 'overwrite'), 'Unknown list status');
        $this->assertVfsError('invalid_request', fn () => $this->vfs->write($this->agent, "/lists/by-id/{$item->id}", str_replace('project: Launch', 'project: Other', $markdown), 'overwrite'), 'read-only');

        // The front matter's version line is the base when no version is passed.
        $this->vfs->write($this->agent, "/lists/by-id/{$item->id}", str_replace(['status: todo', 'Draft the post.'], ['status: done', 'Posted.'], $markdown), 'overwrite');
        $fresh = $item->fresh();
        $this->assertSame(['done', 'Posted.'], [$fresh->status, trim((string) $fresh->description)]);
        $this->assertNotNull($fresh->completed_at);
        $this->assertVfsError('conflict', fn () => $this->vfs->write($this->agent, "/lists/by-id/{$item->id}", $markdown, 'overwrite'));

        $this->assertVfsError('invalid_request', fn () => $this->vfs->patch($this->agent, "/lists/by-id/{$item->id}", json_encode(['status' => 'nope'])), 'Unknown list status');

        $this->assertSame('trashed', $this->vfs->remove($this->agent, "/lists/by-id/{$item->id}")['status']);
        $this->assertNull(ListItem::find($item->id));
        $this->assertCount(1, $this->vfs->list($this->agent, '/lists/.trash'));
        $this->assertSame('restored', $this->vfs->move($this->agent, "/lists/.trash/{$item->id}.md", "/lists/by-id/{$project->id}")['status']);
        $this->assertNotNull(ListItem::find($item->id));
    }

    // Lists: create composes create_list_item; denied → nothing is created.
    public function test_list_item_create_is_denied_without_create_list_item(): void
    {
        ListItem::create([
            'id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'title' => 'Ops', 'description' => '', 'is_folder' => true,
            'status' => 'todo', 'priority' => 'medium', 'position' => 1, 'creator_id' => $this->agent->id,
        ]);
        $this->permission('tool', 'create_list_item', permission: 'deny');

        $this->assertVfsError('permission_denied', fn () => $this->vfs->write($this->agent, '/lists/projects/ops/new.md', "---\ntitle: X\n---\n"));
        $this->assertSame(1, ListItem::forWorkspace()->count());
    }

    // Files: rm is a trash move (bytes kept, name freed), restore via mv.
    public function test_file_rm_trashes_and_restores(): void
    {
        $this->vfs->write($this->agent, '/files/notes/a.txt', 'alpha');
        $file = WorkspaceFile::forWorkspace()->where('name', 'a.txt')->sole();

        $this->assertSame('trashed', $this->vfs->remove($this->agent, '/files/notes/a.txt')['status']);
        $this->assertVfsError('not_found', fn () => $this->vfs->read($this->agent, '/files/notes/a.txt'));
        // The name is free again while the trashed bytes stay restorable.
        $this->vfs->write($this->agent, '/files/notes/a.txt', 'second');
        $this->assertSame('alpha', $this->vfs->read($this->agent, "/files/.trash/{$file->id}"));

        $restored = $this->vfs->move($this->agent, "/files/.trash/{$file->id}", '/files/notes/a-restored.txt');
        $this->assertSame('restored', $restored['status']);
        $this->assertSame('alpha', $this->vfs->read($this->agent, '/files/notes/a-restored.txt'));
        $this->assertSame('second', $this->vfs->read($this->agent, '/files/notes/a.txt'));
    }

    // Shell verbs reach records too (rm/mv/mkdir through vfs_exec).
    public function test_shell_rm_and_mv_work_on_documents(): void
    {
        $this->grantedFolder('Shell');
        $this->vfs->write($this->agent, '/docs/shell/One', 'one');
        $executor = app(VfsCommandExecutor::class);

        $this->assertStringContainsString('moved', $executor->execute($this->agent, 'mv /docs/shell/one /docs/shell/Two')['stdout']);
        $this->assertStringContainsString('trashed', $executor->execute($this->agent, 'rm /docs/shell/two')['stdout']);
        $this->assertSame([], $this->vfs->list($this->agent, '/docs/shell'));
    }

    // Idempotency: a retried create with the same key returns the stored
    // result and does not create a second record; reusing the key for
    // another path is refused.
    public function test_idempotency_key_replays_without_mutating(): void
    {
        [$table] = $this->customersTable();
        $params = ['path' => '/tables/customers/rows/new.json', 'content' => json_encode(['data' => ['Name' => 'Bo']]), 'mode' => 'create', 'idempotency_key' => 'retry-1'];

        $first = $this->vfs->write($this->agent, $params['path'], $params['content'], 'create', null, $params);
        $second = $this->vfs->write($this->agent, $params['path'], $params['content'], 'create', null, $params);

        $this->assertSame($first['canonical_path'], $second['canonical_path']);
        $this->assertTrue($second['idempotent_replay']);
        $this->assertSame(1, DataTableRow::where('table_id', $table->id)->count());

        $this->assertVfsError('invalid_request', fn () => $this->vfs->remove($this->agent, $first['canonical_path'], ['path' => $first['canonical_path'], 'idempotency_key' => 'retry-1']), 'different operation');

        // A failed call releases its key so a corrected retry runs.
        $bad = ['idempotency_key' => 'retry-2'];
        $this->assertVfsError('invalid_request', fn () => $this->vfs->write($this->agent, '/tables/customers/rows/new.json', json_encode(['data' => ['Seats' => 'x']]), 'create', null, $bad));
        $this->vfs->write($this->agent, '/tables/customers/rows/new.json', json_encode(['data' => ['Name' => 'Cy']]), 'create', null, $bad);
        $this->assertSame(2, DataTableRow::where('table_id', $table->id)->count());
    }

    /**
     * @return array{0: DataTable, 1: array<string, DataTableColumn>}
     */
    private function customersTable(): array
    {
        $table = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Customers', 'created_by' => $this->agent->id]);
        $columns = [];
        foreach ([['Name', 'text', true], ['Seats', 'number', false], ['Active', 'checkbox', false]] as $i => [$name, $type, $required]) {
            $columns[$name] = DataTableColumn::create(['table_id' => $table->id, 'name' => $name, 'type' => $type, 'order' => $i, 'required' => $required]);
        }

        return [$table, $columns];
    }

    private function statuses(): void
    {
        foreach ([['todo', false, true, 1], ['done', true, false, 2]] as [$slug, $done, $default, $position]) {
            ListStatus::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'name' => ucfirst($slug), 'slug' => $slug, 'color' => 'gray', 'icon' => 'circle', 'is_done' => $done, 'is_default' => $default, 'position' => $position]);
        }
    }

    private function grantedFolder(string $title): Document
    {
        $folder = Document::factory()->create(['title' => $title, 'is_folder' => true, 'author_id' => $this->agent->id]);
        $this->permission('folder', $folder->id);

        return $folder;
    }

    private function gate(string $toolSlug): void
    {
        $this->permission('tool', $toolSlug, requiresApproval: true);
    }

    private function permission(string $scopeType, string $scopeKey, bool $requiresApproval = false, string $permission = 'allow'): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'permission' => $permission,
            'requires_approval' => $requiresApproval,
        ]);
    }

    private function writeTool(): VfsWrite
    {
        return app(VfsWrite::class, ['agent' => $this->agent, 'vfs' => $this->vfs]);
    }

    private function rmTool(): VfsRm
    {
        return app(VfsRm::class, ['agent' => $this->agent, 'vfs' => $this->vfs]);
    }

    private function approver(): User
    {
        $approver = User::factory()->create(['type' => 'human']);
        WorkspaceMember::query()->firstOrCreate(['workspace_id' => $this->workspace->id, 'user_id' => $approver->id], ['role' => 'admin']);

        return $approver;
    }

    private function assertVfsError(string $code, callable $callback, ?string $messageContains = null): VfsError
    {
        try {
            $callback();
        } catch (VfsError $e) {
            $this->assertSame($code, $e->errorCode, $e->getMessage().' '.json_encode($e->context));
            if ($messageContains !== null) {
                $this->assertStringContainsStringIgnoringCase($messageContains, $e->getMessage().' '.json_encode($e->context));
            }

            return $e;
        }

        $this->fail("Expected VfsError {$code}.");
    }
}
