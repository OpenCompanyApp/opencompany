<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use Laravel\Ai\Tools\Request;

class LuaBridge
{
    /** @var array<string, string> path => toolSlug */
    private array $functionMap;

    /** @var list<array{path: string, durationMs: float, status: string, error?: string}> */
    private array $callLog = [];

    public function __construct(
        private User $agent,
        private ToolRegistry $registry,
        LuaApiDocGenerator $docGenerator,
    ) {
        $this->functionMap = $docGenerator->buildFunctionMap($agent);
    }

    /**
     * Handle a Lua app.* function call by routing it to the corresponding PHP tool.
     *
     * Called from Lua via: app.chat.send_channel_message({channelId = "abc", content = "hi"})
     * Which routes through metatables to: __app.call("chat.send_channel_message", {channelId = "abc", ...})
     */
    public function call(string $path, mixed ...$args): string
    {
        if (!isset($this->functionMap[$path])) {
            $this->callLog[] = ['path' => $path, 'durationMs' => 0, 'status' => 'error', 'error' => "Unknown function: app.{$path}"];
            throw new \RuntimeException("Unknown function: app.{$path}");
        }

        $toolSlug = $this->functionMap[$path];

        $tool = $this->registry->instantiateToolBySlug($toolSlug, $this->agent);

        if ($tool === null) {
            $this->callLog[] = ['path' => $path, 'durationMs' => 0, 'status' => 'error', 'error' => "Tool not available: {$toolSlug}"];
            throw new \RuntimeException("Tool not available: {$toolSlug}");
        }

        // Build arguments from the first Lua arg (a table → PHP associative array)
        $params = [];
        if (!empty($args) && is_array($args[0])) {
            $params = $args[0];
        } elseif (!empty($args) && !is_array($args[0])) {
            // Single non-table arg — try to map to first required parameter
            $params = $this->mapPositionalArgs($path, $args);
        }

        $request = new Request($params);

        $start = microtime(true);

        try {
            $result = $tool->handle($request);
            $this->callLog[] = ['path' => $path, 'durationMs' => round((microtime(true) - $start) * 1000, 1), 'status' => 'ok'];

            return $result;
        } catch (\Throwable $e) {
            $this->callLog[] = ['path' => $path, 'durationMs' => round((microtime(true) - $start) * 1000, 1), 'status' => 'error', 'error' => $e->getMessage()];
            throw $e;
        }
    }

    /** @return list<array{path: string, durationMs: float, status: string, error?: string}> */
    public function getCallLog(): array
    {
        return $this->callLog;
    }

    /**
     * Map positional arguments to parameter names for simple calls like app.memory.save("content").
     *
     * @return array<string, mixed>
     */
    private function mapPositionalArgs(string $path, array $args): array
    {
        // For now, return empty — callers should use named args via Lua table
        // This is a hook for future convenience support
        return [];
    }
}
