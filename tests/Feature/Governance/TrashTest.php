<?php

namespace Tests\Feature\Governance;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Governance revision: delete means trash (docs/architecture/governance-model.md
 * "Trash and purge"). A trashed folder takes its subtree along, trashed docs
 * leave queries and search, restore brings back exactly that cascade, and
 * folders holding system files cannot be trashed.
 */
class TrashTest extends TestCase
{
    use RefreshDatabase;

    private User $human;

    protected function setUp(): void
    {
        parent::setUp();
        $this->human = User::factory()->create();
    }

    private function doc(array $attributes = []): Document
    {
        return Document::factory()->create(['author_id' => $this->human->id, ...$attributes]);
    }

    public function test_trashing_a_folder_trashes_and_deindexes_its_subtree_and_restore_reverses_only_that(): void
    {
        $folder = $this->doc(['is_folder' => true, 'title' => 'Folder']);
        $child = $this->doc(['parent_id' => $folder->id, 'title' => 'Child']);
        $earlier = $this->doc(['parent_id' => $folder->id, 'title' => 'Trashed earlier']);
        DocumentChunk::query()->where('document_id', $child->id)->delete();
        DocumentChunk::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id,
            'document_id' => $child->id, 'content' => 'secret', 'content_hash' => hash('sha256', 'secret'),
            'chunk_index' => 0, 'collection' => 'general']);

        $earlier->delete();
        $this->travel(2)->seconds();
        $folder->delete();

        $this->assertSoftDeleted($folder);
        $this->assertSoftDeleted($child);
        $this->assertNull(Document::find($child->id), 'trashed docs leave queries');
        $this->assertFalse(DocumentChunk::query()->where('document_id', $child->id)->exists(), 'and search');

        $folder->restore();

        $this->assertNotNull(Document::find($child->id));
        $this->assertNull(Document::find($earlier->id), 'separately trashed items stay in trash');
    }

    public function test_purge_removes_the_row(): void
    {
        $doc = $this->doc();
        $doc->delete();
        $doc->forceDelete();

        $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
    }

    public function test_a_folder_holding_system_files_cannot_be_trashed(): void
    {
        $folder = $this->doc(['is_folder' => true]);
        $this->doc(['parent_id' => $folder->id, 'is_system' => true]);

        $this->expectException(\LogicException::class);
        $folder->delete();
    }
}
