<?php

namespace App\Agents\Tools\Vfs\Support;

use App\Agents\Tools\System\ApprovalWrappedTool;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;
use Stringable;

/**
 * Model-facing decorator that applies VfsResultOffloader to direct VFS tools.
 *
 * Owns only the size cap of what a direct `vfs_*` call puts into the model
 * context. Name, description and schema are delegated unchanged, so the tool
 * list stays byte-identical with or without it.
 *
 * Why a decorator (same reasoning as UntrustedOutputTool): Code Mode invokes
 * the same tool classes through IntegrationRuntime and must get the complete
 * structured value (it has its own callback result limit). Offloading
 * therefore happens only where text enters the model context:
 * OpenCompanyAgent::tools() wraps VFS tools with this. Do not move it into
 * the tool classes.
 *
 * vfs_read is never wrapped: it is the paging tool the offload hint points
 * at, and its own maxBytes default keeps pages under the cap.
 */
final class VfsOffloadingTool implements Tool
{
    public function __construct(
        private readonly Tool $innerTool,
        private readonly User $agent,
        private readonly string $toolSlug,
    ) {}

    /**
     * Wrap $tool when it is a direct VFS tool (possibly approval-wrapped)
     * whose results may be offloaded; otherwise return it unchanged.
     */
    public static function wrapIfVfs(Tool $tool, User $agent): Tool
    {
        $inner = $tool instanceof ApprovalWrappedTool ? $tool->getInnerTool() : $tool;
        if (! $inner instanceof ReturnsVfsEnvelope || ! defined($inner::class.'::TOOL_SLUG')) {
            return $tool;
        }

        $slug = (string) constant($inner::class.'::TOOL_SLUG');

        return $slug === 'vfs_read' ? $tool : new self($tool, $agent, $slug);
    }

    public function name(): string
    {
        return ToolNameResolver::resolve($this->innerTool);
    }

    public function description(): Stringable|string
    {
        return $this->innerTool->description();
    }

    public function handle(Request $request): string
    {
        $output = (string) $this->innerTool->handle($request);

        return app(VfsResultOffloader::class)->apply($this->agent, $this->toolSlug, $output);
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
