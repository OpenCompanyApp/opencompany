<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\System\Wait;
use App\Jobs\AgentResumeFromSleepJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class WaitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_sleeping_fields_on_agent(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new Wait($agent);

        $request = new Request([
            'duration' => 30,
            'reason' => 'Waiting for deploy',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('pause', $result);
        $this->assertStringContainsString('Waiting for deploy', $result);

        $agent->refresh();
        $this->assertNotNull($agent->sleeping_until);
        $this->assertEquals('Waiting for deploy', $agent->sleeping_reason);

        // sleeping_until should be approximately 30 minutes from now
        $expectedTime = now()->addMinutes(30);
        $this->assertTrue(
            $agent->sleeping_until->diffInSeconds($expectedTime) < 5,
            'sleeping_until should be approximately 30 minutes from now'
        );
    }

    public function test_dispatches_resume_job(): void
    {
        Queue::fake();

        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new Wait($agent);

        $request = new Request([
            'duration' => 30,
            'reason' => 'Waiting for deploy',
        ]);

        $tool->handle($request);

        Queue::assertPushed(AgentResumeFromSleepJob::class);
    }

    public function test_rejects_invalid_duration(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new Wait($agent);

        // Test duration = 0
        $request = new Request([
            'duration' => 0,
            'reason' => 'Some reason',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Duration must be between 1 and 10080', $result);

        // Test duration = 20000 (exceeds max)
        $request = new Request([
            'duration' => 20000,
            'reason' => 'Some reason',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Duration must be between 1 and 10080', $result);
    }

    public function test_rejects_empty_reason(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new Wait($agent);

        $request = new Request([
            'duration' => 30,
            'reason' => '',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('reason is required', $result);
    }

    public function test_has_correct_description(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new Wait($agent);

        $this->assertStringContainsString('Pause execution', $tool->description());
    }
}
