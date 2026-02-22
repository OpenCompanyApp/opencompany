<?php

namespace App\Agents\Tools\Workspace;

use App\Models\User;
use App\Models\UserExternalIdentity;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class LinkExternalUser implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Link an external identity (e.g., Telegram, Slack) to a workspace user.';
    }

    public function handle(Request $request): string
    {
        try {
            $userId = $request['userId'] ?? null;
            $provider = $request['provider'] ?? null;
            $externalId = $request['externalId'] ?? null;

            if (!$userId || !$provider || !$externalId) {
                return 'userId, provider, and externalId are all required.';
            }

            $user = User::where('workspace_id', $this->agent->workspace_id)
                ->orWhereHas('workspaces', fn ($q) => $q->where('workspaces.id', $this->agent->workspace_id))
                ->find($userId);
            if (!$user) {
                return "User not found: {$userId}";
            }

            $existing = UserExternalIdentity::where('provider', $provider)
                ->where('external_id', $externalId)
                ->first();

            if ($existing && $existing->user_id !== $user->id) {
                /** @var User $existingUser */
                $existingUser = $existing->user;
                return "{$provider} ID {$externalId} is already linked to user: {$existingUser->name}";
            }

            UserExternalIdentity::updateOrCreate(
                ['provider' => $provider, 'external_id' => $externalId],
                [
                    'id' => $existing->id ?? Str::uuid()->toString(),
                    'user_id' => $user->id,
                    'display_name' => $request['displayName'] ?? null,
                ]
            );

            return "Linked {$provider} ID {$externalId} to user {$user->name}.";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'userId' => $schema
                ->string()
                ->description('User UUID to link the external identity to.')
                ->required(),
            'provider' => $schema
                ->string()
                ->description('External provider name (e.g. "telegram", "whatsapp", "slack").')
                ->required(),
            'externalId' => $schema
                ->string()
                ->description('External user ID (e.g. Telegram user ID).')
                ->required(),
            'displayName' => $schema
                ->string()
                ->description('Display name for the external identity.'),
        ];
    }
}