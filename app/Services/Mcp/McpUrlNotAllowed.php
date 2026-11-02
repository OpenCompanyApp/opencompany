<?php

namespace App\Services\Mcp;

/**
 * Raised when an MCP server URL fails the SSRF policy (McpUrlPolicy).
 *
 * The message is safe to show to users and agents: it contains the host and
 * the reason, never auth headers or resolved credentials.
 */
class McpUrlNotAllowed extends \RuntimeException {}
