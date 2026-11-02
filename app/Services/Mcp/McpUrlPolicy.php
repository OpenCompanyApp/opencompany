<?php

namespace App\Services\Mcp;

use App\Domain\Web\Exceptions\WebProviderException;
use App\Domain\Web\Safety\WebRequestGuard;

/**
 * SSRF policy for remote MCP server URLs.
 *
 * Owns: applying WebRequestGuard to MCP URLs with the operator-only private
 * allowlist from config('mcp.allowed_private_hosts'). Called on create/update
 * (controller + agent workspace tools, for early, user-facing errors) and by
 * McpClient before every request (the real enforcement point — stored rows,
 * imported configs and test-connection overrides all pass through it, and DNS
 * can change between save and use).
 *
 * Does NOT own: MCP auth headers, tool permissions, or approval policy.
 * Deliberately does not read the web-fetch workspace setting
 * `web_fetch_allowed_private_hosts`; a workspace admin must not be able to
 * widen MCP egress. See config/mcp.php for the escape hatch.
 */
class McpUrlPolicy
{
    /**
     * Assert the URL may be contacted and return the vetted connection target.
     *
     * @return array{host: string, port: int, ips: list<string>}
     *
     * @throws McpUrlNotAllowed when the URL is malformed or resolves to a blocked address
     */
    public function assertAllowed(string $url): array
    {
        $allowlist = config('mcp.allowed_private_hosts', []);

        try {
            return app(WebRequestGuard::class)->assertSafeUrlWithAllowlist(
                $url,
                is_array($allowlist) ? array_values($allowlist) : [],
            );
        } catch (WebProviderException $e) {
            throw new McpUrlNotAllowed(
                'MCP server URL is not allowed: '.$e->getMessage()
                .' Private-network MCP servers must be allowlisted by the operator (MCP_ALLOWED_PRIVATE_HOSTS).',
                previous: $e,
            );
        }
    }

    /**
     * Validation-friendly variant: returns an error message or null.
     */
    public function violation(string $url): ?string
    {
        try {
            $this->assertAllowed($url);

            return null;
        } catch (McpUrlNotAllowed $e) {
            return $e->getMessage();
        }
    }
}
