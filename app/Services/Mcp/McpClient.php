<?php

namespace App\Services\Mcp;

use App\Models\McpServer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class McpClient
{
    private int $requestId = 0;

    private ?string $sessionId = null;

    public function __construct(
        private string $url,
        private array $authHeaders = [],
        private int $timeout = 30,
    ) {}

    /**
     * Create a client from an McpServer model.
     */
    public static function fromServer(McpServer $server): self
    {
        return new self(
            url: $server->url,
            authHeaders: $server->getAuthHeaders(),
            timeout: $server->timeout,
        );
    }

    /**
     * MCP initialize handshake.
     */
    public function initialize(): array
    {
        return $this->sendRequest('initialize', [
            'protocolVersion' => '2025-03-26',
            'capabilities' => new \stdClass,
            'clientInfo' => [
                'name' => 'opencompany',
                'version' => '1.0.0',
            ],
        ]);
    }

    /**
     * Discover available tools from the MCP server.
     */
    public function listTools(): array
    {
        $response = $this->sendRequest('tools/list');

        return $response['tools'] ?? [];
    }

    /**
     * Execute a tool on the MCP server.
     */
    public function callTool(string $name, array $arguments = []): array
    {
        return $this->sendRequest('tools/call', [
            'name' => $name,
            'arguments' => empty($arguments) ? new \stdClass : $arguments,
        ]);
    }

    /**
     * Send a JSON-RPC 2.0 request to the MCP server.
     */
    private function sendRequest(string $method, array $params = []): array
    {
        $this->requestId++;

        $body = [
            'jsonrpc' => '2.0',
            'id' => $this->requestId,
            'method' => $method,
        ];

        if (!empty($params)) {
            $body['params'] = $params;
        }

        $headers = array_merge($this->authHeaders, [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
        ]);

        if ($this->sessionId) {
            $headers['Mcp-Session-Id'] = $this->sessionId;
        }

        $response = Http::withHeaders($headers)
            ->timeout($this->timeout)
            ->post($this->url, $body);

        // Track session ID from response
        if ($sessionId = $response->header('Mcp-Session-Id')) {
            $this->sessionId = $sessionId;
        }

        $contentType = $response->header('Content-Type');

        // Handle SSE responses
        if (str_contains($contentType, 'text/event-stream')) {
            return $this->parseSseResponse($response->body());
        }

        // Standard JSON response
        if (!$response->successful()) {
            throw new \RuntimeException(
                "MCP server returned HTTP {$response->status()}: {$response->body()}"
            );
        }

        $data = $response->json();

        if (isset($data['error'])) {
            $error = $data['error'];
            $message = $error['message'] ?? 'Unknown MCP error';
            $code = $error['code'] ?? -1;

            throw new \RuntimeException("MCP error ({$code}): {$message}");
        }

        return $data['result'] ?? [];
    }

    /**
     * Parse a Server-Sent Events response to extract the JSON-RPC result.
     */
    private function parseSseResponse(string $body): array
    {
        $lines = explode("\n", $body);
        $data = '';

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'data:')) {
                $data .= substr($line, 5);
            }
        }

        if (!$data) {
            throw new \RuntimeException('Empty SSE response from MCP server');
        }

        $decoded = json_decode(trim($data), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in SSE response: ' . json_last_error_msg());
        }

        if (isset($decoded['error'])) {
            $error = $decoded['error'];
            $message = $error['message'] ?? 'Unknown MCP error';
            $code = $error['code'] ?? -1;

            throw new \RuntimeException("MCP error ({$code}): {$message}");
        }

        return $decoded['result'] ?? [];
    }
}
