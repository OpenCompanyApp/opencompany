<?php

namespace Tests\Evals\Vfs\Support;

use App\Agents\Tools\System\ApprovalWrappedTool;
use App\Agents\Tools\ToolRegistry;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Agents\Tools\Vfs\Support\VfsOffloadingTool;
use App\Domain\Vfs\Events\VfsOperationContext;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Gateway\DeepSeek\Concerns\MapsTools;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;

/**
 * The model-facing tool set of one eval arm, built from the real app tools.
 *
 * vfs_only: exactly the VFS tools `ToolRegistry::getToolsForAgent()` returns
 * for the agent (permission-filtered, approval-wrapped where the registry
 * wraps), decorated with VfsOffloadingTool like `OpenCompanyAgent::tools()`
 * does. Non-VFS tools (tasks, memory, web, Code Mode) are left out on
 * purpose: this arm measures a VFS-only agent.
 *
 * Schemas: mapped by the same `MapsTools` trait the app's `openai_compat`
 * gateway (DeepSeek driver, OpenCompanyAiProviderFactory) uses, so the model
 * sees the same names (ToolNameResolver: class basenames such as `VfsExec`),
 * descriptions and JSON schemas as in production.
 *
 * Execution of unknown tool names: production's gateway silently skips them
 * (no tool result). The eval returns an explicit error instead, so the
 * conversation stays valid, and counts it (`unknown_tool`).
 */
final class EvalToolbox
{
    /** @var array<string, Tool> name => tool */
    private array $tools = [];

    /** @var array<string, string> name => VFS slug (vfs_exec, ...) */
    private array $slugs = [];

    public function __construct(private readonly User $agent, private readonly string $surface = 'tool')
    {
        foreach (app(ToolRegistry::class)->getToolsForAgent($agent) as $tool) {
            $inner = $tool instanceof ApprovalWrappedTool ? $tool->getInnerTool() : $tool;
            if (! $inner instanceof ReturnsVfsEnvelope || ! defined($inner::class.'::TOOL_SLUG')) {
                continue;
            }
            $wrapped = VfsOffloadingTool::wrapIfVfs($tool, $agent);
            $name = ToolNameResolver::resolve($wrapped);
            $this->tools[$name] = $wrapped;
            $this->slugs[$name] = (string) constant($inner::class.'::TOOL_SLUG');
        }
    }

    /** @return list<array<string, mixed>> OpenAI function definitions */
    public function schemas(): array
    {
        $mapper = new class
        {
            use MapsTools {
                mapTools as public;
            }
        };

        return $mapper->mapTools(array_values($this->tools));
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    public function slugFor(string $name): ?string
    {
        return $this->slugs[$name] ?? null;
    }

    public function nameForSlug(string $slug): ?string
    {
        $name = array_search($slug, $this->slugs, true);

        return $name === false ? null : $name;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Execute one call on the real tool under this arm's audit surface.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function call(string $name, array $arguments): string
    {
        $tool = $this->tools[$name] ?? null;
        if ($tool === null) {
            return json_encode(['ok' => false, 'error' => [
                'code' => 'unknown_tool',
                'message' => "Tool {$name} does not exist. Available tools: ".implode(', ', $this->names()).'.',
            ]]);
        }

        return (string) app(VfsOperationContext::class)->runAs(
            $this->surface,
            fn () => $tool->handle(new Request($arguments)),
        );
    }

    /**
     * Call by VFS slug (scripted writers, bridge forwarding).
     *
     * @param  array<string, mixed>  $arguments
     */
    public function callSlug(string $slug, array $arguments): string
    {
        $name = $this->nameForSlug($slug);
        if ($name === null) {
            return json_encode(['ok' => false, 'error' => ['code' => 'permission_denied', 'message' => "{$slug} is not available to this agent."]]);
        }

        return $this->call($name, $arguments);
    }

    public function agent(): User
    {
        return $this->agent;
    }
}
