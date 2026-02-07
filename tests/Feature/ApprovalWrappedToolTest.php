<?php

namespace Tests\Feature;

use App\Agents\Tools\System\ApprovalWrappedTool;
use App\Agents\Tools\Chat\SendChannelMessage;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ApprovalWrappedToolTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private Channel $channel;
    private SendChannelMessage $innerTool;
    private ApprovalWrappedTool $wrappedTool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create([
            'type' => 'agent',
            'must_wait_for_approval' => false,
        ]);

        $this->channel = Channel::factory()->create();

        $permissionService = new AgentPermissionService();
        $this->innerTool = new SendChannelMessage($this->agent, $permissionService);
        $this->wrappedTool = new ApprovalWrappedTool(
            $this->innerTool,
            $this->agent,
            'send_channel_message',
        );
    }

    public function test_creates_approval_request_instead_of_executing(): void
    {
        $request = new Request([
            'channelId' => $this->channel->id,
            'content' => 'Hello, world!',
        ]);

        $result = $this->wrappedTool->handle($request);

        // Should NOT have created a message (inner tool was not executed)
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseMissing('messages', [
            'content' => 'Hello, world!',
        ]);

        // Should have created a pending ApprovalRequest
        $this->assertDatabaseCount('approval_requests', 1);
        $approval = ApprovalRequest::first();
        $this->assertEquals('pending', $approval->status);
        $this->assertEquals($this->agent->id, $approval->requester_id);
        $this->assertEquals('send_channel_message', $approval->tool_execution_context['tool_slug']);
        $this->assertEquals($this->channel->id, $approval->tool_execution_context['parameters']['channelId']);
        $this->assertEquals('Hello, world!', $approval->tool_execution_context['parameters']['content']);
    }

    public function test_auto_pauses_when_must_wait_for_approval(): void
    {
        $this->agent->update(['must_wait_for_approval' => true]);

        $request = new Request([
            'channelId' => $this->channel->id,
            'content' => 'Needs approval',
        ]);

        $result = $this->wrappedTool->handle($request);

        $this->agent->refresh();
        $approval = ApprovalRequest::first();

        $this->assertNotNull($this->agent->awaiting_approval_id);
        $this->assertEquals($approval->id, $this->agent->awaiting_approval_id);
        $this->assertStringContainsString('waiting', strtolower($result));
    }

    public function test_does_not_auto_pause_when_must_wait_is_false(): void
    {
        $this->agent->update(['must_wait_for_approval' => false]);

        $request = new Request([
            'channelId' => $this->channel->id,
            'content' => 'No auto-pause',
        ]);

        $result = $this->wrappedTool->handle($request);

        $this->agent->refresh();

        $this->assertNull($this->agent->awaiting_approval_id);
        $this->assertStringContainsString('wait_for_approval', $result);
    }

    public function test_stores_channel_id_from_request(): void
    {
        $request = new Request([
            'channelId' => $this->channel->id,
            'content' => 'Channel message',
        ]);

        $this->wrappedTool->handle($request);

        $approval = ApprovalRequest::first();
        $this->assertEquals($this->channel->id, $approval->channel_id);
    }

    public function test_stores_null_channel_when_not_provided(): void
    {
        $request = new Request([
            'query' => 'some search query',
        ]);

        $this->wrappedTool->handle($request);

        $approval = ApprovalRequest::first();
        $this->assertNull($approval->channel_id);
    }

    public function test_description_includes_approval_notice(): void
    {
        $description = $this->wrappedTool->description();

        $this->assertStringContainsString('requires human approval', $description);
    }

    public function test_schema_delegates_to_inner_tool(): void
    {
        $jsonSchema = new JsonSchemaTypeFactory();

        $wrappedSchema = $this->wrappedTool->schema($jsonSchema);
        $innerSchema = $this->innerTool->schema($jsonSchema);

        $this->assertEquals($innerSchema, $wrappedSchema);
    }
}
