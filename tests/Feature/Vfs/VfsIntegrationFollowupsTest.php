<?php

namespace Tests\Feature\Vfs;

use App\Agents\Tools\ToolRegistry;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Domain\Vfs\OpenCompany\Policy\VfsActionClass;
use App\Domain\Vfs\Spec\VfsOperationSpecRegistry;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkspaceDisk;
use App\Services\AgentDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Phase 3a integration follow-ups.
 *
 * Protects: `/me` (an alias of the agent's own objects) is not walked twice
 * from `/`; row JSON keeps non-ASCII text so `Café` is searchable and
 * reported on a friendly path; rows.ndjson is newline-terminated; shell
 * lines and record operations carry their declared action class while the
 * per-path class can only raise it; vfs_rm forwards idempotency_key; the
 * operation spec advertises it.
 */
class VfsIntegrationFollowupsTest extends TestCase
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

    // Regression: walks from / visited the agent's notes twice, once under
    // /agents and again under the /me alias. Explicit /me walks still work.
    public function test_recursive_walks_from_root_skip_the_me_alias(): void
    {
        $this->vfs->write($this->agent, '/me/notes/plan.md', "kestrel plan\n");
        $executor = app(VfsCommandExecutor::class);
        $budget = new VfsBudget(maxDepth: 6, maxFiles: 500, maxEntries: 1000);

        $find = $executor->execute($this->agent, 'find / -name "plan*"', '/', $budget)['stdout'];
        $this->assertStringNotContainsString('/me/', $find);
        $this->assertSame(1, substr_count($find, 'plan'));

        $grep = $executor->execute($this->agent, 'grep -rl kestrel /', '/', $budget)['stdout'];
        $this->assertSame("/agents/atlas/notes/plan.md\n", $grep);

        $search = $this->vfs->searchWithMetadata($this->agent, 'kestrel', ['/'], $budget);
        $this->assertSame(1, $search['count']);
        $this->assertStringNotContainsString('/me/', $search['matches'][0]['path']);

        // Explicit alias paths are walked normally.
        $this->assertStringContainsString('/me/notes/plan', $executor->execute($this->agent, 'find /me/notes -name "plan*"')['stdout']);
        $this->assertSame(1, $this->vfs->searchWithMetadata($this->agent, 'kestrel', ['/me'], $budget)['count']);

        // Plain `ls /` still shows /me, marked as an alias.
        $this->assertStringContainsString('me/', $executor->execute($this->agent, 'ls /')['stdout']);
    }

    // Regression: row JSON escaped non-ASCII (Café), so the index found
    // the row but the content match failed. Rows render unescaped; the match
    // is reported on the friendly row path with the by-id path alongside.
    public function test_non_ascii_row_text_is_searchable_on_a_friendly_path(): void
    {
        $table = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Leads', 'created_by' => $this->agent->id]);
        $column = DataTableColumn::create(['table_id' => $table->id, 'name' => 'Company', 'type' => 'text', 'order' => 0, 'required' => false]);
        $row = DataTableRow::create(['table_id' => $table->id, 'data' => [$column->id => 'Café Narwhal'], 'created_by' => $this->agent->id]);

        $this->assertStringContainsString('Café Narwhal', $this->vfs->read($this->agent, "/tables/leads/rows/by-id/{$row->id}.json"));
        $this->assertStringContainsString('Café Narwhal', $this->vfs->read($this->agent, '/tables/leads/rows.ndjson'));

        $result = $this->vfs->searchWithMetadata($this->agent, 'Café', ['/tables']);
        $this->assertSame("/tables/leads/rows/by-id/{$row->id}.json", $result['matches'][0]['path']);
        $this->assertSame("/tables/by-id/{$table->id}/rows/by-id/{$row->id}.json", $result['matches'][0]['canonical_path']);

        $grep = app(VfsCommandExecutor::class)->execute($this->agent, 'grep -r Café /tables/leads/rows')['stdout'];
        $this->assertStringContainsString("/tables/leads/rows/by-id/{$row->id}.json:", $grep);
    }

    // rows.ndjson ends every record with a newline: `wc -l` equals the
    // record count and joined cursor pages stay valid NDJSON.
    public function test_rows_ndjson_is_newline_terminated_across_pages(): void
    {
        $table = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Counts', 'created_by' => $this->agent->id]);
        $column = DataTableColumn::create(['table_id' => $table->id, 'name' => 'N', 'type' => 'text', 'order' => 0, 'required' => false]);
        foreach (range(1, 3) as $index) {
            DataTableRow::create(['table_id' => $table->id, 'data' => [$column->id => "row {$index}"], 'created_by' => $this->agent->id]);
        }

        $this->assertSame("3\n", app(VfsCommandExecutor::class)->execute($this->agent, 'wc -l < /tables/counts/rows.ndjson')['stdout']);

        $joined = '';
        $cursor = null;
        do {
            $page = $this->vfs->readPage($this->agent, '/tables/counts/rows.ndjson', new VfsBudget(maxEntries: 2), $cursor);
            $this->assertStringEndsWith("\n", $page->content);
            $joined .= $page->content;
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        $lines = explode("\n", rtrim($joined, "\n"));
        $this->assertCount(3, $lines);
        foreach ($lines as $line) {
            $this->assertIsArray(json_decode($line, true, flags: JSON_THROW_ON_ERROR));
        }
    }

    // A shell line's approval carries the strictest command spec class of
    // the line (rm → delete), and a declared class never lowers the per-path
    // class (an identity write stays governance).
    public function test_action_classes_are_declared_and_only_raised_by_the_path(): void
    {
        $this->permission('tool', 'vfs_write', requiresApproval: true);
        $this->vfs->makeDirectory($this->agent, '/files/tmp');

        $executor = app(VfsCommandExecutor::class);
        foreach (['touch /files/tmp/a.txt' => 'reversible_edit', 'touch /files/tmp/b.txt && rm /files/tmp/a.txt' => 'delete'] as $command => $class) {
            try {
                $executor->execute($this->agent, $command);
                $this->fail("expected approval_required for {$command}");
            } catch (VfsError $e) {
                $this->assertSame('approval_required', $e->errorCode);
                $this->assertSame($class, $e->context['action_class'], $command);
            }
        }

        $this->assertSame(VfsActionClass::GOVERNANCE, VfsActionClass::strictest(VfsActionClass::REVERSIBLE_EDIT, VfsActionClass::GOVERNANCE));
        $this->assertSame(VfsActionClass::DELETE, VfsActionClass::strictest(null, 'unknown', VfsActionClass::DELETE, VfsActionClass::READ));
        $this->assertSame(VfsActionClass::GOVERNANCE, VfsActionClass::classify('exec_write', "echo hi > /agents/by-id/{$this->agent->id}/memory/x.md"));

        // Record trash declares delete; the stored approval says so.
        $this->permission('tool', 'delete_document', requiresApproval: true);
        $doc = $this->grantedDoc('Old');
        try {
            $this->vfs->remove($this->agent, "/docs/by-id/{$doc->id}");
            $this->fail('expected approval_required');
        } catch (VfsError $e) {
            $this->assertSame('delete', $e->context['action_class']);
        }
    }

    // vfs_rm forwards the whole call (idempotency_key included): a retry
    // returns the first result instead of acting twice, and the spec
    // advertises the key on every mutating operation.
    public function test_vfs_rm_forwards_idempotency_key(): void
    {
        $doc = $this->grantedDoc('Draft');
        $tool = app(ToolRegistry::class)->instantiateToolBySlug('vfs_rm', $this->agent);
        $call = ['path' => "/docs/by-id/{$doc->id}", 'idempotency_key' => 'rm-draft-1'];

        $first = json_decode($tool->handle(new Request($call)), true);
        $this->assertTrue($first['ok'], json_encode($first));
        $second = json_decode($tool->handle(new Request($call)), true);
        $this->assertTrue($second['ok'], json_encode($second));
        $this->assertTrue($second['result']['idempotent_replay'] ?? false);
        unset($second['result']['idempotent_replay']);
        $this->assertSame($first['result'], $second['result']);
        $this->assertSame(0, ApprovalRequest::query()->count());

        foreach (['vfs_write', 'vfs_patch', 'vfs_edit', 'vfs_rm', 'vfs_mv', 'vfs_mkdir'] as $slug) {
            $names = array_map(fn ($parameter) => $parameter->name, VfsOperationSpecRegistry::get($slug)->parameters);
            $this->assertContains('idempotency_key', $names, $slug);
        }
        $this->assertStringNotContainsString('today /files', VfsOperationSpecRegistry::get('vfs_write')->description);
    }

    /** A document inside a folder the agent is granted (document writes need one). */
    private function grantedDoc(string $title): Document
    {
        $folder = Document::factory()->create(['title' => 'Granted', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $this->permission('folder', $folder->id);

        return Document::factory()->create(['title' => $title, 'parent_id' => $folder->id, 'content' => 'x', 'author_id' => $this->agent->id]);
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
}
