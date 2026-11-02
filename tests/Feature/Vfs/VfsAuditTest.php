<?php

namespace Tests\Feature\Vfs;

use App\Agents\Runtime\ActiveAgentRun;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Events\VfsEventSink;
use App\Domain\Vfs\Events\VfsOperationContext;
use App\Domain\Vfs\OpenCompany\AuditedOpenCompanyVfs;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Events\VfsObjectChanged;
use App\Models\AgentPermission;
use App\Models\AgentRunRecord;
use App\Models\ApprovalRequest;
use App\Models\Document;
use App\Models\Task;
use App\Models\User;
use App\Models\VfsOperationEvent;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 2 (Stream C2) audit and events.
 *
 * Every VFS surface reaches the audited facade, so writes from Code Mode
 * helpers (cp/mv/rm/mkdir) are recorded like tool writes; events carry the CER
 * envelope fields (run, causality, action class, versions); reads aggregate
 * into one row per run and path; committed writes broadcast a refresh signal
 * and feed /workspace/changes.ndjson, which re-checks read permission.
 */
class VfsAuditTest extends TestCase
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

    public function test_the_container_hands_out_the_audited_facade(): void
    {
        $this->assertInstanceOf(AuditedOpenCompanyVfs::class, $this->vfs);
    }

    // Regression: rm/cp/mv/mkdir via Code Mode helpers were never audited.
    public function test_helper_mutations_record_committed_events_with_versions(): void
    {
        Event::fake([VfsObjectChanged::class]);

        $this->vfs->makeDirectory($this->agent, '/files/reports');
        $created = $this->vfs->write($this->agent, '/files/reports/q1.txt', "draft\n");
        $updated = $this->vfs->write($this->agent, '/files/reports/q1.txt', "final\n", 'overwrite', $created['version']);
        $this->vfs->copy($this->agent, '/files/reports/q1.txt', '/files/reports/q1-copy.txt');
        $this->vfs->move($this->agent, '/files/reports/q1-copy.txt', '/files/reports/q1-archive.txt');
        $this->vfs->remove($this->agent, '/files/reports/q1-archive.txt');

        $types = VfsOperationEvent::query()->where('committed', true)->orderBy('id')->pluck('type')->all();
        $this->assertSame(['vfs.directory.created', 'vfs.file.created', 'vfs.file.written', 'vfs.file.created', 'vfs.file.moved', 'vfs.file.deleted'], $types);

        $write = VfsOperationEvent::query()->where('type', 'vfs.file.written')->sole();
        $this->assertSame($created['version'], $write->before_version);
        $this->assertSame($updated['version'], $write->after_version);
        $this->assertSame('reversible_edit', $write->action_class);
        $this->assertSame('write', $write->effect);
        $this->assertSame('tool', $write->surface);
        $this->assertSame('vfs', $write->source);
        $this->assertSame('/files/by-id/'.WorkspaceFile::query()->where('name', 'q1.txt')->value('id'), $write->canonical_path);
        $this->assertSame('workspace_file', $write->backend_type);

        $delete = VfsOperationEvent::query()->where('type', 'vfs.file.deleted')->sole();
        $this->assertSame('destructive', $delete->effect);
        $this->assertSame('delete', $delete->action_class);

        Event::assertDispatched(VfsObjectChanged::class, 6);
        Event::assertDispatched(VfsObjectChanged::class, fn (VfsObjectChanged $event): bool => $event->version === $updated['version']
            && $event->broadcastOn()[0]->name === 'private-workspace.'.$this->workspace->id.'.vfs');
    }

    // Reads are aggregated: one row per (run or agent-minute, path); nested
    // reads inside a write or a search are part of their parent.
    public function test_reads_are_aggregated_and_nested_reads_are_not_recorded(): void
    {
        $this->vfs->write($this->agent, '/files/notes.txt', "hello\n");

        foreach (range(1, 3) as $ignored) {
            $this->vfs->read($this->agent, '/files/notes.txt');
        }
        $this->vfs->searchWithMetadata($this->agent, 'hello', ['/files']);
        app(VfsEventSink::class)->flush();

        $reads = VfsOperationEvent::query()->where('type', 'vfs.read')->get();
        $this->assertSame(1, $reads->where('operation', 'read')->where('path', '/files/notes.txt')->count());
        $this->assertSame(1, $reads->where('operation', 'search')->count());
        $this->assertSame(2, $reads->count());
        $this->assertSame('read', $reads->first()->action_class);
    }

    public function test_denials_and_approval_requests_are_recorded(): void
    {
        $doc = Document::factory()->create(['title' => 'Locked', 'content' => 'x', 'author_id' => $this->agent->id]);
        try {
            $this->vfs->patch($this->agent, "/docs/by-id/{$doc->id}", json_encode(['search' => 'x', 'replace' => 'y']));
        } catch (VfsError) {
        }
        $denied = VfsOperationEvent::query()->where('type', 'vfs.denied')->sole();
        $this->assertSame('denied', $denied->decision);
        $this->assertSame('permission_denied', $denied->error_code);

        $this->permission('tool', 'vfs_write', requiresApproval: true);
        try {
            $this->vfs->write($this->agent, '/files/gated.txt', 'x');
        } catch (VfsError) {
        }
        $requested = VfsOperationEvent::query()->where('type', 'vfs.approval_requested')->sole();
        $this->assertSame('approval_required', $requested->decision);
        $this->assertSame(ApprovalRequest::query()->sole()->id, $requested->approval_id);
        $this->assertFalse($requested->committed);
    }

    // Events inherit the run's causality: run id, causality id, depth.
    public function test_events_carry_run_causality_and_surface(): void
    {
        $task = Task::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Delegated',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'agent_id' => $this->agent->id,
            'requester_id' => $this->agent->id,
            'context' => ['delegation_depth' => 2, 'causality_id' => 'chain-1'],
        ]);
        $run = AgentRunRecord::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'task_id' => $task->id,
            'agent_id' => $this->agent->id,
            'kind' => AgentRunRecord::KIND_ASSIGNED_TASK,
        ]);
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));

        try {
            app(VfsOperationContext::class)->runAs('code', fn () => $this->vfs->write($this->agent, '/files/from-code.txt', 'x'));
        } finally {
            app()->forgetInstance(ActiveAgentRun::class);
        }

        $event = VfsOperationEvent::query()->where('type', 'vfs.file.created')->sole();
        $this->assertSame($run->id, $event->run_id);
        $this->assertSame('chain-1', $event->causality_id);
        $this->assertSame(2, (int) $event->depth);
        $this->assertSame('code', $event->surface);
        $this->assertSame('tool', app(VfsOperationContext::class)->surface());
    }

    // The change feed lists committed changes the agent can read, oldest
    // first, and a saved cursor resumes with only newer changes.
    public function test_change_feed_rechecks_permission_and_resumes_from_its_cursor(): void
    {
        // Reader has no file grants: only its home folder agents/reader.
        $reader = User::factory()->agent('writer')->create(['name' => 'Reader', 'status' => 'idle']);

        $this->vfs->write($this->agent, '/files/public.txt', 'a');
        $this->vfs->makeDirectory($this->agent, '/files/agents/reader');
        $this->vfs->write($reader, '/files/agents/reader/mine.txt', 'c');

        $page = $this->vfs->readPage($reader, '/workspace/changes.ndjson');
        $lines = array_map(fn (string $line): array => json_decode($line, true), array_values(array_filter(explode("\n", $page->content))));

        $this->assertSame(['vfs.directory.created', 'vfs.file.created'], array_column($lines, 'type'), json_encode($lines));
        $this->assertSame($reader->id, $lines[1]['actor_id']);
        $this->assertStringStartsWith('/files/by-id/', $lines[1]['path']);
        $this->assertNotNull($lines[1]['version']);
        // Skipped: Atlas's public.txt and the /files/agents folder its
        // mkdir created implicitly (audited since the governance review).
        $this->assertSame(2, $page->skipped);
        $this->assertSame(1, VfsOperationEvent::query()->where('type', 'vfs.directory.created')->where('path', '/files/agents')->count());
        // Drained, but still resumable for polling.
        $this->assertFalse($page->truncated);
        $this->assertNotNull($page->nextCursor);

        $this->vfs->write($reader, '/files/agents/reader/later.txt', 'd');
        $this->vfs->write($this->agent, '/files/public-2.txt', 'e');
        $next = $this->vfs->readPage($reader, '/workspace/changes.ndjson', null, $page->nextCursor);
        $nextLines = array_values(array_filter(explode("\n", $next->content)));

        $this->assertCount(1, $nextLines);
        $this->assertSame(
            '/files/by-id/'.WorkspaceFile::query()->where('name', 'later.txt')->value('id'),
            json_decode($nextLines[0], true)['path'],
        );
    }

    // Pillar 11 (archive invariant): vfs_operation_events is keyed by a
    // stable string id (ULID), never an auto-increment, and the change feed
    // cursor is that ULID. The feed delivers an event only once it is older
    // than the settle window, so a row whose transaction commits late is not
    // skipped by a cursor that already moved on; the cursor never passes the
    // settle boundary and the event is then delivered exactly once.
    public function test_change_feed_pages_on_ulids_behind_a_settle_window(): void
    {
        config(['vfs.change_feed_settle_seconds' => 5]);

        $this->vfs->write($this->agent, '/files/settle.txt', 'x');
        $event = VfsOperationEvent::query()->where('committed', true)->where('type', 'vfs.file.created')->sole();
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', (string) $event->id);

        // Fresh event: inside the settle window, not delivered yet; the
        // returned cursor must still let a later poll see it.
        $first = $this->vfs->readPage($this->agent, '/workspace/changes.ndjson');
        $this->assertSame('', $first->content);
        $this->assertNotNull($first->nextCursor);

        // After the window the same cursor delivers it, once.
        $this->travel(10)->seconds();
        $second = $this->vfs->readPage($this->agent, '/workspace/changes.ndjson', null, $first->nextCursor);
        $this->assertStringContainsString('vfs.file.created', $second->content);
        $third = $this->vfs->readPage($this->agent, '/workspace/changes.ndjson', null, $second->nextCursor);
        $this->assertStringNotContainsString('vfs.file.created', $third->content);
    }

    private function permission(string $scopeType, string $scopeKey, bool $requiresApproval = false, ?User $agent = null): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => ($agent ?? $this->agent)->id,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'permission' => 'allow',
            'requires_approval' => $requiresApproval,
        ]);
    }
}
