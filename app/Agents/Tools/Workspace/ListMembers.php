<?php

namespace App\Agents\Tools\Workspace;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListMembers implements Tool
{
    public function description(): string
    {
        return 'List all members and pending invitations in the current workspace.';
    }

    public function handle(Request $request): string
    {
        try {
            $workspace = workspace();

            $members = $workspace->members()->withPivot('role')->orderBy('name')->get();
            $invitations = $workspace->invitations()->whereNull('accepted_at')->with('inviter:id,name')->get();

            $result = [];

            if ($members->isNotEmpty()) {
                $result['members'] = $members->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->role ?? 'member',
                    'isOwner' => $member->id === $workspace->owner_id,
                ])->values()->toArray();
            }

            if ($invitations->isNotEmpty()) {
                $result['invitations'] = $invitations->map(fn ($inv) => [
                    'email' => $inv->email,
                    'role' => $inv->role,
                    'invitedBy' => $inv->inviter?->name ?? 'unknown',
                ])->values()->toArray();
            }

            return json_encode($result, JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
