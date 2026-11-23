<?php

namespace Tests\Feature\Authorization;

use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\DocumentPermission;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stored document sharing rows are enforced across CRUD, sub-resources and
 * search, never silently ignored (GIN-1199; ROADMAP P0 item 3). A document's
 * rows restrict it and its subtree to its author and the listed users;
 * viewers read, editors write; there is no admin override; revoking a row
 * revokes access.
 */
class DocumentPermissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $viewer;   // shared as viewer

    private User $editor;   // shared as editor

    private User $outsider; // same workspace, not listed

    private Document $folder;

    private Document $doc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = $this->human('member');
        $this->viewer = $this->human('member');
        $this->editor = $this->human('member');
        $this->outsider = $this->human('admin');

        $this->folder = $this->document('Finance', $this->author, null, folder: true);
        $this->share($this->folder, $this->viewer, 'viewer');
        $this->share($this->folder, $this->editor, 'editor');
        $this->doc = $this->document('Salaries', $this->author, $this->folder->id, content: 'confidential salary table');
    }

    public function test_unlisted_member_cannot_see_the_document_anywhere(): void
    {
        $ids = collect($this->actingAs($this->outsider)->getJson('/api/documents')->assertOk()->json())->pluck('id');
        $this->assertNotContains($this->doc->id, $ids);
        $this->assertNotContains($this->folder->id, $ids);

        $this->actingAs($this->outsider)->getJson("/api/documents/{$this->doc->id}")->assertNotFound();
        $this->actingAs($this->outsider)->getJson("/api/documents/{$this->doc->id}/comments")->assertNotFound();
        $this->actingAs($this->outsider)->getJson("/api/documents/{$this->doc->id}/versions")->assertNotFound();
        $this->actingAs($this->outsider)->getJson("/api/documents/{$this->doc->id}/attachments")->assertNotFound();
        $this->actingAs($this->outsider)->patchJson("/api/documents/{$this->doc->id}", ['title' => 'pwned'])->assertNotFound();
        $this->actingAs($this->outsider)->deleteJson("/api/documents/{$this->doc->id}")->assertNotFound();
        $this->actingAs($this->outsider)->postJson('/api/documents', ['title' => 'x', 'parentId' => $this->folder->id])->assertNotFound();

        $this->assertSame([], $this->actingAs($this->outsider)->getJson('/api/search?q=salary&type=documents')->json('documents'));
        $this->assertSame([], $this->actingAs($this->outsider)->getJson('/api/documents/search?q=salary')->json());
        $this->assertSame('Salaries', $this->doc->fresh()->title);
        $this->assertFalse($this->doc->fresh()->trashed());
    }

    public function test_viewer_reads_but_cannot_write(): void
    {
        $this->actingAs($this->viewer)->getJson("/api/documents/{$this->doc->id}")->assertOk();
        $this->assertContains($this->doc->id, collect($this->actingAs($this->viewer)->getJson('/api/documents')->json())->pluck('id'));

        $this->actingAs($this->viewer)->patchJson("/api/documents/{$this->doc->id}", ['title' => 'renamed'])
            ->assertForbidden()->assertJsonPath('error', 'document_editor_required');
        $this->actingAs($this->viewer)->deleteJson("/api/documents/{$this->doc->id}")->assertForbidden();
        $this->actingAs($this->viewer)->postJson('/api/documents', ['title' => 'x', 'parentId' => $this->folder->id])->assertForbidden();

        $version = DocumentVersion::create(['id' => (string) Str::uuid(), 'document_id' => $this->doc->id, 'title' => 'Salaries', 'content' => 'old', 'version_number' => 1, 'author_id' => $this->author->id]);
        $this->actingAs($this->viewer)->postJson("/api/documents/{$this->doc->id}/versions/{$version->id}/restore")->assertForbidden();

        // Viewers may comment, but not edit someone else's comment.
        $this->actingAs($this->viewer)->postJson("/api/documents/{$this->doc->id}/comments", ['content' => 'question'])->assertSuccessful();
        $authors = DocumentComment::create(['id' => (string) Str::uuid(), 'document_id' => $this->doc->id, 'author_id' => $this->author->id, 'content' => 'note']);
        $this->actingAs($this->viewer)->patchJson("/api/documents/{$this->doc->id}/comments/{$authors->id}", ['content' => 'changed'])
            ->assertForbidden()->assertJsonPath('error', 'comment_owner_required');
        $this->actingAs($this->viewer)->deleteJson("/api/documents/{$this->doc->id}/comments/{$authors->id}")->assertForbidden();
        $this->assertSame('note', $authors->fresh()->content);
    }

    public function test_editor_writes_moves_and_trashes(): void
    {
        $this->actingAs($this->editor)->patchJson("/api/documents/{$this->doc->id}", ['title' => 'Salaries 2027'])->assertOk();
        $this->actingAs($this->editor)->postJson('/api/documents', ['title' => 'New', 'content' => '', 'parentId' => $this->folder->id])->assertSuccessful();

        // Moving needs write access to the destination: a folder shared with
        // someone else only is not a valid target.
        $private = $this->document('Private', $this->outsider, null, folder: true);
        $this->share($private, $this->outsider, 'editor');
        $this->actingAs($this->editor)->patchJson("/api/documents/{$this->doc->id}", ['parentId' => $private->id])->assertNotFound();
        $this->assertSame($this->folder->id, $this->doc->fresh()->parent_id);

        $this->actingAs($this->editor)->deleteJson("/api/documents/{$this->doc->id}")->assertOk();
        $this->assertTrue(Document::withTrashed()->find($this->doc->id)->trashed());
    }

    public function test_revoking_a_share_revokes_access(): void
    {
        $this->actingAs($this->viewer)->getJson("/api/documents/{$this->doc->id}")->assertOk();

        DocumentPermission::where('document_id', $this->folder->id)->where('user_id', $this->viewer->id)->delete();

        $this->actingAs($this->viewer)->getJson("/api/documents/{$this->doc->id}")->assertNotFound();
    }

    public function test_unshared_documents_stay_open_to_members_and_system_documents_are_protected(): void
    {
        $open = $this->document('Handbook', $this->author, null);
        $this->actingAs($this->outsider)->getJson("/api/documents/{$open->id}")->assertOk();
        $this->actingAs($this->outsider)->patchJson("/api/documents/{$open->id}", ['title' => 'Handbook v2'])->assertOk();

        $system = $this->document('IDENTITY.md', $this->author, null);
        $system->forceFill(['is_system' => true])->save();
        $this->actingAs($this->outsider)->patchJson("/api/documents/{$system->id}", ['title' => 'x'])
            ->assertForbidden()->assertJsonPath('error', 'system_document');
        $this->actingAs($this->outsider)->deleteJson("/api/documents/{$system->id}")->assertForbidden();
    }

    public function test_sharing_and_parents_cannot_reach_another_workspace(): void
    {
        $other = Workspace::create(['id' => (string) Str::uuid(), 'name' => 'B', 'slug' => 'b-'.Str::random(5)]);
        $foreignFolder = Document::create(['id' => (string) Str::uuid(), 'workspace_id' => $other->id, 'title' => 'Theirs', 'author_id' => $this->author->id, 'is_folder' => true, 'content' => '', 'status' => 'draft']);
        $foreignUser = User::factory()->create(['type' => 'human']);

        $this->actingAs($this->author)->postJson('/api/documents', ['title' => 'x', 'parentId' => $foreignFolder->id])->assertNotFound();
        $this->actingAs($this->author)->postJson('/api/documents', ['title' => 'x', 'viewerIds' => [$foreignUser->id]])->assertStatus(422);
        $this->actingAs($this->author)->getJson("/api/documents/{$foreignFolder->id}")->assertNotFound();
    }

    private function human(string $role): User
    {
        $user = User::factory()->create(['type' => 'human']);
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function document(string $title, User $author, ?string $parentId, bool $folder = false, string $content = 'text'): Document
    {
        return Document::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'title' => $title,
            'content' => $folder ? '' : $content,
            'author_id' => $author->id,
            'parent_id' => $parentId,
            'is_folder' => $folder,
            'status' => 'draft',
        ]);
    }

    private function share(Document $document, User $user, string $role): void
    {
        DocumentPermission::create(['id' => (string) Str::uuid(), 'document_id' => $document->id, 'user_id' => $user->id, 'role' => $role]);
    }
}
