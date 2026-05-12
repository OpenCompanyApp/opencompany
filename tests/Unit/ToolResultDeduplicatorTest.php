<?php

namespace Tests\Unit;

use App\Services\Memory\ToolResultDeduplicator;
use Illuminate\Support\Collection;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\ToolResult;
use Tests\TestCase;

/**
 * Covers duplicate tool-result collapse before model context assembly.
 */
class ToolResultDeduplicatorTest extends TestCase
{
    public function test_supersedes_exact_duplicate_results(): void
    {
        $deduplicator = app(ToolResultDeduplicator::class);

        $messages = [
            new ToolResultMessage(new Collection([
                new ToolResult(
                    id: 'call-1',
                    name: 'read_file',
                    arguments: ['path' => '/tmp/example.txt'],
                    result: 'hello world',
                ),
            ])),
            new ToolResultMessage(new Collection([
                new ToolResult(
                    id: 'call-2',
                    name: 'read_file',
                    arguments: ['path' => '/tmp/example.txt'],
                    result: 'hello world',
                ),
            ])),
        ];

        $result = $deduplicator->deduplicate($messages);

        $this->assertSame(1, $result['deduplicated']);
        $this->assertStringContainsString(
            '[Superseded',
            $result['messages'][0]->toolResults[0]->result
        );
        $this->assertSame('hello world', $result['messages'][1]->toolResults[0]->result);
    }

    public function test_does_not_supersede_write_tool_results(): void
    {
        $deduplicator = app(ToolResultDeduplicator::class);

        $messages = [
            new ToolResultMessage(new Collection([
                new ToolResult(
                    id: 'call-1',
                    name: 'send_channel_message',
                    arguments: ['channelId' => 'chan-1', 'message' => 'hello world'],
                    result: 'Message sent successfully.',
                ),
            ])),
            new ToolResultMessage(new Collection([
                new ToolResult(
                    id: 'call-2',
                    name: 'send_channel_message',
                    arguments: ['channelId' => 'chan-1', 'message' => 'hello world'],
                    result: 'Message sent successfully.',
                ),
            ])),
        ];

        $result = $deduplicator->deduplicate($messages);

        $this->assertSame(0, $result['deduplicated']);
        $this->assertSame('Message sent successfully.', $result['messages'][0]->toolResults[0]->result);
        $this->assertSame('Message sent successfully.', $result['messages'][1]->toolResults[0]->result);
    }
}
