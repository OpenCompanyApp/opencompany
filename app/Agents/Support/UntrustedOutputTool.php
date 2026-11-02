<?php

namespace App\Agents\Support;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;
use Stringable;

/**
 * Model-facing decorator that labels a direct tool's output as untrusted.
 *
 * Owns: wrapping the string a direct (Laravel AI) tool returns to the model in
 * an UntrustedContent block, with the request argument named by $originArgument
 * (e.g. `url`, `query`) as the block origin.
 *
 * Does NOT own: permissions, approvals, cancellation (ApprovalWrappedTool and
 * RunGuardedTool keep those), or the tool's behaviour. Name, description and
 * schema are delegated unchanged so the model-visible contract, checkpoint
 * names, and permission slugs stay identical.
 *
 * Why a decorator instead of wrapping inside the tool: the same tool classes
 * are invoked by Code Mode callbacks through IntegrationRuntime, whose results
 * are structured values consumed by scripts. Labelling must only happen where
 * text enters the model context, so OpenCompanyAgent::tools() applies this
 * decorator and the script path never sees it. Do not "simplify" this into the
 * tool's handle().
 */
final class UntrustedOutputTool implements Tool
{
    public function __construct(
        private Tool $innerTool,
        private string $source,
        private ?string $originArgument = null,
    ) {}

    public function name(): string
    {
        return ToolNameResolver::resolve($this->innerTool);
    }

    public function description(): Stringable|string
    {
        return $this->innerTool->description();
    }

    /**
     * Run the inner tool and label its output.
     *
     * Error strings are labelled too: provider error bodies can echo remote
     * page content. Output that an inner layer already labelled is passed
     * through to avoid nested blocks.
     */
    public function handle(Request $request): Stringable|string
    {
        $output = (string) $this->innerTool->handle($request);

        if ($output === '' || UntrustedContent::isWrapped($output)) {
            return $output;
        }

        $origin = $this->originArgument !== null ? $request[$this->originArgument] ?? null : null;

        return UntrustedContent::wrap($output, $this->source, is_scalar($origin) ? (string) $origin : null);
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return $this->innerTool->schema($schema);
    }

    /** Expose the decorated tool for diagnostics and tests. */
    public function innerTool(): Tool
    {
        return $this->innerTool;
    }
}
