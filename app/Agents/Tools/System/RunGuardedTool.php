<?php

namespace App\Agents\Tools\System;

use App\Agents\Runtime\ActiveAgentRun;
use App\Domain\AgentRuntime\Application\IdempotentToolExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Adds cooperative cancellation and heartbeats to every runtime-exposed tool.
 *
 * The wrapper does not own permissions or approvals; those remain on the inner
 * tool (including ApprovalWrappedTool). It only enforces the active logical run
 * boundary immediately before and after a potentially mutating tool call.
 */
class RunGuardedTool implements Tool
{
    private ?string $toolInvocationId = null;

    private ?int $toolSequence = null;

    public function __construct(private Tool $innerTool) {}

    public function name(): string
    {
        return method_exists($this->innerTool, 'name')
            ? $this->innerTool->name()
            : class_basename($this->innerTool);
    }

    public function description(): Stringable|string
    {
        return $this->innerTool->description();
    }

    public function handle(Request $request): Stringable|string
    {
        $run = app(ActiveAgentRun::class);
        $sequence = $this->toolSequence ?? $run->nextProviderToolSequence();

        try {
            return app(IdempotentToolExecutor::class)->execute(
                $run,
                'provider',
                $sequence,
                $this->name(),
                $request->all(),
                fn (): Stringable|string => $this->innerTool->handle($request),
                $this->toolInvocationId,
            );
        } finally {
            // Tool objects can be reused for later calls in the same provider
            // turn. Invocation metadata is valid for exactly one handle call.
            $this->toolInvocationId = null;
            $this->toolSequence = null;
        }
    }

    /**
     * Called by the pre-invocation listener while the SDK invocation ID and
     * provider-wide sequence are still available.
     */
    public function prepareInvocation(string $toolInvocationId, int $sequence): void
    {
        $this->toolInvocationId = $toolInvocationId;
        $this->toolSequence = $sequence;
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return $this->innerTool->schema($schema);
    }

    public function innerTool(): Tool
    {
        return $this->innerTool;
    }
}
