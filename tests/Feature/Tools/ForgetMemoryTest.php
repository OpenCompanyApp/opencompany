<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Memory\ForgetMemory;
use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\User;
use App\Services\AgentDocumentService;
use App\Services\Memory\DocumentIndexingService;
use App\Services\Memory\MemoryScopeGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Ai\Tools\Request;
use Mockery;
use Tests\TestCase;

class ForgetMemoryTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([IndexDocumentJob::class]);

        $this->agent = User::factory()->agent()->create(['name' => 'test-agent']);
    }

    private function setupStructure(): AgentDocumentService
    {
        $docService = app(AgentDocumentService::class);
        $agentFolder = $docService->createAgentDocumentStructure($this->agent);
        $this->agent->update(['docs_folder_id' => $agentFolder->id]);

        return $docService;
    }

    public function test_forget_topic_deletes_file(): void
    {
        $docService = $this->setupStructure();
        $docService->saveMemoryTopic($this->agent, 'vue3-migration', 'Content about Vue 3.');

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('deindex')->once();

        $tool = new ForgetMemory($this->agent, $docService, $indexer);
        $result = $tool->handle(new Request([
            'target' => 'topic',
            'topic' => 'vue3-migration',
        ]));

        $this->assertStringContainsString("forgotten", $result);
        $this->assertNull($docService->getMemoryTopicFile($this->agent, 'vue3-migration'));
    }

    public function test_forget_topic_not_found(): void
    {
        $docService = $this->setupStructure();
        $indexer = Mockery::mock(DocumentIndexingService::class);

        $tool = new ForgetMemory($this->agent, $docService, $indexer);
        $result = $tool->handle(new Request([
            'target' => 'topic',
            'topic' => 'nonexistent',
        ]));

        $this->assertStringContainsString('not found', $result);
        $this->assertStringContainsString('nothing to forget', $result);
    }

    public function test_forget_peer_deletes_file(): void
    {
        $docService = $this->setupStructure();
        $peerUser = User::factory()->create(['name' => 'Rutger']);
        $docService->savePeerMemory($this->agent, $peerUser->id, 'user', 'Birthday March 15.');

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('deindex')->once();

        $tool = new ForgetMemory($this->agent, $docService, $indexer);
        $result = $tool->handle(new Request([
            'target' => 'peer',
            'peer_id' => $peerUser->id,
            'peer_type' => 'user',
        ]));

        $this->assertStringContainsString('forgotten', $result);
        $this->assertStringContainsString('Rutger', $result);
        $this->assertNull($docService->getPeerMemory($this->agent, $peerUser->id, 'user'));
    }

    public function test_forget_peer_not_found(): void
    {
        $docService = $this->setupStructure();
        $peerUser = User::factory()->create(['name' => 'Nobody']);
        $indexer = Mockery::mock(DocumentIndexingService::class);

        $tool = new ForgetMemory($this->agent, $docService, $indexer);
        $result = $tool->handle(new Request([
            'target' => 'peer',
            'peer_id' => $peerUser->id,
            'peer_type' => 'user',
        ]));

        $this->assertStringContainsString('nothing to forget', $result);
    }

    public function test_forget_requires_target(): void
    {
        $docService = Mockery::mock(AgentDocumentService::class);
        $indexer = Mockery::mock(DocumentIndexingService::class);

        $tool = new ForgetMemory($this->agent, $docService, $indexer);
        $result = $tool->handle(new Request([]));

        $this->assertStringContainsString('Error', $result);
    }

    public function test_forget_denied_in_group_channel(): void
    {
        $scopeGuard = Mockery::mock(MemoryScopeGuard::class);
        $scopeGuard->shouldReceive('canUseMemoryTools')
            ->once()
            ->andReturn(false);
        $scopeGuard->shouldReceive('denialMessage')
            ->once()
            ->with('forget_memory')
            ->andReturn('Memory tools are not available in group channels.');

        $docService = Mockery::mock(AgentDocumentService::class);
        $indexer = Mockery::mock(DocumentIndexingService::class);

        $tool = new ForgetMemory($this->agent, $docService, $indexer, $scopeGuard, 'public-channel-id');
        $result = $tool->handle(new Request([
            'target' => 'topic',
            'topic' => 'test',
        ]));

        $this->assertStringContainsString('not available', $result);
    }
}
