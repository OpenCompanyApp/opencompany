<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UI-audit finding (data loss): the Docs autosave sent no version, so an open
 * human editor silently overwrote agent edits written through the VFS. Content
 * updates now require the version they were based on — the same token the VFS
 * checks — and a stale version is rejected with the current document.
 */
class DocumentConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $human;

    private Document $doc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->human = User::factory()->create();
        $this->actingAs($this->human);
        $this->doc = Document::factory()->create(['title' => 'Plan', 'content' => 'v1', 'author_id' => $this->human->id]);
    }

    public function test_saving_with_the_current_version_succeeds_and_returns_the_new_version(): void
    {
        $version = $this->getJson("/api/documents/{$this->doc->id}")->assertOk()->json('version');
        $this->assertSame($this->doc->contentVersion(), $version);

        $saved = $this->patchJson("/api/documents/{$this->doc->id}", ['content' => 'v2', 'baseVersion' => $version])->assertOk();

        $this->assertSame('v2', $this->doc->fresh()->content);
        $this->assertNotSame($version, $saved->json('version'));
    }

    // The regression: an agent writes through the VFS while the editor is open;
    // the editor's next autosave must not overwrite it.
    public function test_a_stale_editor_save_after_an_agent_edit_is_rejected_with_the_current_document(): void
    {
        $editorVersion = $this->doc->contentVersion();
        $this->travel(2)->seconds();
        $this->doc->update(['content' => 'agent edit via VFS']);

        $this->patchJson("/api/documents/{$this->doc->id}", ['content' => 'human autosave', 'baseVersion' => $editorVersion])
            ->assertStatus(409)
            ->assertJsonPath('error', 'version_conflict')
            ->assertJsonPath('document.content', 'agent edit via VFS');
        $this->assertSame('agent edit via VFS', $this->doc->fresh()->content);
    }

    public function test_if_match_header_is_accepted_and_missing_version_is_refused(): void
    {
        $this->patchJson("/api/documents/{$this->doc->id}", ['content' => 'blind write'])->assertStatus(428);
        $this->assertSame('v1', $this->doc->fresh()->content);

        $this->withHeader('If-Match', '"'.$this->doc->contentVersion().'"')
            ->patchJson("/api/documents/{$this->doc->id}", ['content' => 'v2'])->assertOk();
    }

    public function test_metadata_only_updates_do_not_need_a_version(): void
    {
        $this->patchJson("/api/documents/{$this->doc->id}", ['title' => 'Renamed', 'icon' => 'ph:star'])->assertOk();
        $this->assertSame('Renamed', $this->doc->fresh()->title);
    }
}
