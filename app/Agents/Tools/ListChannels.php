<?php

namespace App\Agents\Tools;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListChannels implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List channels in the workspace that you have access to.';
    }

    public function handle(Request $request): string
    {
        try {
            $type = $request['type'] ?? null;

            $query = Channel::withCount('members');

            if ($type) {
                $query->where('type', $type);
            }

            // Exclude DM channels — those are private between two users
            $query->where('type', '!=', 'dm');

            $channels = $query->orderBy('name')->get();

            if ($channels->isEmpty()) {
                return 'No channels found.';
            }

            $lines = ['Workspace channels:'];
            foreach ($channels as $channel) {
                $prefix = $channel->type === 'external' ? '' : '#';
                $extra = '';
                if ($channel->type === 'external' && $channel->external_provider) {
                    $extra = ", provider: {$channel->external_provider}";
                }
                $lines[] = "- {$prefix}{$channel->name} (id: {$channel->id}, type: {$channel->type}, {$channel->members_count} members{$extra})";
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error listing channels: {$e->getMessage()}";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema
                ->string()
                ->description("Optional filter by channel type: 'public', 'private', or 'external'."),
        ];
    }
}
