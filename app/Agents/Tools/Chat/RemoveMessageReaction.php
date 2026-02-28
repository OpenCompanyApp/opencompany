<?php

namespace App\Agents\Tools\Chat;

use App\Models\MessageReaction;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RemoveMessageReaction implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Remove an emoji reaction from a message by its reaction ID.';
    }

    public function handle(Request $request): string
    {
        try {
            $reactionId = $request['reactionId'];

            $reaction = MessageReaction::whereHas('message', fn ($q) => $q->whereHas('channel', fn ($cq) => $cq->where('workspace_id', workspace()->id)))
                ->where('user_id', $this->agent->id)
                ->find($reactionId);
            if (!$reaction) {
                return "Error: Reaction '{$reactionId}' not found or you don't own it.";
            }

            $reaction->delete();

            return "Reaction removed.";
        } catch (\Throwable $e) {
            return "Error managing message: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reactionId' => $schema
                ->string()
                ->description('The UUID of the reaction to remove.')
                ->required(),
        ];
    }
}
