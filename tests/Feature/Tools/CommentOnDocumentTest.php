<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Docs\AddDocumentComment;
use App\Agents\Tools\Docs\DeleteDocumentComment;
use App\Agents\Tools\Docs\ResolveDocumentComment;
use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class CommentOnDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_adds_comment_to_document(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new AddDocumentComment($agent, app(AgentPermissionService::class));

        $document = Document::factory()->create(['author_id' => $agent->id]);

        $request = new Request([
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
        Bus::fake([IndexDocumentJob::class]);

        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new ResolveDocumentComment($agent, app(AgentPermissionService::class));

        $document = Document::factory()->create(['author_id' => $agent->id]);
        $comment = DocumentComment::factory()->create([
            'document_id' => $document->id,
            'author_id' => $agent->id,
        ]);

        $request = new Request([
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
        Bus::fake([IndexDocumentJob::class]);

        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new DeleteDocumentComment($agent, app(AgentPermissionService::class));

        $document = Document::factory()->create(['author_id' => $agent->id]);
        $comment = DocumentComment::factory()->create([
            'document_id' => $document->id,
            'author_id' => $agent->id,
        ]);
        $commentId = $comment->id;

        $request = new Request([
            'documentId' => $document->id,
            'commentId' => $commentId,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('deleted', $result);
        $this->assertNull(DocumentComment::find($commentId));
    }

    public function test_has_correct_descriptions(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $permissionService = app(AgentPermissionService::class);

        $addTool = new AddDocumentComment($agent, $permissionService);
        $resolveTool = new ResolveDocumentComment($agent, $permissionService);
        $deleteTool = new DeleteDocumentComment($agent, $permissionService);

        $this->assertStringContainsString('Add a comment', $addTool->description());
        $this->assertStringContainsString('Resolve or unresolve a comment', $resolveTool->description());
        $this->assertStringContainsString('Delete a comment', $deleteTool->description());
    }
}
