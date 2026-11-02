<?php

namespace Tests\Feature\Vfs;

use App\Agents\Tools\Lists\DeleteListItem;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Events\VfsEventSink;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Domain\Web\Safety\WebRequestGuard;
use App\Jobs\AgentResumeFromSleepJob;
use App\Jobs\CompactConversationJob;
use App\Jobs\RefreshMcpToolsJob;
use App\Jobs\SendApprovalToTelegramJob;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\IntegrationSetting;
use App\Models\ListItem;
use App\Models\McpServer;
use App\Models\Task;
use App\Models\User;
use App\Models\VfsOperationEvent;
use App\Models\Workspace;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceFileVersion;
use App\Services\AgentDocumentService;
use App\Services\FileSystemService;
use App\Services\Memory\ConversationCompactionService;
use App\Services\Memory\MemoryFlushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\Evals\Vfs\Support\EvalWorld;
use Tests\TestCase;

/**
 * Regressions for the data-integrity review of the VFS hardening branch
 * (review-integrity.md: 1 P0 + 9 P1) and the records/path bugs found by the
 * real-tool eval pilot (vfs-agent-friendliness-audit §9.4).
 *
 * Each test names the scenario it protects. The reviewers' throwaway probes
 * (IntegrityProbeTest) are ported here as real tests.
 */
class VfsReviewFixesIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private OpenCompanyVfs $vfs;

    protected function setUp(): void
    {
        parent::setUp();

        // Not "Atlas": the eval world brings its own Atlas, and two agents
        // sharing a name would make /agents/atlas ambiguous.
        $this->agent = User::factory()->agent('manager')->create(['name' => 'Keeper', 'status' => 'idle']);
        WorkspaceDisk::create([
            'id' => 'disk-integrity',
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
        $this->allow($this->agent, 'file_folder', '*');
        $this->vfs = app(OpenCompanyVfs::class);
    }

    // P0: an overwrite whose history snapshot fails must leave the file
    // exactly as it was: bytes, version token and history. Before, the disk
    // object was overwritten in place and only the SQL rolled back.
    public function test_rolled_back_overwrite_keeps_original_bytes_version_and_history(): void
    {
        $created = $this->vfs->write($this->agent, '/files/probe.txt', 'original');
        $before = WorkspaceFile::query()->where('name', 'probe.txt')->firstOrFail();

        WorkspaceFileVersion::creating(function (): void {
            throw new \RuntimeException('snapshot failure');
        });

        try {
            $this->vfs->write($this->agent, '/files/probe.txt', 'replacement', 'overwrite', $created['version']);
            $this->fail('The overwrite should have failed.');
        } catch (\RuntimeException $e) {
            $this->assertSame('snapshot failure', $e->getMessage());
        }
        WorkspaceFileVersion::flushEventListeners();

        $after = WorkspaceFile::query()->whereKey($before->id)->firstOrFail();
        $this->assertSame('original', $this->vfs->readContent($this->agent, '/files/probe.txt'));
        $this->assertSame($before->storage_path, $after->storage_path);
        $this->assertSame($created['version'], $this->vfs->stat($this->agent, '/files/probe.txt')['version']);
        // The stale-version retry still succeeds because nothing changed.
        $this->vfs->write($this->agent, '/files/probe.txt', 'replacement', 'overwrite', $created['version']);
        $this->assertSame('replacement', $this->vfs->readContent($this->agent, '/files/probe.txt'));
    }

    // P0 (happy path): a committed overwrite moves the row to a new storage
    // object and removes the old one after commit.
    public function test_committed_overwrite_swaps_storage_object_and_removes_old_bytes(): void
    {
        $created = $this->vfs->write($this->agent, '/files/swap.txt', 'one');
        $file = WorkspaceFile::query()->where('name', 'swap.txt')->firstOrFail();
        $disk = app(FileSystemService::class)->getDiskForFile($file);
        $oldPath = $file->storage_path;

        $this->vfs->write($this->agent, '/files/swap.txt', 'two', 'overwrite', $created['version']);

        $file->refresh();
        $this->assertNotSame($oldPath, $file->storage_path);
        $this->assertSame('two', $disk->get($file->storage_path));
        $this->assertFalse($disk->exists($oldPath));
    }

    // P1: SoftDeletes turned folder deletion into an UPDATE, so callers that
    // only call delete() (API controller, delete_document, delete_list_item)
    // left live orphan children. The model now trashes the subtree as a batch.
    public function test_model_folder_delete_trashes_live_subtree_and_restores_it(): void
    {
        $parent = Document::factory()->create(['is_folder' => true, 'title' => 'Parent']);
        $child = Document::factory()->create(['parent_id' => $parent->id, 'is_folder' => false, 'title' => 'Child']);
        $grandFolder = Document::factory()->create(['parent_id' => $parent->id, 'is_folder' => true, 'title' => 'Sub']);
        $grandChild = Document::factory()->create(['parent_id' => $grandFolder->id, 'is_folder' => false, 'title' => 'Deep']);

        $parent->delete();

        $this->assertNull(Document::find($child->id));
        $this->assertNull(Document::find($grandChild->id));
        $batch = Document::withTrashed()->findOrFail($parent->id)->getAttribute('trash_batch_id');
        $this->assertNotNull($batch);
        $this->assertSame($batch, Document::withTrashed()->findOrFail($grandChild->id)->getAttribute('trash_batch_id'));

        Document::withTrashed()->findOrFail($parent->id)->restore();
        $this->assertNotNull(Document::find($grandChild->id));
        $this->assertNull(Document::findOrFail($grandChild->id)->getAttribute('trash_batch_id'));

        // The delete_list_item tool on a project takes its items along.
        $project = ListItem::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'title' => 'Project', 'description' => '', 'is_folder' => true, 'status' => 'todo']);
        $item = ListItem::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'title' => 'Item', 'description' => '', 'is_folder' => false, 'parent_id' => $project->id, 'status' => 'todo']);
        (new DeleteListItem($this->agent))->handle(new Request(['listItemId' => $project->id]));
        $this->assertNull(ListItem::find($item->id));
        $this->assertSame($this->agent->id, ListItem::withTrashed()->findOrFail($item->id)->getAttribute('deleted_by'));
    }

    // P1: deleted_at has second precision, so restoring a folder used to
    // resurrect a child that was trashed on its own in the same second (with
    // its `.trashed-` name). Batches are now a real id.
    public function test_restoring_file_folder_does_not_restore_independently_trashed_child(): void
    {
        $this->freezeTime();
        $this->vfs->write($this->agent, '/files/probe/child.txt', 'child');
        $this->vfs->write($this->agent, '/files/probe/keep.txt', 'keep');
        $this->vfs->remove($this->agent, '/files/probe/child.txt');
        $child = WorkspaceFile::onlyTrashed()->where('name', 'like', 'child.txt%')->firstOrFail();
        $keep = WorkspaceFile::query()->where('name', 'keep.txt')->firstOrFail();

        $this->vfs->remove($this->agent, '/files/probe');
        $parent = WorkspaceFile::onlyTrashed()->where('name', 'like', 'probe.trashed%')->firstOrFail();
        $this->vfs->move($this->agent, '/files/.trash/'.$parent->id, '/files/probe');

        $this->assertNotNull(WorkspaceFile::withTrashed()->findOrFail($child->id)->deleted_at);
        $this->assertNull(WorkspaceFile::withTrashed()->findOrFail($keep->id)->deleted_at);
        $this->assertSame('keep', $this->vfs->readContent($this->agent, '/files/probe/keep.txt'));
        // The independently trashed child stays restorable on its own.
        $this->vfs->move($this->agent, '/files/.trash/'.$child->id, '/files/probe/child.txt');
        $this->assertSame('child', $this->vfs->readContent($this->agent, '/files/probe/child.txt'));
    }

    // P1 (documents): the same batch rule for /docs folders.
    public function test_restoring_document_folder_does_not_restore_independently_trashed_child(): void
    {
        $this->freezeTime();
        $folder = Document::factory()->create(['is_folder' => true, 'title' => 'Reports']);
        $alone = Document::factory()->create(['parent_id' => $folder->id, 'title' => 'Alone', 'content' => 'a']);
        $kept = Document::factory()->create(['parent_id' => $folder->id, 'title' => 'Kept', 'content' => 'k']);
        $this->allow($this->agent, 'folder', $folder->id);
        $this->allow($this->agent, 'tool', 'delete_document');
        $this->allow($this->agent, 'tool', 'update_document');
        $root = Document::factory()->create(['is_folder' => true, 'title' => 'Root Grant']);
        $folder->update(['parent_id' => $root->id]);
        $this->allow($this->agent, 'folder', $root->id);

        $this->vfs->remove($this->agent, "/docs/by-id/{$alone->id}");
        $this->vfs->remove($this->agent, "/docs/by-id/{$folder->id}");
        $trashListing = $this->vfs->list($this->agent, "/docs/.trash/{$folder->id}");
        $this->assertSame([$kept->id], array_map(fn (VfsEntry $entry): ?string => $entry->backendId, $trashListing));

        $this->vfs->move($this->agent, "/docs/.trash/{$folder->id}", "/docs/by-id/{$root->id}/Reports");

        $this->assertNotNull(Document::find($kept->id));
        $this->assertNull(Document::find($alone->id));
        $this->assertNotNull(Document::onlyTrashed()->find($alone->id));
    }

    // P1: document destination-name checks are serialized. A move or create
    // into a folder locks that folder row before its clash check; the root
    // (no folder row) locks the workspace row. sqlite has no row locks, so
    // the test proves the lock statement runs inside the write and that the
    // clash rule holds.
    public function test_document_create_and_move_lock_the_destination_parent(): void
    {
        $this->allow($this->agent, 'tool', 'update_document');
        $root = Document::factory()->create(['is_folder' => true, 'title' => 'Granted']);
        $this->allow($this->agent, 'folder', $root->id);
        $target = Document::factory()->create(['is_folder' => true, 'title' => 'Target', 'parent_id' => $root->id]);
        $other = Document::factory()->create(['is_folder' => true, 'title' => 'Other', 'parent_id' => $root->id]);
        $existing = Document::factory()->create(['parent_id' => $target->id, 'title' => 'Plan', 'content' => 'x']);
        $moving = Document::factory()->create(['parent_id' => $other->id, 'title' => 'Plan', 'content' => 'y']);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = [$query->sql, $query->bindings];
        });

        try {
            $this->vfs->move($this->agent, "/docs/by-id/{$moving->id}", "/docs/by-id/{$target->id}");
            $this->fail('A second "Plan" in Target must be refused.');
        } catch (VfsError $e) {
            $this->assertSame('invalid_request', $e->errorCode);
        }
        // The destination folder row is (re)read under lock inside the
        // transaction, after the policy reads and before the clash check.
        $sqls = array_column($queries, 0);
        $clash = array_key_last(array_filter($sqls, fn (string $sql): bool => str_starts_with($sql, 'select exists(select * from "documents"')));
        $lastPolicyRead = array_key_last(array_filter($sqls, fn (string $sql): bool => str_contains($sql, 'from "document_permissions"')));
        $destinationLock = array_key_last(array_filter($queries, fn (array $query, int $index): bool => $index < $clash
            && str_starts_with($query[0], 'select * from "documents"')
            && $query[1] === [$this->workspace->id, $target->id], ARRAY_FILTER_USE_BOTH));
        $this->assertNotNull($destinationLock);
        $this->assertGreaterThan($lastPolicyRead, $destinationLock);
        $this->assertSame($other->id, Document::findOrFail($moving->id)->parent_id);
        $this->assertSame($target->id, Document::findOrFail($existing->id)->parent_id);

        // Root destinations lock the workspace row.
        $queries = [];
        $lock = new \ReflectionMethod($this->vfs, 'lockDocumentDestination');
        DB::transaction(fn () => $lock->invoke($this->vfs, $this->agent, null, '/docs/x'));
        $this->assertTrue(collect($queries)->contains(fn (array $query): bool => str_contains($query[0], 'from "workspaces"') && in_array($this->workspace->id, $query[1], true)));
    }

    // P1: per-cell base checks compared floats, so 2^53 and 2^53+1 were "the
    // same" and a stale base overwrote a human's edit. Strings compare as
    // strings; numbers by exact decimal value.
    public function test_row_base_comparison_is_exact_for_large_integers_and_numeric_strings(): void
    {
        $world = $this->world();
        $rowId = $world->ids['row.acme'];
        $mrr = $world->columns['Customers']['MRR'];
        $plan = $world->columns['Customers']['Plan'];
        $row = DataTableRow::findOrFail($rowId);
        $row->update(['data' => [...$row->data, $mrr => 9007199254740993, $plan => '007']]);
        $path = "/tables/by-id/{$world->ids['table.customers']}/rows/by-id/{$rowId}.json";

        foreach ([
            ['MRR', 9007199254740992],
            ['Plan', '7'],
        ] as [$column, $staleBase]) {
            try {
                $this->vfs->patch($world->atlas, $path, json_encode(['data' => [$column => 1], 'base' => [$column => $staleBase]]));
                $this->fail("Stale base for {$column} must conflict.");
            } catch (VfsError $e) {
                $this->assertSame('conflict', $e->errorCode, $column);
            }
        }

        // Equal values in another JSON spelling still match.
        $result = $this->vfs->patch($world->atlas, $path, json_encode(['data' => ['MRR' => 1], 'base' => ['MRR' => '9007199254740993.0']]));
        $this->assertSame('updated', $result['status']);
    }

    // Pilot (ergonomics): unknown columns list the valid ones; row patch
    // results name fields by column name with an id map.
    public function test_row_patch_errors_and_results_use_column_names(): void
    {
        $world = $this->world();
        $path = "/tables/by-id/{$world->ids['table.customers']}/rows/by-id/{$world->ids['row.acme']}.json";

        try {
            $this->vfs->patch($world->atlas, $path, json_encode(['data' => ['Owner' => 'x']]));
            $this->fail('Unknown column must be refused.');
        } catch (VfsError $e) {
            $this->assertSame('invalid_request', $e->errorCode);
            $this->assertSame('Owner', $e->context['field']);
            $this->assertSame(['Name', 'Status', 'Plan', 'MRR'], $e->context['columns']);
        }

        $result = $this->vfs->patch($world->atlas, $path, json_encode(['data' => ['status' => 'paused']]));
        $this->assertSame(['Status'], $result['fields']);
        $this->assertSame(['Status' => $world->columns['Customers']['Status']], $result['field_ids']);
    }

    // P1: the change feed paged on the PHP-assigned occurred_at; an event
    // inserted after a later-timestamp event was delivered was skipped
    // forever. It now pages on the insert-assigned id.
    public function test_change_feed_delivers_late_inserted_event_with_older_timestamp(): void
    {
        $this->vfs->write($this->agent, '/files/first.txt', 'a');
        $page = $this->vfs->readPage($this->agent, '/workspace/changes.ndjson');
        $this->assertCount(1, $this->feedLines($page->content));
        $this->assertStringEndsWith("\n", $page->content, 'NDJSON lines are newline-terminated');

        // Writer A built its event before B's delivered one, inserted after.
        $this->vfs->write($this->agent, '/files/late.txt', 'b');
        VfsOperationEvent::query()->where('path', '/files/late.txt')->update(['occurred_at' => now()->subHour()->format('Y-m-d H:i:s.u')]);

        $next = $this->vfs->readPage($this->agent, '/workspace/changes.ndjson', null, $page->nextCursor);
        $lines = $this->feedLines($next->content);
        $this->assertCount(1, $lines);
        $this->assertSame('/files/by-id/'.WorkspaceFile::query()->where('name', 'late.txt')->value('id'), $lines[0]['path']);
    }

    // P1: a deletion through a canonical path (/files/by-id/…) was hidden
    // from every other agent even with full access, so consumers could not
    // keep their copies in sync. Visibility now comes from the object's
    // original location.
    public function test_canonical_path_deletion_is_visible_to_agents_that_could_read_it(): void
    {
        $peer = User::factory()->agent('manager')->create(['name' => 'Peer', 'status' => 'idle']);
        $this->allow($peer, 'file_folder', '*');
        $scoped = User::factory()->agent('writer')->create(['name' => 'Scoped', 'status' => 'idle']);
        $this->allow($this->agent, 'tool', 'delete_file');

        $this->vfs->write($this->agent, '/files/shared/doomed.txt', 'x');
        $file = WorkspaceFile::query()->where('name', 'doomed.txt')->firstOrFail();
        $this->vfs->remove($this->agent, "/files/by-id/{$file->id}");

        $deleted = fn (User $reader): array => array_values(array_filter(
            $this->feedLines($this->vfs->readPage($reader, '/workspace/changes.ndjson')->content),
            fn (array $line): bool => str_ends_with($line['type'], '.deleted'),
        ));
        $this->assertCount(1, $deleted($peer));
        $this->assertSame($file->id, $deleted($peer)[0]['backend_id']);
        // An agent that could never read /files/shared still does not learn of it.
        $this->assertCount(0, $deleted($scoped));
    }

    // P1: mutation claims expire after 24h, but the audit key hashed the same
    // inputs forever, so a real second write with a reused key lost its audit
    // and change-feed event. The key now includes the claim generation.
    public function test_expired_idempotency_key_records_new_change_event(): void
    {
        $first = $this->vfs->write($this->agent, '/files/ttl.txt', 'first', 'create', null, ['idempotency_key' => 'repeat']);
        // A replay inside the window is the same change: no second event.
        $this->vfs->write($this->agent, '/files/ttl.txt', 'first', 'create', null, ['idempotency_key' => 'repeat']);
        $this->assertSame(1, VfsOperationEvent::where('committed', true)->where('operation', 'write')->count());

        $this->travel(25)->hours();
        $this->vfs->write($this->agent, '/files/ttl.txt', 'second', 'overwrite', $first['version'], ['idempotency_key' => 'repeat']);

        $this->assertSame(2, VfsOperationEvent::where('committed', true)->where('operation', 'write')->count());
    }

    // P1: an overwrite above the history ceiling silently dropped the only
    // copy of the old bytes. It is now refused before any approval or write;
    // the trash keeps such a file restorable instead.
    public function test_overwrite_above_history_ceiling_is_refused_and_keeps_bytes(): void
    {
        $created = $this->vfs->write($this->agent, '/files/big.txt', str_repeat('a', 64));
        config(['vfs.max_content_bytes' => 32]);

        try {
            $this->vfs->write($this->agent, '/files/big.txt', 'small', 'overwrite', $created['version']);
            $this->fail('Overwrite above the ceiling must be refused.');
        } catch (VfsError $e) {
            $this->assertSame('not_writable', $e->errorCode);
            $this->assertStringContainsString('rm it', $e->context['hint']);
        }

        config(['vfs.max_content_bytes' => 1024]);
        $this->assertSame(str_repeat('a', 64), $this->vfs->readContent($this->agent, '/files/big.txt'));
        $this->assertSame(1, WorkspaceFileVersion::query()->count(), 'only the creation snapshot');
    }

    // P1: NUL-containing text is valid UTF-8 but PostgreSQL text cannot hold
    // it; such history snapshots are stored base64 and read back exactly.
    public function test_nul_containing_history_snapshot_is_stored_base64(): void
    {
        $created = $this->vfs->write($this->agent, '/files/nul.txt', "a\0b");
        $this->vfs->write($this->agent, '/files/nul.txt', 'plain', 'overwrite', $created['version']);

        $snapshot = WorkspaceFileVersion::query()->where('version_number', 2)->firstOrFail();
        $this->assertSame('base64', $snapshot->encoding);
        $this->assertSame("a\0b", $snapshot->bytes());
    }

    // Pilot bug 1 (HIGH): task listing names did not resolve; only the uuid
    // did. Every listed name opens under the directory it was listed in.
    public function test_every_task_listing_name_opens_where_it_was_listed(): void
    {
        $world = $this->world();
        $taskId = $world->ids['task.t12'];

        foreach (['/tasks/by-id', '/tasks/open', '/tasks/by-status/pending', '/tasks/by-agent/atlas'] as $directory) {
            $entries = $this->vfs->list($world->atlas, $directory);
            $entry = collect($entries)->first(fn (VfsEntry $entry): bool => $entry->backendId === $taskId);
            $this->assertNotNull($entry, $directory);
            $path = "{$directory}/{$entry->name}";

            $this->assertStringContainsString('T-12: Renew SSL certificate', $this->vfs->read($world->atlas, $path), $path);
            $stat = $this->vfs->stat($world->atlas, $path);
            $this->assertSame('file', $stat['type'], $path);
            $this->assertSame("/tasks/by-id/{$taskId}", $stat['canonical_path'], $path);
        }

        $this->assertSame("{$taskId}.md", collect($this->vfs->list($world->atlas, '/tasks/by-id'))->firstWhere('backendId', $taskId)->name);
        $openName = collect($this->vfs->list($world->atlas, '/tasks/open'))->firstWhere('backendId', $taskId)->name;
        $this->assertSame('t-12-renew-ssl-certificate--'.substr($taskId, -8).'.md', $openName);
        // A patch through a listed name reaches the same task.
        $this->vfs->patch($world->atlas, "/tasks/open/{$openName}", json_encode(['status' => 'active']));
        $this->assertSame('active', Task::findOrFail($taskId)->status);
    }

    // Pilot bug 1, generalized: for every mount, every name `ls` shows opens
    // with stat (and read, for files) at `{dir}/{name}`.
    public function test_every_listed_entry_of_every_mount_resolves(): void
    {
        $world = $this->world();
        $atlas = $world->atlas;
        $folder = app(AgentDocumentService::class)->createAgentDocumentStructure($atlas, ['IDENTITY' => 'I am Atlas']);
        $this->agent->delete();
        $atlas->update(['docs_folder_id' => $folder->id]);
        $this->vfs->write($atlas, '/me/notes/plan.md', "plan\n");
        $this->vfs->write($atlas, '/files/reports/q3.md', "q3\n");

        $failures = [];
        $queue = [['/', 0]];
        $visited = 0;
        while ($queue !== [] && $visited < 400) {
            [$directory, $depth] = array_shift($queue);
            try {
                $entries = $this->vfs->list($atlas, $directory, new VfsBudget(maxEntries: 12));
            } catch (VfsError $e) {
                // Listed but closed to this agent is truthful; missing is not.
                if ($e->errorCode !== 'permission_denied') {
                    $failures[] = "ls {$directory}: {$e->errorCode}";
                }

                continue;
            }
            foreach ($entries as $entry) {
                if (str_starts_with($entry->name, '.') || in_array($entry->name, ['changes.ndjson'], true)) {
                    continue;
                }
                $visited++;
                $path = rtrim($directory, '/').'/'.$entry->name;
                try {
                    $stat = $this->vfs->stat($atlas, $path);
                    if (($stat['type'] ?? null) !== $entry->type) {
                        $failures[] = "stat {$path}: type ".($stat['type'] ?? 'null')." != {$entry->type}";
                    }
                    if ($entry->type === 'file') {
                        $this->vfs->read($atlas, $path);
                    }
                } catch (VfsError $e) {
                    $failures[] = ($entry->type === 'file' ? 'read' : 'stat')." {$path}: {$e->errorCode}";

                    continue;
                }
                if ($entry->type === 'directory' && $depth < 3 && ! $entry->isAlias()) {
                    $queue[] = [$path, $depth + 1];
                }
            }
        }

        $this->assertSame([], $failures);
    }

    // Pilot bug 6: `ls -R /docs` descended into the by-id alias (each folder
    // printed three times, under canonical headers); `ls /docs/by-id` listed
    // titles instead of the ids that open there.
    public function test_ls_recursive_docs_skips_by_id_alias_and_by_id_lists_ids(): void
    {
        $world = $this->world();
        $executor = app(VfsCommandExecutor::class);

        $out = $executor->execute($world->atlas, 'ls -R /docs', '/', new VfsBudget(maxEntries: 200))['stdout'];
        $headers = array_values(array_filter(explode("\n", $out), fn (string $line): bool => str_ends_with($line, ':')));
        $this->assertSame(['/docs:', '/docs/handbook:', '/docs/handbook/engineering:', '/docs/handbook/engineering/runbooks:'], $headers, $out);

        $byId = $this->vfs->list($world->atlas, '/docs/by-id');
        $refund = collect($byId)->firstWhere('backendId', $world->ids['doc.refund']);
        $this->assertSame($world->ids['doc.refund'], $refund->name);
    }

    // Pilot: agent-area entries had an unreadable /docs/by-id canonical path,
    // `plan.md` was listed as `planmd`, and `find /` printed /agents/by-id/…
    // forms because the by-id alias was walked first.
    public function test_agent_area_paths_are_readable_and_find_prints_friendly_forms(): void
    {
        $folder = app(AgentDocumentService::class)->createAgentDocumentStructure($this->agent, ['IDENTITY' => 'I am Keeper']);
        $this->agent->update(['docs_folder_id' => $folder->id]);
        $this->vfs->write($this->agent, '/me/notes/plan.md', "kestrel\n");

        $entry = collect($this->vfs->list($this->agent, '/agents/keeper/notes'))->firstWhere('name', 'plan.md');
        $this->assertSame('/agents/keeper/notes/plan.md', $entry->path);
        $this->assertSame("/agents/by-id/{$this->agent->id}/notes/plan.md", $entry->canonicalPath);
        $this->assertSame("kestrel\n", $this->vfs->read($this->agent, $entry->canonicalPath));

        $find = app(VfsCommandExecutor::class)->execute($this->agent, 'find / -name "plan*"', '/', new VfsBudget(maxDepth: 6, maxFiles: 500, maxEntries: 1000))['stdout'];
        $this->assertSame("/agents/keeper/notes/plan.md\n", $find);
    }

    // Pilot/phase-3a leftover: trashed /me notes had no trash view and no
    // restore; they now work like every other mount (ls, cat, mv).
    public function test_trashed_me_note_is_listed_and_restored_with_mv(): void
    {
        $folder = app(AgentDocumentService::class)->createAgentDocumentStructure($this->agent, ['IDENTITY' => 'I am Keeper']);
        $this->agent->update(['docs_folder_id' => $folder->id]);
        $this->vfs->write($this->agent, '/me/notes/plan.md', "keep me\n");
        $trashed = $this->vfs->remove($this->agent, '/me/notes/plan.md');
        $id = basename($trashed['canonical_path']);

        $listing = $this->vfs->list($this->agent, '/me/.trash');
        $this->assertSame([$id], array_map(fn (VfsEntry $entry): ?string => $entry->backendId, $listing));
        $this->assertSame("keep me\n", $this->vfs->read($this->agent, "/me/.trash/{$id}"));
        $this->assertStringContainsString("mv /me/.trash/{$id}", $trashed['restore_hint']);

        $restored = $this->vfs->move($this->agent, "/me/.trash/{$id}", '/me/notes/plan-restored.md');
        $this->assertSame('restored', $restored['status']);
        $this->assertSame("keep me\n", $this->vfs->read($this->agent, '/me/notes/plan-restored.md'));
        $this->assertSame([], $this->vfs->list($this->agent, '/me/.trash'));

        // Another agent cannot restore someone else's notes.
        $this->vfs->remove($this->agent, '/me/notes/plan-restored.md');
        $other = User::factory()->agent('manager')->create(['name' => 'Other', 'status' => 'idle', 'manager_id' => null]);
        $this->agent->update(['manager_id' => $other->id]);
        try {
            $this->vfs->move($other, "/agents/by-id/{$this->agent->id}/.trash/{$id}", "/agents/by-id/{$this->agent->id}/notes/x.md");
            $this->fail('Only the agent itself restores its notes.');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
        }
    }

    // Phase-3a leftover: four jobs bound their workspace with a bare
    // instance() call and leaked it into the caller (sync queue). They now
    // bind for the job only and restore the caller's binding.
    public function test_jobs_restore_the_callers_workspace_binding(): void
    {
        $other = Workspace::create(['name' => 'Other', 'slug' => 'other']);
        $seen = [];

        $sleeper = User::factory()->agent('manager')->create(['name' => 'Sleeper', 'status' => 'sleeping', 'workspace_id' => $other->id]);
        User::updating(function () use (&$seen): void {
            $seen['resume'] = app('currentWorkspace')->id;
        });
        (new AgentResumeFromSleepJob($sleeper))->handle();
        User::flushEventListeners();
        $this->assertSame($other->id, $seen['resume']);
        $this->assertSame($this->workspace->id, app('currentWorkspace')->id);

        $compactor = $this->mock(ConversationCompactionService::class);
        $compactor->shouldReceive('compact')->andReturnUsing(function () use (&$seen) {
            $seen['compact'] = app('currentWorkspace')->id;

            return null;
        });
        $flusher = $this->mock(MemoryFlushService::class);
        $flusher->shouldReceive('flush')->andReturnNull();
        (new CompactConversationJob('channel-1', $sleeper))->handle($compactor, $flusher);
        $this->assertSame($other->id, $seen['compact']);
        $this->assertSame($this->workspace->id, app('currentWorkspace')->id);

        $this->app->instance(WebRequestGuard::class, new class extends WebRequestGuard
        {
            protected function resolveIpAddresses(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
        Http::fake(function ($request) use (&$seen) {
            if (str_contains($request->url(), 'api.telegram.org')) {
                $seen['telegram'] = app('currentWorkspace')->id;

                return Http::response(['ok' => true, 'result' => ['message_id' => 1]]);
            }
            $seen['mcp'] ??= app('currentWorkspace')->id;

            return Http::response(['error' => 'down'], 500);
        });
        McpServer::create(['id' => (string) Str::uuid(), 'name' => 'Other MCP', 'slug' => 'other_mcp', 'url' => 'https://mcp.example.com/api', 'auth_type' => 'none', 'enabled' => true, 'timeout' => 5, 'workspace_id' => $other->id]);
        (new RefreshMcpToolsJob)->handle();
        $this->assertSame($other->id, $seen['mcp'] ?? null);
        $this->assertSame($this->workspace->id, app('currentWorkspace')->id);

        IntegrationSetting::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $other->id,
            'integration_id' => 'telegram',
            'account_alias' => '',
            'config' => ['api_key' => 'telegram-token', 'webhook_secret' => 'secret', 'bot_user_id' => '999', 'bot_username' => 'Bot'],
            'enabled' => true,
            'is_default' => true,
        ]);
        $approval = ApprovalRequest::factory()->pending()->create(['requester_id' => $sleeper->id, 'title' => 'Approve it']);
        (new SendApprovalToTelegramJob($approval, '12345'))->handle();
        $this->assertSame($other->id, $seen['telegram'] ?? null);
        $this->assertSame($this->workspace->id, app('currentWorkspace')->id);
    }

    /**
     * The eval fixture world (it brings its own default disk).
     */
    private function world(): EvalWorld
    {
        WorkspaceDisk::query()->delete();

        return EvalWorld::build($this->workspace, []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function feedLines(string $content): array
    {
        app(VfsEventSink::class)->flush();

        return array_map(fn (string $line): array => json_decode($line, true), array_values(array_filter(explode("\n", $content))));
    }

    private function allow(User $agent, string $scopeType, string $scopeKey): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $agent->id,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'permission' => 'allow',
        ]);
    }
}
