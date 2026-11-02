<?php

namespace Tests\Feature\Vfs;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFile;
use App\Services\AgentDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 2 (C1): stable, guessable paths and the agent's own area.
 *
 * Protects: deterministic `--{id8}` collision suffixes instead of silent
 * first-match, ambiguous bare names, `.md` and `rows/<id>` aliases,
 * did_you_mean on not_found, canonical_path on reads, /tables/by-id, the one
 * name-keyed row shape (ndjson/csv/schema), `/me` layout with lazy creation
 * and the single-writer todo.md, `/me/pending`, and the root README.
 */
class VfsPathsAndMeTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private OpenCompanyVfs $vfs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent('manager')->create(['name' => 'Atlas', 'status' => 'idle']);
        $folder = app(AgentDocumentService::class)->createAgentDocumentStructure($this->agent, ['IDENTITY' => 'I am Atlas']);
        $this->agent->update(['docs_folder_id' => $folder->id]);
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

    // Two sibling docs whose titles slug the same are both listed with a
    // --{id8} suffix; the bare slug is ambiguous (listing both), each suffixed
    // path and the `.md` form resolve exactly one.
    public function test_document_slug_collisions_get_suffixes_and_bare_name_is_ambiguous(): void
    {
        $folder = Document::factory()->create(['title' => 'Plans', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $a = Document::factory()->create(['title' => 'Q3 Plan', 'parent_id' => $folder->id, 'content' => 'A', 'author_id' => $this->agent->id]);
        $b = Document::factory()->create(['title' => 'q3-plan', 'parent_id' => $folder->id, 'content' => 'B', 'author_id' => $this->agent->id]);
        Document::factory()->create(['title' => 'Unique', 'parent_id' => $folder->id, 'content' => 'U', 'author_id' => $this->agent->id]);

        $names = array_map(fn ($entry) => $entry->name, $this->vfs->list($this->agent, '/docs/plans'));
        $paths = array_map(fn ($entry) => $entry->path, $this->vfs->list($this->agent, '/docs/plans'));
        $this->assertContains('q3-plan--'.substr($a->id, -8), array_map(fn ($p) => basename($p), $paths));
        $this->assertContains('q3-plan--'.substr($b->id, -8), array_map(fn ($p) => basename($p), $paths));
        $this->assertContains('/docs/plans/unique', $paths);
        $this->assertContains('Unique', $names);

        $error = $this->assertVfsError('ambiguous_match', fn () => $this->vfs->read($this->agent, '/docs/plans/q3-plan'));
        $this->assertCount(2, $error->context['matches']);

        $this->assertSame('A', $this->vfs->read($this->agent, '/docs/plans/q3-plan--'.substr($a->id, -8)));
        $this->assertSame('B', $this->vfs->read($this->agent, '/docs/plans/q3-plan--'.substr($b->id, -8)));
        $this->assertSame('U', $this->vfs->read($this->agent, '/docs/plans/unique.md'));
        $this->assertSame('A', $this->vfs->read($this->agent, '/docs/plans/Q3 Plan'));
    }

    // A typo returns not_found with the nearest sibling path and its by-id
    // path, from read and from stat.
    public function test_not_found_suggests_did_you_mean(): void
    {
        $doc = Document::factory()->create(['title' => 'Reports', 'is_folder' => true, 'author_id' => $this->agent->id]);

        $error = $this->assertVfsError('not_found', fn () => $this->vfs->read($this->agent, '/docs/reprots'));
        $this->assertSame(['/docs/reports'], $error->context['did_you_mean']);
        $this->assertSame("/docs/by-id/{$doc->id}", $error->context['by_id']);

        $statError = $this->assertVfsError('not_found', fn () => $this->vfs->stat($this->agent, '/docs/reprots'));
        $this->assertSame(['/docs/reports'], $statError->context['did_you_mean']);

        DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Customers', 'created_by' => $this->agent->id]);
        $tableError = $this->assertVfsError('not_found', fn () => $this->vfs->read($this->agent, '/tables/customer/schema.json'));
        $this->assertSame(['/tables/customers'], $tableError->context['did_you_mean']);
    }

    // Tables: by-id addressing, row aliases, the shared name-keyed row shape,
    // schema id map, and rows.csv paging with the header on page 1 only.
    public function test_tables_by_id_aliases_and_row_shapes(): void
    {
        $table = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Deals', 'created_by' => $this->agent->id]);
        $name = DataTableColumn::create(['table_id' => $table->id, 'name' => 'Name', 'type' => 'text', 'order' => 0, 'required' => false]);
        $amount = DataTableColumn::create(['table_id' => $table->id, 'name' => 'Amount', 'type' => 'number', 'order' => 1, 'required' => false]);
        $first = DataTableRow::create(['table_id' => $table->id, 'data' => [$name->id => 'Acme, Inc.', $amount->id => 10], 'created_by' => $this->agent->id]);
        DataTableRow::create(['table_id' => $table->id, 'data' => [$name->id => 'Globex', $amount->id => 20], 'created_by' => $this->agent->id]);

        $schema = json_decode($this->vfs->read($this->agent, "/tables/by-id/{$table->id}/schema.json"), true);
        $this->assertSame(['Name' => $name->id, 'Amount' => $amount->id], $schema['column_ids']);
        $this->assertSame("/tables/by-id/{$table->id}", $schema['canonical_path']);

        $canonicalRow = "/tables/by-id/{$table->id}/rows/by-id/{$first->id}.json";
        foreach (["/tables/deals/rows/{$first->id}", "/tables/deals/rows/{$first->id}.json", $canonicalRow] as $alias) {
            $this->assertSame('Acme, Inc.', json_decode($this->vfs->read($this->agent, $alias), true)['data']['Name'], $alias);
        }
        $this->assertSame($canonicalRow, $this->vfs->stat($this->agent, "/tables/deals/rows/{$first->id}")['canonical_path']);
        $this->assertSame($canonicalRow, $this->vfs->readContentWithVersion($this->agent, "/tables/deals/rows/{$first->id}")['canonical_path']);

        $line = collect(explode("\n", $this->vfs->read($this->agent, '/tables/deals/rows.ndjson')))
            ->map(fn (string $l) => json_decode($l, true))
            ->firstWhere('id', $first->id);
        $this->assertSame(['id', 'path', 'version', 'data', 'data_by_column_id'], array_keys($line));
        $this->assertSame(['Name' => 'Acme, Inc.', 'Amount' => 10], $line['data']);
        $this->assertSame($canonicalRow, $line['path']);

        $page1 = $this->vfs->readPage($this->agent, '/tables/deals/rows.csv', new VfsBudget(maxEntries: 1));
        $this->assertStringStartsWith("id,Name,Amount\n", $page1->content);
        $this->assertNotNull($page1->nextCursor);
        $page2 = $this->vfs->readPage($this->agent, '/tables/deals/rows.csv', new VfsBudget(maxEntries: 1), $page1->nextCursor);
        $this->assertStringNotContainsString('id,Name,Amount', $page2->content);
        $this->assertStringContainsString('"Acme, Inc."', $page1->content.$page2->content);
    }

    // Tables and agents that share a slug get suffixed names instead of
    // resolving to whichever row the database returns first.
    public function test_table_and_agent_collisions_are_suffixed(): void
    {
        $one = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Leads', 'created_by' => $this->agent->id]);
        $two = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'leads', 'created_by' => $this->agent->id]);
        $tables = array_map(fn ($e) => $e->name, $this->vfs->list($this->agent, '/tables'));
        $this->assertContains('leads--'.substr($one->id, -8), $tables);
        $this->assertContains('leads--'.substr($two->id, -8), $tables);
        $this->assertVfsError('ambiguous_match', fn () => $this->vfs->read($this->agent, '/tables/leads/schema.json'));
        $this->assertSame($two->id, json_decode($this->vfs->read($this->agent, '/tables/leads--'.substr($two->id, -8).'/schema.json'), true)['id']);

        $twin = User::factory()->agent()->create(['workspace_id' => $this->workspace->id, 'name' => 'atlas', 'manager_id' => $this->agent->id]);
        $agents = array_map(fn ($e) => $e->name, $this->vfs->list($this->agent, '/agents'));
        $this->assertContains('atlas--'.substr((string) $this->agent->id, -8), $agents);
        $this->assertContains('atlas--'.substr((string) $twin->id, -8), $agents);
        $this->assertVfsError('ambiguous_match', fn () => $this->vfs->list($this->agent, '/agents/atlas'));
    }

    // /me layout; notes/ and todo.md are created on first write (never on
    // read); /me paths report canonical agent paths; scratch maps into the
    // agent's /files home.
    public function test_me_layout_lazy_notes_and_scratch(): void
    {
        $names = array_map(fn ($e) => $e->name, $this->vfs->list($this->agent, '/me'));
        foreach (['memory', 'notes', 'identity', 'scratch', 'results', 'pending', 'todo.md'] as $expected) {
            $this->assertContains($expected, $names);
        }
        $this->assertSame([], $this->vfs->list($this->agent, '/me/notes'));
        $this->assertSame([], $this->vfs->list($this->agent, '/me/scratch'));
        $this->assertFalse(Document::forWorkspace()->where('title', 'notes')->exists(), 'reading must not create notes/');
        $this->assertVfsError('not_found', fn () => $this->vfs->read($this->agent, '/me/todo.md'));

        $note = $this->vfs->write($this->agent, '/me/notes/people/alice.md', 'Prefers email.');
        $this->assertSame("/agents/by-id/{$this->agent->id}/notes/people/alice.md", $note['canonical_path']);
        $this->assertSame('Prefers email.', $this->vfs->read($this->agent, '/me/notes/people/alice.md'));
        $this->assertTrue(Document::forWorkspace()->where('title', 'notes')->where('is_system', true)->exists());
        // Notes are private working state: never listed under /docs.
        $this->assertStringNotContainsString('alice', $this->vfs->read($this->agent, '/docs/by-id'));

        $this->vfs->write($this->agent, '/me/scratch/draft.txt', 'wip');
        $this->assertSame('wip', $this->vfs->read($this->agent, '/files/agents/atlas/scratch/draft.txt'));
        $this->assertSame(['draft.txt'], array_map(fn ($e) => $e->name, $this->vfs->list($this->agent, '/me/scratch')));

        $this->assertStringContainsString('I am Atlas', $this->vfs->read($this->agent, '/me/identity/IDENTITY.md'));
    }

    // todo.md has exactly one writer: the agent. Its manager can read it but
    // not write it; overwrites need the version like every other record.
    public function test_todo_is_single_writer(): void
    {
        $report = User::factory()->agent()->create(['workspace_id' => $this->workspace->id, 'name' => 'Report', 'manager_id' => $this->agent->id]);
        $reportFolder = app(AgentDocumentService::class)->createAgentDocumentStructure($report, ['IDENTITY' => 'I report']);
        $report->update(['docs_folder_id' => $reportFolder->id]);

        $created = $this->vfs->write($report, '/me/todo.md', "- [ ] ship\n", 'create');
        $this->assertSame('created', $created['status']);

        $this->assertSame("- [ ] ship\n", $this->vfs->read($this->agent, "/agents/by-id/{$report->id}/todo.md"));
        $this->assertVfsError('permission_denied', fn () => $this->vfs->write($this->agent, "/agents/by-id/{$report->id}/todo.md", 'hijack', 'overwrite', $created['version']));
        $this->assertVfsError('permission_denied', fn () => $this->vfs->patch($this->agent, "/agents/by-id/{$report->id}/todo.md", json_encode(['search' => 'ship', 'replace' => 'x'])));

        $this->assertVfsError('invalid_request', fn () => $this->vfs->write($report, '/me/todo.md', '- [x] ship', 'overwrite'), 'version');
        $this->vfs->write($report, '/me/todo.md', "- [x] ship\n", 'overwrite', $created['version']);
        $this->vfs->patch($report, '/me/todo.md', json_encode(['search' => '- [x] ship', 'replace' => "- [x] ship\n- [ ] celebrate"]));
        $this->assertStringContainsString('celebrate', $this->vfs->read($report, '/me/todo.md'));
    }

    // /me/pending lists this agent's pending approvals with operation and path.
    public function test_me_pending_lists_own_pending_approvals(): void
    {
        $folder = Document::factory()->create(['title' => 'Gated', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $this->permission('folder', $folder->id);
        $this->permission('tool', 'create_document', requiresApproval: true);

        $this->assertVfsError('approval_required', fn () => $this->vfs->write($this->agent, '/docs/gated/Needs Approval', 'x'));
        $approval = ApprovalRequest::query()->sole();

        $entries = $this->vfs->list($this->agent, '/me/pending');
        $this->assertSame(["{$approval->id}.md"], array_map(fn ($e) => $e->name, $entries));
        $markdown = $this->vfs->read($this->agent, "/me/pending/{$approval->id}.md");
        $this->assertStringContainsString('operation: write', $markdown);
        $this->assertStringContainsString('path: /docs/gated/Needs Approval', $markdown);
        $this->assertStringContainsString('create_document', $markdown);
    }

    // The root README orients a fresh agent; ls -l / shows one-line mount
    // descriptions.
    public function test_root_readme_and_ls_descriptions(): void
    {
        $readme = $this->vfs->read($this->agent, '/README.md');
        foreach (['/me', 'Edit workflow', 'ambiguous_match', '.trash', '.history', 'approval', '/tools/vfs.md', 'help'] as $needle) {
            $this->assertStringContainsString($needle, $readme);
        }
        $this->assertSame($readme, $this->vfs->read($this->agent, '/README.md'), 'README must be byte-stable');

        $root = $this->vfs->list($this->agent, '/');
        $this->assertSame('README.md', $root[0]->name);
        $this->assertNotEmpty(collect($root)->firstWhere('name', 'me')->metadata['description']);

        $ls = app(VfsCommandExecutor::class)->execute($this->agent, 'ls -l /')['stdout'];
        $this->assertStringContainsString('# Your own area', $ls);
        $this->assertStringContainsString('README.md', app(VfsCommandExecutor::class)->execute($this->agent, 'ls /')['stdout']);
    }

    // File version tokens come from the stored content hash after a VFS
    // write and still equal the token computed from the bytes on read.
    public function test_file_version_uses_content_hash_and_matches_read_version(): void
    {
        $written = $this->vfs->write($this->agent, '/files/hash/a.txt', 'hello');
        $file = WorkspaceFile::forWorkspace()->where('name', 'a.txt')->sole();
        $this->assertSame(hash('sha256', 'hello'), $file->content_hash);

        $this->assertSame($written['version'], $this->vfs->stat($this->agent, '/files/hash/a.txt')['version']);
        $this->assertSame($written['version'], $this->vfs->readContentWithVersion($this->agent, '/files/hash/a.txt')['version']);

        // A non-VFS update clears the cached hash instead of trusting it.
        $file->update(['description' => 'touched elsewhere']);
        $this->assertNull($file->fresh()->content_hash);
    }

    private function permission(string $scopeType, string $scopeKey, bool $requiresApproval = false): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'permission' => 'allow',
            'requires_approval' => $requiresApproval,
        ]);
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
