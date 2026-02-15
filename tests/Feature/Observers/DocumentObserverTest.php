<?php

namespace Tests\Feature\Observers;

use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentObserverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_saving_document_dispatches_index_job(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Test Document',
            'content' => 'Some content to index.',
            'is_folder' => false,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Bus::assertDispatched(IndexDocumentJob::class);
    }

    public function test_saving_folder_does_not_dispatch_index_job(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Test Folder',
            'content' => '',
            'is_folder' => true,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Bus::assertNotDispatched(IndexDocumentJob::class);
    }

    public function test_deleting_document_removes_chunks(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        $docId = Str::uuid()->toString();

        $doc = Document::create([
            'id' => $docId,
            'title' => 'Test Document',
            'content' => 'Content here.',
            'is_folder' => false,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        // Manually create a chunk (simulating a completed index job)
        DocumentChunk::create([
            'document_id' => $docId,
            'content' => 'chunk text',
            'content_hash' => hash('sha256', 'chunk text'),
            'embedding' => array_fill(0, 1536, 0.1),
            'collection' => 'general',
            'chunk_index' => 0,
            'workspace_id' => $this->workspace->id,
        ]);

        $this->assertEquals(1, DocumentChunk::where('document_id', $docId)->count());

        $doc->delete();

        $this->assertEquals(0, DocumentChunk::where('document_id', $docId)->count());
    }

    public function test_observer_resolves_memory_collection_from_parent(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        $agentFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'test-agent',
            'is_folder' => true,
            'content' => '',
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $memoryFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'memory',
            'parent_id' => $agentFolder->id,
            'is_folder' => true,
            'content' => '',
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Document::create([
            'id' => Str::uuid()->toString(),
            'title' => '2026-02-13.md',
            'parent_id' => $memoryFolder->id,
            'content' => 'Memory log entry',
            'is_folder' => false,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Bus::assertDispatched(IndexDocumentJob::class, function ($job) {
            $reflection = new \ReflectionClass($job);
            $prop = $reflection->getProperty('collection');

            return $prop->getValue($job) === 'memory';
        });
    }

    public function test_observer_resolves_identity_collection_from_parent(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        $agentFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'test-agent',
            'is_folder' => true,
            'content' => '',
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $identityFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'identity',
            'parent_id' => $agentFolder->id,
            'is_folder' => true,
            'content' => '',
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'IDENTITY.md',
            'parent_id' => $identityFolder->id,
            'content' => 'Agent identity content',
            'is_folder' => false,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Bus::assertDispatched(IndexDocumentJob::class, function ($job) {
            $reflection = new \ReflectionClass($job);
            $prop = $reflection->getProperty('collection');

            return $prop->getValue($job) === 'identity';
        });
    }

    public function test_observer_resolves_general_collection_for_root_docs(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'root-doc.md',
            'content' => 'Root level document',
            'is_folder' => false,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Bus::assertDispatched(IndexDocumentJob::class, function ($job) {
            $reflection = new \ReflectionClass($job);
            $prop = $reflection->getProperty('collection');

            return $prop->getValue($job) === 'general';
        });
    }

    public function test_observer_resolves_agent_id_from_hierarchy(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        $agent = User::factory()->agent()->create(['name' => 'test-agent']);

        $agentsFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'agents',
            'is_folder' => true,
            'content' => '',
            'parent_id' => null,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $agentFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'test-agent',
            'parent_id' => $agentsFolder->id,
            'is_folder' => true,
            'content' => '',
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $memoryFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'memory',
            'parent_id' => $agentFolder->id,
            'is_folder' => true,
            'content' => '',
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Document::create([
            'id' => Str::uuid()->toString(),
            'title' => '2026-02-13.md',
            'parent_id' => $memoryFolder->id,
            'content' => 'Memory entry',
            'is_folder' => false,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Bus::assertDispatched(IndexDocumentJob::class, function ($job) use ($agent) {
            $reflection = new \ReflectionClass($job);
            $prop = $reflection->getProperty('agentId');

            return $prop->getValue($job) === $agent->id;
        });
    }

    public function test_observer_returns_null_agent_for_non_agent_docs(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        $randomFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'random-folder',
            'is_folder' => true,
            'content' => '',
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'some-doc.md',
            'parent_id' => $randomFolder->id,
            'content' => 'Not under agents/',
            'is_folder' => false,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Bus::assertDispatched(IndexDocumentJob::class, function ($job) {
            $reflection = new \ReflectionClass($job);
            $prop = $reflection->getProperty('agentId');

            return $prop->getValue($job) === null;
        });
    }

    public function test_observer_agent_slug_is_case_insensitive(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        $agent = User::factory()->agent()->create(['name' => 'Test Agent']);

        $agentsFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'agents',
            'is_folder' => true,
            'content' => '',
            'parent_id' => null,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $agentFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'test-agent',
            'parent_id' => $agentsFolder->id,
            'is_folder' => true,
            'content' => '',
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'doc.md',
            'parent_id' => $agentFolder->id,
            'content' => 'Agent doc',
            'is_folder' => false,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Bus::assertDispatched(IndexDocumentJob::class, function ($job) use ($agent) {
            $reflection = new \ReflectionClass($job);
            $prop = $reflection->getProperty('agentId');

            return $prop->getValue($job) === $agent->id;
        });
    }

    public function test_observer_skips_non_agent_users_in_slug_match(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        // Create a non-agent user with a matching slug
        User::factory()->create(['name' => 'human-user']);

        $agentsFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'agents',
            'is_folder' => true,
            'content' => '',
            'parent_id' => null,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $userFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'human-user',
            'parent_id' => $agentsFolder->id,
            'is_folder' => true,
            'content' => '',
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'doc.md',
            'parent_id' => $userFolder->id,
            'content' => 'Should not match human user',
            'is_folder' => false,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Bus::assertDispatched(IndexDocumentJob::class, function ($job) {
            $reflection = new \ReflectionClass($job);
            $prop = $reflection->getProperty('agentId');

            return $prop->getValue($job) === null;
        });
    }

    public function test_updating_document_dispatches_index_job(): void
    {
        Bus::fake([IndexDocumentJob::class]);

        $doc = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Test Document',
            'content' => 'Original content.',
            'is_folder' => false,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);

        Bus::assertDispatched(IndexDocumentJob::class);

        // Reset and update
        Bus::fake([IndexDocumentJob::class]);

        $doc->update(['content' => 'Updated content.']);

        Bus::assertDispatched(IndexDocumentJob::class);
    }
}
