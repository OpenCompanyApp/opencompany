<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\CommentOnDocument;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class CommentOnDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function makeTool(?User $agent = null): array
    {
        $agent = $agent ?? User::factory()->create(['type' => 'agent']);
        $tool = new CommentOnDocument($agent, app(AgentPermissionService::class));

        return [$tool, $agent];
    }

    public function test_adds_comment_to_document(): void
    {
        [$tool, $agent] = $this->makeTool();

        $document = Document::factory()->create(['author_id' => $agent->id]);

        $request = new Request([
            'action' => 'add',
            'documentId' => $document->id,
            'content' => 'This looks great!',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Comment added', $result);

        $comment = DocumentComment::where('document_id', $document->id)
            ->where('author_id', $agent->id)
            ->first();
        $this->assertNotNull($comment);
        $this->assertEquals('This looks great!', $comment->content);
    }

    public function test_resolves_comment(): void
    {
        [$tool, $agent] = $this->makeTool();

        $document = Document::factory()->create(['author_id' => $agent->id]);
        $comment = DocumentComment::factory()->create([
            'document_id' => $document->id,
            'author_id' => $agent->id,
        ]);

        $request = new Request([
            'action' => 'resolve',
            'documentId' => $document->id,
            'commentId' => $comment->id,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('resolved', $result);

        $comment->refresh();
        $this->assertTrue($comment->resolved);
        $this->assertEquals($agent->id, $comment->resolved_by_id);
        $this->assertNotNull($comment->resolved_at);
    }

    public function test_deletes_comment(): void
    {
        [$tool, $agent] = $this->makeTool();

        $document = Document::factory()->create(['author_id' => $agent->id]);
        $comment = DocumentComment::factory()->create([
            'document_id' => $document->id,
            'author_id' => $agent->id,
        ]);
        $commentId = $comment->id;

        $request = new Request([
            'action' => 'delete',
            'documentId' => $document->id,
            'commentId' => $commentId,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('deleted', $result);
        $this->assertNull(DocumentComment::find($commentId));
    }

    public function test_has_correct_description(): void
    {
        [$tool] = $this->makeTool();

        $this->assertStringContainsString('Add, resolve, or delete comments', $tool->description());
    }
}
