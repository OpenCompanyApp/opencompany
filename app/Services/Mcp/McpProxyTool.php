<?php

namespace App\Services\Mcp;

use App\Models\McpServer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class McpProxyTool implements Tool
{
    /** @param array<string, mixed> $mcpInputSchema */
    public function __construct(
        private McpServer $server,
        private string $mcpToolName,
        private string $mcpToolDescription,
        private array $mcpInputSchema,
    ) {}

    public function name(): string
    {
        return 'mcp_' . $this->server->slug . '__' . str_replace('-', '_', $this->mcpToolName);
    }

    public function description(): string
    {
        return $this->mcpToolDescription;
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return McpSchemaTranslator::translate($this->mcpInputSchema, $schema);
    }

    public function handle(Request $request): string
    {
        try {
            $client = McpClient::fromServer($this->server);
            $result = $client->callTool($this->mcpToolName, $request->toArray());

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
        if (!empty($result['isError'])) {
            $text = $this->extractText($result['content'] ?? []);

            return 'MCP Error: ' . ($text ?: 'Unknown error from remote server');
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
