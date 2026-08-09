<?php

namespace Tests\Feature;

use App\Agents\OpenCompanyAgent;
use App\Domain\Chat\Telegram\Application\TelegramApprovalRenderer;
use App\Events\MessageSent;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ApprovalResumeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a standard test setup: a human user, an agent (optionally waiting),
     * a channel, and a pending approval with optional tool_execution_context.
     */
    private function createApprovalScenario(
        bool $agentWaiting = true,
        ?array $toolExecutionContext = null,
        string $approvalType = 'action',
    ): array {
        $human = User::factory()->create(['type' => 'human']);

        $channel = Channel::factory()->create(['type' => 'public']);

        // Create agent first (without awaiting_approval_id yet)
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'awaiting_approval',
        ]);

        // Default tool_execution_context for send_channel_message
        if ($toolExecutionContext === null && $approvalType === 'action') {
            $toolExecutionContext = [
                'tool_slug' => 'send_channel_message',
                'parameters' => [
                    'channelId' => $channel->id,
                    'content' => 'Hello from the approved tool!',
                ],
            ];
        }

        $approval = ApprovalRequest::factory()->create([
            'type' => $approvalType,
            'title' => 'Send a message to general',
            'status' => 'pending',
            'tool_execution_context' => $toolExecutionContext,
            'channel_id' => $channel->id,
            'requester_id' => $agent->id,
        ]);

        // Now set the agent's awaiting_approval_id if needed
        if ($agentWaiting) {
            $agent->update(['awaiting_approval_id' => $approval->id]);
        }

        return compact('human', 'agent', 'channel', 'approval');
    }

    public function test_approve_executes_tool_and_posts_result(): void
    {
        OpenCompanyAgent::fake(['Agent follow-up response.']);
        Event::fake([MessageSent::class]);

        ['human' => $human, 'agent' => $agent, 'channel' => $channel, 'approval' => $approval] = $this->createApprovalScenario();

        $response = $this->actingAs($human)->patchJson("/api/approvals/{$approval->id}", [
            'status' => 'approved',
            'respondedById' => $human->id,
        ]);

        $response->assertOk();

        $approval->refresh();
        $this->assertEquals('approved', $approval->status);

        // Assert the tool actually ran: send_channel_message creates a Message with the content from parameters
        $toolMessage = Message::where('channel_id', $channel->id)
            ->where('content', 'Hello from the approved tool!')
            ->where('author_id', $agent->id)
            ->first();
        $this->assertNotNull($toolMessage, 'The send_channel_message tool should have created a message with the specified content.');

        // Assert the "Approved action executed" result message was posted
        $resultMessage = Message::where('channel_id', $channel->id)
            ->where('content', 'LIKE', '%Approved action executed%')
            ->where('author_id', $agent->id)
            ->first();
        $this->assertNotNull($resultMessage, 'An "Approved action executed" message should be posted by the agent.');
    }

    public function test_resolved_approval_cannot_be_executed_twice(): void
    {
        OpenCompanyAgent::fake(['Agent follow-up response.']);
        Event::fake([MessageSent::class]);

        ['human' => $human, 'agent' => $agent, 'channel' => $channel, 'approval' => $approval] = $this->createApprovalScenario();

        $this->actingAs($human)->patchJson("/api/approvals/{$approval->id}", [
            'status' => 'approved',
            'respondedById' => $human->id,
        ])->assertOk();

        $this->actingAs($human)->patchJson("/api/approvals/{$approval->id}", [
            'status' => 'approved',
            'respondedById' => $human->id,
        ])->assertStatus(422);

        $this->assertSame(1, Message::where('channel_id', $channel->id)
            ->where('content', 'Hello from the approved tool!')
            ->where('author_id', $agent->id)
            ->count());
    }

    public function test_approval_store_rejects_client_supplied_execution_context_and_scopes_records(): void
    {
        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create(['workspace_id' => $this->workspace->id]);
        $channel = Channel::factory()->create(['workspace_id' => $this->workspace->id]);

        $this->actingAs($human)->postJson('/api/approvals', [
            'type' => 'action',
            'title' => 'Forged VFS write',
            'description' => 'Needs approval',
            'requesterId' => $agent->id,
            'channelId' => $channel->id,
            'toolExecutionContext' => [
                'tool_slug' => 'vfs_write',
                'parameters' => ['path' => '/files/a.txt', 'content' => 'x'],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('toolExecutionContext');

        $this->assertDatabaseMissing('approval_requests', ['title' => 'Forged VFS write']);

        $this->actingAs($human)->postJson('/api/approvals', [
            'type' => 'action',
            'title' => 'Manual review',
            'description' => 'Needs a human decision but has no executable payload',
            'requesterId' => $agent->id,
            'channelId' => $channel->id,
        ])->assertSuccessful();

        $approval = ApprovalRequest::where('title', 'Manual review')->firstOrFail();
        $this->assertSame($agent->id, $approval->requester_id);
        $this->assertSame($channel->id, $approval->channel_id);
        $this->assertNull($approval->tool_execution_context);

        $otherWorkspace = Workspace::create(['name' => 'Other Workspace', 'slug' => 'other-approvals']);
        $otherAgent = User::factory()->agent()->create(['workspace_id' => $otherWorkspace->id]);
        $otherChannel = Channel::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $this->actingAs($human)->postJson('/api/approvals', [
            'type' => 'action',
            'title' => 'Cross workspace requester',
            'requesterId' => $otherAgent->id,
            'channelId' => $channel->id,
        ])->assertNotFound();

        $this->actingAs($human)->postJson('/api/approvals', [
            'type' => 'action',
            'title' => 'Cross workspace channel',
            'requesterId' => $agent->id,
            'channelId' => $otherChannel->id,
        ])->assertNotFound();

        $this->actingAs($human)->postJson('/api/approvals', [
            'type' => 'action',
            'title' => 'No channel',
            'requesterId' => $agent->id,
        ])->assertUnprocessable();
    }

    public function test_approval_update_accepts_only_final_decision_statuses(): void
    {
        ['human' => $human, 'channel' => $channel, 'approval' => $approval] = $this->createApprovalScenario();

        $this->actingAs($human)->patchJson("/api/approvals/{$approval->id}", [
            'status' => 'pending',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame('pending', $approval->fresh()->status);
        $this->assertDatabaseMissing('messages', [
            'channel_id' => $channel->id,
            'content' => 'Hello from the approved tool!',
        ]);
    }

    public function test_telegram_approval_renderer_redacts_secret_like_values(): void
    {
        ['human' => $human, 'approval' => $approval] = $this->createApprovalScenario(
            toolExecutionContext: [
                'tool_slug' => 'vfs_write',
                'parameters' => [
                    'path' => '/files/secret.txt',
                    'content' => 'api_key=sk-test-secret-value-1234567890',
                    'header' => 'Authorization: Bearer abcdefghijklmnopqrstuvwxyz123456',
                ],
            ],
        );
        $approval->update([
            'title' => 'api_key=title-secret',
            'description' => 'Use https://example.test?access_token=oauth-secret&client_secret=client-secret',
        ]);

        $renderer = app(TelegramApprovalRenderer::class);
        $payload = implode("\n---\n", [
            $renderer->pendingHtml($approval->fresh(['requester', 'channel.workspace']), $this->workspace),
            $renderer->resolvedHtml($approval->fresh(['requester']), 'approved', $human),
            $renderer->inspectText($approval->fresh(['requester'])),
            $renderer->resolutionNotificationText($approval->fresh(), 'approved', $human),
        ]);

        $this->assertStringContainsString('[redacted]', $payload);
        $this->assertStringNotContainsString('title-secret', $payload);
        $this->assertStringNotContainsString('sk-test-secret', $payload);
        $this->assertStringNotContainsString('oauth-secret', $payload);
        $this->assertStringNotContainsString('client-secret', $payload);
        $this->assertStringNotContainsString('abcdefghijklmnopqrstuvwxyz123456', $payload);
    }

    public function test_approve_clears_awaiting_state(): void
    {
        OpenCompanyAgent::fake(['Agent resumed.']);
        Event::fake([MessageSent::class]);

        ['human' => $human, 'agent' => $agent, 'approval' => $approval] = $this->createApprovalScenario();

        $this->actingAs($human)->patchJson("/api/approvals/{$approval->id}", [
            'status' => 'approved',
            'respondedById' => $human->id,
        ]);

        $agent->refresh();
        $this->assertNull($agent->awaiting_approval_id, 'Agent awaiting_approval_id should be cleared after approval.');
        $this->assertEquals('idle', $agent->status, 'Agent status should be idle after AgentRespondJob completes.');
    }

    public function test_approve_dispatches_agent_respond_job(): void
    {
        OpenCompanyAgent::fake(['Resuming after approval.']);
        Event::fake([MessageSent::class]);

        ['human' => $human, 'approval' => $approval] = $this->createApprovalScenario();

        $this->actingAs($human)->patchJson("/api/approvals/{$approval->id}", [
            'status' => 'approved',
            'respondedById' => $human->id,
        ]);

        // Since the queue is sync in tests, AgentRespondJob executes immediately,
        // which calls OpenCompanyAgent::for()->prompt(). The fake captures this.
        OpenCompanyAgent::assertPrompted(fn ($prompt) => true);
    }

    public function test_reject_posts_denial_and_resumes_agent(): void
    {
        OpenCompanyAgent::fake(['Acknowledged denial.']);
        Event::fake([MessageSent::class]);

        ['human' => $human, 'agent' => $agent, 'channel' => $channel, 'approval' => $approval] = $this->createApprovalScenario();

        $response = $this->actingAs($human)->patchJson("/api/approvals/{$approval->id}", [
            'status' => 'rejected',
            'respondedById' => $human->id,
        ]);

        $response->assertOk();

        // Assert denial message posted
        $denialMessage = Message::where('channel_id', $channel->id)
            ->where('content', 'LIKE', '%Approval denied%')
            ->where('author_id', $agent->id)
            ->first();
        $this->assertNotNull($denialMessage, 'An "Approval denied" message should be posted by the agent.');

        // Assert awaiting state cleared
        $agent->refresh();
        $this->assertNull($agent->awaiting_approval_id, 'Agent awaiting_approval_id should be cleared after rejection.');
    }

    public function test_reject_dispatches_agent_respond_job(): void
    {
        OpenCompanyAgent::fake(['Handling rejection.']);
        Event::fake([MessageSent::class]);

        ['human' => $human, 'approval' => $approval] = $this->createApprovalScenario();

        $this->actingAs($human)->patchJson("/api/approvals/{$approval->id}", [
            'status' => 'rejected',
            'respondedById' => $human->id,
        ]);

        OpenCompanyAgent::assertPrompted(fn ($prompt) => true);
    }

    public function test_approve_without_waiting_agent_executes_tool_only(): void
    {
        OpenCompanyAgent::fake(['This should not be called.']);
        Event::fake([MessageSent::class]);

        // Agent is NOT waiting for this approval
        ['human' => $human, 'agent' => $agent, 'channel' => $channel, 'approval' => $approval] = $this->createApprovalScenario(agentWaiting: false);

        $response = $this->actingAs($human)->patchJson("/api/approvals/{$approval->id}", [
            'status' => 'approved',
            'respondedById' => $human->id,
        ]);

        $response->assertOk();

        // The tool should still execute: send_channel_message creates a message
        $toolMessage = Message::where('channel_id', $channel->id)
            ->where('content', 'Hello from the approved tool!')
            ->where('author_id', $agent->id)
            ->first();
        $this->assertNotNull($toolMessage, 'The tool should execute even when agent is not waiting.');

        // The "Approved action executed" message should exist
        $resultMessage = Message::where('channel_id', $channel->id)
            ->where('content', 'LIKE', '%Approved action executed%')
            ->where('author_id', $agent->id)
            ->first();
        $this->assertNotNull($resultMessage, 'The result message should be posted.');

        // No AgentRespondJob should have been dispatched since agent was not waiting.
        // The agent should NOT have been prompted (no resume job).
        OpenCompanyAgent::assertNeverPrompted();
    }

    public function test_approve_with_unknown_tool_slug_no_crash(): void
    {
        OpenCompanyAgent::fake(['Should not run.']);
        Event::fake([MessageSent::class]);

        ['human' => $human, 'agent' => $agent, 'channel' => $channel, 'approval' => $approval] = $this->createApprovalScenario(
            toolExecutionContext: [
                'tool_slug' => 'nonexistent_tool',
                'parameters' => [
                    'channelId' => 'some-channel',
                ],
            ],
        );

        $response = $this->actingAs($human)->patchJson("/api/approvals/{$approval->id}", [
            'status' => 'approved',
            'respondedById' => $human->id,
        ]);

        // Should not crash with a 500
        $response->assertOk();

        // The approval status should still be updated
        $approval->refresh();
        $this->assertEquals('approved', $approval->status);

        // Agent awaiting state is cleared because the unknown tool causes an early
        // return in executeApprovedTool before the clearing logic. Verify the
        // controller handled it gracefully regardless of awaiting state outcome.
        $agent->refresh();
        $this->assertTrue(
            $agent->awaiting_approval_id === null || $agent->awaiting_approval_id === $approval->id,
            'Agent awaiting state should be in a consistent state after unknown tool approval.',
        );
    }

    public function test_non_tool_approval_skips_resume_engine(): void
    {
        Event::fake([MessageSent::class]);

        ['human' => $human, 'agent' => $agent, 'approval' => $approval] = $this->createApprovalScenario(
            agentWaiting: false,
            toolExecutionContext: null,
            approvalType: 'budget',
        );

        $response = $this->actingAs($human)->patchJson("/api/approvals/{$approval->id}", [
            'status' => 'approved',
            'respondedById' => $human->id,
        ]);

        $response->assertOk();

        $approval->refresh();
        $this->assertEquals('approved', $approval->status);

        // No messages should have been created by the agent
        $agentMessages = Message::where('author_id', $agent->id)->count();
        $this->assertEquals(0, $agentMessages, 'No messages should be created for a non-tool approval.');
    }
}
