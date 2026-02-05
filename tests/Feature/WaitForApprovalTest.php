<?php

namespace Tests\Feature;

use App\Agents\Tools\WaitForApproval;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class WaitForApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'awaiting_approval_id' => null,
        ]);
    }

    public function test_sets_awaiting_approval_id_on_agent(): void
    {
        $approval = ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => 'action',
            'title' => 'Test',
            'description' => 'Test',
            'requester_id' => $this->agent->id,
            'status' => 'pending',
        ]);

        $tool = new WaitForApproval($this->agent);
        $result = $tool->handle(new Request(['approvalId' => $approval->id]));

        $this->agent->refresh();

        $this->assertEquals($approval->id, $this->agent->awaiting_approval_id);
        $this->assertStringContainsStringIgnoringCase('pause', $result);
    }

    public function test_error_for_nonexistent_approval(): void
    {
        $tool = new WaitForApproval($this->agent);
        $result = $tool->handle(new Request(['approvalId' => 'nonexistent']));

        $this->agent->refresh();

        $this->assertStringContainsString('not found', $result);
        $this->assertNull($this->agent->awaiting_approval_id);
    }

    public function test_error_for_already_resolved_approval(): void
    {
        $approval = ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => 'action',
            'title' => 'Test',
            'description' => 'Test',
            'requester_id' => $this->agent->id,
            'status' => 'approved',
        ]);

        $tool = new WaitForApproval($this->agent);
        $result = $tool->handle(new Request(['approvalId' => $approval->id]));

        $this->assertStringContainsString('already approved', $result);
    }

    public function test_error_when_approval_belongs_to_other_agent(): void
    {
        $otherAgent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);

        $approval = ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => 'action',
            'title' => 'Test',
            'description' => 'Test',
            'requester_id' => $otherAgent->id,
            'status' => 'pending',
        ]);

        $tool = new WaitForApproval($this->agent);
        $result = $tool->handle(new Request(['approvalId' => $approval->id]));

        $this->assertStringContainsString('not requested by you', $result);
    }

    public function test_has_correct_schema_and_description(): void
    {
        $tool = new WaitForApproval($this->agent);

        $schema = $tool->schema(new JsonSchemaTypeFactory());

        $this->assertArrayHasKey('approvalId', $schema);

        $description = $tool->description();
        $this->assertTrue(
            str_contains(strtolower($description), 'pause') && str_contains(strtolower($description), 'wait'),
            'Description should contain "pause" and "wait".'
        );
    }
}
