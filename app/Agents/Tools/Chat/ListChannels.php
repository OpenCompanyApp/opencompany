<?php

namespace App\Agents\Tools\Chat;

use App\Domain\Authorization\Application\AuthorizationScopes;
use App\Domain\Authorization\Application\OnBehalfOf;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListChannels implements Tool
{
    public function __construct(
        // ChatToolProvider passes ($agent, $permissionService) to every chat
        // tool; this one needs only the agent: visibility comes from
        // AuthorizationScopes (the central `channel.read` rule).
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List channels in the workspace that you have access to, including external channels (Telegram, Slack).';
    }

    public function handle(Request $request): string
    {
        try {
            $type = $request['type'] ?? null;

            // Visibility is the central `channel.read` rule in query form,
            // intersected with the run's on-behalf-of human (GIN-1197).
            $principalId = app(OnBehalfOf::class)->currentPrincipalId();
            $principal = $principalId !== null ? User::query()->find($principalId) : null;
            $query = app(AuthorizationScopes::class)
                ->readableChannels($this->agent, workspace(), $principal)
                ->withCount('members');

            if ($type) {
                $query->where('type', $type);
            }

            // Exclude DM channels — those are private between two users
            $query->where('type', '!=', 'dm');

            $channels = $query->orderBy('name')->get();

            if ($channels->isEmpty()) {
                return json_encode([]);
            }

            return json_encode($channels->map(fn ($channel) => array_filter([
                'id' => $channel->id,
                'name' => $channel->name,
                'type' => $channel->type,
                'members' => $channel->members_count,
                'externalProvider' => $channel->type === 'external' ? $channel->external_provider : null,
            ]))->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error listing channels: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema
                ->string()
                ->description("Optional filter by channel type: 'public', 'private', or 'external'."),
        ];
    }
}
