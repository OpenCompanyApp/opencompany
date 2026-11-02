<?php

namespace Tests\Feature\Vfs;

use App\Agents\Runtime\ActiveAgentRun;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\AgentPermission;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\DataTableRowVersion;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFileVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 2 (C1): object history as the foundation for change sets and
 * "revert this run".
 *
 * Protects: every committed VFS write of a document, workspace file or table
 * row stores the replaced state attributed to the agent and the active run
 * with before/after version tokens; `<object>/.history/` lists and reads
 * versions through the object's own read checks; restore is a normal
 * governed write; history is read-only.
 */
class VfsHistoryTest extends TestCase
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

    protected function tearDown(): void
    {
        app()->forgetInstance(ActiveAgentRun::class);

        parent::tearDown();
    }

    // Docs: create + overwrite + patch inside a run each leave one attributed
    // snapshot; .history lists newest first, v{n} reads the replaced content,
    // and restore is a governed overwrite with the current version.
    public function test_document_history_is_attributed_listed_read_and_restorable(): void
    {
        $folder = Document::factory()->create(['title' => 'Wiki', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $this->permission('folder', $folder->id);
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun('run-42'));

        $created = $this->vfs->write($this->agent, '/docs/wiki/Policy', 'v-one');
        $updated = $this->vfs->write($this->agent, '/docs/wiki/policy', 'v-two', 'overwrite', $created['version']);
        $patched = $this->vfs->patch($this->agent, '/docs/wiki/policy', json_encode(['search' => 'two', 'replace' => 'three']));

        $versions = DocumentVersion::query()->orderBy('version_number')->get();
        $this->assertSame([1, 2, 3], $versions->pluck('version_number')->all());
        $this->assertSame(['', 'v-one', 'v-two'], $versions->pluck('content')->all());
        $this->assertSame(['run-42'], $versions->pluck('run_id')->unique()->values()->all());
        $this->assertSame([(string) $this->agent->id], $versions->pluck('author_id')->unique()->values()->all());
        $this->assertSame([null, $created['version'], $updated['version']], $versions->pluck('before_version')->all());
        $this->assertSame([$created['version'], $updated['version'], $patched['version']], $versions->pluck('after_version')->all());

        $entries = $this->vfs->list($this->agent, '/docs/wiki/policy/.history');
        $this->assertCount(3, $entries);
        $this->assertMatchesRegularExpression('/^v3--atlas--\d{8}T\d{6}Z\.md$/', $entries[0]->name);
        $this->assertSame('run-42', $entries[0]->metadata['run_id']);

        $canonical = $created['canonical_path'];
        $this->assertSame('v-one', $this->vfs->read($this->agent, "{$canonical}/.history/v2"));
        $this->assertSame('v-two', $this->vfs->read($this->agent, '/docs/wiki/policy/.history/'.$entries[0]->name));

        // Restore = read the old version, write it with the current version.
        $current = $this->vfs->readContentWithVersion($this->agent, '/docs/wiki/policy');
        $this->vfs->write($this->agent, '/docs/wiki/policy', $this->vfs->readContent($this->agent, '/docs/wiki/policy/.history/v2'), 'overwrite', $current['version']);
        $this->assertSame('v-one', $this->vfs->read($this->agent, '/docs/wiki/policy'));
        $this->assertSame(4, DocumentVersion::query()->max('version_number'));

        $this->assertVfsError('not_writable', fn () => $this->vfs->write($this->agent, '/docs/wiki/policy/.history/v2', 'x', 'overwrite'));
        $this->assertVfsError('invalid_request', fn () => $this->vfs->remove($this->agent, '/docs/wiki/policy/.history/v2'));
    }

    // History inherits the object's read checks: another agent without the
    // folder grant cannot list a doc's history.
    public function test_history_respects_object_read_permission(): void
    {
        $allowed = Document::factory()->create(['title' => 'Allowed', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $secret = Document::factory()->create(['title' => 'Secret', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $doc = Document::factory()->create(['title' => 'Plan', 'parent_id' => $secret->id, 'content' => 'x', 'author_id' => $this->agent->id]);
        DocumentVersion::create(['id' => (string) Str::uuid(), 'document_id' => $doc->id, 'title' => 'Plan', 'content' => 'old', 'author_id' => $this->agent->id, 'version_number' => 1]);
        $this->permission('folder', $allowed->id);

        $this->assertVfsError('permission_denied', fn () => $this->vfs->list($this->agent, "/docs/by-id/{$doc->id}/.history"));
        $this->assertVfsError('permission_denied', fn () => $this->vfs->read($this->agent, "/docs/by-id/{$doc->id}/.history/v1"));
    }

    // Files: every overwrite snapshots the replaced bytes; history reads them.
    public function test_file_history_snapshots_previous_bytes(): void
    {
        $first = $this->vfs->write($this->agent, '/files/reports/q3.csv', "a,b\n1,2\n");
        $this->vfs->write($this->agent, '/files/reports/q3.csv', "a,b\n3,4\n", 'overwrite', $first['version']);
        $this->vfs->patch($this->agent, '/files/reports/q3.csv', json_encode(['search' => '3,4', 'replace' => '5,6']));

        $this->assertSame(3, WorkspaceFileVersion::query()->count());
        $entries = $this->vfs->list($this->agent, '/files/reports/q3.csv/.history');
        $this->assertCount(3, $entries);
        $this->assertStringEndsWith('.csv', $entries[0]->name);
        $this->assertSame("a,b\n1,2\n", $this->vfs->read($this->agent, '/files/reports/q3.csv/.history/v2'));
        $this->assertSame("a,b\n3,4\n", $this->vfs->read($this->agent, "{$first['canonical_path']}/.history/v3"));
    }

    // Rows: patch and overwrite snapshot the replaced data (by column id) and
    // history renders it in the shared name-keyed shape.
    public function test_table_row_history_snapshots_previous_data(): void
    {
        $table = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Deals', 'created_by' => $this->agent->id]);
        $stage = DataTableColumn::create(['table_id' => $table->id, 'name' => 'Stage', 'type' => 'text', 'order' => 0, 'required' => false]);
        $row = DataTableRow::create(['table_id' => $table->id, 'data' => [$stage->id => 'lead'], 'created_by' => $this->agent->id]);
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun('run-7'));

        $this->vfs->patch($this->agent, "/tables/deals/rows/{$row->id}", json_encode(['data' => ['Stage' => 'won']]));

        $snapshot = DataTableRowVersion::query()->sole();
        $this->assertSame([$stage->id => 'lead'], $snapshot->data);
        $this->assertSame('run-7', $snapshot->run_id);

        $entries = $this->vfs->list($this->agent, "/tables/deals/rows/by-id/{$row->id}.json/.history");
        $this->assertMatchesRegularExpression('/^v1--atlas--.*\.json$/', $entries[0]->name);
        $historical = json_decode($this->vfs->read($this->agent, "/tables/by-id/{$table->id}/rows/by-id/{$row->id}.json/.history/v1"), true);
        $this->assertSame(['Stage' => 'lead'], $historical['data']);
    }

    // Concurrent writers outside the VFS lock cannot make two snapshots share
    // a number: the unique index plus retry assigns the next free one.
    public function test_document_snapshot_numbering_survives_a_competing_writer(): void
    {
        $folder = Document::factory()->create(['title' => 'Race', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $this->permission('folder', $folder->id);
        $created = $this->vfs->write($this->agent, '/docs/race/Doc', 'one');
        $doc = Document::forWorkspace()->where('title', 'Doc')->sole();

        // A domain-tool style writer takes number 2 without the VFS lock.
        DocumentVersion::create(['id' => (string) Str::uuid(), 'document_id' => $doc->id, 'title' => 'Doc', 'content' => 'external', 'author_id' => $this->agent->id, 'version_number' => 2]);

        $this->vfs->write($this->agent, '/docs/race/doc', 'two', 'overwrite', $created['version']);
        $this->assertSame([1, 2, 3], DocumentVersion::query()->where('document_id', $doc->id)->orderBy('version_number')->pluck('version_number')->all());
    }

    private function permission(string $scopeType, string $scopeKey): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'permission' => 'allow',
            'requires_approval' => false,
        ]);
    }

    private function assertVfsError(string $code, callable $callback): VfsError
    {
        try {
            $callback();
        } catch (VfsError $e) {
            $this->assertSame($code, $e->errorCode, $e->getMessage().' '.json_encode($e->context));

            return $e;
        }

        $this->fail("Expected VfsError {$code}.");
    }
}
