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

            if ($members->isEmpty() && $invitations->isEmpty()) {
                return 'No members or pending invitations found.';
            }

            $lines = [];

            if ($members->isNotEmpty()) {
                $lines[] = "Members ({$members->count()}):";
                foreach ($members as $member) {
                    $role = $member->pivot->role ?? 'member';
                    $isOwner = $member->id === $workspace->owner_id ? ' (owner)' : '';
                    $lines[] = "- {$member->name} <{$member->email}> — role: {$role}{$isOwner}";
                }
            }

            if ($invitations->isNotEmpty()) {
                $lines[] = '';
                $lines[] = "Pending Invitations ({$invitations->count()}):";
                foreach ($invitations as $invitation) {
                    $inviter = $invitation->inviter?->name ?? 'unknown';
                    $lines[] = "- {$invitation->email} — role: {$invitation->role}, invited by: {$inviter}";
                }
            }

            return implode("\n", $lines);
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
