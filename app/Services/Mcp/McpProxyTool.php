<?php

namespace App\Services\Mcp;

use App\Models\McpServer;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool as LaravelAiTool;
use Laravel\Ai\Tools\Request;
use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;

/**
 * Laravel AI and integration-core adapter around one discovered MCP tool.
 *
 * The proxy can be executed from two paths: Laravel AI tool calls (`handle`) and
 * integration-core connection tests (`execute`). When an agent is present, calls
 * must go through McpRuntime so workspace permission checks are enforced before
 * the remote server is contacted.
 */
class McpProxyTool implements LaravelAiTool, Tool
{
    /** @param array<string, mixed> $mcpInputSchema */
    public function __construct(
        private McpServer $server,
        private string $mcpToolName,
        private string $mcpToolDescription,
        private array $mcpInputSchema,
        private ?User $agent = null,
    ) {}

    public function name(): string
    {
        // This name is the local permission/catalog slug. The remote MCP name is
        // kept separately in $mcpToolName so JSON-RPC calls can preserve case,
        // hyphens, and any server-specific naming convention.
        return 'mcp_'.$this->server->slug.'__'.Str::snake(str_replace('-', '_', $this->mcpToolName));
    }

    public function description(): string
    {
        return $this->mcpToolDescription;
    }

    public function parameters(): array
    {
        $params = [];
        $properties = $this->mcpInputSchema['properties'] ?? [];
        $required = $this->mcpInputSchema['required'] ?? [];

        foreach ($properties as $name => $def) {
            $param = ['type' => $def['type'] ?? 'string'];
            if (in_array($name, $required)) {
                $param['required'] = true;
            }
            if (! empty($def['description'])) {
                $param['description'] = $def['description'];
            }
            if (! empty($def['enum'])) {
                $param['enum'] = $def['enum'];
            }
            $params[$name] = $param;
        }

        return $params;
    }

    public function execute(array $args): ToolResult
    {
        try {
            if ($this->agent !== null) {
                // Agent-aware execution must use McpRuntime; direct client calls
                // would skip OpenCompany workspace and tool permission checks.
                $result = app(McpRuntime::class)->call($this->agent, $this->server, $this->mcpToolName, $args);

                return ($result['success'] ?? false)
                    ? ToolResult::success((string) ($result['text'] ?? ''))
                    : ToolResult::error((string) ($result['text'] ?? 'Unknown error from remote server'));
            }

            $result = McpClient::fromServer($this->server)->callTool($this->mcpToolName, $args);

            if (! empty($result['isError'])) {
                $text = $this->extractText($result['content'] ?? []);

                return ToolResult::error($text ?: 'Unknown error from remote server');
            }

            return ToolResult::success($this->extractText($result['content'] ?? []));
        } catch (\Throwable $e) {
            return ToolResult::error("MCP tool '{$this->mcpToolName}' on {$this->server->name}: {$e->getMessage()}");
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return McpSchemaTranslator::translate($this->mcpInputSchema, $schema);
    }

    public function handle(Request $request): string
    {
        try {
            if ($this->agent !== null) {
                // Same permission rule as execute(): model-triggered calls are
                // never allowed to contact MCP servers outside runtime checks.
                $result = app(McpRuntime::class)->call($this->agent, $this->server, $this->mcpToolName, $request->toArray());

                return ($result['success'] ?? false)
                    ? (string) ($result['text'] ?? '')
                    : 'MCP Error: '.((string) ($result['text'] ?? 'Unknown error from remote server'));
            }

            $result = McpClient::fromServer($this->server)->callTool($this->mcpToolName, $request->toArray());

            return $this->formatResult($result);
        } catch (\Throwable $e) {
            return "Error calling MCP tool '{$this->mcpToolName}' on {$this->server->name}: {$e->getMessage()}";
        }
    }

    /**
     * Format MCP tool result content into a string.
     *
     * @param  array<string, mixed>  $result
     */
    private function formatResult(array $result): string
    {
        if (! empty($result['isError'])) {
            $text = $this->extractText($result['content'] ?? []);

            return 'MCP Error: '.($text ?: 'Unknown error from remote server');
        }

        return $this->extractText($result['content'] ?? []);
    }

    /**
     * Extract text from MCP content array [{type: "text", text: "..."}].
     *
     * @param  array<int, array<string, string>>  $content
     */
    private function extractText(array $content): string
    {
        $texts = [];
        foreach ($content as $item) {
            if (($item['type'] ?? '') === 'text') {
                $texts[] = $item['text'];
            }
        }

        return implode("\n", $texts) ?: 'No response content';
    }
}
