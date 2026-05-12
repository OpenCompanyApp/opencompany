<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use OpenCompany\PrismRelay\Bridge\ToolAwarePrismMessages;
use Prism\Prism\ValueObjects\Messages\AssistantMessage as PrismAssistantMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage as PrismToolResultMessage;
use Tests\TestCase;

/**
 * Covers conversion of OpenCompany chat messages into Prism-compatible messages.
 */
class PrismMessagesTest extends TestCase
{
    public function test_from_laravel_messages_preserves_assistant_tool_calls_and_tool_results(): void
    {
        $messages = ToolAwarePrismMessages::fromLaravelMessages(new Collection([
            new AssistantMessage(
                '',
                toolCalls: collect([
                    new ToolCall(
                        id: 'call-1',
                        name: 'read_file',
                        arguments: ['path' => '/tmp/test.md'],
                    ),
                ]),
            ),
            new ToolResultMessage(collect([
                new ToolResult(
                    id: 'call-1',
                    name: 'read_file',
                    arguments: ['path' => '/tmp/test.md'],
                    result: 'file contents',
                ),
            ])),
        ]));

        $this->assertCount(2, $messages);
        $this->assertInstanceOf(PrismAssistantMessage::class, $messages[0]);
        $this->assertSame('read_file', $messages[0]->toolCalls[0]->name);
        $this->assertInstanceOf(PrismToolResultMessage::class, $messages[1]);
        $this->assertSame('file contents', $messages[1]->toolResults[0]->result);
    }
}
