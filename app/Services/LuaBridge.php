<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Tools\Request;

class LuaBridge
{
    /** @var array<string, string> path => toolSlug */
    private array $functionMap;

    /** @var array<string, list<string>> path => [paramName1, paramName2, ...] */
    private array $parameterMap;

    /** @var list<array{path: string, durationMs: float, status: string, error?: string}> */
    private array $callLog = [];

    public function __construct(
        private User $agent,
        private ToolRegistry $registry,
        LuaApiDocGenerator $docGenerator,
    ) {
        $this->functionMap = $docGenerator->buildFunctionMap($agent);
        $this->parameterMap = $docGenerator->buildParameterMap($agent);
    }

    /**
     * Handle a Lua app.* function call by routing it to the corresponding PHP tool.
     *
     * Called from Lua via: app.chat.send_channel_message({channel_id = "abc", content = "hi"})
     * Which routes through metatables to: __app.call("chat.send_channel_message", {channel_id = "abc", ...})
     * Snake_case keys are auto-converted to camelCase for the tool.
     */
    public function call(string $path, mixed ...$args): string|array
    {
        if (!isset($this->functionMap[$path])) {
            $msg = "Unknown function: app.{$path}";

            // Suggest available functions from the same namespace
            $parts = explode('.', $path);
            if (count($parts) > 1) {
                $nsPrefix = implode('.', array_slice($parts, 0, -1));
                $available = [];
                foreach ($this->functionMap as $fnPath => $_) {
                    if (str_starts_with($fnPath, $nsPrefix . '.')) {
                        $fnParts = explode('.', $fnPath);
                        $available[] = end($fnParts);
                    }
                }
                if (!empty($available)) {
                    $msg .= '. Did you mean: ' . implode(', ', $available);
                }
            }

            $this->callLog[] = ['path' => $path, 'durationMs' => 0, 'status' => 'error', 'error' => $msg];
            throw new \RuntimeException($msg);
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

        // Convert snake_case keys to camelCase (Lua convention → tool convention)
        $params = $this->snakeToCamel($params);

        $request = new Request($params);

        $start = microtime(true);

        try {
            $result = $tool->handle($request);
            $this->callLog[] = ['path' => $path, 'durationMs' => round((microtime(true) - $start) * 1000, 1), 'status' => 'ok'];

            // Auto-decode JSON responses → PHP arrays (sandbox converts to Lua tables)
            if (is_string($result)) {
                $trimmed = ltrim($result);
                if (($trimmed[0] ?? '') === '{' || ($trimmed[0] ?? '') === '[') {
                    $decoded = json_decode($result, true);
                    if ($decoded !== null) {
                        return $decoded;
                    }
                }
            }

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
     * Convert snake_case array keys to camelCase.
     * Lua convention is snake_case, tool schemas use camelCase.
     *
     * @return array<string, mixed>
     */
    private function snakeToCamel(array $params): array
    {
        $converted = [];
        foreach ($params as $key => $value) {
            $camelKey = lcfirst(str_replace('_', '', ucwords((string) $key, '_')));
            $converted[$camelKey] = $value;
        }

        return $converted;
    }

    /**
     * Map positional arguments to parameter names based on the tool's schema.
     *
     * Allows Lua calls like app.chat.send_channel_message(channel_id, content)
     * instead of requiring app.chat.send_channel_message({channel_id = "...", content = "..."}).
     *
     * @return array<string, mixed>
     */
    private function mapPositionalArgs(string $path, array $args): array
    {
        $paramNames = $this->parameterMap[$path] ?? [];

        if (empty($paramNames)) {
            Log::warning('LuaBridge: positional args passed but no parameter map for function', [
                'path' => $path,
                'arg_count' => count($args),
            ]);

            return [];
        }

        $mapped = [];
        foreach ($args as $i => $value) {
            if (isset($paramNames[$i])) {
                $mapped[$paramNames[$i]] = $value;
            }
        }

        return $mapped;
    }
}
