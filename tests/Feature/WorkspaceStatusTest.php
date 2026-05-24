<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_endpoint_includes_chat_context_budget_without_message_content(): void
    {
        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent('manager')->create([
            'name' => 'Atlas',
            'brain' => 'openai:gpt-4o',
            'status' => 'idle',
        ]);

        $channel = Channel::factory()->dm()->create();
        $channel->users()->attach([$human->id, $agent->id]);

        Message::factory()->create([
            'id' => (string) Str::uuid(),
            'channel_id' => $channel->id,
            'author_id' => $human->id,
            'content' => 'This is a secret planning detail that should not appear in status.',
        ]);

        Task::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Answer chat request',
            'type' => Task::TYPE_REQUEST,
            'status' => Task::STATUS_COMPLETED,
            'priority' => Task::PRIORITY_NORMAL,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'channel_id' => $channel->id,
            'source' => Task::SOURCE_CHAT,
            'context' => [
                'token_breakdown' => [
                    'context_window' => 128000,
                    'system_prompt' => ['total' => 1000],
                    'volatile_prompt_context' => ['total' => 250],
                    'messages' => ['total' => 500, 'count' => 1],
                    'compaction' => [
                        'threshold' => 90000,
                        'adjusted_tokens' => 600,
                        'remaining' => 89400,
                        'pct_used' => 1,
                    ],
                    'actual_prompt_tokens' => 1750,
                ],
            ],
            'result' => [
                'prompt_tokens' => 1750,
                'completion_tokens' => 120,
                'tool_calls_count' => 0,
            ],
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($human)->getJson("/api/stats/status?channelId={$channel->id}&agentId={$agent->id}");

        $response
            ->assertOk()
            ->assertJsonPath('conversation.channel_id', $channel->id)
            ->assertJsonPath('conversation.agent_id', $agent->id)
            ->assertJsonPath('conversation.model', 'gpt-4o')
            ->assertJsonPath('conversation.context.context_window', 128000)
            ->assertJsonPath('conversation.messages_total', 1)
            ->assertJsonPath('conversation.last_run.token_breakdown.compaction.remaining', 89400)
            ->assertJsonMissing(['content' => 'This is a secret planning detail that should not appear in status.']);
    }
}
