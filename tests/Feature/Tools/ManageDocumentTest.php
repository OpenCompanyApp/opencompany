<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Docs\ManageDocument;
use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ManageDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function makeTool(?User $agent = null): array
    {
        $agent = $agent ?? User::factory()->create(['type' => 'agent']);
        $tool = new ManageDocument($agent, app(AgentPermissionService::class));

        return [$tool, $agent];
    }

    public function test_creates_document(): void
    {
        [$tool, $agent] = $this->makeTool();

        $request = new Request([
            'action' => 'create',
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
        [$tool, $agent] = $this->makeTool();

        $document = Document::factory()->create(['author_id' => $agent->id]);

        $request = new Request([
            'action' => 'update',
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
        [$tool, $agent] = $this->makeTool();

        $document = Document::factory()->create(['author_id' => $agent->id]);
        $documentId = $document->id;

        $request = new Request([
            'action' => 'delete',
            'documentId' => $documentId,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('deleted', $result);
        $this->assertNull(Document::find($documentId));
    }

    public function test_creates_folder(): void
    {
        [$tool, $agent] = $this->makeTool();

        $request = new Request([
            'action' => 'create',
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

    public function test_has_correct_description(): void
    {
        [$tool] = $this->makeTool();

        $this->assertStringContainsString('Create, update, or delete', $tool->description());
    }
}
