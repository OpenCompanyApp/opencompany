<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentCommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AgentCommunicationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AgentCommunicationService $service;
    private User $agentA;
    private User $agentB;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->service = app(AgentCommunicationService::class);
        $this->agentA = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);
        $this->agentB = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);
    }

    protected function tearDown(): void
    {
        AgentCommunicationService::resetDepth();

        parent::tearDown();
    }

    // ── getOrCreateDmChannel Tests ────────────────────────────────────

    public function test_get_or_create_dm_channel_creates_new_dm(): void
    {
        $channelId = $this->service->getOrCreateDmChannel($this->agentA, $this->agentB);

        $this->assertNotEmpty($channelId);

        $channel = Channel::find($channelId);
        $this->assertNotNull($channel);
        $this->assertEquals('dm', $channel->type);

        $this->assertDatabaseHas('channel_members', [
            'channel_id' => $channelId,
            'user_id' => $this->agentA->id,
        ]);
        $this->assertDatabaseHas('channel_members', [
            'channel_id' => $channelId,
            'user_id' => $this->agentB->id,
        ]);
        $this->assertEquals(2, ChannelMember::where('channel_id', $channelId)->count());

        $this->assertEquals(1, DirectMessage::where('channel_id', $channelId)->count());
    }

    public function test_get_or_create_dm_channel_returns_existing_dm(): void
    {
        $firstId = $this->service->getOrCreateDmChannel($this->agentA, $this->agentB);
        $secondId = $this->service->getOrCreateDmChannel($this->agentA, $this->agentB);

        $this->assertEquals($firstId, $secondId);
        $this->assertEquals(1, DirectMessage::count());
    }

    public function test_get_or_create_dm_channel_is_bidirectional(): void
    {
        $channelAB = $this->service->getOrCreateDmChannel($this->agentA, $this->agentB);
        $channelBA = $this->service->getOrCreateDmChannel($this->agentB, $this->agentA);

        $this->assertEquals($channelAB, $channelBA);
    }

    // ── postMessage Tests ─────────────────────────────────────────────

    public function test_post_message_creates_message_and_updates_timestamps(): void
    {
        $channelId = $this->service->getOrCreateDmChannel($this->agentA, $this->agentB);

        $message = $this->service->postMessage($channelId, $this->agentA, 'Hello agent B');

        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals('Hello agent B', $message->content);
        $this->assertEquals($channelId, $message->channel_id);
        $this->assertEquals($this->agentA->id, $message->author_id);
        $this->assertEquals('agent_contact', $message->source);

        $channel = Channel::find($channelId);
        $this->assertNotNull($channel->last_message_at);

        $dm = DirectMessage::where('channel_id', $channelId)->first();
        $this->assertNotNull($dm->last_message_at);
    }

    // ── formatRequestMessage Tests ────────────────────────────────────

    public function test_format_request_message_ask(): void
    {
        $result = $this->service->formatRequestMessage(
            'ask',
            $this->agentA,
            'What is the status?',
            null,
            null,
            null,
        );

        $this->assertStringContainsString("[Agent Request from {$this->agentA->name} | Pattern: ask]", $result);
        $this->assertStringContainsString('What is the status?', $result);
    }

    public function test_format_request_message_delegate_with_priority_and_task(): void
    {
        $result = $this->service->formatRequestMessage(
            'delegate',
            $this->agentA,
            'Please handle this',
            null,
            'high',
            'task-abc-123',
        );

        $this->assertStringContainsString('Priority: high', $result);
        $this->assertStringContainsString('Task: task-abc-123', $result);
        $this->assertStringContainsString('Pattern: delegate', $result);
    }

    public function test_format_request_message_delegate_with_context(): void
    {
        $result = $this->service->formatRequestMessage(
            'delegate',
            $this->agentA,
            'Do the work',
            'Some extra context here',
            null,
            null,
        );

        $this->assertStringContainsString('Context: Some extra context here', $result);
        $this->assertStringContainsString('Do the work', $result);
    }

    public function test_format_request_message_notify(): void
    {
        $result = $this->service->formatRequestMessage(
            'notify',
            $this->agentA,
            'Task completed successfully',
            null,
            null,
            null,
        );

        $this->assertStringContainsString("[Agent Notification from {$this->agentA->name}]", $result);
        $this->assertStringContainsString('Task completed successfully', $result);
    }

    // ── formatResultMessage Tests ─────────────────────────────────────

    public function test_format_result_message(): void
    {
        $result = $this->service->formatResultMessage(
            $this->agentA,
            'task-xyz-789',
            'The work is done',
        );

        $this->assertStringContainsString("[Delegation Result from {$this->agentA->name} | task-xyz-789]", $result);
        $this->assertStringContainsString('The work is done', $result);
    }

    // ── Depth Tracking Tests ──────────────────────────────────────────

    public function test_depth_tracking(): void
    {
        AgentCommunicationService::incrementDepth();
        AgentCommunicationService::incrementDepth();
        $this->assertEquals(2, AgentCommunicationService::currentDepth());

        AgentCommunicationService::decrementDepth();
        $this->assertEquals(1, AgentCommunicationService::currentDepth());

        AgentCommunicationService::resetDepth();
        $this->assertEquals(0, AgentCommunicationService::currentDepth());
    }

    public function test_depth_decrement_floors_at_zero(): void
    {
        AgentCommunicationService::resetDepth();
        AgentCommunicationService::decrementDepth();

        $this->assertEquals(0, AgentCommunicationService::currentDepth());
    }

    // ── User Delegation Helper Tests ──────────────────────────────────

    public function test_add_awaiting_delegation(): void
    {
        $this->agentA->addAwaitingDelegation('task-001');
        $this->agentA->refresh();

        $this->assertContains('task-001', $this->agentA->awaiting_delegation_ids);
    }

    public function test_add_awaiting_delegation_deduplicates(): void
    {
        $this->agentA->addAwaitingDelegation('task-001');
        $this->agentA->addAwaitingDelegation('task-001');
        $this->agentA->refresh();

        $this->assertCount(1, $this->agentA->awaiting_delegation_ids);
    }

    public function test_remove_awaiting_delegation(): void
    {
        $this->agentA->addAwaitingDelegation('task-001');
        $this->agentA->addAwaitingDelegation('task-002');
        $this->agentA->removeAwaitingDelegation('task-001');
        $this->agentA->refresh();

        $this->assertNotContains('task-001', $this->agentA->awaiting_delegation_ids);
        $this->assertContains('task-002', $this->agentA->awaiting_delegation_ids);
    }

    public function test_remove_sets_null_when_empty(): void
    {
        $this->agentA->addAwaitingDelegation('task-001');
        $this->agentA->removeAwaitingDelegation('task-001');
        $this->agentA->refresh();

        $this->assertNull($this->agentA->awaiting_delegation_ids);
    }

    public function test_is_awaiting_delegation(): void
    {
        $this->assertFalse($this->agentA->isAwaitingDelegation());

        $this->agentA->addAwaitingDelegation('task-001');

        $this->assertTrue($this->agentA->isAwaitingDelegation());
    }
}
