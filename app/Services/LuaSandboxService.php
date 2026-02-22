<?php

namespace App\Services;

use Lua\Exception as LuaException;
use Lua\Sandbox;

class LuaSandboxService
{
    /**
     * Execute Lua code in a sandboxed environment.
     *
     * @param  array{memoryLimit?: int, cpuLimit?: float}  $options
     */
    public function execute(string $code, array $options = [], ?LuaBridge $bridge = null): LuaResult
    {
        $memoryLimit = $options['memoryLimit'] ?? 32 * 1024 * 1024; // 32 MB
        $cpuLimit = $options['cpuLimit'] ?? 30.0; // 30 seconds

        $sandbox = new Sandbox(
            memory_limit: $memoryLimit,
            cpu_limit: $cpuLimit,
        );

        $output = [];
        $this->setupPrintCapture($sandbox, $output);

        if ($bridge !== null) {
            $this->setupAppNamespace($sandbox, $bridge);
        }

        $start = microtime(true);

        try {
            $fn = $sandbox->load($code);
            $result = $fn();
            $elapsed = round((microtime(true) - $start) * 1000, 1);

            return new LuaResult(
                output: implode("\n", $output),
                error: null,
                result: $result,
                executionTime: $elapsed,
                memoryUsage: $sandbox->memoryUsage(),
            );
        } catch (LuaException $e) {
            $elapsed = round((microtime(true) - $start) * 1000, 1);

            return new LuaResult(
                output: implode("\n", $output),
                error: $e->getMessage(),
                result: null,
                executionTime: $elapsed,
                memoryUsage: null,
            );
        }
    }

    /**
     * Override Lua's print() to capture output via a PHP callback.
     *
     * @param  array<int, string>  $output
     */
    private function setupPrintCapture(Sandbox $sandbox, array &$output): void
    {
        $sandbox->register('__php', [
            'capture' => function ($line) use (&$output) {
                $output[] = (string) $line;
            },
        ]);

        $sandbox->load('
            function print(...)
                local parts = {}
                for i = 1, select("#", ...) do parts[i] = tostring(select(i, ...)) end
                __php.capture(table.concat(parts, "\t"))
            end
        ')->call();
    }

    /**
     * Register the app.* namespace using metatables to route calls to PHP via LuaBridge.
     *
     * Creates an infinitely nested proxy table where any app.X.Y.Z(args) call
     * is intercepted and routed to __app.call("X.Y.Z", args).
     */
    private function setupAppNamespace(Sandbox $sandbox, LuaBridge $bridge): void
    {
        $sandbox->register('__app', [
            'call' => function (string $path, mixed ...$args) use ($bridge) {
                try {
                    return $bridge->call($path, ...$args);
                } catch (\Throwable $e) {
                    // Return error as table — Lua side converts to error()
                    return ['__error' => $e->getMessage()];
                }
            },
        ]);

        $sandbox->load('
            local function make_namespace(path)
                return setmetatable({}, {
                    __index = function(self, key)
                        local child = path == "" and key or (path .. "." .. key)
                        local ns = make_namespace(child)
                        rawset(self, key, ns)
                        return ns
                    end,
                    __call = function(self, ...)
                        local result = __app.call(path, ...)
                        if type(result) == "table" and result.__error then
                            error(result.__error, 2)
                        end
                        return result
                    end
                })
            end
            app = make_namespace("")
        ')->call();
    }
}
