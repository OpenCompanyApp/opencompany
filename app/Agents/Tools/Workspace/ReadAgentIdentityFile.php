<?php

namespace App\Agents\Tools\Workspace;

use App\Models\User;
use App\Services\AgentDocumentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ReadAgentIdentityFile implements Tool
{
    public function __construct(
        private User $agent,
        private AgentDocumentService $agentDocumentService,
    ) {}

    public function description(): string
    {
        return 'Read an identity file (IDENTITY, SOUL, USER, AGENTS, TOOLS, HEARTBEAT, BOOTSTRAP, or MEMORY) for a specific agent.';
    }

    public function handle(Request $request): string
    {
        try {
            $agentId = $request['agentId'] ?? null;
            $fileType = $request['fileType'] ?? null;

            if (!$agentId || !$fileType) {
                return 'Required: agentId, fileType (e.g., IDENTITY, SOUL, USER, AGENTS, TOOLS, HEARTBEAT, BOOTSTRAP, MEMORY).';
            }

            $target = User::where('type', 'agent')->where('workspace_id', $this->agent->workspace_id)->find($agentId);
            if (!$target) {
                return "Agent not found: {$agentId}";
            }

            $file = $this->agentDocumentService->getIdentityFile($target, $fileType);
            if (!$file) {
                return "Identity file not found: {$fileType}";
            }

            return "File: {$file->title}\nUpdated: {$file->updated_at}\n\n{$file->content}";
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
        ];
    }
}