<?php

namespace Tests\Unit;

use App\Services\Memory\ContextPruner;
use Illuminate\Support\Collection;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\ToolResult;
use Tests\TestCase;

class ContextPrunerTest extends TestCase
{
    public function test_prunes_older_large_read_results_and_keeps_recent_one(): void
    {
        config([
            'memory.pruning.keep_recent_read_results' => 1,
            'memory.pruning.min_result_tokens' => 10,
            'memory.pruning.min_total_saved_tokens' => 10,
        ]);

        $pruner = app(ContextPruner::class);
        $messages = [
            new ToolResultMessage(new Collection([
                new ToolResult('call-1', 'read_file', ['path' => '/tmp/a.md'], str_repeat('old result ', 100)),
            ])),
            new ToolResultMessage(new Collection([
                new ToolResult('call-2', 'read_file', ['path' => '/tmp/b.md'], str_repeat('recent result ', 100)),
            ])),
        ];

        $result = $pruner->prune($messages);

        $this->assertSame(1, $result['pruned_results']);
        $this->assertStringContainsString('omitted from retry context', $result['messages'][0]->toolResults[0]->result);
        $this->assertStringContainsString('recent result', $result['messages'][1]->toolResults[0]->result);
    }

    public function test_does_not_prune_write_results(): void
    {
        config([
            'memory.pruning.keep_recent_read_results' => 0,
            'memory.pruning.min_result_tokens' => 10,
            'memory.pruning.min_total_saved_tokens' => 10,
        ]);

        $pruner = app(ContextPruner::class);
        $messages = [
            new ToolResultMessage(new Collection([
                new ToolResult('call-1', 'send_channel_message', ['channelId' => 'chan-1'], str_repeat('sent ', 100)),
            ])),
        ];

        $result = $pruner->prune($messages);

        $this->assertSame(0, $result['pruned_results']);
        $this->assertStringNotContainsString('omitted from retry context', $result['messages'][0]->toolResults[0]->result);
    }
}
