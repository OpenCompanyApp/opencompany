<?php

namespace Tests\Feature\Authorization;

use App\Domain\Authorization\Application\ApprovalResolutionDenied;
use App\Domain\Authorization\Application\ApprovalResolutionPolicy;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\IntegrationSetting;
use App\Models\Message;
use App\Models\TelegramInteraction;
use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\ApprovalExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Approval tenancy and resolver authority.
 *
 * Protects: approvals scoped by approvals.workspace_id (channel-less approvals
 * stay reachable in their own workspace and invisible elsewhere), and
 * ApprovalResolutionPolicy enforced inside ApprovalExecutionService::resolve()
 * for every transport: only admins/approvers, never the requester, never an
 * agent, and a refusal leaves the approval pending with no side effects.
 */
class ApprovalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function memberWithRole(string $role, ?Workspace $workspace = null): User
    {
        $user = User::factory()->create(['type' => 'human']);
        WorkspaceMember::create([
            'workspace_id' => ($workspace ?? $this->workspace)->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user;
    }

    /**
     * A pending approval whose approved execution would post a message, so a
     * test can prove "not executed" by counting messages in the channel.
     *
     * @return array{0: ApprovalRequest, 1: Channel, 2: User}
     */
    private function pendingExecutableApproval(?string $requesterId = null): array
    {
        $agent = User::factory()->agent()->create();
        $channel = Channel::factory()->create(['type' => 'public']);

        $approval = ApprovalRequest::factory()->pending()->create([
            'type' => 'action',
            'requester_id' => $requesterId ?? $agent->id,
            'channel_id' => $channel->id,
            'tool_execution_context' => [
                'tool_slug' => 'send_channel_message',
                'parameters' => ['channelId' => $channel->id, 'content' => 'executed!'],
            ],
        ]);

        return [$approval, $channel, $agent];
    }

    private function assertStillPending(ApprovalRequest $approval, Channel $channel): void
    {
        $fresh = $approval->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertNull($fresh->responded_by_id);
        $this->assertSame(0, Message::where('channel_id', $channel->id)->where('content', 'executed!')->count());
    }

    public function test_channel_less_approval_is_listed_in_its_workspace_only(): void
    {
        // ContactAgent-style access request: no channel. Channel-based scoping
        // made these invisible everywhere; workspace_id scoping fixes that.
        $agent = User::factory()->agent()->create();
        $approval = ApprovalRequest::factory()->pending()->create([
            'type' => 'access',
            'requester_id' => $agent->id,
            'channel_id' => null,
        ]);
        $this->assertSame($this->workspace->id, $approval->workspace_id);

        $admin = $this->memberWithRole('admin');
        $this->actingAs($admin)->getJson('/api/approvals')
            ->assertOk()
            ->assertJsonFragment(['id' => $approval->id]);
        $this->actingAs($admin)->getJson("/api/approvals/{$approval->id}")->assertOk();

        $other = Workspace::create(['id' => Str::uuid()->toString(), 'name' => 'B', 'slug' => 'b']);
        $outsider = $this->memberWithRole('admin', $other);
        // Select workspace B explicitly: the session still points at A, which
        // ResolveWorkspace would (correctly) reject with 403 for an outsider.
        $this->be($outsider)->withHeader('X-Workspace-Id', $other->id);
        $this->getJson('/api/approvals')->assertOk()->assertJsonMissing(['id' => $approval->id]);
        $this->getJson("/api/approvals/{$approval->id}")->assertNotFound();
        $this->patchJson("/api/approvals/{$approval->id}", ['status' => 'approved'])->assertNotFound();
        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_plain_member_gets_403_and_nothing_executes(): void
    {
        [$approval, $channel] = $this->pendingExecutableApproval();
        $member = $this->memberWithRole('member');

        $this->actingAs($member)->getJson('/api/approvals')
            ->assertOk()
            ->assertJsonFragment(['id' => $approval->id, 'can_resolve' => false]);

        $this->actingAs($member)->patchJson("/api/approvals/{$approval->id}", ['status' => 'approved'])
            ->assertForbidden()
            ->assertJson(['ok' => false, 'error' => ApprovalResolutionPolicy::DENY_ROLE]);

        $this->assertStillPending($approval, $channel);
    }

    public function test_approver_and_admin_can_resolve(): void
    {
        [$first] = $this->pendingExecutableApproval();
        [$second] = $this->pendingExecutableApproval();
        $approver = $this->memberWithRole('approver');
        $admin = $this->memberWithRole('admin');

        $this->actingAs($approver)->getJson("/api/approvals/{$first->id}")
            ->assertOk()
            ->assertJson(['can_resolve' => true]);

        $this->actingAs($approver)->patchJson("/api/approvals/{$first->id}", ['status' => 'rejected'])
            ->assertOk()
            ->assertJson(['status' => 'rejected', 'can_resolve' => false]);
        $this->assertSame($approver->id, $first->fresh()->responded_by_id);

        $this->actingAs($admin)->patchJson("/api/approvals/{$second->id}", ['status' => 'rejected'])->assertOk();
        $this->assertSame('rejected', $second->fresh()->status);
    }

    public function test_requester_cannot_self_approve_even_as_admin(): void
    {
        $admin = $this->memberWithRole('admin');
        [$approval, $channel] = $this->pendingExecutableApproval($admin->id);

        $this->actingAs($admin)->getJson("/api/approvals/{$approval->id}")
            ->assertOk()
            ->assertJson(['can_resolve' => false]);

        $this->actingAs($admin)->patchJson("/api/approvals/{$approval->id}", ['status' => 'approved'])
            ->assertForbidden()
            ->assertJson(['error' => ApprovalResolutionPolicy::DENY_SELF]);

        $this->assertStillPending($approval, $channel);
    }

    public function test_agent_and_unattributed_callers_cannot_resolve_via_service(): void
    {
        // The service is the shared enforcement point for every transport,
        // so prove it directly rather than only through the HTTP controller.
        [$approval, $channel, $agent] = $this->pendingExecutableApproval();
        $otherAgent = User::factory()->agent()->create();
        $service = app(ApprovalExecutionService::class);

        foreach ([$agent, $otherAgent, null] as $responder) {
            try {
                $service->resolve($approval, 'approved', $responder);
                $this->fail('Expected ApprovalResolutionDenied.');
            } catch (ApprovalResolutionDenied $denied) {
                $this->assertContains($denied->reason, [
                    ApprovalResolutionPolicy::DENY_AGENT,
                    ApprovalResolutionPolicy::DENY_UNATTRIBUTED,
                ]);
            }
        }

        $this->assertStillPending($approval, $channel);
    }

    public function test_telegram_callback_refuses_plain_member_without_executing(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $setting = $this->telegramSetting();
        $member = $this->memberWithRole('member');
        $this->linkTelegram($member, '111');
        [$approval, $channel] = $this->pendingExecutableApproval();

        $interaction = TelegramInteraction::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'approval',
            'target_type' => ApprovalRequest::class,
            'target_id' => $approval->id,
            'payload' => ['action' => 'approve', 'approval_id' => $approval->id],
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 4242,
                'callback_query' => [
                    'id' => 'cb-deny',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Member'],
                    'message' => ['message_id' => 81, 'chat' => ['id' => 12345, 'type' => 'private']],
                    'data' => $interaction->token,
                ],
            ])
            ->assertOk();

        $this->assertStillPending($approval, $channel);
        $this->assertNull($interaction->fresh()->final_status);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'answerCallbackQuery')
            && str_contains((string) ($request['text'] ?? ''), 'admins and approvers'));
    }

    public function test_telegram_mini_app_refuses_plain_member_with_403(): void
    {
        $this->telegramSetting();
        $member = $this->memberWithRole('member');
        $this->linkTelegram($member, '111');
        [$approval, $channel] = $this->pendingExecutableApproval();

        $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $this->signedTelegramInitData([
                'user' => json_encode(['id' => 111, 'first_name' => 'Member']),
            ]),
            'action' => 'approve_approval',
            'approval_id' => $approval->id,
        ])
            ->assertForbidden()
            ->assertJson(['ok' => false, 'error' => ApprovalResolutionPolicy::DENY_ROLE]);

        $this->assertStillPending($approval, $channel);
    }

    public function test_approver_role_is_assignable_by_admins(): void
    {
        $admin = $this->memberWithRole('admin');
        $member = $this->memberWithRole('member');
        $membership = WorkspaceMember::where('user_id', $member->id)->firstOrFail();

        $this->actingAs($admin)->patchJson("/api/workspace/members/{$membership->id}/role", ['role' => 'approver'])
            ->assertOk();
        $this->assertSame('approver', $membership->fresh()->role);

        $this->actingAs($admin)->patchJson("/api/workspace/members/{$membership->id}/role", ['role' => 'owner'])
            ->assertUnprocessable();
    }

    private function telegramSetting(): IntegrationSetting
    {
        return IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'telegram',
            'account_alias' => '',
            'config' => [
                'api_key' => 'telegram-token',
                'webhook_secret' => 'telegram-secret',
                'bot_user_id' => '999',
                'bot_username' => 'OpenCompanyBot',
            ],
            'enabled' => true,
            'is_default' => true,
        ]);
    }

    private function linkTelegram(User $user, string $externalId): void
    {
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'provider' => 'telegram',
            'external_id' => $externalId,
            'display_name' => $user->name,
        ]);
    }

    /** @param  array<string, string>  $params */
    private function signedTelegramInitData(array $params, string $botToken = 'telegram-token'): string
    {
        $params['auth_date'] ??= (string) now()->timestamp;
        ksort($params);
        $dataCheckString = collect($params)->map(fn ($value, $key) => $key.'='.$value)->implode("\n");
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $params['hash'] = hash_hmac('sha256', $dataCheckString, $secretKey);

        return http_build_query($params);
    }
}
