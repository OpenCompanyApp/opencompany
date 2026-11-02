<?php

namespace App\Services\Mcp;

use App\Models\McpServer;
use App\Models\User;

/**
 * Agent-aware execution of one remote MCP tool call.
 *
 * Owns: the last permission re-check before contacting the MCP server and
 * result normalisation. Does NOT own approvals: MCP tools are never exposed as
 * direct model tools (they are not in ToolRegistry::DIRECT_TOOL_GROUPS), so
 * every agent call arrives either through IntegrationRuntime (which turns
 * `approval_required` into a pending ApprovalRequest before constructing this
 * tool) or through ApprovalExecutionService executing an approved request.
 *
 * Does NOT own model-facing labelling either: results are structured script
 * values; CodeExec labels its model-visible output as untrusted.
 */
class McpRuntime
{
    public function __construct(
        private McpPermissionEvaluator $permissions,
        private McpResultNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws \RuntimeException when the agent is denied this MCP tool or the server call fails
     */
    public function call(User $agent, McpServer $server, string $toolName, array $arguments = []): array
    {
        $decision = $this->permissions->evaluate($agent, $server, $toolName);

        // Invariant: deny is absolute here (revocations after approval still
        // win). `approval_required` is NOT rejected: MCP tools sit behind the
        // external-mutation approval floor in every behavior mode, and the
        // approved execution path re-enters this method with the same decision.
        // Rejecting it would make approved MCP calls impossible. The approval
        // gate itself lives upstream (see class docblock); do not add a direct
        // model-facing MCP tool path without adding an approval check there.
        if ($decision->decision === 'deny') {
            throw new \RuntimeException($decision->reason);
        }

        return $this->normalizer->normalize(McpClient::fromServer($server)->callTool($toolName, $arguments));
    }
}
