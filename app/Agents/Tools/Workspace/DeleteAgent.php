<?php

namespace App\Agents\Tools\Workspace;

use App\Models\User;
use App\Services\AgentDocumentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteAgent implements Tool
{
    public function __construct(
        private User $agent,
        private AgentDocumentService $agentDocumentService,
    ) {}

    public function description(): string
    {
        return 'Delete an agent from the workspace, including its document structure.';
    }

    public function handle(Request $request): string
    {
        try {
            $agentId = $request['agentId'] ?? null;
            if (!$agentId) {
                return 'agentId is required.';
            }

            // Self-deletion guard
            if ($this->agent->id === $agentId) {
                return 'Self-deletion denied: you cannot delete yourself.';
            }

            $target = User::where('type', 'agent')->where('workspace_id', $this->agent->workspace_id)->find($agentId);
            if (!$target) {
                return "Agent not found: {$agentId}";
            }

            $name = $target->name;
            $this->agentDocumentService->deleteAgentDocumentStructure($target);
            $target->delete();

            return "Agent deleted: {$name}";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'agentId' => $schema
                ->string()
                ->description('Agent UUID of the agent to delete.')
                ->required(),
        ];
    }
}