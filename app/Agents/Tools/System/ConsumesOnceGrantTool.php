<?php

namespace App\Agents\Tools\System;

use App\Domain\Authorization\Application\PolicyGrantService;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Consumes a single-use (`once`) policy grant when the covered tool executes.
 *
 * ToolRegistry wraps a tool with this decorator only when a live `once` grant
 * is what lets it run without approval. Evaluation happens at registration
 * time, so consumption must happen here, on actual execution — never when the
 * tool list is built.
 */
class ConsumesOnceGrantTool implements Tool
{
    public function __construct(
        private Tool $innerTool,
        private User $agent,
        private string $toolSlug,
    ) {}

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
        try {
            return $this->innerTool->handle($request);
        } finally {
            // Invariant: an attempted execution uses the grant even if the
            // tool failed; a failed external call may already have had effects.
            app(PolicyGrantService::class)->consumeOnce($this->agent, $this->toolSlug);
        }
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
