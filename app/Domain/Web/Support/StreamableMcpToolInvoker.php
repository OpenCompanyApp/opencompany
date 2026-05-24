<?php

namespace App\Domain\Web\Support;

use App\Domain\Web\Exceptions\WebProviderException;
use Illuminate\Support\Facades\Http;

/**
 * Minimal streamable-HTTP MCP client for provider-hosted web-search tools.
 *
 * Z.AI exposes web_search_prime through a remote MCP endpoint. This invoker
 * performs the initialize/initialized/tools-call sequence and understands both
 * JSON and simple server-sent-event responses.
 */
class StreamableMcpToolInvoker
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, string>  $headers
     * @return array<string, mixed>|string
     */
    public function call(string $remoteUrl, string $toolName, array $arguments, array $headers = []): array|string
    {
        [$sessionId] = $this->send($remoteUrl, [
            'jsonrpc' => '2.0',
            'id' => 0,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => (object) [],
                'clientInfo' => ['name' => 'opencompany', 'version' => '0.1'],
            ],
        ], $headers);

        $this->send($remoteUrl, [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ], $headers, $sessionId, expectPayload: false);

        [, $payload] = $this->send($remoteUrl, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => $toolName,
                'arguments' => $arguments,
            ],
        ], $headers, $sessionId);

        if (! is_array($payload)) {
            throw new WebProviderException('Remote MCP tool returned an invalid payload.');
        }

        if (($payload['isError'] ?? false) === true) {
            throw new WebProviderException($this->extractToolText($payload) ?: 'Remote MCP tool call failed.');
        }

        return $this->decodeEmbeddedPayload($this->extractToolText($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array{0: string, 1: ?array<string, mixed>}
     */
    private function send(string $remoteUrl, array $payload, array $headers, string $sessionId = '', bool $expectPayload = true): array
    {
        $pending = Http::withHeaders(array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
        ], $headers))->timeout(30);

        if ($sessionId !== '') {
            $pending = $pending->withHeader('mcp-session-id', $sessionId);
        }

        $response = $pending->post($remoteUrl, $payload);
        if (! $response->successful()) {
            throw new WebProviderException("Remote MCP request failed ({$response->status()}).");
        }

        $headerSessionId = $response->header('mcp-session-id');
        $resolvedSessionId = $headerSessionId !== '' ? $headerSessionId : $sessionId;
        if (! $expectPayload) {
            return [$resolvedSessionId, null];
        }

        $decoded = str_contains(strtolower((string) $response->header('content-type')), 'text/event-stream')
            ? $this->decodeSsePayload($response->body())
            : $response->json();

        if (! is_array($decoded)) {
            throw new WebProviderException('Remote MCP response was not valid JSON.');
        }

        $result = $decoded['result'] ?? $decoded;
        if (! is_array($result)) {
            throw new WebProviderException('Remote MCP result payload was invalid.');
        }

        return [$resolvedSessionId, $result];
    }

    /**
     * @return ?array<string, mixed>
     */
    private function decodeSsePayload(string $body): ?array
    {
        foreach (preg_split("/\n\n/", str_replace(["\r\n", "\r"], "\n", $body)) ?: [] as $event) {
            $data = [];
            foreach (explode("\n", $event) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $data[] = ltrim(substr($line, 5));
                }
            }
            if ($data !== []) {
                $decoded = json_decode(implode("\n", $data), true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractToolText(array $payload): string
    {
        foreach (is_array($payload['content'] ?? null) ? $payload['content'] : [] as $item) {
            if (is_array($item) && ($item['type'] ?? null) === 'text' && is_string($item['text'] ?? null)) {
                return $item['text'];
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>|string
     */
    private function decodeEmbeddedPayload(string $text): array|string
    {
        $value = $text;
        for ($i = 0; $i < 3; $i++) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                break;
            }
            if (is_array($decoded)) {
                return $decoded;
            }
            if (! is_string($decoded)) {
                return $value;
            }
            $value = $decoded;
        }

        return $value;
    }
}
