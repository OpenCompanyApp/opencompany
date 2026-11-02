<?php

namespace Tests\Feature\Vfs;

use App\Agents\Tools\ToolRegistry;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use App\Models\WorkspaceDisk;
use App\Services\ApprovalExecutionService;
use App\Services\CodeApiDocGenerator;
use App\Services\CodeBridge;
use App\Services\CodeExecutionResult;
use App\Services\MrubySandboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Code Mode × VFS effect safety, through the real pinned mruby engine.
 *
 * Protects: VFS `ok:false` envelopes surface as typed failures (not values
 * receipted as successful writes); an approval created by the VFS's own
 * composed policy latches dispatch like any approval-pending callback;
 * `app.vfs.shell` is classified per pipeline so mutating commands get a
 * durable write receipt; and `app.vfs.grep` is literal.
 */
class VfsCodeModeEffectsTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private ?CodeBridge $bridge = null;

    private ?Task $task = null;

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
    }

    // ok:false is a failure carrying the VFS code, both when it ends the
    // program and when Ruby rescues it.
    public function test_vfs_ok_false_envelope_surfaces_as_a_typed_failure(): void
    {
        $result = $this->runRuby('app.vfs.read(path: "/docs/by-id/does-not-exist")');

        $this->assertNotNull($result->error);
        $this->assertSame('not_found', $result->error['type']);

        $rescued = $this->runRuby(<<<'RUBY'
begin
  app.vfs.read(path: "/docs/by-id/does-not-exist")
  "no error"
rescue => e
  e.message
end
RUBY);
        $this->assertNull($rescued->error, json_encode($rescued->error));
        $this->assertStringContainsString('not_found', (string) $rescued->result);
        $this->assertStringContainsString('/docs/by-id/does-not-exist', (string) $rescued->result);
        // The guest only gets (type, message), so the VFS context rides along
        // as JSON for repair (e.g. minimum_bytes, current_version, lines).
        $this->assertStringContainsString('{"path":"/docs/by-id/does-not-exist"}', (string) $rescued->result);
    }

    // A failed VFS write is never receipted as succeeded.
    public function test_failed_vfs_write_is_not_receipted_as_succeeded(): void
    {
        // /docs became writable in Phase 2 (C1); /workspace stays read-only,
        // so it still exercises a failed write.
        $result = $this->runRuby('app.vfs.write(path: "/workspace/not-writable", content: "x", mode: "create")');

        $this->assertSame('not_writable', $result->error['type']);
        $this->assertSame(0, $result->effects['writesSucceeded']);
        $this->assertSame([], $this->receiptDispositions('succeeded'));
    }

    // vfs_patch is allowed but its backing update_table_row is gated: the VFS
    // creates the approval itself. That must latch dispatch even if Ruby
    // rescues, report pending (not success), and apply only after approval.
    public function test_vfs_internal_approval_latches_dispatch(): void
    {
        $table = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Ruby Leads', 'created_by' => $this->agent->id]);
        $column = DataTableColumn::create(['table_id' => $table->id, 'name' => 'Status', 'type' => 'text', 'order' => 0, 'required' => false]);
        $row = DataTableRow::create(['table_id' => $table->id, 'data' => [$column->id => 'lead'], 'created_by' => $this->agent->id]);
        $this->permission('tool', 'update_table_row', requiresApproval: true);
        $path = "/tables/ruby-leads/rows/by-id/{$row->id}.json";

        $result = $this->runRuby(sprintf(<<<'RUBY'
begin
  app.vfs.patch(path: "%s", patch: '{"data":{"Status":"active"}}')
rescue => e
  nil
end
app.vfs.stat(path: "/")
"finished"
RUBY, $path));

        $this->assertSame('approval_pending', $result->error['type']);
        $this->assertNull($result->result);
        $this->assertSame(1, $result->effects['callbacksPendingApproval']);
        $this->assertSame(0, $result->effects['writesSucceeded']);
        $this->assertSame('lead', $row->fresh()->data[$column->id]);
        $this->assertSame(['approval_pending'], $this->receiptDispositions());

        app(ApprovalExecutionService::class)->resolve(ApprovalRequest::query()->sole(), 'approved', $this->workspaceApprover());
        $this->assertSame('active', $row->fresh()->data[$column->id]);
    }

    // app.vfs.shell is typed read in the registry, but a mutating pipeline
    // must open a durable write receipt and count as a write; a read-only one
    // must not.
    public function test_shell_effect_is_classified_per_pipeline(): void
    {
        app(OpenCompanyVfs::class)->write($this->agent, '/files/ruby/x.txt', 'bye', 'create');

        $result = $this->runRuby(<<<'RUBY'
app.vfs.shell(command: "ls /files/ruby")
app.vfs.shell(command: "cat /files/ruby/x.txt | wc -c")
app.vfs.shell(command: "rm /files/ruby/x.txt")
"done"
RUBY);

        $this->assertNull($result->error, json_encode($result->error));
        $log = $this->bridge->getCallLog();
        $this->assertSame(['read', 'read', 'write'], array_column($log, 'effect'));
        $this->assertSame('succeeded', $log[2]['effectStatus']);
        $this->assertSame(1, $result->effects['writesSucceeded']);
        $this->assertSame(2, $result->effects['reads']);
        $this->assertSame(['succeeded'], $this->receiptDispositions());
        $this->assertSame('vfs_exec', TaskStep::query()->sole()->metadata['script_callback']['tool_slug']);
    }

    // The classifier fails closed: redirection, xargs-invoked writers and
    // unparseable input are writes.
    public function test_command_effect_classifier_fails_closed(): void
    {
        $executor = app(VfsCommandExecutor::class);

        $this->assertSame('read', $executor->classifyEffect('ls /docs | head -n 3 && cat /tools/vfs.md 2>/dev/null'));
        $this->assertSame('write', $executor->classifyEffect('cat /docs/x > /files/out.txt'));
        $this->assertSame('write', $executor->classifyEffect('printf hi >> /files/out.txt'));
        $this->assertSame('write', $executor->classifyEffect('find /files -name "*.tmp" | xargs rm'));
        $this->assertSame('write', $executor->classifyEffect('tar -t -f /files/a.tar'));
        $this->assertSame('write', $executor->classifyEffect('ls; mkdir /files/new'));
        $this->assertSame('write', $executor->classifyEffect('cat "unterminated'));
    }

    // app.vfs.grep is documented as literal; app.vfs.rg stays regex. Both
    // calling conventions get the alias default, and an explicit value wins.
    public function test_vfs_grep_alias_is_literal_by_default(): void
    {
        $doc = Document::factory()->create(['title' => 'Grep Fixture', 'content' => "a.b\naxb\n", 'author_id' => $this->agent->id]);

        $result = $this->runRuby(sprintf(<<<'RUBY'
{
  grep: app.vfs.grep(pattern: "a.b", paths: ["/docs/by-id/%1$s"])[:result][:count],
  grep_positional: app.vfs.grep("a.b", ["/docs/by-id/%1$s"])[:result][:count],
  grep_regex: app.vfs.grep(pattern: "a.b", paths: ["/docs/by-id/%1$s"], regex: true)[:result][:count],
  rg: app.vfs.rg(pattern: "a.b", paths: ["/docs/by-id/%1$s"])[:result][:count],
}
RUBY, $doc->id));

        $this->assertNull($result->error, json_encode($result->error));
        $payload = json_decode(json_encode($result->result), true);
        $this->assertSame(1, $payload['grep']);
        $this->assertSame(1, $payload['grep_positional']);
        $this->assertSame(2, $payload['grep_regex']);
        $this->assertSame(2, $payload['rg']);
    }

    /**
     * Run Ruby through the real engine inside an active task so write
     * receipts are satisfiable; keeps the bridge for ledger assertions.
     */
    private function runRuby(string $code): CodeExecutionResult
    {
        $this->task ??= Task::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->agent->workspace_id,
            'title' => 'VFS Code Mode effects',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_MANUAL,
            'agent_id' => $this->agent->id,
            'requester_id' => $this->agent->id,
            'started_at' => now(),
        ]);
        $registry = app(ToolRegistry::class);
        $registry->setTaskContext($this->task->id);
        $sandbox = app(MrubySandboxService::class);
        $this->bridge = new CodeBridge($this->agent, $registry, app(CodeApiDocGenerator::class), $sandbox->profile('agent'));

        return $sandbox->execute($code, 'agent', $this->bridge);
    }

    /** @return list<string> */
    private function receiptDispositions(?string $only = null): array
    {
        return TaskStep::query()->get()
            ->map(fn (TaskStep $step): ?string => $step->metadata['script_callback']['disposition'] ?? null)
            ->filter(fn (?string $disposition): bool => $disposition !== null && ($only === null || $disposition === $only))
            ->values()
            ->all();
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

    /**
     * A human admin of the test workspace: ApprovalResolutionPolicy refuses
     * unattributed (null) responders, so approvals are resolved by a member.
     */
    private function workspaceApprover(): User
    {
        $approver = User::factory()->create(['type' => 'human']);
        \App\Models\WorkspaceMember::query()->firstOrCreate(
            ['workspace_id' => $this->workspace->id, 'user_id' => $approver->id],
            ['role' => 'admin'],
        );

        return $approver;
    }
}
