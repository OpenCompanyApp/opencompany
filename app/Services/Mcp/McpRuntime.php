<?php

namespace App\Services\Mcp;

use App\Models\McpServer;
use App\Models\User;

class McpRuntime
{
    public function __construct(
        private McpPermissionEvaluator $permissions,
        private McpResultNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function call(User $agent, McpServer $server, string $toolName, array $arguments = []): array
    {
        $decision = $this->permissions->evaluate($agent, $server, $toolName);
        if (! $decision->allowed()) {
            throw new \RuntimeException($decision->reason);
        }

        return $this->normalizer->normalize(McpClient::fromServer($server)->callTool($toolName, $arguments));
    }
}
