<?php

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Message;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use Illuminate\Support\Str;

it('renders a persisted chat answer and its task run history', function () {
    $user = User::factory()->create(['name' => 'Browser User']);
    $agent = User::factory()->agent('manager')->create(['id' => 'a-browser', 'name' => 'Atlas']);
    $workspace = app('currentWorkspace');

    $this->actingAs($user);

    $channel = Channel::create([
        'id' => 'browser-chat',
        'workspace_id' => $workspace->id,
        'name' => 'browser-chat',
        'type' => 'public',
        'creator_id' => $user->id,
    ]);

    ChannelMember::create([
        'channel_id' => $channel->id,
        'user_id' => $user->id,
        'role' => 'admin',
        'joined_at' => now(),
    ]);

    $requestMessage = Message::create([
        'id' => Str::uuid()->toString(),
        'channel_id' => $channel->id,
        'author_id' => $user->id,
        'content' => '@Atlas browser QA prompt',
        'timestamp' => now()->subMinute(),
        'source' => 'app',
    ]);

    Message::create([
        'id' => Str::uuid()->toString(),
        'channel_id' => $channel->id,
        'author_id' => $agent->id,
        'content' => 'QA_PEST_BROWSER_CHAT_OK',
        'timestamp' => now(),
        'source' => 'agent',
    ]);

    $task = Task::create([
        'id' => Str::uuid()->toString(),
        'workspace_id' => $workspace->id,
        'title' => 'Browser QA chat response',
        'description' => 'Browser test seeded completed agent response.',
        'type' => Task::TYPE_REQUEST,
        'status' => Task::STATUS_COMPLETED,
        'priority' => Task::PRIORITY_NORMAL,
        'agent_id' => $agent->id,
        'requester_id' => $user->id,
        'channel_id' => $channel->id,
        'trigger_message_id' => $requestMessage->id,
        'source' => Task::SOURCE_CHAT,
        'context' => [
            'model' => 'browser-test-model',
            'provider' => 'browser-test-provider',
            'messages' => [['role' => 'user', 'content' => '@Atlas browser QA prompt']],
            'token_breakdown' => [
                'context_window' => 128000,
                'system_prompt' => [
                    'total' => 100,
                    'sections' => [['label' => 'Identity', 'chars' => 400]],
                ],
                'messages' => ['total' => 20, 'count' => 1],
                'output_reserve' => 4096,
                'compaction' => [
                    'threshold' => 0.75,
                    'adjusted_tokens' => 120,
                    'remaining' => 95880,
                    'pct_used' => 1,
                ],
            ],
        ],
        'result' => [
            'response' => 'QA_PEST_BROWSER_CHAT_OK',
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 8],
            'generation_time_ms' => 250,
            'tokens_per_second' => 32,
        ],
        'started_at' => now()->subSeconds(2),
        'completed_at' => now(),
    ]);

    TaskStep::create([
        'id' => Str::uuid()->toString(),
        'task_id' => $task->id,
        'description' => 'Generating response',
        'status' => TaskStep::STATUS_COMPLETED,
        'step_type' => TaskStep::TYPE_ACTION,
        'metadata' => ['duration_ms' => 250],
        'started_at' => now()->subSecond(),
        'completed_at' => now(),
    ]);

    $this->visit('/w/test/chat')
        ->wait(0.5)
        ->assertSee('browser-chat')
        ->assertSee('QA_PEST_BROWSER_CHAT_OK');

    $this->visit("/w/test/tasks/{$task->id}")
        ->waitForText('Browser QA chat response', 10)
        ->assertSee('Browser QA chat response')
        ->assertSee('Completed')
        ->assertSee('Execution Trace')
        ->assertSee('Generating response')
        ->assertSee('Output')
        ->assertSee('QA_PEST_BROWSER_CHAT_OK')
        ->assertSee('LLM Context');
});
