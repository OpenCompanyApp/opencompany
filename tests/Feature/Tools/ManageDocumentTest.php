<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Docs\CreateDocument;
use App\Agents\Tools\Docs\DeleteDocument;
use App\Agents\Tools\Docs\UpdateDocument;
use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ManageDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_document(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new CreateDocument($agent, app(AgentPermissionService::class));

        $request = new Request([
            'title' => 'Test Doc',
            'content' => 'Hello',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('created', $result);
        $this->assertStringContainsString('Test Doc', $result);

        $document = Document::where('title', 'Test Doc')->first();
        $this->assertNotNull($document);
        $this->assertEquals('Hello', $document->content);
        $this->assertEquals($agent->id, $document->author_id);
        $this->assertFalse($document->is_folder);
    }

    public function test_updates_document(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new UpdateDocument($agent, app(AgentPermissionService::class));

        $document = Document::factory()->create(['author_id' => $agent->id]);

        $request = new Request([
            'documentId' => $document->id,
            'title' => 'New Title',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('updated', $result);
        $this->assertStringContainsString('New Title', $result);

        $document->refresh();
        $this->assertEquals('New Title', $document->title);
    }

    public function test_deletes_document(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new DeleteDocument($agent, app(AgentPermissionService::class));

        $document = Document::factory()->create(['author_id' => $agent->id]);
        $documentId = $document->id;

        $request = new Request([
            'documentId' => $documentId,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('deleted', $result);
        $this->assertNull(Document::find($documentId));
    }

    public function test_creates_folder(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new CreateDocument($agent, app(AgentPermissionService::class));

        $request = new Request([
            'title' => 'My Folder',
            'isFolder' => true,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('created', $result);
        $this->assertStringContainsString('My Folder', $result);

        $folder = Document::where('title', 'My Folder')->first();
        $this->assertNotNull($folder);
        $this->assertTrue($folder->is_folder);
    }

    public function test_has_correct_descriptions(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $permissionService = app(AgentPermissionService::class);

        $createTool = new CreateDocument($agent, $permissionService);
        $updateTool = new UpdateDocument($agent, $permissionService);
        $deleteTool = new DeleteDocument($agent, $permissionService);

        $this->assertStringContainsString('Create a new workspace document', $createTool->description());
        $this->assertStringContainsString('Update an existing workspace document', $updateTool->description());
        $this->assertStringContainsString('Delete a workspace document', $deleteTool->description());
    }
}
