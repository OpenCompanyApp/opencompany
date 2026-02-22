<?php

namespace App\Agents\Tools\Lua;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\LuaApiDocGenerator;
use App\Services\LuaBridge;
use App\Services\LuaSandboxService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class LuaExec implements Tool
{
    public function __construct(
        private LuaSandboxService $lua,
        private ToolRegistry $registry,
        private LuaApiDocGenerator $docGenerator,
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Execute Lua code in a sandboxed environment with access to the app.* namespace for workspace operations. Use print() for output. Call workspace tools via app.{namespace}.{function}({named_args}).';
    }

    public function handle(Request $request): string
    {
        try {
            $code = $request['code'];
            $options = [];

            if (isset($request['memoryLimit'])) {
                $options['memoryLimit'] = (int) $request['memoryLimit'];
            }

            if (isset($request['cpuLimit'])) {
                $options['cpuLimit'] = (float) $request['cpuLimit'];
            }

            $bridge = new LuaBridge($this->agent, $this->registry, $this->docGenerator);
            $result = $this->lua->execute($code, $options, $bridge);

            $lines = [];

            if ($result->output !== '') {
                $lines[] = "Output:\n{$result->output}";
            }

            if ($result->error !== null) {
                $lines[] = "Error: {$result->error}";
            }

            if ($result->result !== null) {
                $lines[] = 'Return value: ' . $this->formatResult($result->result);
            }

            $lines[] = "Execution time: {$result->executionTime}ms";

            if ($result->memoryUsage !== null) {
                $lines[] = 'Memory: ' . $this->formatBytes($result->memoryUsage);
            }

            $humanText = empty($lines) ? 'Script executed successfully with no output.' : implode("\n", $lines);

            // Prepend structured metadata for the frontend task detail view.
            // Placed first so it survives the 2000-char truncation in logToolSteps.
            $meta = json_encode([
                'output' => $result->output,
                'error' => $result->error,
                'returnValue' => $result->result,
                'executionTime' => $result->executionTime,
                'memoryUsage' => $result->memoryUsage,
                'bridgeCalls' => $bridge->getCallLog(),
            ]);

            return "<!--__LUA_META__{$meta}__LUA_META__-->\n{$humanText}";
        } catch (\Throwable $e) {
            return "Failed to execute Lua code: {$e->getMessage()}";
        }
    }

    private function formatResult(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema
                ->string()
                ->description('The Lua code to execute. Use print() for output. Access workspace tools via app.{namespace}.{function}({args_table}).')
                ->required(),
            'memoryLimit' => $schema
                ->integer()
                ->description('Memory limit in bytes. Default: 33554432 (32 MB).'),
            'cpuLimit' => $schema
                ->number()
                ->description('CPU time limit in seconds. Default: 5.0.'),
        ];
    }
}
