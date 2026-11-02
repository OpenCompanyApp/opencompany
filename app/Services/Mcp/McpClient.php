<?php

namespace App\Services\Mcp;

use App\Models\McpServer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Streamable-HTTP JSON-RPC client for one remote MCP server.
 *
 * Owns: request framing, session-id tracking, SSE/JSON response parsing, and
 * the per-request SSRF enforcement for MCP egress.
 *
 * Does NOT own: permissions (McpRuntime/McpPermissionEvaluator), result
 * labelling for the model (CodeExec labels model-visible output), or the
 * private-network allowlist (config/mcp.php via McpUrlPolicy).
 *
 * Security invariant: every request re-validates the URL through
 * McpUrlPolicy, never follows redirects, and pins the connection to the
 * addresses that were just validated. Do not add a code path that sends HTTP
 * to $this->url without going through sendRequest().
 */
class McpClient
{
    private int $requestId = 0;

    private ?string $sessionId = null;

    /**
     * @param array<string, string> $authHeaders
     */
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
     *
     * @return array<string, mixed>
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
     *
     * @return array<int, mixed>
     */
    public function listTools(): array
    {
        $response = $this->sendRequest('tools/list');

        return $response['tools'] ?? [];
    }

    /**
     * Execute a tool on the MCP server.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
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
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
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

        // SSRF guard: validate on every request (DNS can change after the
        // server was saved). Redirects are disabled because a public endpoint
        // could otherwise bounce the POST, including auth headers, to an
        // internal address. CURLOPT_RESOLVE pins the connection to the vetted
        // IPs so a DNS rebind between check and connect cannot swap targets.
        $target = app(McpUrlPolicy::class)->assertAllowed($this->url);

        $response = Http::withHeaders($headers)
            ->timeout($this->timeout)
            ->withoutRedirecting()
            ->withOptions($this->pinnedResolveOptions($target))
            ->post($this->url, $body);

        if ($response->redirect()) {
            throw new \RuntimeException(
                "MCP server returned a redirect (HTTP {$response->status()}); redirects are not followed for MCP servers. Update the server URL to the final endpoint."
            );
        }

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
     * Build Guzzle options that pin the host to the IPs the guard just vetted.
     *
     * Only applied for host names on the cURL handler; IP-literal URLs need no
     * pinning. Http::fake() ignores these options, so tests are unaffected.
     *
     * @param  array{host: string, port: int, ips: list<string>}  $target
     * @return array<string, mixed>
     */
    private function pinnedResolveOptions(array $target): array
    {
        if (filter_var($target['host'], FILTER_VALIDATE_IP) || $target['ips'] === [] || ! defined('CURLOPT_RESOLVE')) {
            return [];
        }

        $addresses = array_map(
            fn (string $ip): string => str_contains($ip, ':') ? "[{$ip}]" : $ip,
            $target['ips'],
        );

        return ['curl' => [CURLOPT_RESOLVE => [$target['host'].':'.$target['port'].':'.implode(',', $addresses)]]];
    }

    /**
     * Parse a Server-Sent Events response to extract the JSON-RPC result.
     *
     * @return array<string, mixed>
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
