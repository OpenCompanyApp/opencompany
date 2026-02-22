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
    public function execute(string $code, array $options = []): LuaResult
    {
        $memoryLimit = $options['memoryLimit'] ?? 8 * 1024 * 1024; // 8 MB
        $cpuLimit = $options['cpuLimit'] ?? 5.0; // 5 seconds

        $sandbox = new Sandbox(
            memory_limit: $memoryLimit,
            cpu_limit: $cpuLimit,
        );

        $output = [];
        $this->setupPrintCapture($sandbox, $output);

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
}
