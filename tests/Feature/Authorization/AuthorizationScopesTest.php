<?php

namespace Tests\Feature\Authorization;

use App\Domain\Authorization\Application\AgentPermissionResolver;
use App\Domain\Authorization\Application\AuthorizationScopes;
use App\Domain\Authorization\Application\AuthorizationService;
use App\Domain\Authorization\Application\OnBehalfOf;
use App\Models\AgentPermission;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * AuthorizationScopes::readableChannels() is the SQL form of the engine's
 * `channel.read` rule. Lists (channels, message search, the VFS channel
 * mount, list_channels) use the scope; single reads use the engine. This
 * test checks they agree for every actor × channel in a mixed workspace,
 * including agents with and without grants and on-behalf-of intersection.
 */
class AuthorizationScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_channel_scope_matches_the_engine_for_every_actor_and_channel(): void
    {
        $admin = $this->human('admin');
        $member = $this->human('member');
        $viewer = $this->human('viewer');
        $agent = User::factory()->agent()->create(['workspace_id' => $this->workspace->id, 'manager_id' => $member->id]);
        $granted = User::factory()->agent()->create(['workspace_id' => $this->workspace->id]);

        $channels = [];
        foreach (['public', 'private', 'dm', 'external', null] as $type) {
            foreach ([[], [$member], [$agent], [$admin, $granted]] as $i => $members) {
                $channel = Channel::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'name' => "c{$i}", 'type' => $type ?? 'mystery']);
                foreach ($members as $user) {
                    ChannelMember::create(['channel_id' => $channel->id, 'user_id' => $user->id, 'role' => 'member']);
                }
                $channels[] = $channel;
            }
        }
        // The granted agent may read exactly two channels by explicit grant.
        foreach ([$channels[1], $channels[9]] as $channel) {
            AgentPermission::create(['id' => (string) Str::uuid(), 'agent_id' => $granted->id, 'scope_type' => 'channel', 'scope_key' => $channel->id, 'permission' => 'allow', 'requires_approval' => false]);
        }

        $service = app(AuthorizationService::class);
        $scopes = app(AuthorizationScopes::class);
        $cases = [[$admin, null], [$member, null], [$viewer, null], [$agent, null], [$granted, null], [$agent, $member], [$agent, $viewer], [$granted, $admin]];

        foreach ($cases as [$actor, $principal]) {
            $scoped = $scopes->readableChannels($actor, $this->workspace, $principal)->pluck('id')->sort()->values()->all();
            $engine = collect($channels)
                ->filter(fn (Channel $c) => $service->decide($actor, 'channel.read', $c, onBehalfOf: $principal, workspace: $this->workspace, record: false)->allowed())
                ->pluck('id')->sort()->values()->all();

            $this->assertSame($engine, $scoped, "scope and engine disagree for {$actor->id} on behalf of ".($principal->id ?? 'nobody'));
        }
    }

    public function test_agent_channel_access_intersects_with_the_on_behalf_of_human(): void
    {
        $member = $this->human('member');
        $outsider = $this->human('member');
        $agent = User::factory()->agent()->create(['workspace_id' => $this->workspace->id]);
        $private = Channel::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'name' => 'p', 'type' => 'private']);
        foreach ([$member, $agent] as $user) {
            ChannelMember::create(['channel_id' => $private->id, 'user_id' => $user->id, 'role' => 'member']);
        }

        $resolver = app(AgentPermissionResolver::class);
        $onBehalfOf = app(OnBehalfOf::class);

        $this->assertTrue($onBehalfOf->runAs($member->id, fn () => $resolver->canAccessChannel($agent, $private->id))['allowed']);
        // Confused deputy: a human outside the channel cannot read it through the agent.
        $this->assertFalse($onBehalfOf->runAs($outsider->id, fn () => $resolver->canAccessChannel($agent, $private->id))['allowed']);
    }

    private function human(string $role): User
    {
        $user = User::factory()->create(['type' => 'human']);
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
