<?php

namespace Tests\Feature\Vfs;

use App\Agents\Tools\Vfs\VfsRead;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\AgentPermission;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceDisk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;
use ZipArchive;

/**
 * Regression coverage for the VFS write path's data-integrity contracts:
 * content reads vs display reads (no truncated write-back), the exact-match
 * text patch engine, and locked optimistic concurrency with `conflict`.
 */
class VfsContentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private OpenCompanyVfs $vfs;

    private VfsCommandExecutor $executor;

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
        $this->allow('file_folder', '*');

        $this->vfs = app(OpenCompanyVfs::class);
        $this->executor = app(VfsCommandExecutor::class);
    }

    // P0 regression: patching a >100 KB document used to rebuild it from the
    // 100 KB display read and persist "[... truncated]", dropping the tail.
    public function test_patching_a_large_document_preserves_every_byte(): void
    {
        $body = $this->largeText('doc');
        $doc = $this->patchableDocument('Big Runbook', "HEAD-MARKER\n".$body."TAIL-MARKER\n");

        $this->vfs->patch($this->agent, "/docs/by-id/{$doc->id}", json_encode(['search' => 'HEAD-MARKER', 'replace' => 'HEAD-EDITED'], JSON_THROW_ON_ERROR));

        $this->assertSame("HEAD-EDITED\n".$body."TAIL-MARKER\n", $doc->fresh()->content);
    }

    // P0 regression: same bug for workspace files, plus `>>`, `tee -a`, and
    // the stderr-append path, which all prepended a display read.
    public function test_file_patch_append_and_tee_append_preserve_every_byte(): void
    {
        $body = $this->largeText('file');
        $this->vfs->write($this->agent, '/files/big/log.txt', "HEAD\n".$body, 'create');

        $this->vfs->patch($this->agent, '/files/big/log.txt', json_encode(['search' => "HEAD\n", 'replace' => "HEAD2\n"], JSON_THROW_ON_ERROR));
        $this->executor->execute($this->agent, 'printf appended-once >> /files/big/log.txt');
        $this->executor->execute($this->agent, 'printf appended-twice | tee -a /files/big/log.txt');

        $this->assertSame("HEAD2\n".$body.'appended-once'.'appended-twice', $this->vfs->readContent($this->agent, '/files/big/log.txt'));
    }

    // P0 regression: tar/unzip extraction truncated each member to maxBytes
    // and archive creation embedded display reads; round trips must be exact.
    public function test_archive_create_and_extract_round_trip_large_files_exactly(): void
    {
        $body = $this->largeText('archive');
        $this->vfs->write($this->agent, '/files/arch/src/big.txt', $body, 'create');

        $this->executor->execute($this->agent, 'tar -c -f /files/arch/big.tar /files/arch/src/big.txt');
        $this->executor->execute($this->agent, 'tar -x -f /files/arch/big.tar -C /files/arch/tar-out');
        $this->assertSame($body, $this->vfs->readContent($this->agent, '/files/arch/tar-out/big.txt'));

        if (class_exists(ZipArchive::class)) {
            $this->executor->execute($this->agent, 'zip /files/arch/big.zip /files/arch/src/big.txt');
            $this->executor->execute($this->agent, 'unzip -o /files/arch/big.zip -d /files/arch/zip-out');
            $this->assertSame($body, $this->vfs->readContent($this->agent, '/files/arch/zip-out/big.txt'));
        }
    }

    // Display reads keep the visible truncation line, but shell redirection
    // must refuse to persist it instead of silently writing a prefix.
    public function test_display_reads_stay_truncated_but_cannot_be_written_back(): void
    {
        $body = $this->largeText('display');
        $this->vfs->write($this->agent, '/files/display/big.txt', $body, 'create');

        $this->assertStringEndsWith(VfsText::TRUNCATION_SUFFIX, $this->vfs->read($this->agent, '/files/display/big.txt'));
        $this->assertStringEndsWith(VfsText::TRUNCATION_SUFFIX, $this->executor->execute($this->agent, 'cat /files/display/big.txt')['stdout']);

        try {
            $this->executor->execute($this->agent, 'cat /files/display/big.txt > /files/display/copy.txt');
            $this->fail('A truncated display read must not be written back.');
        } catch (VfsError $e) {
            $this->assertSame('invalid_request', $e->errorCode);
            $this->assertSame('/files/display/copy.txt', $e->context['path']);
        }

        // Raising the read budget makes the same pipeline exact.
        $this->executor->execute($this->agent, 'cat /files/display/big.txt > /files/display/copy.txt', '/', new VfsBudget(maxBytes: 1_000_000));
        $this->assertSame($body, $this->vfs->readContent($this->agent, '/files/display/copy.txt'));
    }

    // readContent never truncates: above the ceiling it fails loudly, and a
    // directory is not content.
    public function test_read_content_enforces_the_ceiling_and_rejects_directories(): void
    {
        $this->vfs->write($this->agent, '/files/ceiling/a.txt', str_repeat('x', 2048), 'create');
        config(['vfs.max_content_bytes' => 1024]);

        try {
            $this->vfs->readContent($this->agent, '/files/ceiling/a.txt');
            $this->fail('Content reads above the ceiling must fail, not truncate.');
        } catch (VfsError $e) {
            $this->assertSame('invalid_request', $e->errorCode);
            $this->assertSame(1024, $e->context['max_content_bytes']);
        }

        try {
            $this->vfs->readContent($this->agent, '/files/ceiling');
            $this->fail('A directory listing is not content.');
        } catch (VfsError $e) {
            $this->assertSame('not_readable', $e->errorCode);
        }
    }

    // Patch matching is exact: zero matches name the path and give a hint,
    // multiple matches report 1-based line numbers unless replace_all.
    public function test_text_patch_rejects_missing_and_ambiguous_matches_and_supports_replace_all(): void
    {
        $doc = $this->patchableDocument('Ambiguous', "alpha\nbeta TODO\ngamma\ndelta TODO\n");
        $path = "/docs/by-id/{$doc->id}";

        try {
            $this->vfs->patch($this->agent, $path, json_encode(['search' => 'missing', 'replace' => 'x'], JSON_THROW_ON_ERROR));
            $this->fail('Missing search text must fail.');
        } catch (VfsError $e) {
            $this->assertSame('invalid_request', $e->errorCode);
            $this->assertSame("Patch search text was not found in {$path}", $e->getMessage());
            $this->assertSame($path, $e->context['path']);
            $this->assertArrayHasKey('hint', $e->context);
        }

        try {
            $this->vfs->patch($this->agent, $path, json_encode(['search' => 'TODO', 'replace' => 'DONE'], JSON_THROW_ON_ERROR));
            $this->fail('Ambiguous search text must fail without replace_all.');
        } catch (VfsError $e) {
            $this->assertSame('ambiguous_match', $e->errorCode);
            $this->assertSame(2, $e->context['count']);
            $this->assertSame([2, 4], $e->context['lines']);
            $this->assertArrayHasKey('hint', $e->context);
        }
        $this->assertSame("alpha\nbeta TODO\ngamma\ndelta TODO\n", $doc->fresh()->content);

        $result = $this->vfs->patch($this->agent, $path, json_encode(['search' => 'TODO', 'replace' => 'DONE', 'replace_all' => true], JSON_THROW_ON_ERROR));
        $this->assertSame(2, $result['replacements']);
        $this->assertSame("alpha\nbeta DONE\ngamma\ndelta DONE\n", $doc->fresh()->content);
    }

    // Content-anchored staleness: a stale version still applies when the
    // anchor matches exactly once (reported as stale_base); otherwise conflict.
    public function test_stale_text_patch_applies_when_anchor_is_unique_and_conflicts_otherwise(): void
    {
        $doc = $this->patchableDocument('Shared', "intro\nowner: alice\nfooter\n");
        $path = "/docs/by-id/{$doc->id}";
        $staleVersion = $this->vfs->stat($this->agent, $path)['version'];

        // Someone else edits a different region after our read.
        $this->travel(2)->seconds();
        $doc->update(['content' => "intro (edited)\nowner: alice\nfooter\n"]);

        $result = $this->vfs->patch($this->agent, $path, json_encode(['search' => 'owner: alice', 'replace' => 'owner: bob'], JSON_THROW_ON_ERROR), $staleVersion);
        $this->assertTrue($result['stale_base']);
        $this->assertStringContainsString('changed elsewhere', $result['note']);
        $this->assertSame("intro (edited)\nowner: bob\nfooter\n", $doc->fresh()->content);

        try {
            $this->vfs->patch($this->agent, $path, json_encode(['search' => 'owner: alice', 'replace' => 'owner: carol'], JSON_THROW_ON_ERROR), $staleVersion);
            $this->fail('A stale base whose anchor is gone must conflict.');
        } catch (VfsError $e) {
            $this->assertSame('conflict', $e->errorCode);
            $this->assertSame($staleVersion, $e->context['given_version']);
            $this->assertSame($this->vfs->stat($this->agent, $path)['version'], $e->context['current_version']);
        }
    }

    // Full overwrites and task patches stay strict: a stale version is a
    // conflict carrying the current version.
    public function test_stale_overwrite_and_task_patch_conflict(): void
    {
        $created = $this->vfs->write($this->agent, '/files/strict/a.txt', 'one', 'create');
        $this->travel(2)->seconds();
        $this->vfs->write($this->agent, '/files/strict/a.txt', 'two', 'overwrite');

        try {
            $this->vfs->write($this->agent, '/files/strict/a.txt', 'three', 'overwrite', $created['version']);
            $this->fail('A stale overwrite must conflict.');
        } catch (VfsError $e) {
            $this->assertSame('conflict', $e->errorCode);
            $this->assertSame($created['version'], $e->context['given_version']);
        }
        $this->assertSame('two', $this->vfs->readContent($this->agent, '/files/strict/a.txt'));

        $task = Task::create([
            'id' => 'task-strict',
            'workspace_id' => $this->workspace->id,
            'title' => 'Strict',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'agent_id' => $this->agent->id,
            'requester_id' => $this->agent->id,
            'source' => Task::SOURCE_MANUAL,
        ]);
        $version = $this->vfs->stat($this->agent, "/tasks/by-id/{$task->id}")['version'];
        $this->travel(2)->seconds();
        $task->update(['status' => Task::STATUS_PAUSED]);

        try {
            $this->vfs->patch($this->agent, "/tasks/by-id/{$task->id}", json_encode(['title' => 'Renamed'], JSON_THROW_ON_ERROR), $version);
            $this->fail('A stale task patch must conflict.');
        } catch (VfsError $e) {
            $this->assertSame('conflict', $e->errorCode);
        }
        $this->assertSame('Strict', $task->fresh()->title);
    }

    // Row patches merge only the supplied columns into the row as it is now
    // (a concurrent edit to another column survives), and per-field base
    // values reject the patch when that field changed underneath.
    public function test_table_row_patch_merges_supplied_columns_and_honors_base_values(): void
    {
        $table = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Leads', 'created_by' => $this->agent->id]);
        $status = DataTableColumn::create(['table_id' => $table->id, 'name' => 'Status', 'type' => 'text', 'order' => 0, 'required' => false]);
        $owner = DataTableColumn::create(['table_id' => $table->id, 'name' => 'Owner', 'type' => 'text', 'order' => 1, 'required' => false]);
        $row = DataTableRow::create(['table_id' => $table->id, 'data' => [$status->id => 'lead', $owner->id => 'alice'], 'created_by' => $this->agent->id]);
        $path = "/tables/leads/rows/by-id/{$row->id}.json";

        // A concurrent writer changes Owner after our read.
        $row->update(['data' => [$status->id => 'lead', $owner->id => 'bob']]);

        $this->vfs->patch($this->agent, $path, json_encode(['data' => ['Status' => 'active'], 'base' => ['Status' => 'lead']], JSON_THROW_ON_ERROR));
        $this->assertSame([$status->id => 'active', $owner->id => 'bob'], $row->fresh()->data);

        try {
            $this->vfs->patch($this->agent, $path, json_encode(['data' => ['Owner' => 'carol'], 'base' => ['Owner' => 'alice']], JSON_THROW_ON_ERROR));
            $this->fail('A row patch whose base value changed must conflict.');
        } catch (VfsError $e) {
            $this->assertSame('conflict', $e->errorCode);
            $this->assertSame(['Owner'], $e->context['changed_fields']);
            $this->assertSame(['Owner' => 'bob'], $e->context['current']);
        }
        $this->assertSame('bob', $row->fresh()->data[$owner->id]);
    }

    // vfs_read's version must describe the bytes it returned (taken from the
    // same read), so it can be handed straight back as a patch base.
    public function test_vfs_read_version_matches_the_returned_content(): void
    {
        $doc = $this->patchableDocument('Versioned', 'v1 content');
        $read = json_decode(app(VfsRead::class, ['agent' => $this->agent, 'vfs' => $this->vfs])
            ->handle(new Request(['path' => "/docs/by-id/{$doc->id}", 'raw' => true])), true);

        $this->assertSame('v1 content', $read['result']['content']);
        $this->assertSame($this->vfs->stat($this->agent, "/docs/by-id/{$doc->id}")['version'], $read['result']['version']);

        $result = $this->vfs->patch($this->agent, "/docs/by-id/{$doc->id}", json_encode(['search' => 'v1', 'replace' => 'v2'], JSON_THROW_ON_ERROR), $read['result']['version']);
        $this->assertArrayNotHasKey('stale_base', $result);
    }

    private function patchableDocument(string $title, string $content): Document
    {
        $folder = Document::factory()->create(['title' => "{$title} Folder", 'is_folder' => true, 'author_id' => $this->agent->id]);
        $this->allow('folder', $folder->id);

        return Document::factory()->create([
            'title' => $title,
            'parent_id' => $folder->id,
            'content' => $content,
            'author_id' => $this->agent->id,
        ]);
    }

    /** About 160 KB of distinct lines, well past the 100 KB display budget. */
    private function largeText(string $seed): string
    {
        $lines = [];
        for ($i = 0; $i < 4000; $i++) {
            $lines[] = sprintf('%s line %05d %s', $seed, $i, str_repeat('.', 25));
        }

        return implode("\n", $lines)."\n";
    }

    private function allow(string $scopeType, string $scopeKey): void
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
}
