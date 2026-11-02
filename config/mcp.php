<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Remote MCP Server Network Policy
    |--------------------------------------------------------------------------
    |
    | Every MCP server URL is checked by App\Domain\Web\Safety\WebRequestGuard
    | when it is created/updated and again before every JSON-RPC request
    | (App\Services\Mcp\McpClient). By default only public addresses are
    | reachable: loopback, RFC1918, CGNAT, unique-local and other reserved
    | ranges are refused, so a workspace member or an agent cannot point an MCP
    | server at internal infrastructure (SSRF).
    |
    | Self-hosters who intentionally run MCP servers on a private network can
    | opt in with MCP_ALLOWED_PRIVATE_HOSTS, a comma-separated list of exact
    | host names and/or IPs/CIDRs, e.g.:
    |
    |   MCP_ALLOWED_PRIVATE_HOSTS="localhost,mcp.internal,10.20.0.0/16,fd00::/8"
    |
    | - A host name entry lets that host resolve to private addresses (and
    |   permits `localhost`-style names only when listed explicitly).
    | - An IP/CIDR entry permits private addresses in that range for any host.
    |
    | This is deliberately operator-level config (env), not a workspace
    | setting: workspace admins and agents must not be able to widen it.
    | Cloud metadata endpoints (169.254.0.0/16, fe80::/10, metadata.google.*)
    | are always blocked regardless of this list. Redirects are never followed
    | for MCP requests.
    |
    */

    'allowed_private_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MCP_ALLOWED_PRIVATE_HOSTS', '')),
    ))),
];
