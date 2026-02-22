<?php

namespace App\Agents\Tools\Workspace;

use App\Models\User;
use App\Services\AgentDocumentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateAgentIdentityFile implements Tool
{
    public function __construct(
        private User $agent,
        private AgentDocumentService $agentDocumentService,
    ) {}

    public function description(): string
    {
        return 'Update the content of an identity file for a specific agent.';
    }

    public function handle(Request $request): string
    {
        try {
            $agentId = $request['agentId'] ?? null;
            $fileType = $request['fileType'] ?? null;
            $content = $request['content'] ?? null;

            if (!$agentId || !$fileType || $content === null) {
                return 'Required: agentId, fileType, content.';
            }

            $target = User::where('type', 'agent')->where('workspace_id', $this->agent->workspace_id)->find($agentId);
            if (!$target) {
                return "Agent not found: {$agentId}";
            }

            $file = $this->agentDocumentService->updateIdentityFile($target, $fileType, $content);
            if (!$file) {
                return "Failed to update identity file: {$fileType}";
            }

            return "Identity file updated: {$file->title} for agent {$target->name}";
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
                ->description('Agent UUID.')
                ->required(),
            'fileType' => $schema
                ->string()
                ->description('Identity file type: IDENTITY, SOUL, USER, AGENTS, TOOLS, HEARTBEAT, BOOTSTRAP, or MEMORY.')
                ->required(),
            'content' => $schema
                ->string()
                ->description('New content for the identity file.')
                ->required(),
        ];
    }
}