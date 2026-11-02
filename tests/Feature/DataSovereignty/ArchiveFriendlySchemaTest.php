<?php

namespace Tests\Feature\DataSovereignty;

use App\Models\ChangeRecord;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Models\WorkspaceFile;
use App\Services\FileSystemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pillar 11 invariant: the workspace archive (`.ocarchive`) must round-trip
 * losslessly. Until the exporter exists, these tests keep the schema
 * archive-friendly (database/schema/README.md, "Archive invariants").
 */
class ArchiveFriendlySchemaTest extends TestCase
{
    use RefreshDatabase;

    /** Integer-keyed tables that never enter an archive. Grow only with a reason in the README. */
    private const NON_ARCHIVED_INTEGER_KEYED = ['migrations', 'jobs', 'failed_jobs', 'telescope_entries', 'codex_tokens'];

    public function test_every_archived_table_is_keyed_by_a_stable_string_id(): void
    {
        $offenders = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (in_array($table, self::NON_ARCHIVED_INTEGER_KEYED, true)) {
                continue;
            }
            foreach (DB::select("PRAGMA table_info(\"{$table}\")") as $column) {
                if ((int) $column->pk > 0 && str_contains(strtolower((string) $column->type), 'int')) {
                    $offenders[] = "{$table}.{$column->name}";
                }
            }
        }

        $this->assertSame([], $offenders, 'Archived tables need UUID/ULID keys, not auto-increment integers.');
    }

    public function test_file_blobs_are_content_addressed(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $file = app(FileSystemService::class)->writeFile($this->workspace->id, null, 'a.txt', 'hello', $owner->id);

        $this->assertSame(hash('sha256', 'hello'), $file->content_hash);
        $this->assertSame($file->content_hash, app(FileSystemService::class)->copyFile($file, null, 'b.txt')->content_hash);
        $this->assertInstanceOf(WorkspaceFile::class, $file);
    }

    public function test_history_and_change_records_are_append_only(): void
    {
        $doc = Document::factory()->create();
        $version = DocumentVersion::create(['id' => (string) Str::uuid(), 'document_id' => $doc->id,
            'title' => 't', 'content' => 'v1', 'author_id' => $doc->author_id, 'version_number' => 1]);
        $record = ChangeRecord::create(['id' => (string) Str::uuid7(), 'workspace_id' => $this->workspace->id,
            'subject_type' => Document::class, 'subject_id' => $doc->id, 'operation' => 'updated']);

        $record->update(['reverted_at' => now()]);
        foreach ([fn () => $version->update(['content' => 'rewritten']), fn () => $version->delete(),
            fn () => $record->update(['operation' => 'created']), fn () => $record->delete()] as $mutation) {
            try {
                $mutation();
                $this->fail('Append-only history was mutated.');
            } catch (\LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_workspace_archive_round_trips_losslessly(): void
    {
        $this->markTestSkipped('Invariant placeholder (pillar 11): export -> import -> export must be identical. '
            .'Implemented with the .ocarchive exporter (data-sovereignty.md, Phase 2).');
    }
}
