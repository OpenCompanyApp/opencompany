<?php

namespace Tests\Feature\Governance;

use App\Agents\Tools\System\ApprovalWrappedTool;
use App\Domain\Authorization\Application\ApprovalResolutionDenied;
use App\Models\AppSetting;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\ApprovalExecutionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Invariant (pillar 5): agents never approve anything — not by resolving an
 * approval, and not by supplying an argument that waives one. Regression: a
 * wrapped tool auto-ran when the agent passed an `amount`/`price` below the
 * workspace budget threshold.
 */
class AgentsNeverApproveTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_agent_supplied_amount_never_waives_approval(): void
    {
        AppSetting::setValue('budget_approval_threshold', 100);
        $agent = User::factory()->agent()->create();
        $inner = new class implements Tool
        {
            public bool $ran = false;

            public function description(): string
            {
                return 'pay';
            }

            public function handle(Request $request): string
            {
                $this->ran = true;

                return 'paid';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }
        };

        $result = (new ApprovalWrappedTool($inner, $agent, 'mcp_pay__charge'))->handle(new Request(['price' => 0]));

        $this->assertFalse($inner->ran);
        $this->assertStringContainsString('requires human approval', $result);
        $this->assertSame('pending', ApprovalRequest::query()->sole()->status);
    }

    public function test_agents_cannot_resolve_approvals(): void
    {
        $agent = User::factory()->agent()->create();
        $approval = ApprovalRequest::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'workspace_id' => $this->workspace->id, 'type' => 'action',
            'title' => 't', 'description' => 'd', 'requester_id' => User::factory()->agent()->create()->id, 'status' => 'pending']);

        try {
            app(ApprovalExecutionService::class)->resolve($approval, 'approved', $agent);
            $this->fail('An agent resolved an approval.');
        } catch (ApprovalResolutionDenied) {
            $this->assertSame('pending', $approval->fresh()->status);
        }
    }
}
