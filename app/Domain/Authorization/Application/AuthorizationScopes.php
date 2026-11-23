<?php

namespace App\Domain\Authorization\Application;

use App\Models\AgentPermission;
use App\Models\Channel;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query forms of PolicyEngine rules, for listing endpoints that cannot
 * decide row by row (channel lists, message search, the VFS channel mount).
 *
 * Invariant: each scope here returns exactly the rows PolicyEngine would
 * allow for the same actor and action. tests/Feature/Authorization/
 * AuthorizationScopesTest.php checks the two agree over a matrix of
 * channel types, memberships, roles and grants; change both together.
 *
 * Workspace boundary: every scope starts from the given workspace.
 */
class AuthorizationScopes
{
    /**
     * Channels `$actor` may read (`channel.read`), intersected with the
     * on-behalf-of human's readable channels when an agent acts for one.
     *
     * @return Builder<Channel>
     */
    public function readableChannels(User $actor, Workspace $workspace, ?User $onBehalfOf = null): Builder
    {
        $query = Channel::forWorkspace($workspace);
        $this->constrainReadable($query, $actor, $workspace);

        if ($actor->type === 'agent' && $onBehalfOf !== null) {
            if ($onBehalfOf->type !== 'human' || ! $this->isMember($onBehalfOf, $workspace)) {
                return $query->whereRaw('1 = 0');
            }
            $this->constrainReadable($query, $onBehalfOf, $workspace);
        }

        return $query;
    }

    /** @param  Builder<Channel>  $query */
    private function constrainReadable(Builder $query, User $actor, Workspace $workspace): void
    {
        $memberOf = fn ($q) => $q->whereIn('channels.id', function ($sub) use ($actor): void {
            $sub->select('channel_id')->from('channel_members')->where('user_id', $actor->id);
        })->orWhereIn('channels.id', function ($sub) use ($actor): void {
            // DM participants are members even without a membership row.
            $sub->select('channel_id')->from('direct_messages')
                ->where(fn ($p) => $p->where('user1_id', $actor->id)->orWhere('user2_id', $actor->id));
        });

        if ($actor->type === 'agent') {
            if ($actor->workspace_id !== $workspace->id) {
                $query->whereRaw('1 = 0');

                return;
            }
            $grants = AgentPermission::forAgent($actor->id)->channels()->allowed()->pluck('scope_key')
                ->map(fn (mixed $id): string => (string) $id)->values()->all();
            if ($grants !== []) {
                $query->whereIn('channels.id', $grants);
            }
            $query->where(function (Builder $visible) use ($memberOf, $grants): void {
                $visible->whereNull('channels.type')
                    ->orWhereNotIn('channels.type', ['dm', 'private'])
                    ->orWhere(fn ($q) => $memberOf($q));
                if ($grants !== []) {
                    $visible->orWhereIn('channels.id', $grants);
                }
            });

            return;
        }

        $role = $this->roleOf($actor, $workspace);
        if ($role === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $visible) use ($memberOf, $role): void {
            $visible->where('channels.type', 'public')->orWhere(fn ($q) => $memberOf($q));
            if (in_array($role, ['owner', 'admin'], true)) {
                $visible->orWhere('channels.type', 'external');
            }
        });
    }

    private function isMember(User $human, Workspace $workspace): bool
    {
        return $this->roleOf($human, $workspace) !== null;
    }

    private function roleOf(User $human, Workspace $workspace): ?string
    {
        if ($workspace->owner_id !== null && (string) $workspace->owner_id === (string) $human->id) {
            return 'owner';
        }
        $role = WorkspaceMember::query()->where('workspace_id', $workspace->id)->where('user_id', $human->id)->value('role');

        return is_string($role) && in_array($role, ['admin', 'approver', 'member', 'viewer'], true) ? $role : null;
    }
}
