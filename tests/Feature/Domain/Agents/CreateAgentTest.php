<?php

namespace Tests\Feature\Domain\Agents;

use App\Domain\Agents\Application\CreateAgent;
use App\Domain\Agents\Application\CreateAgentInput;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\DirectMessage;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_a_runnable_agent_with_identity_docs_and_default_dm(): void
    {
        $creator = User::factory()->create(['type' => 'human']);
        Channel::create([
            'id' => 'general-channel',
            'workspace_id' => $this->workspace->id,
            'name' => 'general',
            'type' => 'public',
        ]);

        $agent = app(CreateAgent::class)->handle(
            $this->workspace,
            $creator,
            new CreateAgentInput(
                name: 'Atlas',
                agentType: 'coder',
                brain: 'anthropic:claude-sonnet-4-5-20250929',
                identity: [
                    'IDENTITY' => "# Identity\n\nName: Atlas",
                    'INSTRUCTIONS' => 'Be precise.',
                    'MEMORY' => 'Initial memory.',
                ],
            ),
        );

        $this->assertEquals('agent', $agent->type);
        $this->assertEquals($this->workspace->id, $agent->workspace_id);
        $this->assertEquals($creator->id, $agent->manager_id);
        $this->assertNotNull($agent->docs_folder_id);

        $this->assertDatabaseHas('documents', [
            'id' => $agent->docs_folder_id,
            'workspace_id' => $this->workspace->id,
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('documents', [
            'title' => 'IDENTITY.md',
            'author_id' => $agent->id,
            'content' => "# Identity\n\nName: Atlas",
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('documents', [
            'title' => 'MEMORY.md',
            'author_id' => $agent->id,
            'content' => 'Initial memory.',
            'is_system' => true,
        ]);

        $dm = DirectMessage::query()
            ->where('user1_id', $creator->id)
            ->where('user2_id', $agent->id)
            ->first();

        $this->assertNotNull($dm);
        $this->assertTrue(ChannelMember::where('channel_id', $dm->channel_id)->where('user_id', $creator->id)->exists());
        $this->assertTrue(ChannelMember::where('channel_id', $dm->channel_id)->where('user_id', $agent->id)->exists());
        $this->assertTrue(ChannelMember::where('channel_id', 'general-channel')->where('user_id', $agent->id)->exists());
    }

    public function test_brain_validation_happens_before_side_effects(): void
    {
        $creator = User::factory()->create(['type' => 'human']);

        try {
            app(CreateAgent::class)->handle(
                $this->workspace,
                $creator,
                new CreateAgentInput(
                    name: 'Bad Brain',
                    agentType: 'coder',
                    brain: 'missing-provider:model',
                ),
            );
        } catch (\InvalidArgumentException) {
            //
        }

        $this->assertDatabaseMissing('users', [
            'name' => 'Bad Brain',
            'type' => 'agent',
        ]);
        $this->assertFalse(Document::where('title', 'Bad Brain')->exists());
    }
}
