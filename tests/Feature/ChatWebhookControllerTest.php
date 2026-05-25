<?php

namespace Tests\Feature;

use App\Agents\Tools\Chat\ReadRecentMessages;
use App\Agents\Tools\Files\ListFiles;
use App\Domain\Chat\Telegram\Application\TelegramCardRenderer;
use App\Domain\Chat\Telegram\Application\TelegramNotificationRouter;
use App\Domain\Chat\Telegram\Application\TelegramRateLimitException;
use App\Domain\Chat\Telegram\Application\TelegramSetupService;
use App\Ai\Agents\OneShotTextAgent;
use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessagePinned;
use App\Events\MessageReactionAdded;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Jobs\AgentRespondJob;
use App\Jobs\EnrichTelegramMediaJob;
use App\Jobs\RunAutomationJob;
use App\Jobs\SendApprovalToTelegramJob;
use App\Listeners\SyncToChat;
use App\Models\ApprovalRequest;
use App\Models\AgentPermission;
use App\Models\Automation;
use App\Models\CalendarEvent;
use App\Models\Channel;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\IntegrationSetting;
use App\Models\ListItem;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\TelegramConversation;
use App\Models\TelegramDelivery;
use App\Models\TelegramIntegrationProfile;
use App\Models\TelegramInteraction;
use App\Models\TelegramSubscription;
use App\Models\TelegramUpdateReceipt;
use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Models\Workspace;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceMember;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request as ToolRequest;
use Laravel\Ai\Transcription;
use Tests\TestCase;

/**
 * Protects workspace resolution for unauthenticated external chat webhooks.
 */
class ChatWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_webhook_uses_opencompany_owned_pipeline_and_persists_update(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $this->telegramSetting([
            'default_agent_id' => $agent->id,
            'bot_username' => 'OpenCompanyBot',
            'bot_user_id' => '999',
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1001,
                'message' => [
                    'message_id' => 77,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'text' => 'Investigate revenue churn',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('telegram_update_receipts', [
            'integration_setting_id' => IntegrationSetting::where('integration_id', 'telegram')->value('id'),
            'update_id' => '1001',
            'update_type' => 'message',
            'status' => 'processed',
        ]);

        $this->assertDatabaseHas('telegram_conversations', [
            'chat_id' => '12345',
            'chat_type' => 'private',
        ]);

        $this->assertDatabaseHas('channels', [
            'workspace_id' => $this->workspace->id,
            'external_provider' => 'telegram',
            'external_id' => '12345',
            'type' => 'external',
        ]);

        $this->assertDatabaseHas('messages', [
            'content' => 'Investigate revenue churn',
            'source' => 'telegram',
            'external_message_id' => '77',
        ]);

        Queue::assertPushed(AgentRespondJob::class);
    }

    public function test_telegram_lane_queue_starts_next_task_after_active_completion(): void
    {
        Queue::fake();

        $setting = $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Product',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345',
        ]);
        $trigger = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Run after the current Telegram task',
            'channel_id' => $channel->id,
            'author_id' => $human->id,
            'timestamp' => now(),
            'source' => 'telegram',
        ]);
        $finished = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Current Telegram task',
            'status' => Task::STATUS_COMPLETED,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'channel_id' => $channel->id,
            'completed_at' => now(),
        ]);
        $queued = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Run after the current Telegram task',
            'status' => Task::STATUS_PENDING,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'channel_id' => $channel->id,
            'trigger_message_id' => $trigger->id,
            'context' => [
                'telegram_lane_queue' => [
                    'open_count' => 1,
                    'active_count' => 1,
                    'pending_count' => 0,
                    'ahead_task_id' => $finished->id,
                    'ahead_title' => $finished->title,
                ],
            ],
        ]);

        app(TelegramNotificationRouter::class)->handleTaskUpdated(new TaskUpdated($finished, 'completed'));

        Queue::assertPushed(AgentRespondJob::class);
        $this->assertSame(
            $finished->id,
            $queued->fresh()->context['telegram_lane_queue']['dispatched_after_task_id'] ?? null,
        );
    }

    public function test_telegram_update_receipts_are_idempotent(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $agent = User::factory()->agent()->create();
        $this->telegramSetting(['default_agent_id' => $agent->id]);

        $payload = [
            'update_id' => 1002,
            'message' => [
                'message_id' => 78,
                'date' => now()->timestamp,
                'chat' => ['id' => 12345, 'type' => 'private'],
                'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                'text' => 'Only process once',
            ],
        ];

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', $payload)
            ->assertOk();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', $payload)
            ->assertOk();

        $this->assertSame(1, TelegramUpdateReceipt::where('update_id', '1002')->count());
        $receipt = TelegramUpdateReceipt::where('update_id', '1002')->firstOrFail();
        $this->assertSame(1, $receipt->duplicate_count);
        $this->assertNotNull($receipt->last_duplicate_at);
        $this->assertSame(1, Message::where('external_message_id', '78')->count());
        Queue::assertPushed(AgentRespondJob::class, 1);
    }

    public function test_telegram_inbound_agent_dispatch_sends_run_card_with_lifecycle_tokens(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 609]])]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $human->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $this->telegramSetting(['default_agent_id' => $agent->id]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1009,
                'message' => [
                    'message_id' => 84,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'text' => 'Investigate enterprise expansion',
                ],
            ])
            ->assertOk();

        $task = Task::where('title', 'Investigate enterprise expansion')->firstOrFail();
        $pause = TelegramInteraction::where('interaction_type', 'task')
            ->where('target_id', $task->id)
            ->where('payload->action', 'pause')
            ->firstOrFail();

        $this->assertDatabaseHas('telegram_deliveries', [
            'source_type' => Task::class,
            'source_id' => $task->id,
            'renderer_version' => 'telegram-run-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) use ($pause) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $markup = json_decode((string) $request['reply_markup'], true);

            return str_contains($request['text'], 'Investigate enterprise expansion')
                && str_contains($request['text'], 'Iris')
                && str_contains(json_encode($markup), $pause->token);
        });

        Queue::assertPushed(AgentRespondJob::class);
    }

    public function test_telegram_inbound_agent_dispatch_can_send_ephemeral_draft_preview(): void
    {
        config(['telegram.draft_streaming_enabled' => true]);
        Queue::fake();
        Http::fake([
            'api.telegram.org/*/sendMessageDraft' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 611]]),
            'api.telegram.org/*/sendChatAction' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $this->telegramSetting(['default_agent_id' => $agent->id]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1019,
                'message' => [
                    'message_id' => 86,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'text' => 'Draft-stream this agent run',
                ],
            ])
            ->assertOk();

        $task = Task::where('title', 'Draft-stream this agent run')->firstOrFail();
        $delivery = TelegramDelivery::where('source_id', $task->id)
            ->where('renderer_version', 'telegram-draft-stream:v1')
            ->firstOrFail();

        $this->assertSame('sent', $delivery->status);
        $this->assertSame('sendMessageDraft', $delivery->request_payload['method']);
        $this->assertSame('', $delivery->request_payload['text']);
        $this->assertGreaterThan(0, $delivery->request_payload['draft_id']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessageDraft')
            && (string) $request['chat_id'] === '12345'
            && (string) $request['text'] === ''
            && (int) $request['draft_id'] === $delivery->request_payload['draft_id']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Draft-stream this agent run'));
        Queue::assertPushed(AgentRespondJob::class);
    }

    public function test_telegram_inbound_agent_dispatch_surfaces_lane_queue_state(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 612]])]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        $this->telegramSetting(['default_agent_id' => $agent->id]);

        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Rutger',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345',
        ]);

        Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'channel_id' => $channel->id,
            'title' => 'Existing long-running Telegram request',
            'description' => 'Already running in this Telegram lane.',
            'status' => Task::STATUS_ACTIVE,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'started_at' => now()->subMinutes(3),
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1021,
                'message' => [
                    'message_id' => 101,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'text' => 'Add this after the current analysis',
                ],
            ])
            ->assertOk();

        $queuedTask = Task::where('title', 'Add this after the current analysis')->firstOrFail();
        $queueState = $queuedTask->context['telegram_lane_queue'] ?? [];

        $this->assertSame(1, $queueState['open_count'] ?? null);
        $this->assertSame(1, $queueState['active_count'] ?? null);
        $this->assertSame('Existing long-running Telegram request', $queueState['ahead_title'] ?? null);

        $this->assertDatabaseHas('telegram_deliveries', [
            'source_type' => Task::class,
            'source_id' => $queuedTask->id,
            'renderer_version' => TelegramCardRenderer::RUN_CARD_VERSION,
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Waiting behind other work')
            && ! str_contains($request['text'], 'Queue:')
            && ! str_contains($request['text'], 'First ahead')
            && ! str_contains($request['text'], 'Existing long-running Telegram request'));
        Queue::assertNotPushed(AgentRespondJob::class);
    }

    public function test_telegram_modern_update_types_are_persisted_and_diagnosed_without_dispatch(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $setting = $this->telegramSetting();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1007,
                'my_chat_member' => [
                    'chat' => ['id' => -100777, 'type' => 'supergroup', 'title' => 'Ops'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'date' => now()->timestamp,
                    'old_chat_member' => ['status' => 'member', 'user' => ['id' => 999, 'is_bot' => true]],
                    'new_chat_member' => ['status' => 'kicked', 'user' => ['id' => 999, 'is_bot' => true]],
                ],
            ])
            ->assertOk();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1008,
                'business_connection' => [
                    'id' => 'biz-1',
                    'user' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Customer'],
                    'date' => now()->timestamp,
                    'can_reply' => true,
                    'is_enabled' => true,
                ],
            ])
            ->assertOk();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1009,
                'purchased_paid_media' => [
                    'from' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Customer'],
                    'paid_media_payload' => 'invoice:123',
                ],
            ])
            ->assertOk();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1010,
                'shipping_query' => [
                    'id' => 'ship-1',
                    'from' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Customer'],
                    'invoice_payload' => 'invoice:123',
                    'shipping_address' => ['country_code' => 'BE'],
                ],
            ])
            ->assertOk();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1011,
                'pre_checkout_query' => [
                    'id' => 'checkout-1',
                    'from' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Customer'],
                    'currency' => 'XTR',
                    'total_amount' => 250,
                    'invoice_payload' => 'invoice:123',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('telegram_update_receipts', [
            'integration_setting_id' => $setting->id,
            'update_id' => '1007',
            'update_type' => 'my_chat_member',
            'status' => 'processed',
        ]);
        $this->assertDatabaseHas('telegram_conversations', [
            'chat_id' => '-100777',
            'chat_type' => 'supergroup',
        ]);
        $this->assertDatabaseHas('telegram_update_receipts', [
            'integration_setting_id' => $setting->id,
            'update_id' => '1009',
            'update_type' => 'purchased_paid_media',
            'status' => 'processed',
        ]);
        $this->assertDatabaseHas('telegram_update_receipts', [
            'integration_setting_id' => $setting->id,
            'update_id' => '1010',
            'update_type' => 'shipping_query',
            'status' => 'processed',
        ]);
        $this->assertDatabaseHas('telegram_update_receipts', [
            'integration_setting_id' => $setting->id,
            'update_id' => '1011',
            'update_type' => 'pre_checkout_query',
            'status' => 'processed',
        ]);

        $profile = TelegramIntegrationProfile::where('integration_setting_id', $setting->id)->firstOrFail();
        $this->assertSame('degraded', $profile->health_status);
        $this->assertSame('pre_checkout_query', $profile->capabilities['last_unsupported_update']['type']);
        $this->assertSame('kicked', $profile->capabilities['last_chat_member_update']['status']);
        Queue::assertNothingPushed();
    }

    public function test_telegram_guest_updates_are_feature_flagged_and_do_not_dispatch_when_disabled(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
        config(['telegram.guest_mode_enabled' => false]);

        $setting = $this->telegramSetting();

        $this->assertNotContains('guest_message', app(TelegramSetupService::class)->allowedUpdates());
        config(['telegram.guest_mode_enabled' => true]);
        $this->assertContains('guest_message', app(TelegramSetupService::class)->allowedUpdates());
        config(['telegram.guest_mode_enabled' => false]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1010,
                'guest_message' => [
                    'message_id' => 501,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Guest'],
                    'from' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Guest'],
                    'text' => 'Guest wants support',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('telegram_update_receipts', [
            'integration_setting_id' => $setting->id,
            'update_id' => '1010',
            'update_type' => 'guest_message',
            'status' => 'processed',
        ]);

        $profile = TelegramIntegrationProfile::where('integration_setting_id', $setting->id)->firstOrFail();
        $this->assertSame('guest_message', $profile->capabilities['last_disabled_update']['type']);
        $this->assertSame('guest_mode_enabled', $profile->capabilities['last_disabled_update']['feature_flag']);
        Queue::assertNothingPushed();
    }

    public function test_telegram_guest_message_answers_with_bounded_guest_reply_when_enabled(): void
    {
        Queue::fake();
        Cache::flush();
        config([
            'telegram.guest_mode_enabled' => true,
            'telegram.guest_reply_text' => 'OpenCompany guest access is limited. Open the bot and run /link.',
        ]);

        $setting = $this->telegramSetting();

        Http::fake([
            'api.telegram.org/*/answerGuestQuery' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 9001,
                    'date' => now()->timestamp,
                ],
            ]),
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1011,
                'guest_message' => [
                    'message_id' => 502,
                    'guest_query_id' => 'guest-query-1',
                    'date' => now()->timestamp,
                    'chat' => ['id' => -100555, 'type' => 'supergroup', 'title' => 'Public Chat'],
                    'guest_bot_caller_user' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Guest'],
                    'text' => 'Can the agent help here?',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('telegram_update_receipts', [
            'integration_setting_id' => $setting->id,
            'update_id' => '1011',
            'update_type' => 'guest_message',
            'status' => 'processed',
        ]);
        $this->assertDatabaseHas('telegram_deliveries', [
            'integration_setting_id' => $setting->id,
            'chat_id' => '-100555',
            'renderer_version' => 'telegram-guest-reply:v1',
            'status' => 'sent',
        ]);
        $this->assertSame(0, Message::count());

        $profile = TelegramIntegrationProfile::where('integration_setting_id', $setting->id)->firstOrFail();
        $this->assertSame('answered', $profile->capabilities['last_guest_message']['status']);
        $this->assertTrue($profile->capabilities['last_guest_message']['answered']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/answerGuestQuery')) {
                return false;
            }

            $result = json_decode((string) $request['result'], true);

            return $request['guest_query_id'] === 'guest-query-1'
                && ($result['type'] ?? null) === 'article'
                && ($result['input_message_content']['message_text'] ?? null) === 'OpenCompany guest access is limited. Open the bot and run /link.';
        });
        Queue::assertNothingPushed();
    }

    public function test_telegram_guest_message_rate_limit_prevents_repeated_guest_replies(): void
    {
        Queue::fake();
        Cache::flush();
        config([
            'telegram.guest_mode_enabled' => true,
            'telegram.guest_rate_limit_seconds' => 300,
        ]);

        $setting = $this->telegramSetting();

        Http::fake([
            'api.telegram.org/*/answerGuestQuery' => Http::response(['ok' => true, 'result' => ['message_id' => 9001]]),
        ]);

        foreach ([1012 => 'guest-query-2', 1013 => 'guest-query-3'] as $updateId => $queryId) {
            $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
                ->postJson('/api/webhooks/chat/telegram', [
                    'update_id' => $updateId,
                    'guest_message' => [
                        'message_id' => $updateId,
                        'guest_query_id' => $queryId,
                        'date' => now()->timestamp,
                        'chat' => ['id' => -100555, 'type' => 'supergroup', 'title' => 'Public Chat'],
                        'guest_bot_caller_user' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Guest'],
                        'text' => 'Repeated guest request',
                    ],
                ])
                ->assertOk();
        }

        $this->assertSame(1, TelegramDelivery::where('renderer_version', 'telegram-guest-reply:v1')->count());

        $profile = TelegramIntegrationProfile::where('integration_setting_id', $setting->id)->firstOrFail();
        $this->assertSame('rate_limited', $profile->capabilities['last_guest_message']['status']);
        $this->assertFalse($profile->capabilities['last_guest_message']['answered']);

        Http::assertSentCount(1);
        Queue::assertNothingPushed();
    }

    public function test_telegram_inline_query_answers_with_safe_launcher_results(): void
    {
        Queue::fake();

        $setting = $this->telegramSetting();
        $human = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $human->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        Http::fake([
            'api.telegram.org/*/answerInlineQuery' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1014,
                'inline_query' => [
                    'id' => 'inline-query-1',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'query' => 'tasks',
                    'offset' => '',
                    'chat_type' => 'private',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('telegram_update_receipts', [
            'integration_setting_id' => $setting->id,
            'update_id' => '1014',
            'update_type' => 'inline_query',
            'status' => 'processed',
        ]);
        $this->assertDatabaseHas('telegram_deliveries', [
            'integration_setting_id' => $setting->id,
            'chat_id' => 'inline:111',
            'renderer_version' => 'telegram-inline-query:v1',
            'status' => 'sent',
        ]);

        $profile = TelegramIntegrationProfile::where('integration_setting_id', $setting->id)->firstOrFail();
        $this->assertSame('answered', $profile->capabilities['last_inline_query']['status']);
        $this->assertTrue($profile->capabilities['last_inline_query']['linked_workspace_member']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/answerInlineQuery')) {
                return false;
            }

            $results = json_decode((string) $request['results'], true);

            return $request['inline_query_id'] === 'inline-query-1'
                && $request['is_personal'] === true
                && collect($results)->pluck('id')->contains('opencompany-agent-home')
                && collect($results)->pluck('id')->contains('opencompany-approvals')
                && ! collect($results)->pluck('id')->contains('opencompany-tasks');
        });
        Queue::assertNothingPushed();
    }

    public function test_telegram_chosen_inline_result_is_persisted_as_diagnostic_without_dispatch(): void
    {
        Queue::fake();

        $setting = $this->telegramSetting();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1015,
                'chosen_inline_result' => [
                    'result_id' => 'opencompany-command-center',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'inline_message_id' => 'inline-message-1',
                    'query' => 'tasks',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('telegram_update_receipts', [
            'integration_setting_id' => $setting->id,
            'update_id' => '1015',
            'update_type' => 'chosen_inline_result',
            'status' => 'processed',
        ]);

        $profile = TelegramIntegrationProfile::where('integration_setting_id', $setting->id)->firstOrFail();
        $this->assertSame('opencompany-command-center', $profile->capabilities['last_chosen_inline_result']['result_id']);
        $this->assertSame('111', $profile->capabilities['last_chosen_inline_result']['from_id']);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_telegram_reaction_updates_replace_and_clear_actor_reactions(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $setting = $this->telegramSetting();
        $user = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Product',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345',
        ]);
        TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'chat_type' => 'private',
            'channel_id' => $channel->id,
        ]);
        $message = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Reactable Telegram message',
            'channel_id' => $channel->id,
            'author_id' => $user->id,
            'timestamp' => now(),
            'source' => 'telegram',
            'external_message_id' => '501',
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1501,
                'message_reaction' => [
                    'chat' => ['id' => 12345, 'type' => 'private'],
                    'message_id' => 501,
                    'user' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'date' => now()->timestamp,
                    'old_reaction' => [],
                    'new_reaction' => [
                        ['type' => 'emoji', 'emoji' => '👍'],
                        ['type' => 'emoji', 'emoji' => '🔥'],
                    ],
                ],
            ])
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            ['🔥', '👍'],
            MessageReaction::where('message_id', $message->id)->where('user_id', $user->id)->pluck('emoji')->all(),
        );

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1502,
                'message_reaction' => [
                    'chat' => ['id' => 12345, 'type' => 'private'],
                    'message_id' => 501,
                    'user' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'date' => now()->timestamp,
                    'old_reaction' => [
                        ['type' => 'emoji', 'emoji' => '👍'],
                        ['type' => 'emoji', 'emoji' => '🔥'],
                    ],
                    'new_reaction' => [],
                ],
            ])
            ->assertOk();

        $this->assertSame(0, MessageReaction::where('message_id', $message->id)->where('user_id', $user->id)->count());
        Queue::assertNothingPushed();
    }

    public function test_telegram_allowlist_rejects_before_creating_workspace_channel_history(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 501]])]);

        $this->telegramSetting([
            'allowed_users' => ['999999'],
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1003,
                'message' => [
                    'message_id' => 79,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'text' => 'Should be rejected',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('telegram_update_receipts', [
            'update_id' => '1003',
            'status' => 'processed',
        ]);

        $this->assertDatabaseMissing('channels', [
            'workspace_id' => $this->workspace->id,
            'external_provider' => 'telegram',
            'external_id' => '12345',
        ]);

        $this->assertDatabaseMissing('messages', [
            'content' => 'Should be rejected',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_telegram_group_messages_require_entity_mention_by_default(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $agent = User::factory()->agent()->create();
        $this->telegramSetting([
            'default_agent_id' => $agent->id,
            'bot_username' => 'OpenCompanyBot',
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1004,
                'message' => [
                    'message_id' => 80,
                    'date' => now()->timestamp,
                    'chat' => ['id' => -100123, 'type' => 'supergroup', 'title' => 'Product'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'text' => 'OpenCompanyBot without a Telegram entity should not dispatch',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('telegram_conversations', [
            'chat_id' => '-100123',
            'chat_type' => 'supergroup',
        ]);
        $this->assertDatabaseMissing('messages', [
            'content' => 'OpenCompanyBot without a Telegram entity should not dispatch',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_telegram_group_commands_addressed_to_another_bot_are_ignored(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $agent = User::factory()->agent()->create();
        $this->telegramSetting([
            'default_agent_id' => $agent->id,
            'bot_username' => 'OpenCompanyBot',
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1005,
                'message' => [
                    'message_id' => 81,
                    'date' => now()->timestamp,
                    'chat' => ['id' => -100123, 'type' => 'supergroup', 'title' => 'Product'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'text' => '/status@OtherBot',
                    'entities' => [[
                        'type' => 'bot_command',
                        'offset' => 0,
                        'length' => 16,
                    ]],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('telegram_conversations', [
            'chat_id' => '-100123',
            'chat_type' => 'supergroup',
        ]);
        $this->assertDatabaseMissing('telegram_deliveries', [
            'chat_id' => '-100123',
            'renderer_version' => 'telegram-outbound-sync:v1',
        ]);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_telegram_group_reply_to_bot_message_continues_agent_lane(): void
    {
        Queue::fake();
        Http::fake([
            'api.telegram.org/*/sendChatAction' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 611]]),
        ]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $this->telegramSetting([
            'default_agent_id' => $agent->id,
            'bot_username' => 'OpenCompanyBot',
            'bot_user_id' => '999',
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1006,
                'message' => [
                    'message_id' => 82,
                    'date' => now()->timestamp,
                    'chat' => ['id' => -100123, 'type' => 'supergroup', 'title' => 'Product'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'reply_to_message' => [
                        'message_id' => 50,
                        'from' => ['id' => 999, 'is_bot' => true, 'username' => 'OpenCompanyBot'],
                        'text' => 'Previous OpenCompany bot answer',
                    ],
                    'text' => 'Continue this thread without mentioning the bot.',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('messages', [
            'content' => 'Continue this thread without mentioning the bot.',
            'source' => 'telegram',
            'external_message_id' => '82',
        ]);
        $this->assertDatabaseHas('telegram_deliveries', [
            'chat_id' => '-100123',
            'renderer_version' => 'telegram-run-card:v1',
            'status' => 'sent',
        ]);
        Queue::assertPushed(AgentRespondJob::class);
    }

    public function test_telegram_topic_commands_control_group_lane_modes(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 610]])]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $human->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $this->telegramSetting([
            'default_agent_id' => $agent->id,
            'bot_username' => 'OpenCompanyBot',
        ]);

        $chat = ['id' => -100123, 'type' => 'supergroup', 'title' => 'Product'];
        $this->postTelegramCommand('/topic observe', updateId: 1010, messageId: 90, chat: $chat, threadId: 55);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1011,
                'message' => [
                    'message_id' => 91,
                    'message_thread_id' => 55,
                    'date' => now()->timestamp,
                    'chat' => $chat,
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'text' => 'Unmentioned context for later.',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('telegram_conversations', [
            'chat_id' => '-100123',
            'topic_id' => '55',
            'mode' => 'observed',
            'observed_context_enabled' => true,
        ]);
        $this->assertDatabaseHas('messages', [
            'content' => 'Unmentioned context for later.',
            'source' => 'telegram_observed',
            'external_message_id' => '91',
        ]);
        Queue::assertNotPushed(AgentRespondJob::class);

        $this->postTelegramCommand('/topic free', updateId: 1012, messageId: 92, chat: $chat, threadId: 55);
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1013,
                'message' => [
                    'message_id' => 93,
                    'message_thread_id' => 55,
                    'date' => now()->timestamp,
                    'chat' => $chat,
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'text' => 'Free-response prompt.',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('messages', [
            'content' => 'Free-response prompt.',
            'source' => 'telegram',
            'external_message_id' => '93',
        ]);
        Queue::assertPushed(AgentRespondJob::class);

        $this->postTelegramCommand('/topic ignore', updateId: 1014, messageId: 94, chat: $chat, threadId: 55);
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1015,
                'message' => [
                    'message_id' => 95,
                    'message_thread_id' => 55,
                    'date' => now()->timestamp,
                    'chat' => $chat,
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'text' => '@OpenCompanyBot this should be ignored',
                    'entities' => [[
                        'type' => 'mention',
                        'offset' => 0,
                        'length' => 15,
                    ]],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('messages', [
            'external_message_id' => '95',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && ($request->data()['message_thread_id'] ?? null) === 55
            && str_contains($request['text'], 'remember context'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'chat_id' => '-100123',
            'topic_id' => '55',
            'renderer_version' => 'telegram-topic-card:v1',
            'status' => 'sent',
        ]);

        // Agent names are common phone-typed command targets. This guards the
        // PostgreSQL UUID column from receiving non-UUID text comparisons while
        // still allowing name lookup.
        $this->postTelegramCommand('/topic agent Iris', updateId: 1016, messageId: 96, chat: $chat, threadId: 55);

        $this->assertDatabaseHas('telegram_conversations', [
            'chat_id' => '-100123',
            'topic_id' => '55',
            'default_agent_id' => $agent->id,
            'archived_at' => null,
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && ($request->data()['message_thread_id'] ?? null) === 55
            && str_contains($request['text'], 'Iris is now active in this lane'));
    }

    public function test_telegram_lane_cancel_and_resume_commands_store_stable_renderer_versions(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 611]])]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        $this->telegramSetting(['default_agent_id' => $agent->id]);

        $this->postTelegramCommand('/settings', updateId: 1017, messageId: 97);

        $conversation = TelegramConversation::where('chat_id', '12345')->firstOrFail();
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Lane lifecycle',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345',
        ]);
        $conversation->update(['channel_id' => $channel->id]);

        $activeTask = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'channel_id' => $channel->id,
            'title' => 'Telegram lane active task',
            'description' => 'Cancel through Telegram.',
            'status' => Task::STATUS_ACTIVE,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
        ]);
        TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => IntegrationSetting::where('integration_id', 'telegram')->value('id'),
            'source_type' => Task::class,
            'source_id' => $activeTask->id,
            'chat_id' => '12345',
            'telegram_message_id' => '650',
            'renderer_version' => TelegramCardRenderer::RUN_CARD_VERSION,
            'parse_mode' => 'HTML',
            'status' => 'sent',
            'request_payload' => ['method' => 'sendMessage'],
            'sent_at' => now()->subMinute(),
        ]);

        $this->postTelegramCommand('/cancel', updateId: 1018, messageId: 98);

        $pausedTask = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'channel_id' => $channel->id,
            'title' => 'Telegram lane paused task',
            'description' => 'Resume through Telegram.',
            'status' => Task::STATUS_PAUSED,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
        ]);

        $this->postTelegramCommand('/resume', updateId: 1019, messageId: 99);

        $this->assertSame(Task::STATUS_CANCELLED, $activeTask->fresh()->status);
        $this->assertSame(Task::STATUS_ACTIVE, $pausedTask->fresh()->status);
        $cancelledDelivery = TelegramDelivery::where('source_id', $activeTask->id)
            ->where('renderer_version', TelegramCardRenderer::TASK_CARD_UPDATED_VERSION)
            ->firstOrFail();
        $this->assertSame('cancelled', $cancelledDelivery->request_payload['lifecycle_action']);
        $this->assertSame(['open'], $cancelledDelivery->request_payload['buttons']);

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-cancel-command-card:v1',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-resume-command-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Stopped current work.'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Resumed paused work.'));
    }

    public function test_telegram_plain_feedback_uses_notice_renderer_version(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 612]])]);

        $this->enableTelegramDirectResourceCommands();
        $this->telegramSetting();

        $this->postTelegramCommand('/task', updateId: 1020, messageId: 100);

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-notice-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Usage')
            && str_contains($request['text'], '/task &lt;id&gt;'));
    }

    public function test_telegram_persisted_interaction_token_resolves_approval(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $setting = $this->telegramSetting();
        $agent = User::factory()->agent()->create();
        $responder = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $responder->id,
            'role' => 'member',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $responder->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $approval = ApprovalRequest::factory()->pending()->create([
            'requester_id' => $agent->id,
        ]);

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
                'update_id' => 1005,
                'callback_query' => [
                    'id' => 'cb-1',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'message' => [
                        'message_id' => 81,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $interaction->token,
                ],
            ])
            ->assertOk();

        $approval->refresh();
        $interaction->refresh();

        $this->assertSame('approved', $approval->status);
        $this->assertSame($responder->id, $approval->responded_by_id);
        $this->assertSame('approved', $interaction->final_status);
        $this->assertNotNull($interaction->resolved_at);
    }

    public function test_telegram_approval_inspect_redacts_sensitive_tool_arguments(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $setting = $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $responder = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $responder->id,
            'role' => 'member',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $responder->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $approval = ApprovalRequest::factory()->pending()->create([
            'requester_id' => $agent->id,
            'title' => 'Inspect vendor renewal',
            'amount' => 1250,
            'tool_execution_context' => [
                'tool_slug' => 'send_channel_message',
                'parameters' => [
                    'channelId' => 'chan_123',
                    'content' => 'Renewal approved',
                    'api_key' => 'must-not-leak',
                ],
            ],
        ]);

        $interaction = TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'approval',
            'target_type' => ApprovalRequest::class,
            'target_id' => $approval->id,
            'payload' => ['action' => 'inspect', 'approval_id' => $approval->id],
            'expires_at' => now()->addMinutes(10),
        ]));

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 10051,
                'callback_query' => [
                    'id' => 'cb-inspect-redacted',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'message' => [
                        'message_id' => 810,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $interaction->token,
                ],
            ])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Approval detail')
            && str_contains($request['text'], 'send_channel_message')
            && str_contains($request['text'], 'api_key=[redacted]')
            && str_contains($request['text'], 'high')
            && ! str_contains($request['text'], 'must-not-leak'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-approval-inspect-card:v1',
            'status' => 'sent',
        ]);
    }

    public function test_telegram_raw_legacy_approval_callback_does_not_mutate_state(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $this->telegramSetting();
        $agent = User::factory()->agent()->create();
        $responder = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $responder->id,
            'role' => 'member',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $responder->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $approval = ApprovalRequest::factory()->pending()->create([
            'requester_id' => $agent->id,
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1008,
                'callback_query' => [
                    'id' => 'legacy-approval-cb',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'message' => [
                        'message_id' => 83,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => 'approve:'.$approval->id,
                ],
            ])
            ->assertOk();

        $approval->refresh();

        $this->assertSame('pending', $approval->status);
        $this->assertNull($approval->responded_by_id);
        $this->assertSame(0, TelegramInteraction::where('target_id', $approval->id)->count());
        Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
            && str_contains((string) ($request->data()['text'] ?? ''), 'no longer valid'));
    }

    public function test_telegram_callback_token_is_bound_to_authenticated_integration_setting(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $this->telegramSetting();
        $secondarySetting = IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'telegram',
            'account_alias' => 'secondary',
            'config' => [
                'api_key' => 'telegram-token-2',
                'webhook_secret' => 'telegram-secret-2',
                'bot_user_id' => '1000',
                'bot_username' => 'OtherOpenCompanyBot',
            ],
            'enabled' => true,
            'is_default' => false,
        ]);
        $agent = User::factory()->agent()->create();
        $responder = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $responder->id,
            'role' => 'member',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $responder->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $approval = ApprovalRequest::factory()->pending()->create([
            'requester_id' => $agent->id,
        ]);
        $interaction = TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $secondarySetting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'approval',
            'target_type' => ApprovalRequest::class,
            'target_id' => $approval->id,
            'payload' => ['action' => 'approve', 'approval_id' => $approval->id],
            'allowed_actor_rule' => ['type' => 'linked_workspace_member'],
            'expires_at' => now()->addMinutes(10),
        ]));

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1009,
                'callback_query' => [
                    'id' => 'wrong-setting-cb',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'message' => [
                        'message_id' => 84,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $interaction->token,
                ],
            ])
            ->assertOk();

        $approval->refresh();
        $interaction->refresh();

        $this->assertSame('pending', $approval->status);
        $this->assertNull($approval->responded_by_id);
        $this->assertNull($interaction->resolved_at);
    }

    public function test_telegram_approval_resolution_edits_original_delivery_to_resolved_card(): void
    {
        Queue::fake();
        Http::fake([
            'api.telegram.org/*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/editMessageText' => Http::sequence()
                ->push([
                    'ok' => false,
                    'error_code' => 400,
                    'description' => "Bad Request: can't parse entities: malformed HTML",
                ], 400)
                ->push(['ok' => true, 'result' => ['message_id' => 700]]),
            'api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 701]]),
        ]);

        $setting = $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $responder = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $responder->id,
            'role' => 'member',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $responder->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $approvalChannel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Product',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '555:44',
        ]);
        $approval = ApprovalRequest::factory()->pending()->create([
            'requester_id' => $agent->id,
            'title' => 'Deploy production fix',
            'channel_id' => $approvalChannel->id,
            'amount' => 1250,
            'tool_execution_context' => [
                'tool_slug' => 'deploy_app',
                'parameters' => [
                    'environment' => 'production',
                    'api_token' => 'secret-token-value',
                ],
            ],
        ]);
        TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '555',
            'chat_type' => 'supergroup',
            'topic_id' => '44',
            'channel_id' => $approvalChannel->id,
            'mode' => 'free',
            'last_seen_at' => now(),
        ]);
        $originalDelivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'source_type' => ApprovalRequest::class,
            'source_id' => $approval->id,
            'chat_id' => '12345',
            'telegram_message_id' => '700',
            'parse_mode' => 'HTML',
            'renderer_version' => 'telegram-approval-card:v1',
            'status' => 'sent',
            'request_payload' => ['method' => 'sendMessage'],
            'sent_at' => now()->subMinute(),
        ]);

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
                'update_id' => 1007,
                'callback_query' => [
                    'id' => 'cb-resolve-card',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'message' => [
                        'message_id' => 999,
                        'chat' => ['id' => 98765, 'type' => 'private'],
                    ],
                    'data' => $interaction->token,
                ],
            ])
            ->assertOk();

        $resolvedDelivery = TelegramDelivery::where('renderer_version', 'telegram-approval-card:resolved:v1')->firstOrFail();

        $this->assertSame($originalDelivery->id, $resolvedDelivery->previous_delivery_id);
        $this->assertSame('12345', $resolvedDelivery->chat_id);
        $this->assertSame('700', $resolvedDelivery->telegram_message_id);
        $this->assertSame('sent', $resolvedDelivery->status);
        $this->assertSame('plain_text_fallback', $resolvedDelivery->parse_mode);
        $this->assertSame('editMessageText', $resolvedDelivery->request_payload['method']);
        $this->assertSame([], $resolvedDelivery->request_payload['buttons']);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-approval-resolution:v1',
            'chat_id' => '555',
            'topic_id' => '44',
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/editMessageText')) {
                return false;
            }

            $markup = json_decode($request['reply_markup'], true);

            return (string) $request['chat_id'] === '12345'
                && (int) $request['message_id'] === 700
                && str_contains($request['text'], 'Approval resolved')
                && str_contains($request['text'], 'Deploy production fix')
                && str_contains($request['text'], 'Status: APPROVED')
                && str_contains($request['text'], 'Tool: deploy_app')
                && str_contains($request['text'], 'api_token=[redacted]')
                && str_contains($request['text'], 'Risk: high')
                && ! str_contains($request['text'], 'Audit ID:')
                && ! str_contains($request['text'], 'secret-token-value')
                && ($markup['inline_keyboard'] ?? null) === [];
        });

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), '/editMessageText')
                && ! isset($data['parse_mode'])
                && (string) $request['chat_id'] === '12345'
                && (int) $request['message_id'] === 700
                && str_contains($request['text'], 'Approval resolved')
                && ! str_contains($request['text'], '<b>')
                && str_contains((string) $request['reply_markup'], 'inline_keyboard');
        });

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && (string) $request['chat_id'] === '555'
            && (int) $request['message_thread_id'] === 44
            && str_contains($request['text'], 'Approval approved')
            && ! str_contains($request['text'], 'Audit ID:')
            && str_contains($request['text'], 'Deploy production fix'));
    }

    public function test_telegram_approval_callbacks_require_linked_workspace_actor(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $setting = $this->telegramSetting();
        $agent = User::factory()->agent()->create();
        $approval = ApprovalRequest::factory()->pending()->create([
            'requester_id' => $agent->id,
        ]);

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
                'update_id' => 1006,
                'callback_query' => [
                    'id' => 'cb-unauthorized',
                    'from' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Mallory'],
                    'message' => [
                        'message_id' => 82,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $interaction->token,
                ],
            ])
            ->assertOk();

        $approval->refresh();
        $interaction->refresh();

        $this->assertSame('pending', $approval->status);
        $this->assertNull($approval->responded_by_id);
        $this->assertNull($interaction->resolved_at);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
            && str_contains((string) ($request->data()['text'] ?? ''), 'Link your Telegram account'));
    }

    public function test_telegram_link_command_creates_signed_identity_link(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 603]])]);

        $this->telegramSetting();

        $this->postTelegramCommand('/link', updateId: 2007, messageId: 107);

        $interaction = TelegramInteraction::where('interaction_type', 'identity_link')->firstOrFail();

        $this->assertSame('111', $interaction->payload['telegram_user_id']);
        $this->assertSame('authenticated_workspace_member', $interaction->allowed_actor_rule['type']);
        $this->assertSame(TelegramInteraction::CHECKSUM_VERSION, $interaction->allowed_actor_rule['checksum_version']);
        $this->assertNotEmpty($interaction->payload_checksum);
        $this->assertNotNull($interaction->expires_at);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-link-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], '/telegram/link/')
            && str_contains($request['text'], $interaction->token));

        $user = User::factory()->create(['name' => 'Rutger']);
        $claimUrl = URL::temporarySignedRoute('telegram.link.claim', now()->addMinutes(15), [
            'token' => $interaction->token,
        ]);

        $this->actingAs($user)
            ->get($claimUrl)
            ->assertRedirect('/w/test/integrations');

        $this->assertDatabaseHas('user_external_identities', [
            'user_id' => $user->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $interaction->refresh();
        $this->assertSame('linked', $interaction->final_status);
        $this->assertSame($user->id, $interaction->resolved_by_id);
    }

    public function test_telegram_link_claim_rejects_tampered_interaction_payload(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 603]])]);

        $this->telegramSetting();

        $this->postTelegramCommand('/link', updateId: 2008, messageId: 108);

        $interaction = TelegramInteraction::where('interaction_type', 'identity_link')->firstOrFail();
        $interaction->update([
            'payload' => [
                ...$interaction->payload,
                'telegram_user_id' => '999999',
            ],
        ]);

        $user = User::factory()->create(['name' => 'Rutger']);
        $claimUrl = URL::temporarySignedRoute('telegram.link.claim', now()->addMinutes(15), [
            'token' => $interaction->token,
        ]);

        $this->actingAs($user)
            ->get($claimUrl)
            ->assertRedirect('/w/test/integrations');

        $this->assertDatabaseMissing('user_external_identities', [
            'user_id' => $user->id,
            'provider' => 'telegram',
            'external_id' => '999999',
        ]);

        $interaction->refresh();
        $this->assertSame('tampered', $interaction->final_status);
        $this->assertSame($user->id, $interaction->resolved_by_id);
    }

    public function test_telegram_mini_app_session_verifies_init_data_and_binds_linked_workspace_user(): void
    {
        $this->telegramSetting();
        $user = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $response = $this->postJson('/api/telegram/mini-app/session', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $this->signedTelegramInitData([
                'user' => json_encode([
                    'id' => 111,
                    'first_name' => 'Rutger',
                    'username' => 'rutger',
                ]),
                'start_param' => 'approvals',
                'chat_type' => 'sender',
            ]),
        ])->assertOk()->json();

        $this->assertTrue($response['ok']);
        $this->assertSame($this->workspace->id, $response['workspace']['id']);
        $this->assertSame($user->id, $response['user']['id']);
        $this->assertSame('111', $response['telegram']['user_id']);
        $this->assertContains('approvals', $response['panels']);
        $this->assertContains('docs', $response['panels']);
    }

    public function test_telegram_mini_app_workspace_selector_returns_only_signed_linked_workspaces(): void
    {
        $this->telegramSetting();
        $user = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $otherWorkspace = Workspace::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Other Linked Workspace',
            'slug' => 'other-linked-workspace',
        ]);
        WorkspaceMember::create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
        IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $otherWorkspace->id,
            'integration_id' => 'telegram',
            'account_alias' => '',
            'config' => [
                'api_key' => 'telegram-token',
                'webhook_secret' => 'other-secret',
                'bot_username' => 'OpenCompanyBot',
            ],
            'enabled' => true,
            'is_default' => true,
        ]);

        $unsignedWorkspace = Workspace::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Unsigned Bot Workspace',
            'slug' => 'unsigned-bot-workspace',
        ]);
        WorkspaceMember::create([
            'workspace_id' => $unsignedWorkspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $unsignedWorkspace->id,
            'integration_id' => 'telegram',
            'account_alias' => '',
            'config' => [
                'api_key' => 'different-telegram-token',
                'webhook_secret' => 'unsigned-secret',
                'bot_username' => 'OtherBot',
            ],
            'enabled' => true,
            'is_default' => true,
        ]);

        $response = $this->postJson('/api/telegram/mini-app/workspaces', [
            'init_data' => $this->signedTelegramInitData([
                'user' => json_encode(['id' => 111, 'first_name' => 'Rutger']),
                'start_param' => 'workspace',
            ]),
        ])->assertOk()->json();

        $this->assertTrue($response['ok']);
        $this->assertSame('111', $response['telegram']['user_id']);
        $this->assertCount(2, $response['workspaces']);
        $this->assertEqualsCanonicalizing(
            [$this->workspace->id, $otherWorkspace->id],
            collect($response['workspaces'])->pluck('id')->all(),
        );
        $this->assertNotContains($unsignedWorkspace->id, collect($response['workspaces'])->pluck('id')->all());
    }

    public function test_telegram_mini_app_shell_route_renders_public_inertia_page(): void
    {
        $this->get('/telegram/mini-app?workspace_id='.$this->workspace->id.'&panel=settings&target_id=lane-123')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Telegram/MiniApp')
                ->where('workspaceId', $this->workspace->id)
                ->where('initialPanel', 'settings')
                ->where('targetId', 'lane-123'));
    }

    public function test_telegram_mini_app_approval_panel_returns_authorized_dense_inspection(): void
    {
        $this->telegramSetting();
        $user = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $approval = ApprovalRequest::factory()->pending()->create([
            'requester_id' => $agent->id,
            'title' => 'Send vendor renewal',
            'tool_execution_context' => [
                'tool_slug' => 'send_channel_message',
                'parameters' => [
                    'channelId' => 'chan_123',
                    'content' => 'Renewal approved',
                    'api_key' => 'must-not-leak',
                ],
            ],
        ]);

        $response = $this->postJson('/api/telegram/mini-app/panel', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $this->signedTelegramInitData([
                'user' => json_encode(['id' => 111, 'first_name' => 'Rutger']),
                'start_param' => 'approvals',
            ]),
            'panel' => 'approvals',
            'target_id' => $approval->id,
        ])->assertOk()->json();

        $this->assertTrue($response['ok']);
        $this->assertSame('approvals', $response['panel']);
        $this->assertSame('approval_detail', $response['data']['type']);
        $this->assertSame('Send vendor renewal', $response['data']['title']);
        $this->assertSame('send_channel_message', $response['data']['tool']);
        $this->assertSame('Renewal approved', $response['data']['arguments']['content']);
        $this->assertSame('[redacted]', $response['data']['arguments']['api_key']);
        $this->assertContains('approve', $response['data']['actions']);
        $this->assertContains('reject', $response['data']['actions']);
        $this->assertSame("/w/{$this->workspace->slug}/approvals/{$approval->id}", $response['data']['web_url']);

        $this->postJson('/api/telegram/mini-app/panel', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $this->signedTelegramInitData([
                'user' => json_encode(['id' => 111, 'first_name' => 'Rutger']),
            ]),
            'panel' => 'approvals',
            'target_id' => 'apr_missing',
        ])
            ->assertNotFound()
            ->assertJson([
                'ok' => false,
                'error' => 'panel_target_not_found',
            ]);
    }

    public function test_telegram_mini_app_approval_actions_resolve_pending_requests_once(): void
    {
        $this->telegramSetting();
        $user = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $approval = ApprovalRequest::factory()->pending()->create([
            'requester_id' => $agent->id,
            'title' => 'Approve deploy',
            'tool_execution_context' => null,
        ]);
        $rejectedApproval = ApprovalRequest::factory()->pending()->create([
            'requester_id' => $agent->id,
            'title' => 'Reject risky change',
            'tool_execution_context' => null,
        ]);
        $otherWorkspace = Workspace::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Other Workspace',
            'slug' => 'other-workspace',
        ]);
        $otherAgent = User::factory()->agent()->create([
            'name' => 'Other Agent',
            'workspace_id' => $otherWorkspace->id,
        ]);
        $otherApproval = ApprovalRequest::factory()->pending()->create([
            'requester_id' => $otherAgent->id,
            'title' => 'Other workspace approval',
            'tool_execution_context' => null,
        ]);

        $initData = $this->signedTelegramInitData([
            'user' => json_encode(['id' => 111, 'first_name' => 'Rutger', 'username' => 'rutger']),
        ]);

        $approved = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'approve_approval',
            'approval_id' => $approval->id,
        ])->assertOk()->json('data.approval');

        $this->assertSame('approved', $approved['status']);
        $this->assertSame('Rutger', $approved['responded_by']);
        $this->assertSame(['open'], $approved['actions']);
        $this->assertSame('approved', $approval->fresh()->status);
        $this->assertSame($user->id, $approval->fresh()->responded_by_id);

        $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'reject_approval',
            'approval_id' => $approval->id,
        ])
            ->assertUnprocessable()
            ->assertJson([
                'ok' => false,
                'error' => 'telegram_approval_already_resolved',
                'status' => 'approved',
            ]);

        $rejected = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'reject_approval',
            'approval_id' => $rejectedApproval->id,
        ])->assertOk()->json('data.approval');

        $this->assertSame('rejected', $rejected['status']);
        $this->assertSame('rejected', $rejectedApproval->fresh()->status);

        $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'approve_approval',
            'approval_id' => $otherApproval->id,
        ])
            ->assertNotFound()
            ->assertJson([
                'ok' => false,
                'error' => 'telegram_approval_not_found',
            ]);
    }

    public function test_telegram_mini_app_serves_identity_file_automation_and_notification_panels(): void
    {
        $setting = $this->telegramSetting();
        $user = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $folder = WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram Captures',
            'is_folder' => true,
            'owner_id' => $user->id,
            'metadata' => ['source' => 'telegram'],
        ]);
        $file = WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'parent_id' => $folder->id,
            'name' => 'renewal.txt',
            'is_folder' => false,
            'storage_disk' => 'local',
            'storage_path' => 'workspaces/test/renewal.txt',
            'mime_type' => 'text/plain',
            'size' => 42,
            'owner_id' => $user->id,
            'metadata' => [
                'source' => 'telegram',
                'telegram_file_path' => 'documents/renewal.txt',
            ],
        ]);
        $docFolder = Document::create([
            'id' => 'telegram-doc-folder-'.Str::random(8),
            'workspace_id' => $this->workspace->id,
            'title' => 'Telegram Notes',
            'content' => '',
            'author_id' => $user->id,
            'is_folder' => true,
        ]);
        $document = Document::create([
            'id' => 'telegram-doc-'.Str::random(8),
            'workspace_id' => $this->workspace->id,
            'title' => 'Telegram Renewal Brief',
            'content' => "# Renewal\n\nCaptured requirements from Telegram.",
            'content_format' => 'markdown',
            'author_id' => $user->id,
            'parent_id' => $docFolder->id,
            'is_folder' => false,
        ]);
        $automation = Automation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Morning Digest',
            'trigger_type' => 'schedule',
            'execution_type' => 'prompt',
            'description' => 'Summarize workspace state.',
            'agent_id' => $agent->id,
            'prompt' => 'Summarize workspace state.',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'is_active' => true,
            'run_count' => 3,
            'consecutive_failures' => 1,
            'created_by_id' => $user->id,
            'keep_history' => true,
            'last_result' => ['error' => 'Previous run failed'],
        ]);
        TelegramSubscription::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'scope_type' => 'telegram_conversation',
            'scope_id' => 'lane-1',
            'event_type' => 'task_failed',
            'severity' => 'high',
            'filters' => ['snoozed_until' => now()->addHour()->toIso8601String()],
            'schedule' => null,
            'timezone' => 'UTC',
            'enabled' => true,
        ]);

        $initData = $this->signedTelegramInitData([
            'user' => json_encode(['id' => 111, 'first_name' => 'Rutger', 'username' => 'rutger']),
        ]);

        $identity = $this->postJson('/api/telegram/mini-app/panel', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'panel' => 'identity',
        ])->assertOk()->json('data');

        $fileDetail = $this->postJson('/api/telegram/mini-app/panel', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'panel' => 'files',
            'target_id' => $file->id,
        ])->assertOk()->json('data');

        $documentDetail = $this->postJson('/api/telegram/mini-app/panel', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'panel' => 'docs',
            'target_id' => $document->id,
        ])->assertOk()->json('data');

        $automationDetail = $this->postJson('/api/telegram/mini-app/panel', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'panel' => 'automation',
            'target_id' => $automation->id,
        ])->assertOk()->json('data');

        $notifications = $this->postJson('/api/telegram/mini-app/panel', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'panel' => 'notifications',
        ])->assertOk()->json('data');

        $this->assertSame('identity', $identity['type']);
        $this->assertSame('111', $identity['telegram']['user_id']);
        $this->assertSame('file_detail', $fileDetail['type']);
        $this->assertSame('/Telegram Captures/renewal.txt', $fileDetail['path']);
        $this->assertSame("/api/files/{$file->id}/download", $fileDetail['download_url']);
        $this->assertArrayNotHasKey('telegram_file_path', $fileDetail);
        $this->assertSame('document_detail', $documentDetail['type']);
        $this->assertSame('Telegram Renewal Brief', $documentDetail['title']);
        $this->assertSame('Telegram Notes', $documentDetail['parent']);
        $this->assertStringContainsString('Captured requirements', $documentDetail['content_excerpt']);
        $this->assertSame("/w/{$this->workspace->slug}/docs?document={$document->id}", $documentDetail['web_url']);
        $this->assertSame('automation_detail', $automationDetail['type']);
        $this->assertSame('Morning Digest', $automationDetail['name']);
        $this->assertSame('Previous run failed', $automationDetail['last_error']);
        $this->assertContains('run', $automationDetail['actions']);
        $this->assertContains('pause', $automationDetail['actions']);
        $this->assertSame("/w/{$this->workspace->slug}/automation/{$automation->id}/edit", $automationDetail['web_url']);
        $this->assertArrayNotHasKey('prompt', $automationDetail);
        $this->assertArrayNotHasKey('script', $automationDetail);
        $this->assertSame('notifications', $notifications['type']);
        $this->assertSame(1, $notifications['enabled_count']);
        $this->assertSame('task_failed', $notifications['items'][0]['event_type']);
    }

    public function test_telegram_mini_app_automation_actions_run_pause_and_resume_with_workspace_scope(): void
    {
        Queue::fake();

        $this->telegramSetting();
        $user = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $automation = Automation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Morning Digest',
            'trigger_type' => 'schedule',
            'execution_type' => 'prompt',
            'description' => 'Summarize workspace state.',
            'agent_id' => $agent->id,
            'prompt' => 'Summarize workspace state.',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'is_active' => true,
            'run_count' => 3,
            'consecutive_failures' => 2,
            'created_by_id' => $user->id,
            'keep_history' => true,
        ]);
        $otherWorkspace = Workspace::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Other Workspace',
            'slug' => 'other-workspace',
        ]);
        $otherUser = User::factory()->create(['name' => 'Mallory']);
        $otherAgent = User::factory()->agent()->create([
            'name' => 'Other Agent',
            'workspace_id' => $otherWorkspace->id,
        ]);
        $otherAutomation = Automation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $otherWorkspace->id,
            'name' => 'Other Digest',
            'trigger_type' => 'schedule',
            'execution_type' => 'prompt',
            'agent_id' => $otherAgent->id,
            'prompt' => 'Other workspace state.',
            'cron_expression' => '0 8 * * *',
            'timezone' => 'UTC',
            'is_active' => true,
            'created_by_id' => $otherUser->id,
            'keep_history' => true,
        ]);

        $initData = $this->signedTelegramInitData([
            'user' => json_encode(['id' => 111, 'first_name' => 'Rutger', 'username' => 'rutger']),
        ]);

        $run = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'run_automation',
            'automation_id' => substr($automation->id, 0, 12),
        ])->assertOk()->json('data.automation');

        Queue::assertPushed(RunAutomationJob::class);
        $this->assertSame('Morning Digest', $run['name']);
        $this->assertTrue($run['is_active']);

        $paused = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'pause_automation',
            'automation_id' => $automation->id,
        ])->assertOk()->json('data.automation');

        $this->assertFalse($paused['is_active']);
        $this->assertContains('resume', $paused['actions']);
        $this->assertFalse($automation->fresh()->is_active);

        $resumed = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'resume_automation',
            'automation_id' => $automation->id,
        ])->assertOk()->json('data.automation');

        $this->assertTrue($resumed['is_active']);
        $this->assertSame(0, $automation->fresh()->consecutive_failures);
        $this->assertNotNull($automation->fresh()->next_run_at);

        $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'pause_automation',
            'automation_id' => $otherAutomation->id,
        ])
            ->assertNotFound()
            ->assertJson([
                'ok' => false,
                'error' => 'telegram_automation_not_found',
            ]);
    }

    public function test_telegram_mini_app_settings_panel_returns_diagnostics_without_secrets(): void
    {
        config(['telegram.draft_streaming_enabled' => true, 'telegram.guest_mode_enabled' => false]);

        $setting = $this->telegramSetting([
            'api_key' => 'telegram-token-secret',
            'webhook_secret' => 'webhook-secret-value',
            'allowed_updates' => ['message'],
        ]);
        $user = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        TelegramIntegrationProfile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'bot_id' => '999',
            'bot_username' => 'OpenCompanyBot',
            'capabilities' => [
                'diagnostics' => [[
                    'code' => 'missing_allowed_updates',
                    'severity' => 'warning',
                    'message' => 'Telegram webhook is missing allowed updates: message_reaction.',
                ]],
            ],
            'webhook_url' => 'https://local-ngrok.example/api/webhooks/chat/telegram',
            'webhook_secret_fingerprint' => hash('sha256', 'webhook-secret-value'),
            'allowed_updates' => ['message'],
            'command_sync_status' => 'synced',
            'profile_sync_status' => 'synced',
            'pending_update_count' => 2,
            'default_mode' => 'command_center',
            'default_agent_id' => $agent->id,
            'health_status' => 'degraded',
            'last_health_error' => 'Webhook is missing updates.',
            'last_health_checked_at' => now(),
        ]);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Product',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345:55',
        ]);
        TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'chat_type' => 'supergroup',
            'title' => 'Product',
            'topic_id' => '55',
            'direct_messages_topic_id' => '77',
            'channel_id' => $channel->id,
            'default_agent_id' => $agent->id,
            'mode' => 'observed',
            'observed_context_enabled' => true,
            'last_seen_at' => now(),
        ]);
        TelegramUpdateReceipt::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'update_id' => 'settings-1',
            'update_type' => 'message',
            'payload_hash' => hash('sha256', 'settings-1'),
            'payload' => ['update_id' => 'settings-1'],
            'status' => 'processed',
            'received_at' => now(),
            'processed_at' => now(),
        ]);
        TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'renderer_version' => 'telegram-task-notification:v1',
            'status' => 'failed',
            'request_payload' => ['method' => 'sendMessage', 'text' => 'Failed send'],
            'provider_error_code' => \RuntimeException::class,
            'provider_error_message' => 'Network failed',
        ]);

        $settings = $this->postJson('/api/telegram/mini-app/panel', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $this->signedTelegramInitData([
                'user' => json_encode(['id' => 111, 'first_name' => 'Rutger', 'username' => 'rutger']),
            ], 'telegram-token-secret'),
            'panel' => 'settings',
        ])->assertOk()->json('data');

        $this->assertSame('settings', $settings['type']);
        $this->assertSame('OpenCompanyBot', $settings['bot_username']);
        $this->assertSame('degraded', $settings['health_status']);
        $this->assertSame('missing_allowed_updates', $settings['diagnostics'][0]['code']);
        $this->assertTrue($settings['feature_flags']['draft_streaming_enabled']);
        $this->assertFalse($settings['feature_flags']['guest_mode_enabled']);
        $this->assertSame(1, $settings['linked_identities_count']);
        $this->assertSame(1, $settings['conversations']);
        $this->assertSame('Telegram: Product', $settings['recent_lanes'][0]['channel']);
        $this->assertSame('Iris', $settings['recent_lanes'][0]['default_agent']);
        $this->assertSame(1, $settings['operations']['receipts_24h']);
        $this->assertSame(1, $settings['operations']['failed_deliveries_24h']);
        $this->assertArrayNotHasKey('api_key', $settings);
        $this->assertArrayNotHasKey('webhook_secret', $settings);
    }

    public function test_telegram_mini_app_settings_panel_repairs_local_telegram_state_with_admin_gate(): void
    {
        $setting = $this->telegramSetting([
            'api_key' => 'telegram-token-secret',
        ]);
        $admin = User::factory()->create(['name' => 'Rutger']);
        $member = User::factory()->create(['name' => 'Mallory']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $admin->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $member->id,
            'provider' => 'telegram',
            'external_id' => '222',
            'display_name' => 'Mallory',
        ]);

        $expired = TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'command',
            'target_type' => null,
            'target_id' => null,
            'payload' => ['telegram_user_id' => '111'],
            'expires_at' => now()->subMinute(),
        ]));
        $orphaned = TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'chat_type' => 'supergroup',
            'title' => 'Product',
            'topic_id' => '88',
        ]);
        $driftedChannel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Wrong external channel',
            'type' => 'external',
            'external_provider' => 'slack',
            'external_id' => 'T123:C456',
        ]);
        $drifted = TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '555',
            'chat_type' => 'private',
            'title' => 'Rutger',
            'channel_id' => $driftedChannel->id,
        ]);

        $adminInitData = $this->signedTelegramInitData([
            'user' => json_encode(['id' => 111, 'first_name' => 'Rutger', 'username' => 'rutger']),
        ], 'telegram-token-secret');
        $memberInitData = $this->signedTelegramInitData([
            'user' => json_encode(['id' => 222, 'first_name' => 'Mallory', 'username' => 'mallory']),
        ], 'telegram-token-secret');

        $settings = $this->postJson('/api/telegram/mini-app/panel', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $adminInitData,
            'panel' => 'settings',
        ])->assertOk()->json('data');

        $this->assertSame($expired->id, $settings['repair_candidates']['expired_open_interactions'][0]['id']);
        $this->assertSame($orphaned->id, $settings['repair_candidates']['orphaned_conversations'][0]['id']);
        $this->assertSame($drifted->id, $settings['repair_candidates']['broken_conversation_mappings'][0]['id']);

        $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $memberInitData,
            'action' => 'repair_telegram_state',
            'repair_actions' => ['expire_interactions', 'repair_conversations'],
        ])
            ->assertForbidden()
            ->assertJson([
                'ok' => false,
                'error' => 'telegram_admin_required',
            ]);

        $repair = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $adminInitData,
            'action' => 'repair_telegram_state',
            'repair_actions' => ['expire_interactions', 'repair_conversations'],
        ])->assertOk()->json('data');

        $this->assertSame(1, $repair['repair']['expired_interactions']);
        $this->assertSame(1, $repair['repair']['created_channels']);
        $this->assertSame(1, $repair['repair']['repaired_channel_mappings']);
        $this->assertSame(0, $repair['metrics']['expired_open_interactions']);
        $this->assertSame(0, $repair['metrics']['orphaned_conversations']);
        $this->assertSame(0, $repair['metrics']['broken_conversation_mappings']);
        $this->assertSame([], $repair['repair_candidates']['expired_open_interactions']);

        $expired->refresh();
        $this->assertSame('expired', $expired->final_status);
        $this->assertNotNull($expired->resolved_at);

        $orphaned->refresh();
        $this->assertNotNull($orphaned->channel_id);
        $this->assertDatabaseHas('channels', [
            'id' => $orphaned->channel_id,
            'workspace_id' => $this->workspace->id,
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345:88',
        ]);

        $driftedChannel->refresh();
        $this->assertSame('Telegram: Rutger', $driftedChannel->name);
        $this->assertSame('telegram', $driftedChannel->external_provider);
        $this->assertSame('555', $driftedChannel->external_id);
    }

    public function test_telegram_mini_app_settings_panel_exposes_safe_operation_logs_and_replay_actions(): void
    {
        Queue::fake();
        Http::fake([
            'api.telegram.org/*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 889],
            ]),
        ]);

        $setting = $this->telegramSetting([
            'api_key' => 'telegram-token-secret',
        ]);
        $admin = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $admin->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'renderer_version' => 'telegram-command-card:v1',
            'status' => 'failed',
            'request_payload' => [
                'method' => 'sendMessage',
                'text' => 'Mini App retry delivery',
            ],
            'provider_error_code' => 'PreviousError',
            'provider_error_message' => 'previous delivery failure',
            'attempts' => 1,
        ]);
        $payload = [
            'update_id' => 2601,
            'message' => [
                'message_id' => 601,
                'date' => now()->timestamp,
                'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                'text' => '/status',
                'entities' => [
                    ['offset' => 0, 'length' => 7, 'type' => 'bot_command'],
                ],
            ],
        ];
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $receipt = TelegramUpdateReceipt::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'update_id' => '2601',
            'update_type' => 'message',
            'payload_hash' => hash('sha256', $encoded ?: ''),
            'payload' => $payload,
            'status' => 'failed',
            'error_class' => 'RuntimeException',
            'error_message' => 'previous receipt failure',
            'retry_count' => 0,
            'received_at' => now(),
        ]);
        $initData = $this->signedTelegramInitData([
            'user' => json_encode(['id' => 111, 'first_name' => 'Rutger', 'username' => 'rutger']),
        ], 'telegram-token-secret');

        $settings = $this->postJson('/api/telegram/mini-app/panel', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'panel' => 'settings',
        ])->assertOk()->json('data');

        $this->assertSame($delivery->id, $settings['operations_logs']['deliveries'][0]['id']);
        $this->assertSame('sendMessage', $settings['operations_logs']['deliveries'][0]['method']);
        $this->assertArrayNotHasKey('request_payload', $settings['operations_logs']['deliveries'][0]);
        $this->assertSame($receipt->id, $settings['operations_logs']['receipts'][0]['id']);
        $this->assertSame('2601', $settings['operations_logs']['receipts'][0]['update_id']);
        $this->assertArrayNotHasKey('payload', $settings['operations_logs']['receipts'][0]);

        $retry = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'retry_telegram_delivery',
            'delivery_id' => $delivery->id,
        ])->assertOk()->json('data');

        $this->assertSame('sent', $retry['delivery']['status']);
        $this->assertSame(2, $retry['delivery']['attempts']);
        $this->assertSame(0, $retry['metrics']['retryable_deliveries']);

        $replay = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'replay_telegram_receipt',
            'receipt_id' => $receipt->id,
        ])->assertOk()->json('data');

        $this->assertSame('processed', $replay['receipt']['status']);
        $this->assertSame(1, $replay['receipt']['retry_count']);
        $this->assertSame(0, $replay['metrics']['retryable_receipts']);
        $this->assertDatabaseHas('telegram_update_receipts', [
            'id' => $receipt->id,
            'status' => 'processed',
            'retry_count' => 1,
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && $request['text'] === 'Mini App retry delivery');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Status'));
    }

    public function test_telegram_mini_app_settings_panel_lists_and_revokes_workspace_telegram_identities(): void
    {
        $this->telegramSetting([
            'api_key' => 'telegram-token-secret',
        ]);
        $admin = User::factory()->create(['name' => 'Rutger']);
        $member = User::factory()->create(['name' => 'Mallory']);
        $outsider = User::factory()->create(['name' => 'Eve']);
        $otherWorkspace = Workspace::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Other Workspace',
            'slug' => 'other-workspace',
        ]);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);
        WorkspaceMember::create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $outsider->id,
            'role' => 'admin',
        ]);
        $adminIdentity = UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $admin->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $memberIdentity = UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $member->id,
            'provider' => 'telegram',
            'external_id' => '222',
            'display_name' => 'Mallory',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $outsider->id,
            'provider' => 'telegram',
            'external_id' => '333',
            'display_name' => 'Eve',
        ]);

        $adminInitData = $this->signedTelegramInitData([
            'user' => json_encode(['id' => 111, 'first_name' => 'Rutger', 'username' => 'rutger']),
        ], 'telegram-token-secret');
        $memberInitData = $this->signedTelegramInitData([
            'user' => json_encode(['id' => 222, 'first_name' => 'Mallory', 'username' => 'mallory']),
        ], 'telegram-token-secret');

        $settings = $this->postJson('/api/telegram/mini-app/panel', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $adminInitData,
            'panel' => 'settings',
        ])->assertOk()->json('data');

        $this->assertCount(2, $settings['linked_identities']);
        $this->assertSame('111', $settings['linked_identities'][0]['telegram_user_id']);
        $this->assertTrue($settings['linked_identities'][0]['is_current']);
        $this->assertFalse($settings['linked_identities'][0]['can_revoke']);
        $this->assertSame('222', $settings['linked_identities'][1]['telegram_user_id']);
        $this->assertTrue($settings['linked_identities'][1]['can_revoke']);
        $this->assertNotContains('333', collect($settings['linked_identities'])->pluck('telegram_user_id')->all());

        $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $memberInitData,
            'action' => 'revoke_telegram_identity',
            'identity_id' => $adminIdentity->id,
        ])
            ->assertForbidden()
            ->assertJson([
                'ok' => false,
                'error' => 'telegram_admin_required',
            ]);

        $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $adminInitData,
            'action' => 'revoke_telegram_identity',
            'identity_id' => $adminIdentity->id,
        ])
            ->assertUnprocessable()
            ->assertJson([
                'ok' => false,
                'error' => 'telegram_identity_self_revoke_not_allowed',
            ]);

        $revoked = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $adminInitData,
            'action' => 'revoke_telegram_identity',
            'identity_id' => $memberIdentity->id,
        ])->assertOk()->json('data');

        $this->assertSame($memberIdentity->id, $revoked['revoked_identity']['id']);
        $this->assertCount(1, $revoked['linked_identities']);
        $this->assertSame('111', $revoked['linked_identities'][0]['telegram_user_id']);
        $this->assertDatabaseMissing('user_external_identities', [
            'id' => $memberIdentity->id,
        ]);
        $this->assertDatabaseHas('user_external_identities', [
            'id' => $adminIdentity->id,
            'external_id' => '111',
        ]);
    }

    public function test_telegram_mini_app_actions_update_lane_agent_mode_and_notifications_with_admin_gate(): void
    {
        $setting = $this->telegramSetting([
            'api_key' => 'telegram-token-secret',
            'default_mode' => 'command_center',
        ]);
        $admin = User::factory()->create(['name' => 'Rutger']);
        $member = User::factory()->create(['name' => 'Mallory']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $admin->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $member->id,
            'provider' => 'telegram',
            'external_id' => '222',
            'display_name' => 'Mallory',
        ]);

        $iris = User::factory()->agent()->create(['name' => 'Iris']);
        $nova = User::factory()->agent()->create(['name' => 'Nova']);
        $otherWorkspace = Workspace::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Other Workspace',
            'slug' => 'other-workspace',
        ]);
        $otherAgent = User::factory()->agent()->create([
            'name' => 'Other Agent',
            'workspace_id' => $otherWorkspace->id,
        ]);
        $conversation = TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'chat_type' => 'supergroup',
            'title' => 'Product',
            'topic_id' => '55',
            'default_agent_id' => $iris->id,
            'mode' => 'command_center',
            'observed_context_enabled' => false,
        ]);

        $adminInitData = $this->signedTelegramInitData([
            'user' => json_encode(['id' => 111, 'first_name' => 'Rutger', 'username' => 'rutger']),
        ], 'telegram-token-secret');
        $memberInitData = $this->signedTelegramInitData([
            'user' => json_encode(['id' => 222, 'first_name' => 'Mallory', 'username' => 'mallory']),
        ], 'telegram-token-secret');

        $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $memberInitData,
            'action' => 'set_conversation_mode',
            'conversation_id' => $conversation->id,
            'mode' => 'observed',
        ])
            ->assertForbidden()
            ->assertJson([
                'ok' => false,
                'error' => 'telegram_admin_required',
            ]);

        $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $adminInitData,
            'action' => 'set_default_agent',
            'conversation_id' => $conversation->id,
            'agent_id' => $otherAgent->id,
        ])
            ->assertNotFound()
            ->assertJson([
                'ok' => false,
                'error' => 'telegram_agent_not_found',
            ]);

        $laneAgent = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $adminInitData,
            'action' => 'set_default_agent',
            'conversation_id' => $conversation->id,
            'agent_id' => $nova->id,
        ])->assertOk()->json('data.conversation');

        $this->assertSame('Nova', $laneAgent['default_agent']['name']);
        $this->assertSame($nova->id, $conversation->fresh()->default_agent_id);

        $mode = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $adminInitData,
            'action' => 'set_conversation_mode',
            'conversation_id' => $conversation->id,
            'mode' => 'observed',
        ])->assertOk()->json('data.conversation');

        $this->assertSame('observed', $mode['mode']);
        $this->assertTrue($mode['observed_context_enabled']);

        $notification = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $adminInitData,
            'action' => 'set_notification',
            'conversation_id' => $conversation->id,
            'event_type' => 'approval_needed',
            'severity' => 'high',
            'notification_mode' => 'digest',
        ])->assertOk()->json('data.subscription');

        $this->assertSame('approval_needed', $notification['event_type']);
        $this->assertSame('high', $notification['severity']);
        $this->assertSame('digest', $notification['filters']['mode']);
        $this->assertTrue($notification['enabled']);

        $digest = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $adminInitData,
            'action' => 'set_digest',
            'conversation_id' => $conversation->id,
            'schedule' => 'weekly',
            'time' => '08:30',
        ])->assertOk()->json('data.subscription');

        $this->assertSame('workspace_digest', $digest['event_type']);
        $this->assertSame('weekly', $digest['schedule']);
        $this->assertSame('08:30', $digest['filters']['time']);

        $workspaceDefault = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $adminInitData,
            'action' => 'set_default_agent',
            'agent_id' => $nova->id,
        ])->assertOk()->json('data');

        $this->assertSame('workspace', $workspaceDefault['scope']);
        $this->assertSame('Nova', $workspaceDefault['default_agent']['name']);
        $this->assertSame($nova->id, $setting->fresh()->getConfigValue('default_agent_id'));
        $this->assertDatabaseHas('telegram_integration_profiles', [
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'default_agent_id' => $nova->id,
        ]);
    }

    public function test_telegram_mini_app_task_panel_returns_execution_steps_without_secret_leaks(): void
    {
        $this->telegramSetting();
        $user = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Product',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345',
        ]);
        $task = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Investigate renewal risk',
            'description' => 'Inspect CRM signals and draft a short renewal brief.',
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_HIGH,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $user->id,
            'channel_id' => $channel->id,
            'context' => [
                'prompt' => 'Check Globex renewal',
                'api_key' => 'must-not-leak',
            ],
            'result' => [
                'output' => 'Draft in progress',
                'token' => 'also-secret',
            ],
            'started_at' => now()->subMinutes(3),
        ]);
        $task->steps()->create([
            'id' => Str::uuid()->toString(),
            'description' => 'Loaded CRM account history',
            'status' => TaskStep::STATUS_COMPLETED,
            'step_type' => TaskStep::TYPE_ACTION,
            'metadata' => ['tool' => 'query_table', 'secret_token' => 'hidden'],
            'started_at' => now()->subMinutes(3),
            'completed_at' => now()->subMinutes(2),
        ]);
        $task->steps()->create([
            'id' => Str::uuid()->toString(),
            'description' => 'Summarizing renewal blockers',
            'status' => TaskStep::STATUS_IN_PROGRESS,
            'step_type' => TaskStep::TYPE_DECISION,
            'metadata' => ['customer' => 'Globex'],
            'started_at' => now()->subMinute(),
        ]);
        $otherWorkspace = Workspace::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Other Workspace',
            'slug' => 'other-workspace',
        ]);
        $otherUser = User::factory()->create(['name' => 'Mallory']);
        $otherTask = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $otherWorkspace->id,
            'title' => 'Should not be visible',
            'status' => Task::STATUS_ACTIVE,
            'source' => Task::SOURCE_CHAT,
            'requester_id' => $otherUser->id,
        ]);

        $initData = $this->signedTelegramInitData([
            'user' => json_encode(['id' => 111, 'first_name' => 'Rutger', 'username' => 'rutger']),
        ]);

        $detail = $this->postJson('/api/telegram/mini-app/panel', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'panel' => 'tasks',
            'target_id' => substr($task->id, 0, 12),
        ])->assertOk()->json('data');

        $this->assertSame('task_detail', $detail['type']);
        $this->assertSame('Investigate renewal risk', $detail['title']);
        $this->assertSame('Iris', $detail['agent']);
        $this->assertSame('Telegram: Product', $detail['channel']);
        $this->assertSame("/w/{$this->workspace->slug}/tasks/{$task->id}", $detail['web_url']);
        $this->assertSame(2, $detail['step_counts']['total']);
        $this->assertSame('Summarizing renewal blockers', $detail['current_step']['description']);
        $this->assertSame('[redacted]', $detail['context']['api_key']);
        $this->assertSame('[redacted]', $detail['result']['token']);
        $this->assertSame('[redacted]', $detail['steps'][0]['metadata']['secret_token']);
        $this->assertContains('cancel', $detail['actions']);

        $this->postJson('/api/telegram/mini-app/panel', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'panel' => 'tasks',
            'target_id' => $otherTask->id,
        ])
            ->assertNotFound()
            ->assertJson([
                'ok' => false,
                'error' => 'panel_target_not_found',
            ]);
    }

    public function test_telegram_mini_app_task_actions_follow_workspace_scope_and_member_auth(): void
    {
        $this->telegramSetting();
        $user = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $task = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Live Telegram run',
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_CHAT,
            'requester_id' => $user->id,
            'started_at' => now(),
        ]);
        $otherWorkspace = Workspace::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Other Workspace',
            'slug' => 'other-workspace',
        ]);
        $otherUser = User::factory()->create(['name' => 'Mallory']);
        $otherTask = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $otherWorkspace->id,
            'title' => 'Cross workspace run',
            'status' => Task::STATUS_ACTIVE,
            'source' => Task::SOURCE_CHAT,
            'requester_id' => $otherUser->id,
        ]);

        $initData = $this->signedTelegramInitData([
            'user' => json_encode(['id' => 111, 'first_name' => 'Rutger', 'username' => 'rutger']),
        ]);

        $paused = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'pause_task',
            'task_id' => substr($task->id, 0, 12),
        ])->assertOk()->json('data.task');

        $this->assertSame(Task::STATUS_PAUSED, $paused['status']);
        $this->assertContains('resume', $paused['actions']);
        $this->assertSame(Task::STATUS_PAUSED, $task->fresh()->status);

        $resumed = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'resume_task',
            'task_id' => $task->id,
        ])->assertOk()->json('data.task');

        $this->assertSame(Task::STATUS_ACTIVE, $resumed['status']);

        $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'resume_task',
            'task_id' => $task->id,
        ])
            ->assertUnprocessable()
            ->assertJson([
                'ok' => false,
                'error' => 'telegram_invalid_task_transition',
                'status' => Task::STATUS_ACTIVE,
            ]);

        $cancelled = $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'cancel_task',
            'task_id' => $task->id,
        ])->assertOk()->json('data.task');

        $this->assertSame(Task::STATUS_CANCELLED, $cancelled['status']);
        $this->assertNotContains('cancel', $cancelled['actions']);
        $this->assertNotNull($task->fresh()->completed_at);

        $this->postJson('/api/telegram/mini-app/action', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $initData,
            'action' => 'cancel_task',
            'task_id' => $otherTask->id,
        ])
            ->assertNotFound()
            ->assertJson([
                'ok' => false,
                'error' => 'telegram_task_not_found',
            ]);
    }

    public function test_telegram_mini_app_session_rejects_tampered_init_data(): void
    {
        $this->telegramSetting();

        $initData = $this->signedTelegramInitData([
            'user' => json_encode(['id' => 111, 'first_name' => 'Rutger']),
        ]);

        $this->postJson('/api/telegram/mini-app/session', [
            'workspace_id' => $this->workspace->id,
            'init_data' => str_replace('Rutger', 'Mallory', $initData),
        ])
            ->assertUnauthorized()
            ->assertJson([
                'ok' => false,
                'error' => 'invalid_init_data',
            ]);
    }

    public function test_telegram_mini_app_session_requires_linked_workspace_identity(): void
    {
        $this->telegramSetting();

        $this->postJson('/api/telegram/mini-app/session', [
            'workspace_id' => $this->workspace->id,
            'init_data' => $this->signedTelegramInitData([
                'user' => json_encode(['id' => 111, 'first_name' => 'Rutger']),
            ]),
        ])
            ->assertForbidden()
            ->assertJson([
                'ok' => false,
                'error' => 'telegram_user_not_linked',
                'needs_link' => true,
            ]);
    }

    public function test_telegram_mini_app_can_be_disabled_by_runtime_flag(): void
    {
        config(['telegram.mini_app_enabled' => false]);

        $this->postJson('/api/telegram/mini-app/session', [])
            ->assertNotFound()
            ->assertJson([
                'ok' => false,
                'error' => 'telegram_mini_app_disabled',
            ]);
    }

    public function test_telegram_approval_delivery_uses_opaque_interaction_tokens(): void
    {
        Http::fake([
            'api.telegram.org/*/sendMessage' => Http::sequence()
                ->push([
                    'ok' => false,
                    'error_code' => 400,
                    'description' => "Bad Request: can't parse entities: malformed HTML",
                ], 400)
                ->push([
                    'ok' => true,
                    'result' => ['message_id' => 700],
                ]),
        ]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $approval = ApprovalRequest::factory()->pending()->create([
            'requester_id' => $agent->id,
            'title' => 'Send renewal email',
            'description' => 'Send the final vendor renewal email.',
            'amount' => 1250,
            'tool_execution_context' => [
                'tool_slug' => 'send_channel_message',
                'parameters' => [
                    'channelId' => 'chan_123',
                    'content' => 'Hello renewal team',
                    'api_key' => 'should-not-leak',
                ],
            ],
        ]);
        $this->telegramSetting(['notify_chat_id' => '12345']);

        (new SendApprovalToTelegramJob($approval, '12345'))->handle();

        $this->assertSame(3, TelegramInteraction::where('target_id', $approval->id)->count());
        $this->assertSame(
            3,
            TelegramInteraction::where('target_id', $approval->id)
                ->where('allowed_actor_rule->checksum_version', TelegramInteraction::CHECKSUM_VERSION)
                ->whereNotNull('payload_checksum')
                ->count(),
        );
        $this->assertDatabaseHas('telegram_deliveries', [
            'source_type' => ApprovalRequest::class,
            'source_id' => $approval->id,
            'chat_id' => '12345',
            'telegram_message_id' => '700',
            'parse_mode' => 'plain_text_fallback',
            'status' => 'sent',
            'renderer_version' => 'telegram-approval-card:v1',
        ]);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $markup = json_decode($request['reply_markup'], true);
            $buttons = collect($markup['inline_keyboard'])->flatten(1);
            $callbackData = $buttons->pluck('callback_data')->all();

            return str_contains($request['text'], 'Approval needed')
                && str_contains($request['text'], 'Send renewal email')
                && str_contains($request['text'], 'Tool: send_channel_message')
                && str_contains($request['text'], 'Arguments: channelId=chan_123, content=Hello renewal team, api_key=[redacted]')
                && str_contains($request['text'], 'Risk: high')
                && str_contains($request['text'], 'Button expiry: 30 minutes')
                && ! str_contains($request['text'], 'Audit ID:')
                && count($callbackData) === 3
                && collect($callbackData)->every(fn ($value) => str_starts_with($value, 'tg_'))
                && collect($callbackData)->every(fn ($value) => ! str_contains($value, 'approve:'));
        });
    }

    public function test_telegram_tampered_persisted_interaction_is_rejected_before_mutation(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 700]])]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $responder = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $responder->id,
            'role' => 'member',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $responder->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $approval = ApprovalRequest::factory()->pending()->create([
            'requester_id' => $agent->id,
            'title' => 'Sensitive change',
        ]);
        $this->telegramSetting(['notify_chat_id' => '12345']);

        (new SendApprovalToTelegramJob($approval, '12345'))->handle();

        $approve = TelegramInteraction::where('target_id', $approval->id)
            ->where('payload->action', 'approve')
            ->firstOrFail();
        $approve->update([
            'payload' => [
                'action' => 'reject',
                'approval_id' => $approval->id,
            ],
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1551,
                'callback_query' => [
                    'id' => 'tampered-interaction-cb',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'message' => [
                        'message_id' => 700,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $approve->token,
                ],
            ])
            ->assertOk();

        $approval->refresh();
        $approve->refresh();

        $this->assertSame('pending', $approval->status);
        $this->assertNull($approval->responded_by_id);
        $this->assertSame('tampered', $approve->final_status);
        $this->assertNotNull($approve->resolved_at);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
            && str_contains((string) ($request->data()['text'] ?? ''), 'no longer valid'));
    }

    public function test_telegram_outbound_sync_is_app_owned_and_records_delivery(): void
    {
        Http::fake([
            'api.telegram.org/*/sendMessage' => Http::sequence()
                ->push([
                    'ok' => false,
                    'error_code' => 400,
                    'description' => "Bad Request: can't parse entities: malformed HTML",
                ], 400)
                ->push([
                    'ok' => true,
                    'result' => ['message_id' => 90210],
                ]),
        ]);

        $this->telegramSetting();
        $user = User::factory()->create(['name' => 'Rutger']);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Product',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345:99',
            'external_config' => [
                'adapter' => 'telegram',
                'chat_id' => '12345',
                'topic_id' => '99',
                'thread_id' => 'telegram:12345:99',
            ],
        ]);

        $message = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Outbound **Telegram** update',
            'channel_id' => $channel->id,
            'author_id' => $user->id,
            'timestamp' => now(),
        ]);

        app(SyncToChat::class)->handleMessageSent(new MessageSent($message));

        $message->refresh();

        $this->assertSame('90210', $message->external_message_id);
        $this->assertDatabaseHas('telegram_deliveries', [
            'source_type' => Message::class,
            'source_id' => $message->id,
            'chat_id' => '12345',
            'topic_id' => '99',
            'telegram_message_id' => '90210',
            'parse_mode' => 'plain_text_fallback',
            'status' => 'sent',
            'renderer_version' => 'telegram-outbound-sync:v1',
        ]);

        $this->assertSame(1, TelegramDelivery::where('source_id', $message->id)->count());
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && $request['chat_id'] === '12345'
            && $request['message_thread_id'] === 99
            && str_contains($request['text'], 'Outbound'));
        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), '/sendMessage')
                && ! isset($data['parse_mode'])
                && ! str_contains($request['text'], '<b>')
                && str_contains($request['text'], 'Outbound');
        });
    }

    public function test_telegram_outbound_sync_omits_general_topic_thread_id(): void
    {
        Http::fake([
            'api.telegram.org/*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 90211],
            ]),
        ]);

        $this->telegramSetting();
        $user = User::factory()->create(['name' => 'Rutger']);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: General',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345:1',
            'external_config' => [
                'adapter' => 'telegram',
                'chat_id' => '12345',
                'topic_id' => '1',
                'thread_id' => 'telegram:12345:1',
            ],
        ]);

        $message = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'General topic update',
            'channel_id' => $channel->id,
            'author_id' => $user->id,
            'timestamp' => now(),
        ]);

        app(SyncToChat::class)->handleMessageSent(new MessageSent($message));

        $this->assertDatabaseHas('telegram_deliveries', [
            'source_type' => Message::class,
            'source_id' => $message->id,
            'topic_id' => '1',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && (string) $request['chat_id'] === '12345'
            && ! array_key_exists('message_thread_id', $request->data())
            && str_contains($request['text'], 'General topic update'));
    }

    public function test_telegram_outbound_sync_sends_direct_message_topic_id(): void
    {
        Http::fake([
            'api.telegram.org/*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 90212],
            ]),
        ]);

        $this->telegramSetting();
        $user = User::factory()->create(['name' => 'Rutger']);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Channel DM Topic',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '-100555',
            'external_config' => [
                'adapter' => 'telegram',
                'chat_id' => '-100555',
                'direct_messages_topic_id' => '777',
                'thread_id' => 'telegram:-100555:dm:777',
            ],
        ]);

        $message = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Direct-message topic update',
            'channel_id' => $channel->id,
            'author_id' => $user->id,
            'timestamp' => now(),
        ]);

        app(SyncToChat::class)->handleMessageSent(new MessageSent($message));

        $this->assertDatabaseHas('telegram_deliveries', [
            'source_type' => Message::class,
            'source_id' => $message->id,
            'direct_messages_topic_id' => '777',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && (string) $request['chat_id'] === '-100555'
            && (int) $request['direct_messages_topic_id'] === 777
            && ! array_key_exists('message_thread_id', $request->data())
            && str_contains($request['text'], 'Direct-message topic update'));
    }

    public function test_telegram_outbound_sync_records_rate_limit_retry_metadata(): void
    {
        Http::fake([
            'api.telegram.org/*/sendMessage' => Http::response([
                'ok' => false,
                'error_code' => 429,
                'description' => 'Too Many Requests: retry after 17',
                'parameters' => ['retry_after' => 17],
            ], 429),
        ]);

        $this->telegramSetting();
        $user = User::factory()->create(['name' => 'Rutger']);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Product',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345',
            'external_config' => [
                'adapter' => 'telegram',
                'chat_id' => '12345',
            ],
        ]);
        $message = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Rate limited update',
            'channel_id' => $channel->id,
            'author_id' => $user->id,
            'timestamp' => now(),
        ]);

        app(SyncToChat::class)->handleMessageSent(new MessageSent($message));

        $message->refresh();
        $this->assertNull($message->external_message_id);

        $delivery = TelegramDelivery::where('source_id', $message->id)->firstOrFail();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(TelegramRateLimitException::class, $delivery->provider_error_code);
        $this->assertStringContainsString('rate limit', $delivery->provider_error_message);
        $this->assertSame(17, $delivery->response_payload['retry_after']);
        $this->assertNotEmpty($delivery->response_payload['retry_at']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && (string) $request['chat_id'] === '12345'
            && str_contains($request['text'], 'Rate limited update'));
    }

    public function test_telegram_upload_methods_use_structured_rate_limit_exception(): void
    {
        Http::fake([
            'api.telegram.org/*/sendDocument' => Http::response([
                'ok' => false,
                'error_code' => 429,
                'description' => 'Too Many Requests: retry after 13',
                'parameters' => ['retry_after' => 13],
            ], 429),
        ]);

        $this->telegramSetting();
        $path = tempnam(sys_get_temp_dir(), 'telegram-doc-');
        file_put_contents($path, 'hello');

        try {
            app(TelegramService::class)->sendDocument('12345', $path, 'Rate limited document');
            $this->fail('Expected Telegram upload rate limit exception.');
        } catch (TelegramRateLimitException $e) {
            $this->assertSame(13, $e->retryAfter);
            $this->assertStringContainsString('rate limit', $e->getMessage());
        } finally {
            @unlink($path);
        }
    }

    public function test_telegram_service_uses_configured_bot_api_and_file_base_urls(): void
    {
        config([
            'telegram.bot_api_base_url' => 'https://telegram-local.example/api/',
            'telegram.bot_api_file_base_url' => 'https://telegram-files.example/files/',
        ]);

        Http::fake([
            'https://telegram-local.example/api/bottelegram-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 123],
            ]),
            'https://telegram-local.example/api/bottelegram-token/getFile' => Http::response([
                'ok' => true,
                'result' => [
                    'file_id' => 'doc-file',
                    'file_unique_id' => 'doc-unique',
                    'file_size' => 11,
                    'file_path' => 'documents/report.txt',
                ],
            ]),
            'https://telegram-local.example/api/bottelegram-token/sendPhoto' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 124],
            ]),
            'https://telegram-files.example/files/bottelegram-token/documents/report.txt' => Http::response('hello world', 200, [
                'Content-Type' => 'text/plain',
            ]),
        ]);

        $this->telegramSetting();
        $path = tempnam(sys_get_temp_dir(), 'telegram-photo-');
        file_put_contents($path, 'fake photo bytes');

        try {
            $message = app(TelegramService::class)->sendMessage('12345', 'Hello local Bot API');
            $file = app(TelegramService::class)->getFile('doc-file');
            $bytes = app(TelegramService::class)->downloadFile('documents/report.txt');
            $photo = app(TelegramService::class)->sendPhoto('12345', $path, 'Local Bot API photo');
        } finally {
            @unlink($path);
        }

        $this->assertSame(123, $message['message_id']);
        $this->assertSame('documents/report.txt', $file['file_path']);
        $this->assertSame('hello world', $bytes);
        $this->assertSame(124, $photo['message_id']);

        Http::assertSent(fn ($request) => $request->url() === 'https://telegram-local.example/api/bottelegram-token/sendMessage'
            && json_decode((string) $request['link_preview_options'], true) === ['is_disabled' => true]);
        Http::assertSent(fn ($request) => $request->url() === 'https://telegram-local.example/api/bottelegram-token/getFile');
        Http::assertSent(fn ($request) => $request->url() === 'https://telegram-files.example/files/bottelegram-token/documents/report.txt');
        Http::assertSent(fn ($request) => $request->url() === 'https://telegram-local.example/api/bottelegram-token/sendPhoto');
    }

    public function test_telegram_edit_message_fallback_preserves_buttons_when_html_parse_fails(): void
    {
        Http::fake([
            'api.telegram.org/*/editMessageText' => Http::sequence()
                ->push([
                    'ok' => false,
                    'error_code' => 400,
                    'description' => "Bad Request: can't parse entities: malformed HTML",
                ], 400)
                ->push(['ok' => true, 'result' => ['message_id' => 90210]]),
        ]);

        $this->telegramSetting();

        $result = app(TelegramService::class)->editMessageText(
            '12345',
            700,
            '<b>Approval &amp; resolved</b>',
            ['inline_keyboard' => []],
        );

        $this->assertSame(90210, $result['message_id']);
        $this->assertTrue($result['_opencompany_parse_mode_fallback']);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), '/editMessageText')
                && ($data['parse_mode'] ?? null) === 'HTML'
                && $request['text'] === '<b>Approval &amp; resolved</b>'
                && json_decode((string) $request['link_preview_options'], true) === ['is_disabled' => true]
                && str_contains((string) $request['reply_markup'], 'inline_keyboard');
        });
        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), '/editMessageText')
                && ! isset($data['parse_mode'])
                && $request['text'] === 'Approval & resolved'
                && str_contains((string) $request['reply_markup'], 'inline_keyboard');
        });
    }

    public function test_telegram_edit_message_treats_not_modified_as_successful_noop(): void
    {
        Http::fake([
            'api.telegram.org/*/editMessageText' => Http::response([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: message is not modified: specified new message content and reply markup are exactly the same as a current content and reply markup of the message',
            ], 400),
        ]);

        $this->telegramSetting();

        $result = app(TelegramService::class)->editMessageText(
            '12345',
            700,
            '<b>Already current</b>',
            ['inline_keyboard' => []],
        );

        $this->assertSame(700, $result['message_id']);
        $this->assertTrue($result['_opencompany_noop']);
        $this->assertSame('message_not_modified', $result['_opencompany_noop_reason']);
    }

    public function test_telegram_card_renderer_does_not_treat_timestamps_as_labels(): void
    {
        $html = app(TelegramCardRenderer::class)->cardHtml("OpenCompany status\n\n2026-05-25 13:07 UTC");

        $this->assertStringContainsString('2026-05-25 13:07 UTC', $html);
        $this->assertStringNotContainsString('13:</b> 07', $html);
    }

    public function test_telegram_outbound_sync_records_edit_delete_pin_and_reaction_deliveries(): void
    {
        Http::fake([
            'api.telegram.org/*/editMessageText' => Http::response(['ok' => true, 'result' => ['message_id' => 90210]]),
            'api.telegram.org/*/deleteMessage' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/pinChatMessage' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/setMessageReaction' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->telegramSetting();
        $user = User::factory()->create(['name' => 'Rutger']);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Product',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345:99',
            'external_config' => [
                'adapter' => 'telegram',
                'chat_id' => '12345',
                'topic_id' => '99',
            ],
        ]);
        $message = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Edited **Telegram** update',
            'channel_id' => $channel->id,
            'author_id' => $user->id,
            'timestamp' => now(),
            'external_message_id' => '90210',
        ]);

        $listener = app(SyncToChat::class);
        $listener->handleMessageEdited(new MessageEdited($message));
        $listener->handleMessageDeleted(new MessageDeleted($message));
        $listener->handleMessagePinned(new MessagePinned($message));
        $listener->handleReactionAdded(new MessageReactionAdded($message, '👍'));

        $deliveries = TelegramDelivery::where('source_id', $message->id)
            ->get();

        $this->assertEqualsCanonicalizing([
            'editMessageText',
            'deleteMessage',
            'pinChatMessage',
            'setMessageReaction',
        ], $deliveries->pluck('request_payload.method')->all());
        $this->assertTrue($deliveries->every(fn (TelegramDelivery $delivery) => $delivery->status === 'sent'));
        $this->assertSame('Edited <b>Telegram</b> update', $deliveries->firstWhere('request_payload.method', 'editMessageText')->request_payload['text']);
        $this->assertSame('👍', $deliveries->firstWhere('request_payload.method', 'setMessageReaction')->request_payload['emoji']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/editMessageText')
            && $request['chat_id'] === '12345'
            && (int) $request['message_id'] === 90210
            && str_contains($request['text'], '<b>Telegram</b>'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/deleteMessage')
            && (int) $request['message_id'] === 90210);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/pinChatMessage')
            && (int) $request['message_id'] === 90210);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/setMessageReaction')
            && (int) $request['message_id'] === 90210);
    }

    public function test_telegram_media_group_updates_coalesce_into_one_logical_prompt(): void
    {
        config(['telegram.media_ingestion_enabled' => false]);
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 614]])]);

        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $this->telegramSetting(['default_agent_id' => $agent->id]);
        $chat = ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'];
        $from = ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'];

        foreach ([83 => 'Launch photos', 84 => null] as $messageId => $caption) {
            $message = [
                'message_id' => $messageId,
                'media_group_id' => 'album-1',
                'date' => now()->timestamp,
                'chat' => $chat,
                'from' => $from,
                'photo' => [[
                    'file_id' => 'photo-'.$messageId,
                    'file_unique_id' => 'photo-unique-'.$messageId,
                    'file_size' => 100,
                    'width' => 640,
                    'height' => 480,
                ]],
            ];

            if ($caption !== null) {
                $message['caption'] = $caption;
            }

            $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
                ->postJson('/api/webhooks/chat/telegram', [
                    'update_id' => 1800 + $messageId,
                    'message' => $message,
                ])
                ->assertOk();
        }

        $this->assertSame(1, Message::where('external_message_id', 'media_group:album-1')->count());
        $message = Message::where('external_message_id', 'media_group:album-1')->firstOrFail();
        $this->assertSame('Launch photos', $message->content);
        $this->assertSame(1, Task::where('title', 'Launch photos')->count());
        Queue::assertPushed(AgentRespondJob::class, 1);
    }

    public function test_telegram_rapid_text_splits_batch_into_one_logical_prompt(): void
    {
        config([
            'telegram.text_batching_enabled' => true,
            'telegram.text_batch_window_seconds' => 5,
        ]);
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 615]])]);
        Carbon::setTestNow(Carbon::parse('2026-05-25 12:00:00', 'UTC'));

        try {
            $agent = User::factory()->agent()->create(['name' => 'Iris']);
            $this->telegramSetting(['default_agent_id' => $agent->id]);
            $chat = ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'];
            $from = ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'];

            foreach ([83 => 'Please inspect', 84 => 'the renewal risk'] as $messageId => $text) {
                $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
                    ->postJson('/api/webhooks/chat/telegram', [
                        'update_id' => 1880 + $messageId,
                        'message' => [
                            'message_id' => $messageId,
                            'date' => now()->timestamp,
                            'chat' => $chat,
                            'from' => $from,
                            'text' => $text,
                        ],
                    ])
                    ->assertOk();

                Carbon::setTestNow(now()->addSecond());
            }

            $message = Message::where('source', 'telegram')->firstOrFail();
            $this->assertSame("Please inspect\nthe renewal risk", $message->content);
            $this->assertSame(1, Message::where('source', 'telegram')->count());

            $task = Task::where('trigger_message_id', $message->id)->firstOrFail();
            $this->assertSame("Please inspect\nthe renewal risk", $task->description);
            $this->assertSame('Please inspect', $task->title);
            $this->assertSame(['83', '84'], $task->context['telegram_text_batch']['message_ids']);
            $this->assertSame(2, $task->context['telegram_text_batch']['message_count']);
            Http::assertSent(fn ($request) => str_contains($request->url(), '/editMessageText')
                && str_contains($request['text'], '2 messages included')
                && str_contains($request['text'], 'the renewal risk'));

            $this->assertSame(2, DB::table('telegram_message_mappings')->where('message_id', $message->id)->count());
            Queue::assertPushed(AgentRespondJob::class, 1);

            $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
                ->postJson('/api/webhooks/chat/telegram', [
                    'update_id' => 1965,
                    'message' => [
                        'message_id' => 85,
                        'date' => now()->timestamp,
                        'chat' => $chat,
                        'from' => $from,
                        'text' => 'Follow up on that',
                        'reply_to_message' => [
                            'message_id' => 84,
                            'date' => now()->subSecond()->timestamp,
                            'chat' => $chat,
                            'from' => $from,
                            'text' => 'the renewal risk',
                        ],
                    ],
                ])
                ->assertOk();

            $reply = Message::where('external_message_id', '85')->firstOrFail();
            $this->assertSame($message->id, $reply->reply_to_id);
            $this->assertSame(2, Task::where('source', Task::SOURCE_CHAT)->count());
            $queuedReplyTask = Task::where('trigger_message_id', $reply->id)->firstOrFail();
            $this->assertSame(1, $queuedReplyTask->context['telegram_lane_queue']['open_count'] ?? null);
            Queue::assertPushed(AgentRespondJob::class, 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_telegram_task_card_preserves_code_like_literals(): void
    {
        $agent = User::factory()->agent()->create(['name' => 'Atlas']);
        $human = User::factory()->create(['name' => 'Rutger']);
        $task = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Reply exactly TELEGRAM_ATLAS_SMOKE_OK',
            'description' => 'Literal underscores should survive Telegram snippets.',
            'status' => Task::STATUS_PENDING,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
        ]);

        $text = app(TelegramCardRenderer::class)->taskCardText($task);

        $this->assertStringContainsString('TELEGRAM_ATLAS_SMOKE_OK', $text);
    }

    public function test_telegram_inbound_document_is_captured_as_workspace_file_attachment(): void
    {
        Queue::fake();
        Storage::fake('local');
        Http::fake([
            'api.telegram.org/bottelegram-token/getFile' => Http::response([
                'ok' => true,
                'result' => [
                    'file_id' => 'doc-file',
                    'file_unique_id' => 'doc-unique',
                    'file_size' => 11,
                    'file_path' => 'documents/report.txt',
                ],
            ]),
            'api.telegram.org/file/bottelegram-token/documents/report.txt' => Http::response('hello world', 200, [
                'Content-Type' => 'text/plain',
            ]),
            'api.telegram.org/*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 613],
            ]),
        ]);

        WorkspaceDisk::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
        $this->telegramSetting();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1007,
                'message' => [
                    'message_id' => 83,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'caption' => 'Quarterly report',
                    'document' => [
                        'file_id' => 'doc-file',
                        'file_unique_id' => 'doc-unique',
                        'file_name' => 'report.txt',
                        'mime_type' => 'text/plain',
                        'file_size' => 11,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('messages', [
            'content' => 'Quarterly report',
            'source' => 'telegram',
            'external_message_id' => '83',
        ]);

        $this->assertDatabaseHas('workspace_files', [
            'workspace_id' => $this->workspace->id,
            'name' => 'telegram-83-report.txt',
            'mime_type' => 'text/plain',
            'size' => 11,
        ]);

        $file = WorkspaceFile::where('name', 'telegram-83-report.txt')->firstOrFail();
        $this->assertSame('telegram', $file->metadata['source']);
        $this->assertSame('doc-file', $file->metadata['telegram_file_id']);
        Storage::disk('local')->assertExists($file->storage_path);
        $this->assertSame('hello world', Storage::disk('local')->get($file->storage_path));

        $this->assertDatabaseHas('message_attachments', [
            'original_name' => 'telegram-83-report.txt',
            'mime_type' => 'text/plain',
            'size' => 11,
            'url' => "/api/files/{$file->id}/download",
        ]);

        $channel = Channel::where('external_provider', 'telegram')->where('external_id', '12345')->firstOrFail();
        $agent = User::factory()->agent()->create(['name' => 'Atlas']);
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $recentMessages = json_decode(
            (new ReadRecentMessages($agent, app(\App\Services\AgentPermissionService::class)))
                ->handle(new ToolRequest(['channelId' => $channel->id])),
            true,
        );
        $this->assertSame('/Telegram Captures/telegram-83-report.txt', $recentMessages[0]['attachments'][0]['path']);
        $this->assertSame('hello world', $recentMessages[0]['attachments'][0]['textPreview']);

        $listedFiles = json_decode(
            (new ListFiles($agent, app(\App\Services\AgentPermissionService::class), app(\App\Services\FileSystemService::class)))
                ->handle(new ToolRequest(['folder' => 'Telegram Captures'])),
            true,
        );
        $this->assertSame('/Telegram Captures/telegram-83-report.txt', $listedFiles[0]['path']);

        $summarize = TelegramInteraction::where('interaction_type', 'media_capture')
            ->where('payload->action', 'summarize')
            ->firstOrFail();
        $switchAgent = TelegramInteraction::where('interaction_type', 'command')
            ->where('payload->command', '/agents')
            ->firstOrFail();
        $topic = TelegramInteraction::where('interaction_type', 'command')
            ->where('payload->command', '/topic')
            ->firstOrFail();
        $this->assertDatabaseMissing('telegram_interactions', [
            'interaction_type' => 'media_capture',
            'payload->action' => 'change_folder',
        ]);
        $this->assertDatabaseMissing('telegram_interactions', [
            'interaction_type' => 'media_capture',
            'payload->action' => 'choose_doc',
        ]);

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-media-capture-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) use ($summarize, $switchAgent, $topic) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $replyMarkup = $request->data()['reply_markup'] ?? $request['reply_markup'] ?? null;
            $replyMarkupJson = is_string($replyMarkup) ? $replyMarkup : json_encode($replyMarkup);

            return str_contains((string) $request['text'], 'Added to agent')
                && str_contains((string) $request['text'], 'telegram-83-report.txt')
                && str_contains((string) $replyMarkupJson, $summarize->token)
                && str_contains((string) $replyMarkupJson, $switchAgent->token)
                && str_contains((string) $replyMarkupJson, $topic->token)
                && str_contains((string) $replyMarkupJson, 'Ask agent')
                && str_contains((string) $replyMarkupJson, 'Switch agent')
                && ! str_contains((string) $replyMarkupJson, 'Move')
                && ! str_contains((string) $replyMarkupJson, 'Add doc')
                && ! str_contains((string) $replyMarkupJson, 'Draft doc');
        });
    }

    public function test_telegram_media_capture_folder_picker_moves_files_to_selected_workspace_folder(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 615]])]);

        $setting = $this->telegramSetting();
        $actor = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $actor->id,
            'role' => 'member',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $actor->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $disk = WorkspaceDisk::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Rutger',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345',
        ]);
        $message = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Move this evidence',
            'channel_id' => $channel->id,
            'author_id' => $actor->id,
            'timestamp' => now(),
            'source' => 'telegram',
            'external_message_id' => '83',
        ]);
        TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'chat_type' => 'private',
            'channel_id' => $channel->id,
            'mode' => 'agent',
            'last_seen_at' => now(),
        ]);
        $capturesFolder = WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram Captures',
            'is_folder' => true,
            'workspace_disk_id' => $disk->id,
            'owner_id' => $actor->id,
        ]);
        $destinationFolder = WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Customer Evidence',
            'is_folder' => true,
            'workspace_disk_id' => $disk->id,
            'owner_id' => $actor->id,
        ]);
        $file = WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'parent_id' => $capturesFolder->id,
            'name' => 'telegram-83-report.txt',
            'is_folder' => false,
            'storage_disk' => 'local',
            'workspace_disk_id' => $disk->id,
            'storage_path' => 'workspaces/report.txt',
            'mime_type' => 'text/plain',
            'size' => 11,
            'owner_id' => $actor->id,
            'metadata' => ['source' => 'telegram'],
        ]);
        $changeFolder = TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'media_capture',
            'target_type' => Message::class,
            'target_id' => $message->id,
            'payload' => [
                'action' => 'change_folder',
                'message_id' => $message->id,
                'workspace_file_ids' => [$file->id],
            ],
            'allowed_actor_rule' => ['type' => 'linked_workspace_member'],
            'expires_at' => now()->addMinutes(10),
        ]));

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1009,
                'callback_query' => [
                    'id' => 'cb-folder-picker',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'message' => [
                        'message_id' => 83,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $changeFolder->token,
                ],
            ])
            ->assertOk();

        $changeFolder->refresh();
        $this->assertSame('folder_picker_opened', $changeFolder->final_status);
        $this->assertNotNull($changeFolder->resolved_at);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-media-folder-picker-card:v1',
            'status' => 'sent',
        ]);

        $moveToDestination = TelegramInteraction::where('interaction_type', 'media_capture')
            ->where('payload->action', 'move_to_folder')
            ->where('payload->folder_id', $destinationFolder->id)
            ->firstOrFail();

        Http::assertSent(function ($request) use ($moveToDestination) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $replyMarkup = $request->data()['reply_markup'] ?? $request['reply_markup'] ?? null;
            $replyMarkupJson = is_string($replyMarkup) ? $replyMarkup : json_encode($replyMarkup);

            return str_contains((string) $request['text'], 'Move Telegram files')
                && str_contains((string) $request['text'], 'Customer Evidence')
                && str_contains((string) $request['text'], 'Top level')
                && str_contains((string) $replyMarkupJson, $moveToDestination->token);
        });

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1010,
                'callback_query' => [
                    'id' => 'cb-move-folder',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'message' => [
                        'message_id' => 84,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $moveToDestination->token,
                ],
            ])
            ->assertOk();

        $this->assertSame($destinationFolder->id, $file->refresh()->parent_id);
        $this->assertSame('moved', $moveToDestination->refresh()->final_status);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'Moved 1 Telegram file(s)')
            && str_contains((string) $request['text'], '/Customer Evidence'));
    }

    public function test_telegram_media_capture_document_picker_attaches_files_to_existing_doc(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 616]])]);

        $setting = $this->telegramSetting();
        $actor = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $actor->id,
            'role' => 'member',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $actor->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $disk = WorkspaceDisk::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Rutger',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345',
        ]);
        $message = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Attach this to the renewal doc',
            'channel_id' => $channel->id,
            'author_id' => $actor->id,
            'timestamp' => now(),
            'source' => 'telegram',
            'external_message_id' => '86',
        ]);
        TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'chat_type' => 'private',
            'channel_id' => $channel->id,
            'mode' => 'agent',
            'last_seen_at' => now(),
        ]);
        $document = Document::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Renewal evidence brief',
            'content' => '# Renewal evidence brief',
            'content_format' => 'markdown',
            'author_id' => $actor->id,
            'is_folder' => false,
            'icon' => 'file-text',
            'updated_at' => now()->addMinute(),
        ]);
        $file = WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'telegram-86-renewal.txt',
            'is_folder' => false,
            'storage_disk' => 'local',
            'workspace_disk_id' => $disk->id,
            'storage_path' => 'workspaces/renewal.txt',
            'mime_type' => 'text/plain',
            'size' => 31,
            'owner_id' => $actor->id,
            'metadata' => ['source' => 'telegram'],
        ]);
        $chooseDoc = TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'media_capture',
            'target_type' => Message::class,
            'target_id' => $message->id,
            'payload' => [
                'action' => 'choose_doc',
                'message_id' => $message->id,
                'workspace_file_ids' => [$file->id],
            ],
            'allowed_actor_rule' => ['type' => 'linked_workspace_member'],
            'expires_at' => now()->addMinutes(10),
        ]));

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1012,
                'callback_query' => [
                    'id' => 'cb-doc-picker',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'message' => [
                        'message_id' => 86,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $chooseDoc->token,
                ],
            ])
            ->assertOk();

        $chooseDoc->refresh();
        $this->assertSame('document_picker_opened', $chooseDoc->final_status);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-media-document-picker-card:v1',
            'status' => 'sent',
        ]);

        $attach = TelegramInteraction::where('interaction_type', 'media_capture')
            ->where('payload->action', 'attach_to_doc')
            ->where('payload->document_id', $document->id)
            ->firstOrFail();

        Http::assertSent(function ($request) use ($attach) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $replyMarkup = $request->data()['reply_markup'] ?? $request['reply_markup'] ?? null;
            $replyMarkupJson = is_string($replyMarkup) ? $replyMarkup : json_encode($replyMarkup);

            return str_contains((string) $request['text'], 'Add Telegram files to doc')
                && str_contains((string) $request['text'], 'Renewal evidence brief')
                && str_contains((string) $replyMarkupJson, $attach->token);
        });

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1013,
                'callback_query' => [
                    'id' => 'cb-doc-attach',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'message' => [
                        'message_id' => 87,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $attach->token,
                ],
            ])
            ->assertOk();

        $this->assertSame('attached_to_doc', $attach->fresh()->final_status);
        $this->assertDatabaseHas('document_attachments', [
            'document_id' => $document->id,
            'original_name' => 'telegram-86-renewal.txt',
            'url' => "/api/files/{$file->id}/download",
            'uploaded_by_id' => $actor->id,
        ]);
        $this->assertStringContainsString('Attach this to the renewal doc', $document->refresh()->content);
        $this->assertStringContainsString('telegram-86-renewal.txt', $document->content);

        $this->assertTrue(
            TelegramDelivery::where('renderer_version', TelegramCardRenderer::NOTICE_VERSION)
                ->get()
                ->contains(fn (TelegramDelivery $delivery): bool => str_contains((string) ($delivery->request_payload['text'] ?? ''), 'Files added to doc')
                    && str_contains((string) ($delivery->request_payload['text'] ?? ''), 'Renewal evidence brief')
                    && str_contains((string) ($delivery->request_payload['text'] ?? ''), 'Attached')),
        );
    }

    public function test_telegram_photo_and_voice_capture_queue_enrichment_metadata(): void
    {
        Queue::fake();
        Storage::fake('local');
        Http::fake([
            'api.telegram.org/bottelegram-token/getFile' => Http::sequence()
                ->push([
                    'ok' => true,
                    'result' => [
                        'file_id' => 'photo-file',
                        'file_unique_id' => 'photo-unique',
                        'file_size' => 10,
                        'file_path' => 'photos/photo.jpg',
                    ],
                ])
                ->push([
                    'ok' => true,
                    'result' => [
                        'file_id' => 'voice-file',
                        'file_unique_id' => 'voice-unique',
                        'file_size' => 10,
                        'file_path' => 'voice/voice.ogg',
                    ],
                ]),
            'api.telegram.org/file/bottelegram-token/photos/photo.jpg' => Http::response('photo-data', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
            'api.telegram.org/file/bottelegram-token/voice/voice.ogg' => Http::response('voice-data', 200, [
                'Content-Type' => 'audio/ogg',
            ]),
            'api.telegram.org/*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 614],
            ]),
        ]);

        WorkspaceDisk::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
        $this->telegramSetting();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1008,
                'message' => [
                    'message_id' => 84,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'caption' => 'Capture this customer evidence',
                    'photo' => [[
                        'file_id' => 'photo-file',
                        'file_unique_id' => 'photo-unique',
                        'file_size' => 10,
                        'width' => 640,
                        'height' => 480,
                    ]],
                    'voice' => [
                        'file_id' => 'voice-file',
                        'file_unique_id' => 'voice-unique',
                        'duration' => 7,
                        'mime_type' => 'audio/ogg',
                    ],
                ],
            ])
            ->assertOk();

        $photo = WorkspaceFile::where('name', 'telegram-84-photo.jpg')->firstOrFail();
        $voice = WorkspaceFile::where('name', 'telegram-84-voice.ogg')->firstOrFail();

        $this->assertSame([
            'status' => 'queued',
            'type' => 'description',
            'cache_key' => 'telegram_media_description:photo-unique',
        ], $photo->metadata['telegram_enrichment']);
        $this->assertSame([
            'status' => 'queued',
            'type' => 'transcription',
            'cache_key' => 'telegram_media_transcription:voice-unique',
        ], $voice->metadata['telegram_enrichment']);
        Queue::assertPushed(EnrichTelegramMediaJob::class, 2);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'Processing')
            && str_contains((string) $request['text'], 'Photo understanding queued')
            && str_contains((string) $request['text'], 'Voice transcript queued'));
    }

    public function test_telegram_media_enrichment_job_transcribes_voice_metadata(): void
    {
        Storage::fake('local');
        Transcription::fake(['Customer says renewal risk is tied to onboarding delays.']);

        WorkspaceDisk::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
        $owner = User::factory()->create();
        Storage::disk('local')->put('workspaces/voice.ogg', 'voice-bytes');

        $file = WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'telegram-84-voice.ogg',
            'is_folder' => false,
            'storage_disk' => 'local',
            'workspace_disk_id' => WorkspaceDisk::where('workspace_id', $this->workspace->id)->value('id'),
            'storage_path' => 'workspaces/voice.ogg',
            'mime_type' => 'audio/ogg',
            'size' => 10,
            'owner_id' => $owner->id,
            'metadata' => [
                'source' => 'telegram',
                'telegram_enrichment' => [
                    'status' => 'queued',
                    'type' => 'transcription',
                    'cache_key' => 'telegram_media_transcription:voice-unique',
                ],
            ],
        ]);

        EnrichTelegramMediaJob::dispatchSync($file);

        $enrichment = $file->refresh()->metadata['telegram_enrichment'];
        $this->assertSame('completed', $enrichment['status']);
        $this->assertSame('transcription', $enrichment['type']);
        $this->assertSame('Customer says renewal risk is tied to onboarding delays.', $enrichment['text']);
        $this->assertSame('telegram_media_transcription:voice-unique', $enrichment['cache_key']);
        Transcription::assertGenerated(fn ($prompt) => true);
    }

    public function test_telegram_media_enrichment_job_describes_image_metadata(): void
    {
        Storage::fake('local');
        OneShotTextAgent::fake(['Screenshot shows an OpenCompany task board with a delayed renewal item.']);

        WorkspaceDisk::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
        $owner = User::factory()->create();
        Storage::disk('local')->put('workspaces/photo.jpg', 'photo-bytes');

        $file = WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'telegram-84-photo.jpg',
            'is_folder' => false,
            'storage_disk' => 'local',
            'workspace_disk_id' => WorkspaceDisk::where('workspace_id', $this->workspace->id)->value('id'),
            'storage_path' => 'workspaces/photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 10,
            'owner_id' => $owner->id,
            'metadata' => [
                'source' => 'telegram',
                'telegram_enrichment' => [
                    'status' => 'queued',
                    'type' => 'description',
                    'cache_key' => 'telegram_media_description:photo-unique',
                ],
            ],
        ]);

        EnrichTelegramMediaJob::dispatchSync($file);

        $enrichment = $file->refresh()->metadata['telegram_enrichment'];
        $this->assertSame('completed', $enrichment['status']);
        $this->assertSame('description', $enrichment['type']);
        $this->assertSame('Screenshot shows an OpenCompany task board with a delayed renewal item.', $enrichment['text']);
        $this->assertSame('telegram_media_description:photo-unique', $enrichment['cache_key']);
        OneShotTextAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Describe this Telegram attachment')
            && $prompt->attachments->count() === 1);
    }

    public function test_telegram_media_ingestion_can_be_disabled_by_runtime_flag(): void
    {
        config(['telegram.media_ingestion_enabled' => false]);
        Queue::fake();
        Storage::fake('local');
        Http::fake();

        WorkspaceDisk::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
        $this->telegramSetting();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1008,
                'message' => [
                    'message_id' => 84,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'caption' => 'Do not ingest this report',
                    'document' => [
                        'file_id' => 'doc-file',
                        'file_unique_id' => 'doc-unique',
                        'file_name' => 'report.txt',
                        'mime_type' => 'text/plain',
                        'file_size' => 11,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('messages', [
            'content' => 'Do not ingest this report',
            'source' => 'telegram',
            'external_message_id' => '84',
        ]);
        $this->assertDatabaseMissing('workspace_files', [
            'name' => 'telegram-84-report.txt',
        ]);
        $this->assertDatabaseCount('message_attachments', 0);
        Http::assertNothingSent();
    }

    public function test_legacy_telegram_webhook_is_disabled_by_default(): void
    {
        $this->telegramSetting();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/telegram', [
                'update_id' => 3001,
                'message' => [
                    'message_id' => 1,
                    'chat' => ['id' => 12345],
                    'from' => ['id' => 111],
                    'text' => '/start',
                ],
            ])
            ->assertStatus(410);
    }

    public function test_legacy_telegram_webhook_remains_non_mutating_even_if_flag_is_enabled(): void
    {
        config(['telegram.legacy_webhook_enabled' => true]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
        $this->telegramSetting();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/telegram', [
                'update_id' => 3002,
                'message' => [
                    'message_id' => 2,
                    'chat' => ['id' => 12345, 'type' => 'private'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'text' => '/start',
                ],
            ])
            ->assertStatus(410);

        $this->assertDatabaseMissing('telegram_update_receipts', ['update_id' => '3002']);
        $this->assertDatabaseCount('channels', 0);
        $this->assertDatabaseCount('messages', 0);
        Http::assertNothingSent();
    }

    public function test_telegram_media_capture_create_doc_callback_creates_document_with_attachments(): void
    {
        Queue::fake();
        Storage::fake('local');
        Http::fake([
            'api.telegram.org/bottelegram-token/getFile' => Http::response([
                'ok' => true,
                'result' => [
                    'file_id' => 'doc-file',
                    'file_unique_id' => 'doc-unique',
                    'file_size' => 11,
                    'file_path' => 'documents/report.txt',
                ],
            ]),
            'api.telegram.org/file/bottelegram-token/documents/report.txt' => Http::response('hello world', 200, [
                'Content-Type' => 'text/plain',
            ]),
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 614],
            ]),
        ]);

        WorkspaceDisk::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
        $setting = $this->telegramSetting();
        $human = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $human->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1010,
                'message' => [
                    'message_id' => 85,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'caption' => 'Turn this into a doc',
                    'document' => [
                        'file_id' => 'doc-file',
                        'file_unique_id' => 'doc-unique',
                        'file_name' => 'report.txt',
                        'mime_type' => 'text/plain',
                        'file_size' => 11,
                    ],
                ],
            ])
            ->assertOk();

        $sourceMessage = Message::where('external_message_id', '85')->firstOrFail();
        $file = WorkspaceFile::where('name', 'telegram-85-report.txt')->firstOrFail();
        $createDoc = TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'media_capture',
            'target_type' => Message::class,
            'target_id' => $sourceMessage->id,
            'payload' => [
                'action' => 'create_doc',
                'message_id' => $sourceMessage->id,
                'workspace_file_ids' => [$file->id],
            ],
            'allowed_actor_rule' => ['type' => 'linked_workspace_member'],
            'expires_at' => now()->addMinutes(10),
        ]));

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 1011,
                'callback_query' => [
                    'id' => 'media-doc-cb-1',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'message' => [
                        'message_id' => 614,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $createDoc->token,
                ],
            ])
            ->assertOk();

        $document = Document::where('title', 'like', 'Telegram capture - telegram-85-report.txt%')->firstOrFail();
        $this->assertSame($this->workspace->id, $document->workspace_id);
        $this->assertSame($human->id, $document->author_id);
        $this->assertStringContainsString('Turn this into a doc', $document->content);
        $this->assertStringContainsString('telegram-85-report.txt', $document->content);
        $this->assertSame('doc_created', $createDoc->fresh()->final_status);
        $this->assertSame(Document::class, $createDoc->fresh()->target_type);
        $this->assertSame($document->id, $createDoc->fresh()->target_id);

        $this->assertDatabaseHas('document_attachments', [
            'document_id' => $document->id,
            'original_name' => 'telegram-85-report.txt',
            'url' => "/api/files/{$file->id}/download",
            'uploaded_by_id' => $human->id,
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
            && str_contains((string) ($request->data()['text'] ?? ''), 'Document created'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'Document created: Telegram capture - telegram-85-report.txt'));
    }

    public function test_telegram_structured_location_venue_contact_and_poll_payloads_are_normalized(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $this->telegramSetting();

        $payloads = [
            [
                'update_id' => 2030,
                'message_id' => 130,
                'body' => [
                    'location' => [
                        'latitude' => 52.3676,
                        'longitude' => 4.9041,
                    ],
                ],
            ],
            [
                'update_id' => 2031,
                'message_id' => 131,
                'body' => [
                    'venue' => [
                        'title' => 'Amsterdam Office',
                        'address' => 'Damrak 1',
                        'location' => [
                            'latitude' => 52.375,
                            'longitude' => 4.9,
                        ],
                    ],
                ],
            ],
            [
                'update_id' => 2032,
                'message_id' => 132,
                'body' => [
                    'contact' => [
                        'first_name' => 'Anna',
                        'last_name' => 'Smith',
                        'phone_number' => '+31201234567',
                        'user_id' => 777,
                    ],
                ],
            ],
            [
                'update_id' => 2033,
                'message_id' => 133,
                'body' => [
                    'poll' => [
                        'question' => 'Ship today?',
                        'options' => [
                            ['text' => 'Yes', 'voter_count' => 3],
                            ['text' => 'No', 'voter_count' => 1],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($payloads as $payload) {
            $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
                ->postJson('/api/webhooks/chat/telegram', [
                    'update_id' => $payload['update_id'],
                    'message' => array_merge([
                        'message_id' => $payload['message_id'],
                        'date' => now()->timestamp,
                        'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                        'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    ], $payload['body']),
                ])
                ->assertOk();
        }

        $this->assertStringContainsString(
            'Map: https://maps.google.com/?q=52.3676,4.9041',
            Message::where('external_message_id', '130')->firstOrFail()->content,
        );
        $this->assertStringContainsString(
            'Title: Amsterdam Office',
            Message::where('external_message_id', '131')->firstOrFail()->content,
        );
        $this->assertStringContainsString(
            'Phone: +31201234567',
            Message::where('external_message_id', '132')->firstOrFail()->content,
        );
        $this->assertStringContainsString(
            '- Yes (3 votes)',
            Message::where('external_message_id', '133')->firstOrFail()->content,
        );

        Queue::assertNotPushed(AgentRespondJob::class);
    }

    public function test_telegram_modern_message_metadata_is_normalized_for_agents(): void
    {
        config(['telegram.media_ingestion_enabled' => false]);
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $this->telegramSetting();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2034,
                'message' => [
                    'message_id' => 134,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'checklist' => [
                        'title' => 'Launch prep',
                        'tasks' => [
                            ['id' => 1, 'text' => 'Confirm billing', 'is_checked' => false],
                            ['id' => 2, 'text' => 'Ship announcement', 'is_checked' => true],
                        ],
                    ],
                    'effect_id' => '5107584321108051014',
                    'is_paid_post' => true,
                    'paid_star_count' => 25,
                    'suggested_post_info' => [
                        'state' => 'pending',
                        'price' => ['currency' => 'XTR', 'amount' => 250],
                        'send_date' => 1779727200,
                    ],
                ],
            ])
            ->assertOk();

        $content = Message::where('external_message_id', '134')->firstOrFail()->content;

        $this->assertStringContainsString('Telegram checklist', $content);
        $this->assertStringContainsString('Title: Launch prep', $content);
        $this->assertStringContainsString('[ ] Confirm billing', $content);
        $this->assertStringContainsString('[x] Ship announcement', $content);
        $this->assertStringContainsString('Telegram effect: 5107584321108051014', $content);
        $this->assertStringContainsString('Telegram paid post', $content);
        $this->assertStringContainsString('Telegram paid stars: 25', $content);
        $this->assertStringContainsString('Telegram suggested post', $content);
        $this->assertStringContainsString('Price: 250 XTR', $content);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2035,
                'message' => [
                    'message_id' => 135,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'managed_bot_created' => [
                        'bot' => ['id' => 333, 'is_bot' => true, 'first_name' => 'Ops Bot', 'username' => 'ops_bot'],
                        'manage_url' => 'https://t.me/ops_bot?start=manage',
                    ],
                ],
            ])
            ->assertOk();

        $this->assertStringContainsString(
            'Telegram managed bot created',
            Message::where('external_message_id', '135')->firstOrFail()->content,
        );
        $this->assertStringContainsString(
            'Bot: Ops Bot @ops_bot (333)',
            Message::where('external_message_id', '135')->firstOrFail()->content,
        );

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2036,
                'message' => [
                    'message_id' => 136,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'poll_option_added' => [
                        'poll_id' => 'poll-1',
                        'option' => ['text' => 'Ship Friday'],
                    ],
                ],
            ])
            ->assertOk();

        $this->assertStringContainsString(
            'Telegram poll option added',
            Message::where('external_message_id', '136')->firstOrFail()->content,
        );
        $this->assertStringContainsString(
            'Option: Ship Friday',
            Message::where('external_message_id', '136')->firstOrFail()->content,
        );

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2037,
                'message' => [
                    'message_id' => 137,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'chat_owner_changed' => [
                        'old_owner' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                        'new_owner' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Atlas'],
                    ],
                ],
            ])
            ->assertOk();

        $this->assertStringContainsString(
            'Telegram chat owner changed',
            Message::where('external_message_id', '137')->firstOrFail()->content,
        );
        $this->assertStringContainsString(
            'New owner: Atlas (222)',
            Message::where('external_message_id', '137')->firstOrFail()->content,
        );

        Queue::assertNotPushed(AgentRespondJob::class);
    }

    public function test_telegram_paid_media_and_live_photo_assets_are_captured(): void
    {
        Queue::fake();
        Storage::fake('local');
        Http::fake([
            'api.telegram.org/bottelegram-token/getFile' => Http::sequence()
                ->push([
                    'ok' => true,
                    'result' => [
                        'file_id' => 'live-video-file',
                        'file_unique_id' => 'live-video-unique',
                        'file_size' => 12,
                        'file_path' => 'videos/live.mp4',
                    ],
                ])
                ->push([
                    'ok' => true,
                    'result' => [
                        'file_id' => 'paid-photo-file',
                        'file_unique_id' => 'paid-photo-unique',
                        'file_size' => 11,
                        'file_path' => 'photos/paid.jpg',
                    ],
                ]),
            'api.telegram.org/file/bottelegram-token/photos/paid.jpg' => Http::response('paid-photo', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
            'api.telegram.org/file/bottelegram-token/videos/live.mp4' => Http::response('live-video', 200, [
                'Content-Type' => 'video/mp4',
            ]),
            'api.telegram.org/*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 615],
            ]),
        ]);

        WorkspaceDisk::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
        $this->telegramSetting();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2035,
                'message' => [
                    'message_id' => 135,
                    'date' => now()->timestamp,
                    'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'paid_media' => [
                        'star_count' => 25,
                        'paid_media' => [[
                            'type' => 'photo',
                            'photo' => [[
                                'file_id' => 'paid-photo-file',
                                'file_unique_id' => 'paid-photo-unique',
                                'file_size' => 11,
                                'width' => 640,
                                'height' => 480,
                            ]],
                        ]],
                    ],
                    'live_photo' => [
                        'duration' => 2.5,
                        'video' => [
                            'file_id' => 'live-video-file',
                            'file_unique_id' => 'live-video-unique',
                            'file_size' => 12,
                            'mime_type' => 'video/mp4',
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $message = Message::where('external_message_id', '135')->firstOrFail();
        $this->assertStringContainsString('Telegram paid media', $message->content);
        $this->assertStringContainsString('Stars: 25', $message->content);

        $this->assertDatabaseHas('workspace_files', [
            'workspace_id' => $this->workspace->id,
            'name' => 'telegram-135-paid-media-0.jpg',
        ]);
        $this->assertDatabaseHas('workspace_files', [
            'workspace_id' => $this->workspace->id,
            'name' => 'telegram-135-live-photo-video.mp4',
        ]);
        $this->assertSame(2, $message->attachments()->count());
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Added to agent')
            && str_contains($request['text'], 'ready')
            && str_contains($request['text'], '2'));
        Queue::assertNotPushed(AgentRespondJob::class);
    }

    public function test_telegram_lists_and_tables_commands_render_compact_workspace_cards(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 611]])]);

        $this->enableTelegramDirectResourceCommands();
        $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $project = ListItem::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Launch Board',
            'description' => 'Launch project board.',
            'is_folder' => true,
        ]);
        ListItem::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'parent_id' => $project->id,
            'title' => 'Prepare onboarding checklist',
            'description' => 'Prepare checklist.',
            'status' => 'backlog',
            'priority' => 'high',
            'assignee_id' => $agent->id,
        ]);
        ListItem::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'parent_id' => $project->id,
            'title' => 'The : bug on top pages',
            'description' => 'Protects titles that look label-like in Telegram.',
            'status' => 'backlog',
            'priority' => 'medium',
            'assignee_id' => $agent->id,
            'created_at' => now()->addSecond(),
        ]);

        $table = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Customers',
            'description' => 'Customer records.',
            'created_by' => $agent->id,
        ]);
        DataTableColumn::create([
            'table_id' => $table->id,
            'name' => 'Name',
            'type' => 'text',
            'order' => 0,
        ]);
        DataTableRow::create([
            'table_id' => $table->id,
            'data' => ['Name' => 'Acme'],
            'created_by' => $agent->id,
        ]);

        $this->postTelegramCommand('/lists', updateId: 2034, messageId: 134);
        $this->postTelegramCommand('/tables', updateId: 2035, messageId: 135);

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-lists-card:v1',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-tables-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Lists')
            && str_contains($request['text'], 'Prepare onboarding checklist')
            && str_contains($request['text'], 'Launch Board')
            && str_contains($request['text'], 'Iris'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Lists')
            && str_contains($request['text'], 'The : bug on top pages')
            && ! str_contains($request['text'], '<b>The :</b>'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Tables')
            && str_contains($request['text'], 'Customers')
            && str_contains($request['text'], '1 columns')
            && str_contains($request['text'], '1 rows'));
    }

    public function test_telegram_lists_add_uses_confirmation_token_before_creating_item(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 615]])]);

        $this->enableTelegramDirectResourceCommands();
        $this->telegramSetting();
        $human = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $human->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $project = ListItem::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Launch Board',
            'description' => 'Launch project board.',
            'is_folder' => true,
            'status' => 'backlog',
            'priority' => 'medium',
            'creator_id' => $human->id,
        ]);

        $this->postTelegramCommand('/lists add Prepare launch checklist', updateId: 2040, messageId: 140);

        $create = TelegramInteraction::where('interaction_type', 'list_draft')
            ->where('payload->action', 'create')
            ->firstOrFail();

        $this->assertDatabaseMissing('list_items', [
            'title' => 'Prepare launch checklist',
            'is_folder' => false,
        ]);
        $this->assertSame($project->id, $create->payload['draft']['project_id']);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-list-draft-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) use ($create) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $markup = json_decode((string) $request['reply_markup'], true);

            return str_contains($request['text'], 'List item draft')
                && str_contains($request['text'], 'Prepare launch checklist')
                && str_contains($request['text'], 'Launch Board')
                && str_contains(json_encode($markup), $create->token);
        });

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2041,
                'callback_query' => [
                    'id' => 'list-cb-1',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'message' => [
                        'message_id' => 615,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $create->token,
                ],
            ])
            ->assertOk();

        $item = ListItem::where('title', 'Prepare launch checklist')->where('is_folder', false)->firstOrFail();
        $this->assertSame($this->workspace->id, $item->workspace_id);
        $this->assertSame($project->id, $item->parent_id);
        $this->assertSame($human->id, $item->creator_id);
        $this->assertSame('created', $create->fresh()->final_status);
        $this->assertSame(ListItem::class, $create->fresh()->target_type);
        $this->assertSame($item->id, $create->fresh()->target_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
            && str_contains((string) ($request->data()['text'] ?? ''), 'List item created'));
    }

    public function test_telegram_list_draft_callback_requires_linked_actor(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 616]])]);

        $setting = $this->telegramSetting();
        $draft = [
            'title' => 'Unauthorized item',
            'description' => 'Created from Telegram.',
            'status' => 'backlog',
            'priority' => 'medium',
            'project_id' => null,
            'project_title' => 'Telegram Inbox',
        ];
        $interaction = TelegramInteraction::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'list_draft',
            'target_type' => ListItem::class,
            'payload' => [
                'action' => 'create',
                'draft' => $draft,
            ],
            'allowed_actor_rule' => ['type' => 'linked_workspace_member'],
            'expires_at' => now()->addMinutes(30),
            'payload_checksum' => hash('sha256', $this->workspace->id.'|create|'.json_encode($draft)),
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2042,
                'callback_query' => [
                    'id' => 'list-cb-2',
                    'from' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Mallory'],
                    'message' => [
                        'message_id' => 616,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $interaction->token,
                ],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('list_items', [
            'title' => 'Unauthorized item',
        ]);
        $this->assertNull($interaction->fresh()->resolved_at);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
            && str_contains((string) ($request->data()['text'] ?? ''), 'Link your Telegram account'));
    }

    public function test_telegram_tables_add_uses_confirmation_token_before_creating_row(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 617]])]);

        $this->enableTelegramDirectResourceCommands();
        $this->telegramSetting();
        $human = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $human->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $table = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Customers',
            'description' => 'Customer records.',
            'created_by' => $human->id,
        ]);
        DataTableColumn::create([
            'table_id' => $table->id,
            'name' => 'Name',
            'type' => 'text',
            'order' => 0,
        ]);

        $this->postTelegramCommand('/tables add Customers Name=Globex', updateId: 2043, messageId: 143);

        $create = TelegramInteraction::where('interaction_type', 'table_row_draft')
            ->where('payload->action', 'create')
            ->firstOrFail();

        $this->assertDatabaseMissing('data_table_rows', [
            'table_id' => $table->id,
        ]);
        $this->assertSame($table->id, $create->payload['draft']['table_id']);
        $this->assertSame(['Name' => 'Globex'], $create->payload['draft']['data']);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-table-row-draft-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) use ($create) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $markup = json_decode((string) $request['reply_markup'], true);

            return str_contains($request['text'], 'Table row draft')
                && str_contains($request['text'], 'Customers')
                && str_contains($request['text'], 'Globex')
                && str_contains(json_encode($markup), $create->token);
        });

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2044,
                'callback_query' => [
                    'id' => 'table-cb-1',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'message' => [
                        'message_id' => 617,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $create->token,
                ],
            ])
            ->assertOk();

        $row = DataTableRow::where('table_id', $table->id)->firstOrFail();
        $this->assertSame(['Name' => 'Globex'], $row->data);
        $this->assertSame($human->id, $row->created_by);
        $this->assertSame('created', $create->fresh()->final_status);
        $this->assertSame(DataTableRow::class, $create->fresh()->target_type);
        $this->assertSame($row->id, $create->fresh()->target_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
            && str_contains((string) ($request->data()['text'] ?? ''), 'Table row created'));
    }

    public function test_telegram_table_row_draft_callback_requires_linked_actor(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 618]])]);

        $setting = $this->telegramSetting();
        $human = User::factory()->create();
        $table = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Customers',
            'description' => 'Customer records.',
            'created_by' => $human->id,
        ]);
        $draft = [
            'table_id' => $table->id,
            'table_name' => 'Customers',
            'data' => ['Name' => 'MalloryCo'],
        ];
        $interaction = TelegramInteraction::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'table_row_draft',
            'target_type' => DataTable::class,
            'target_id' => $table->id,
            'payload' => [
                'action' => 'create',
                'draft' => $draft,
            ],
            'allowed_actor_rule' => ['type' => 'linked_workspace_member'],
            'expires_at' => now()->addMinutes(30),
            'payload_checksum' => hash('sha256', $this->workspace->id.'|create|'.json_encode($draft)),
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2045,
                'callback_query' => [
                    'id' => 'table-cb-2',
                    'from' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Mallory'],
                    'message' => [
                        'message_id' => 618,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $interaction->token,
                ],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('data_table_rows', [
            'table_id' => $table->id,
        ]);
        $this->assertNull($interaction->fresh()->resolved_at);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
            && str_contains((string) ($request->data()['text'] ?? ''), 'Link your Telegram account'));
    }

    public function test_telegram_tables_add_asks_for_table_when_target_is_ambiguous(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 619]])]);

        $this->enableTelegramDirectResourceCommands();
        $this->telegramSetting();
        $human = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $human->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $customers = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Customers',
            'description' => 'Customer records.',
            'created_by' => $human->id,
        ]);
        DataTableColumn::create([
            'table_id' => $customers->id,
            'name' => 'Name',
            'type' => 'text',
            'order' => 0,
        ]);
        $leads = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Leads',
            'description' => 'Lead records.',
            'created_by' => $human->id,
        ]);
        DataTableColumn::create([
            'table_id' => $leads->id,
            'name' => 'Company',
            'type' => 'text',
            'order' => 0,
        ]);

        $this->postTelegramCommand('/tables add Globex', updateId: 2046, messageId: 146);

        $select = TelegramInteraction::where('interaction_type', 'table_clarify')
            ->where('target_id', $customers->id)
            ->where('payload->action', 'select')
            ->firstOrFail();

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-table-clarify-card:v1',
            'status' => 'sent',
        ]);
        Http::assertSent(function ($request) use ($select) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $markup = json_decode((string) $request['reply_markup'], true);

            return str_contains($request['text'], 'Choose table')
                && str_contains($request['text'], 'Globex')
                && str_contains(json_encode($markup), $select->token);
        });

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2047,
                'callback_query' => [
                    'id' => 'table-clarify-cb-1',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'message' => [
                        'message_id' => 619,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $select->token,
                ],
            ])
            ->assertOk();

        $draft = TelegramInteraction::where('interaction_type', 'table_row_draft')
            ->where('target_id', $customers->id)
            ->where('payload->action', 'create')
            ->firstOrFail();

        $this->assertSame('selected', $select->fresh()->final_status);
        $this->assertSame(['Name' => 'Globex'], $draft->payload['draft']['data']);
        $this->assertDatabaseMissing('data_table_rows', [
            'table_id' => $customers->id,
        ]);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
            && str_contains((string) ($request->data()['text'] ?? ''), 'Table selected'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'Table row draft')
            && str_contains((string) $request['text'], 'Globex'));
    }

    public function test_telegram_table_clarify_callback_requires_linked_actor(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 620]])]);

        $setting = $this->telegramSetting();
        $human = User::factory()->create();
        $table = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Customers',
            'description' => 'Customer records.',
            'created_by' => $human->id,
        ]);
        $payload = [
            'action' => 'select',
            'table_id' => $table->id,
            'table_name' => 'Customers',
            'row_input' => 'MalloryCo',
        ];
        $interaction = TelegramInteraction::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'table_clarify',
            'target_type' => DataTable::class,
            'target_id' => $table->id,
            'payload' => $payload,
            'allowed_actor_rule' => ['type' => 'linked_workspace_member'],
            'expires_at' => now()->addMinutes(15),
            'payload_checksum' => hash('sha256', $this->workspace->id.'|'.json_encode($payload)),
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2048,
                'callback_query' => [
                    'id' => 'table-clarify-cb-2',
                    'from' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Mallory'],
                    'message' => [
                        'message_id' => 620,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $interaction->token,
                ],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('telegram_interactions', [
            'interaction_type' => 'table_row_draft',
            'target_id' => $table->id,
        ]);
        $this->assertNull($interaction->fresh()->resolved_at);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
            && str_contains((string) ($request->data()['text'] ?? ''), 'Link your Telegram account'));
    }

    public function test_telegram_setup_webhook_syncs_profile_commands_and_allowed_updates(): void
    {
        config(['app.url' => 'https://local-ngrok.example']);

        Http::fake([
            'api.telegram.org/*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 999,
                    'username' => 'OpenCompanyBot',
                    'can_read_all_group_messages' => true,
                    'supports_inline_queries' => false,
                ],
            ]),
            'api.telegram.org/*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/setMyCommands' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/setChatMenuButton' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => [
                    'url' => 'https://local-ngrok.example/api/webhooks/chat/telegram',
                    'allowed_updates' => ['message', 'callback_query'],
                    'pending_update_count' => 0,
                ],
            ]),
        ]);

        $admin = User::factory()->create();

        $response = $this->actingAs($admin)
            ->withHeader('X-Workspace-Id', $this->workspace->id)
            ->postJson('/api/integrations/telegram/setup-webhook', [
                'apiKey' => 'telegram-token',
            ])
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertSame('https://local-ngrok.example/api/webhooks/chat/telegram', $response['webhookUrl']);

        $setting = IntegrationSetting::where('integration_id', 'telegram')->firstOrFail();
        $this->assertSame('OpenCompanyBot', $setting->getConfigValue('bot_username'));
        $this->assertContains('inline_query', $setting->getConfigValue('allowed_updates'));
        $this->assertContains('chosen_inline_result', $setting->getConfigValue('allowed_updates'));
        $this->assertContains('message_reaction', $setting->getConfigValue('allowed_updates'));
        $this->assertContains('purchased_paid_media', $setting->getConfigValue('allowed_updates'));
        $this->assertNotContains('guest_message', $setting->getConfigValue('allowed_updates'));

        $profile = TelegramIntegrationProfile::where('integration_setting_id', $setting->id)->firstOrFail();
        $this->assertSame('999', $profile->bot_id);
        $this->assertSame('degraded', $profile->health_status);
        $this->assertSame('synced', $profile->command_sync_status);
        $this->assertSame('synced', $profile->profile_sync_status);
        $this->assertEqualsCanonicalizing(['default', 'private', 'groups', 'chat_admins'], $profile->capabilities['command_scopes']);
        $this->assertSame('missing_allowed_updates', $profile->capabilities['diagnostics'][0]['code']);
        $this->assertStringContainsString('message_reaction', $profile->capabilities['diagnostics'][0]['message']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/setWebhook')
            && $request['url'] === 'https://local-ngrok.example/api/webhooks/chat/telegram'
            && in_array('inline_query', json_decode($request['allowed_updates'], true), true)
            && in_array('chosen_inline_result', json_decode($request['allowed_updates'], true), true)
            && in_array('message_reaction', json_decode($request['allowed_updates'], true), true)
            && in_array('purchased_paid_media', json_decode($request['allowed_updates'], true), true));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/setMyCommands')
            && str_contains($request['commands'], '"approvals"')
            && str_contains($request['commands'], '"agents"')
            && str_contains($request['commands'], '"topic"')
            && ! str_contains($request['commands'], '"digest"')
            && ! str_contains($request['commands'], '"lists"')
            && ! str_contains($request['commands'], '"tables"')
            && ! str_contains($request['commands'], '"files"')
            && ! str_contains($request['commands'], '"docs"'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/setMyCommands')
            && json_decode((string) ($request->data()['scope'] ?? ''), true) === ['type' => 'all_private_chats']
            && str_contains($request['commands'], '"agents"')
            && str_contains($request['commands'], '"topic"')
            && str_contains($request['commands'], '"link"')
            && ! str_contains($request['commands'], '"health"')
            && ! str_contains($request['commands'], '"cancel"')
            && ! str_contains($request['commands'], '"automation"'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/setMyCommands')
            && json_decode((string) ($request->data()['scope'] ?? ''), true) === ['type' => 'all_group_chats']
            && str_contains($request['commands'], '"topic"')
            && ! str_contains($request['commands'], '"tables"'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/setMyCommands')
            && json_decode((string) ($request->data()['scope'] ?? ''), true) === ['type' => 'all_chat_administrators']
            && str_contains($request['commands'], '"health"')
            && str_contains($request['commands'], '"settings"'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/setChatMenuButton')
            && json_decode($request['menu_button'], true) === ['type' => 'commands']);
    }

    public function test_telegram_setup_syncs_bot_profile_and_mini_app_menu_button(): void
    {
        config([
            'app.url' => 'https://local-ngrok.example',
            'telegram.mini_app_enabled' => true,
            'telegram.bot_api_base_url' => 'https://local-bot-api.example',
        ]);

        Http::fake([
            'local-bot-api.example/*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 999,
                    'username' => 'OpenCompanyBot',
                    'can_read_all_group_messages' => true,
                ],
            ]),
            'local-bot-api.example/*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
            'local-bot-api.example/*/setMyCommands' => Http::response(['ok' => true, 'result' => true]),
            'local-bot-api.example/*/setMyName' => Http::response(['ok' => true, 'result' => true]),
            'local-bot-api.example/*/setMyDescription' => Http::response(['ok' => true, 'result' => true]),
            'local-bot-api.example/*/setMyShortDescription' => Http::response(['ok' => true, 'result' => true]),
            'local-bot-api.example/*/setChatMenuButton' => Http::response(['ok' => true, 'result' => true]),
            'local-bot-api.example/*/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => [
                    'url' => 'https://local-ngrok.example/api/webhooks/chat/telegram',
                    'allowed_updates' => app(TelegramSetupService::class)->allowedUpdates(),
                    'pending_update_count' => 0,
                ],
            ]),
        ]);

        $setting = $this->telegramSetting([
            'bot_display_name' => 'OpenCompany',
            'bot_description' => 'Operate OpenCompany from Telegram.',
            'bot_short_description' => 'OpenCompany agent shell',
            'mini_app_url' => 'https://local-ngrok.example/telegram/mini-app',
            'menu_button_text' => 'Open OC',
        ]);

        $result = app(TelegramSetupService::class)->setupWebhook($setting, 'telegram-token');

        $this->assertTrue($result['success']);
        $profile = TelegramIntegrationProfile::where('integration_setting_id', $setting->id)->firstOrFail();
        $this->assertSame('healthy', $profile->health_status);
        $this->assertSame('synced', $profile->profile_sync_status);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://local-bot-api.example/bottelegram-token/'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/setMyName')
            && $request['name'] === 'OpenCompany');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/setMyDescription')
            && $request['description'] === 'Operate OpenCompany from Telegram.');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/setMyShortDescription')
            && $request['short_description'] === 'OpenCompany agent shell');
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/setChatMenuButton')) {
                return false;
            }

            $button = json_decode($request['menu_button'], true);

            return $button['type'] === 'web_app'
                && $button['text'] === 'Open OC'
            && $button['web_app']['url'] === 'https://local-ngrok.example/telegram/mini-app';
        });
    }

    public function test_telegram_admin_setup_actions_refresh_health_and_sync_bot_ux_without_exposing_secrets(): void
    {
        config([
            'app.url' => 'https://local-ngrok.example',
            'telegram.mini_app_enabled' => true,
        ]);

        Http::fake([
            'api.telegram.org/*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 999,
                    'username' => 'OpenCompanyBot',
                    'can_join_groups' => true,
                    'can_read_all_group_messages' => true,
                    'supports_inline_queries' => true,
                ],
            ]),
            'api.telegram.org/*/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => [
                    'url' => 'https://local-ngrok.example/api/webhooks/chat/telegram',
                    'allowed_updates' => app(TelegramSetupService::class)->allowedUpdates(),
                    'pending_update_count' => 0,
                ],
            ]),
            'api.telegram.org/*/setMyCommands' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/setChatMenuButton' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $admin = User::factory()->create();
        $setting = $this->telegramSetting([
            'webhook_url' => 'https://local-ngrok.example/api/webhooks/chat/telegram',
            'allowed_updates' => app(TelegramSetupService::class)->allowedUpdates(),
            'mini_app_url' => 'https://local-ngrok.example/telegram/mini-app',
        ]);

        $health = $this->actingAs($admin)
            ->withHeader('X-Workspace-Id', $this->workspace->id)
            ->postJson('/api/integrations/telegram/health-check')
            ->assertOk()
            ->json();

        $this->assertTrue($health['success']);
        $this->assertSame('healthy', $health['status']);
        $this->assertSame('OpenCompanyBot', $health['profile']['bot_username']);
        $this->assertSame('https://local-ngrok.example/api/webhooks/chat/telegram', $health['profile']['webhook_url']);
        $this->assertStringNotContainsString('telegram-token', json_encode($health, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('telegram-secret', json_encode($health, JSON_THROW_ON_ERROR));

        $sync = $this->actingAs($admin)
            ->withHeader('X-Workspace-Id', $this->workspace->id)
            ->postJson('/api/integrations/telegram/sync-bot-profile')
            ->assertOk()
            ->json();

        $this->assertTrue($sync['success']);
        $this->assertSame('synced', $sync['commands']['status']);
        $this->assertSame('synced', $sync['profile_sync']['status']);
        $this->assertSame('synced', $sync['profile']['command_sync_status']);
        $this->assertSame('synced', $sync['profile']['profile_sync_status']);
        $this->assertStringNotContainsString('telegram-token', json_encode($sync, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('telegram-secret', json_encode($sync, JSON_THROW_ON_ERROR));

        $profile = TelegramIntegrationProfile::where('integration_setting_id', $setting->id)->firstOrFail();
        $this->assertSame('healthy', $profile->health_status);
        $this->assertSame('synced', $profile->command_sync_status);
        $this->assertSame('synced', $profile->profile_sync_status);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/getWebhookInfo'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/setMyCommands')
            && str_contains($request['commands'], '"settings"'));
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/setChatMenuButton')) {
                return false;
            }

            $button = json_decode((string) $request['menu_button'], true);

            return $button['type'] === 'web_app'
            && $button['web_app']['url'] === 'https://local-ngrok.example/telegram/mini-app';
        });
    }

    public function test_telegram_admin_test_send_targets_linked_identity_and_records_delivery(): void
    {
        Http::fake([
            'api.telegram.org/*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 2026],
            ]),
        ]);

        $admin = User::factory()->create(['name' => 'Telegram Admin']);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $admin->id,
            'provider' => 'telegram',
            'external_id' => '12345',
            'display_name' => 'Telegram Admin',
        ]);
        $this->telegramSetting();

        $response = $this->actingAs($admin)
            ->withHeader('X-Workspace-Id', $this->workspace->id)
            ->postJson('/api/integrations/telegram/test-send')
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertSame('Telegram Admin', $response['target']);
        $this->assertSame('sent', $response['delivery']['status']);
        $this->assertSame('2026', $response['delivery']['telegram_message_id']);
        $this->assertStringNotContainsString('telegram-token', json_encode($response, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('telegram-secret', json_encode($response, JSON_THROW_ON_ERROR));

        $this->assertDatabaseHas('telegram_deliveries', [
            'chat_id' => '12345',
            'renderer_version' => 'telegram-admin-test-send:v1',
            'status' => 'sent',
            'telegram_message_id' => '2026',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && $request['chat_id'] === '12345'
            && str_contains($request['text'], 'OpenCompany Telegram test'));
    }

    public function test_telegram_admin_rotate_secret_registers_new_webhook_without_returning_raw_secret(): void
    {
        config(['app.url' => 'https://local-ngrok.example']);

        Http::fake([
            'api.telegram.org/*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 999,
                    'username' => 'OpenCompanyBot',
                    'can_read_all_group_messages' => true,
                ],
            ]),
            'api.telegram.org/*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/setMyCommands' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/setChatMenuButton' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => [
                    'url' => 'https://local-ngrok.example/api/webhooks/chat/telegram',
                    'allowed_updates' => app(TelegramSetupService::class)->allowedUpdates(),
                    'pending_update_count' => 0,
                ],
            ]),
        ]);

        $admin = User::factory()->create();
        $setting = $this->telegramSetting(['webhook_secret' => 'old-secret']);

        $response = $this->actingAs($admin)
            ->withHeader('X-Workspace-Id', $this->workspace->id)
            ->postJson('/api/integrations/telegram/rotate-secret')
            ->assertOk()
            ->json();

        $setting->refresh();
        $newSecret = $setting->getConfigValue('webhook_secret');

        $this->assertTrue($response['success']);
        $this->assertTrue($response['rotated']);
        $this->assertNotSame('old-secret', $newSecret);
        $this->assertSame(hash('sha256', $newSecret), $response['secretFingerprint']);
        $this->assertSame(hash('sha256', $newSecret), $response['profile']['webhook_secret_fingerprint']);
        $this->assertStringNotContainsString($newSecret, json_encode($response, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('telegram-token', json_encode($response, JSON_THROW_ON_ERROR));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/setWebhook')
            && $request['secret_token'] === $newSecret
            && $request['url'] === 'https://local-ngrok.example/api/webhooks/chat/telegram');
    }

    public function test_telegram_sync_console_uses_app_owned_scoped_command_setup(): void
    {
        Http::fake([
            'api.telegram.org/*/setMyCommands' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/setChatMenuButton' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $setting = $this->telegramSetting();

        $this->artisan('telegram:sync')
            ->assertExitCode(0);

        $profile = TelegramIntegrationProfile::where('integration_setting_id', $setting->id)->firstOrFail();
        $this->assertSame('synced', $profile->command_sync_status);
        $this->assertEqualsCanonicalizing(['default', 'private', 'groups', 'chat_admins'], $profile->capabilities['command_scopes']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/setMyCommands')
            && json_decode((string) ($request->data()['scope'] ?? ''), true) === ['type' => 'all_chat_administrators']
            && str_contains($request['commands'], '"health"'));
    }

    public function test_telegram_operations_metrics_and_delivery_retry_use_stored_delivery_payloads(): void
    {
        Http::fake([
            'api.telegram.org/*/sendMessage' => Http::sequence()
                ->push([
                    'ok' => false,
                    'error_code' => 400,
                    'description' => "Bad Request: can't parse entities: malformed HTML",
                ], 400)
                ->push([
                    'ok' => true,
                    'result' => ['message_id' => 777],
                ]),
        ]);

        $admin = User::factory()->create();
        $setting = $this->telegramSetting();
        $receipt = TelegramUpdateReceipt::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'update_id' => 'ops-1',
            'update_type' => 'message',
            'payload_hash' => hash('sha256', '{}'),
            'payload' => ['message' => ['text' => 'secret-ish operator payload']],
            'diagnostics' => [
                'media_capture_failures' => [[
                    'telegram_file_id' => 'bad-file',
                    'telegram_kind' => 'photo',
                    'error_message' => 'download failed',
                ]],
            ],
            'status' => 'failed',
            'error_class' => 'RuntimeException',
            'error_message' => 'Processing failed',
            'retry_count' => 1,
            'received_at' => now(),
        ]);
        TelegramUpdateReceipt::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'update_id' => 'ops-processed',
            'update_type' => 'callback_query',
            'payload_hash' => hash('sha256', '{"callback_query":true}'),
            'payload' => ['callback_query' => true],
            'status' => 'processed',
            'duplicate_count' => 2,
            'last_duplicate_at' => now(),
            'received_at' => now()->subMinutes(5),
            'processed_at' => now()->subMinutes(5)->addSecond(),
        ]);
        TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'approval',
            'target_type' => ApprovalRequest::class,
            'target_id' => Str::uuid()->toString(),
            'payload' => ['action' => 'approve'],
            'allowed_actor_rule' => ['type' => 'linked_workspace_member'],
            'resolved_at' => now(),
            'final_status' => 'tampered',
            'expires_at' => now()->addHour(),
        ]));
        WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'failed-telegram-description.jpg',
            'is_folder' => false,
            'storage_disk' => 'local',
            'storage_path' => 'workspaces/failed-telegram-description.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 10,
            'owner_id' => $admin->id,
            'metadata' => [
                'source' => 'telegram',
                'telegram_enrichment' => [
                    'status' => 'failed',
                    'type' => 'description',
                    'error_message' => 'provider unavailable',
                ],
            ],
        ]);
        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'topic_id' => '55',
            'parse_mode' => 'HTML',
            'renderer_version' => 'telegram-command-card:v1',
            'status' => 'failed',
            'request_payload' => [
                'method' => 'sendMessage',
                'text' => 'Retryable command card',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [['text' => 'Open', 'callback_data' => 'opaque-token']],
                    ],
                ],
            ],
            'response_payload' => [
                'retry_after' => 30,
                'retry_at' => now()->addSeconds(30)->toIso8601String(),
            ],
            'provider_error_code' => TelegramRateLimitException::class,
            'provider_error_message' => 'retry later',
            'attempts' => 1,
        ]);

        $operations = $this->actingAs($admin)
            ->withHeader('X-Workspace-Id', $this->workspace->id)
            ->getJson('/api/integrations/telegram/operations')
            ->assertOk()
            ->json();
        $metrics = $operations['metrics'];

        $this->assertSame(1, $metrics['retryable_deliveries']);
        $this->assertSame(0, $metrics['plain_text_fallback_deliveries_24h']);
        $this->assertSame(1, $metrics['rate_limited_deliveries']);
        $this->assertNotEmpty($metrics['next_rate_limit_retry_at']);
        $this->assertSame(1, $metrics['delivery_failures_by_error'][TelegramRateLimitException::class]);
        $this->assertSame(2, $metrics['duplicate_update_hits']);
        $this->assertSame(2, $metrics['duplicate_update_hits_24h']);
        $this->assertSame(1, $metrics['deduped_receipts_24h']);
        $this->assertSame(1, $metrics['update_type_counts_24h']['message']);
        $this->assertSame(1, $metrics['update_type_counts_24h']['callback_query']);
        $this->assertSame(1000, $metrics['average_processing_delay_ms_24h']);
        $this->assertSame(1000, $metrics['p95_processing_delay_ms_24h']);
        $this->assertSame(1, $metrics['callback_failures_24h']);
        $this->assertSame(1, $metrics['media_capture_failures_24h']);
        $this->assertSame(1, $metrics['media_enrichment_failures_24h']);
        $this->assertSame($receipt->id, $operations['logs']['receipts'][0]['id']);
        $this->assertSame('ops-1', $operations['logs']['receipts'][0]['update_id']);
        $this->assertSame(1, $operations['logs']['receipts'][0]['diagnostic_counts']['media_capture_failures']);
        $this->assertNull($operations['logs']['receipts'][0]['processing_duration_ms']);
        $this->assertArrayNotHasKey('payload', $operations['logs']['receipts'][0]);
        $this->assertSame($delivery->id, $operations['logs']['deliveries'][0]['id']);
        $this->assertSame('sendMessage', $operations['logs']['deliveries'][0]['method']);
        $this->assertSame('HTML', $operations['logs']['deliveries'][0]['parse_mode']);
        $this->assertArrayNotHasKey('request_payload', $operations['logs']['deliveries'][0]);

        $response = $this->actingAs($admin)
            ->withHeader('X-Workspace-Id', $this->workspace->id)
            ->postJson("/api/integrations/telegram/deliveries/{$delivery->id}/retry")
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertSame(1, $response['metrics']['plain_text_fallback_deliveries_24h']);
        $this->assertSame('plain_text_fallback', $response['logs']['deliveries'][0]['parse_mode']);
        $this->assertDatabaseHas('telegram_deliveries', [
            'id' => $delivery->id,
            'status' => 'sent',
            'telegram_message_id' => '777',
            'parse_mode' => 'plain_text_fallback',
            'attempts' => 2,
        ]);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            $replyMarkup = json_decode((string) $request['reply_markup'], true);

            return str_contains($request->url(), '/sendMessage')
                && $request['chat_id'] === '12345'
                && $request['message_thread_id'] === 55
                && $request['text'] === 'Retryable command card'
                && $replyMarkup['inline_keyboard'][0][0]['callback_data'] === 'opaque-token';
        });
    }

    public function test_telegram_operations_retry_preserves_rate_limit_metadata_on_failure(): void
    {
        Http::fake([
            'api.telegram.org/*/sendMessage' => Http::response([
                'ok' => false,
                'error_code' => 429,
                'description' => 'Too Many Requests: retry after 21',
                'parameters' => ['retry_after' => 21],
            ], 429),
        ]);

        $admin = User::factory()->create();
        $setting = $this->telegramSetting();
        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'parse_mode' => 'HTML',
            'renderer_version' => 'telegram-command-card:v1',
            'status' => 'failed',
            'request_payload' => [
                'method' => 'sendMessage',
                'text' => 'Retry hits Telegram flood control',
            ],
            'provider_error_code' => 'PreviousError',
            'provider_error_message' => 'previous failure',
            'attempts' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('X-Workspace-Id', $this->workspace->id)
            ->postJson("/api/integrations/telegram/deliveries/{$delivery->id}/retry")
            ->assertUnprocessable()
            ->json();

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('rate limit', $response['error']);

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(TelegramRateLimitException::class, $delivery->provider_error_code);
        $this->assertSame(21, $delivery->response_payload['retry_after']);
        $this->assertNotEmpty($delivery->response_payload['retry_at']);
        $this->assertSame(2, $delivery->attempts);
    }

    public function test_telegram_operations_repair_expires_tokens_and_repairs_conversation_mappings(): void
    {
        $admin = User::factory()->create();
        $setting = $this->telegramSetting();

        $expired = TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'identity_link',
            'payload' => ['telegram_user_id' => '111'],
            'expires_at' => now()->subMinute(),
        ]));
        $orphaned = TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'chat_type' => 'supergroup',
            'title' => 'Product',
            'topic_id' => '88',
        ]);
        $driftedChannel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Wrong external channel',
            'type' => 'external',
            'external_provider' => 'slack',
            'external_id' => 'T123:C456',
        ]);
        $drifted = TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '555',
            'chat_type' => 'private',
            'title' => 'Rutger',
            'channel_id' => $driftedChannel->id,
        ]);

        $operations = $this->actingAs($admin)
            ->withHeader('X-Workspace-Id', $this->workspace->id)
            ->getJson('/api/integrations/telegram/operations')
            ->assertOk()
            ->json();

        $this->assertSame(1, $operations['metrics']['expired_open_interactions']);
        $this->assertSame(1, $operations['metrics']['orphaned_conversations']);
        $this->assertSame(1, $operations['metrics']['broken_conversation_mappings']);
        $this->assertSame($expired->id, $operations['repairCandidates']['expired_open_interactions'][0]['id']);
        $this->assertSame($orphaned->id, $operations['repairCandidates']['orphaned_conversations'][0]['id']);
        $this->assertSame($drifted->id, $operations['repairCandidates']['broken_conversation_mappings'][0]['id']);

        $repair = $this->actingAs($admin)
            ->withHeader('X-Workspace-Id', $this->workspace->id)
            ->postJson('/api/integrations/telegram/operations/repair', [
                'actions' => ['expire_interactions', 'repair_conversations'],
            ])
            ->assertOk()
            ->json();

        $this->assertSame(1, $repair['repair']['expired_interactions']);
        $this->assertSame(1, $repair['repair']['created_channels']);
        $this->assertSame(1, $repair['repair']['repaired_channel_mappings']);
        $this->assertSame(0, $repair['metrics']['expired_open_interactions']);
        $this->assertSame(0, $repair['metrics']['orphaned_conversations']);
        $this->assertSame(0, $repair['metrics']['broken_conversation_mappings']);

        $expired->refresh();
        $this->assertSame('expired', $expired->final_status);
        $this->assertNotNull($expired->resolved_at);

        $orphaned->refresh();
        $this->assertNotNull($orphaned->channel_id);
        $this->assertDatabaseHas('channels', [
            'id' => $orphaned->channel_id,
            'workspace_id' => $this->workspace->id,
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345:88',
        ]);

        $driftedChannel->refresh();
        $this->assertSame('Telegram: Rutger', $driftedChannel->name);
        $this->assertSame('telegram', $driftedChannel->external_provider);
        $this->assertSame('555', $driftedChannel->external_id);
    }

    public function test_telegram_operations_repair_webhook_resyncs_stale_profile_state(): void
    {
        config(['app.url' => 'https://local-ngrok.example']);

        Http::fake([
            'api.telegram.org/*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 999,
                    'username' => 'OpenCompanyBot',
                    'can_join_groups' => true,
                    'supports_inline_queries' => true,
                ],
            ]),
            'api.telegram.org/*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/setMyCommands' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/setChatMenuButton' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => [
                    'url' => 'https://local-ngrok.example/api/webhooks/chat/telegram',
                    'allowed_updates' => app(TelegramSetupService::class)->allowedUpdates(),
                    'pending_update_count' => 0,
                ],
            ]),
        ]);

        $admin = User::factory()->create();
        $setting = $this->telegramSetting([
            'webhook_url' => 'https://old.example/api/webhooks/telegram',
            'allowed_updates' => ['message'],
        ]);

        TelegramIntegrationProfile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'bot_id' => '999',
            'bot_username' => 'OldBot',
            'webhook_url' => 'https://old.example/api/webhooks/telegram',
            'allowed_updates' => ['message'],
            'command_sync_status' => 'unknown',
            'profile_sync_status' => 'unknown',
            'health_status' => 'webhook_mismatch',
            'last_health_checked_at' => now()->subHour(),
        ]);

        $operations = $this->actingAs($admin)
            ->withHeader('X-Workspace-Id', $this->workspace->id)
            ->getJson('/api/integrations/telegram/operations')
            ->assertOk()
            ->json();

        $this->assertTrue($operations['metrics']['stale_health_profile']);
        $this->assertSame(
            'https://local-ngrok.example/api/webhooks/chat/telegram',
            $operations['repairCandidates']['stale_health_profile']['expected_webhook_url'],
        );

        $repair = $this->actingAs($admin)
            ->withHeader('X-Workspace-Id', $this->workspace->id)
            ->postJson('/api/integrations/telegram/operations/repair', [
                'actions' => ['repair_webhook'],
            ])
            ->assertOk()
            ->json();

        $this->assertTrue($repair['repair']['webhook_repair']['attempted']);
        $this->assertTrue($repair['repair']['webhook_repair']['success']);
        $this->assertSame('healthy', $repair['repair']['webhook_repair']['status']);
        $this->assertSame('https://local-ngrok.example/api/webhooks/chat/telegram', $repair['repair']['webhook_repair']['webhook_url']);
        $this->assertFalse($repair['metrics']['stale_health_profile']);
        $this->assertNull($repair['repairCandidates']['stale_health_profile']);

        $profile = TelegramIntegrationProfile::where('integration_setting_id', $setting->id)->firstOrFail();
        $this->assertSame('OpenCompanyBot', $profile->bot_username);
        $this->assertSame('https://local-ngrok.example/api/webhooks/chat/telegram', $profile->webhook_url);
        $this->assertSame('synced', $profile->command_sync_status);
        $this->assertSame('healthy', $profile->health_status);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/setWebhook')
            && $request['url'] === 'https://local-ngrok.example/api/webhooks/chat/telegram'
            && in_array('inline_query', json_decode((string) $request['allowed_updates'], true), true));
    }

    public function test_telegram_operations_replay_failed_receipt_from_stored_payload(): void
    {
        Queue::fake();
        Http::fake([
            'api.telegram.org/*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 778],
            ]),
        ]);

        $admin = User::factory()->create();
        $setting = $this->telegramSetting();
        $payload = [
            'update_id' => 2501,
            'message' => [
                'message_id' => 501,
                'date' => now()->timestamp,
                'chat' => ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
                'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                'text' => '/status',
                'entities' => [
                    ['offset' => 0, 'length' => 7, 'type' => 'bot_command'],
                ],
            ],
        ];
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $receipt = TelegramUpdateReceipt::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'update_id' => '2501',
            'update_type' => 'message',
            'payload_hash' => hash('sha256', $encoded ?: ''),
            'payload' => $payload,
            'status' => 'failed',
            'error_class' => 'RuntimeException',
            'error_message' => 'transient failure',
            'retry_count' => 0,
            'received_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('X-Workspace-Id', $this->workspace->id)
            ->postJson("/api/integrations/telegram/receipts/{$receipt->id}/replay")
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertDatabaseHas('telegram_update_receipts', [
            'id' => $receipt->id,
            'status' => 'processed',
            'retry_count' => 1,
            'error_class' => null,
            'error_message' => null,
        ]);
        $this->assertDatabaseHas('telegram_deliveries', [
            'chat_id' => '12345',
            'telegram_message_id' => '778',
            'renderer_version' => 'telegram-status-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Status')
            && str_contains($request['text'], 'is active')
            && str_contains($request['text'], 'Now'));
    }

    public function test_telegram_start_renders_agent_home_buttons_with_opaque_command_tokens(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 607]])]);

        $this->telegramSetting([
            'mini_app_url' => 'https://local-ngrok.example/telegram/mini-app',
        ]);

        $this->postTelegramCommand('/start', updateId: 2023, messageId: 123);

        $agentsButton = TelegramInteraction::where('interaction_type', 'command')
            ->get()
            ->first(fn (TelegramInteraction $interaction) => ($interaction->payload['command'] ?? null) === '/agents');

        $this->assertNotNull($agentsButton);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-command-center-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) use ($agentsButton) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $markup = json_decode((string) $request['reply_markup'], true);
            $markupJson = json_encode($markup);
            $hasMiniAppButton = collect($markup['inline_keyboard'])
                ->flatten(1)
                ->contains(fn (array $button) => ($button['web_app']['url'] ?? null) === 'https://local-ngrok.example/telegram/mini-app');

            return str_contains($request['text'], 'OpenCompany')
                && str_contains((string) $request['text'], 'is active')
                && str_contains((string) $request['text'], 'Lane:')
                && ! str_contains((string) $request['text'], 'Mode:')
                && ! str_contains((string) $request['text'], 'Topic:')
                && str_contains((string) $markupJson, $agentsButton->token)
                && $hasMiniAppButton
                && str_contains((string) $markupJson, 'Switch agent')
                && str_contains((string) $markupJson, 'Lane')
                && ! str_contains((string) $markupJson, 'Settings')
                && ! str_contains((string) $markupJson, '/tasks')
                && ! str_contains((string) $markupJson, '/files')
                && ! str_contains((string) $markupJson, '/docs')
                && ! str_contains((string) $markupJson, '/automation');
        });

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2024,
                'callback_query' => [
                    'id' => 'command-cb-1',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'message' => [
                        'message_id' => 607,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $agentsButton->token,
                ],
            ])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Agents'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-agents-card:v1',
            'status' => 'sent',
        ]);
    }

    public function test_telegram_resource_commands_default_to_removed_shortcut_notice(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 613]])]);

        config(['telegram.direct_resource_commands_enabled' => false]);
        $agent = User::factory()->agent()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Iris',
        ]);
        $this->telegramSetting(['default_agent_id' => $agent->id]);

        $this->postTelegramCommand('/files', updateId: 2026, messageId: 126);

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-notice-card:v1',
            'status' => 'sent',
        ]);
        $this->assertDatabaseMissing('telegram_deliveries', [
            'renderer_version' => 'telegram-files-card:v1',
        ]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            return ! isset($request->data()['reply_markup'])
                && str_contains($request['text'], 'Command removed')
                && str_contains($request['text'], '/files')
                && str_contains($request['text'], 'Send a normal message to Iris instead');
        });
    }

    public function test_telegram_legacy_resource_callbacks_default_to_removed_shortcut_notice(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 614]])]);

        config(['telegram.direct_resource_commands_enabled' => false]);
        $setting = $this->telegramSetting();
        $interaction = TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'command',
            'payload' => [
                'command' => '/docs',
            ],
            'allowed_actor_rule' => [
                'type' => 'telegram_lane_command',
            ],
            'expires_at' => now()->addDay(),
        ]));

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2027,
                'callback_query' => [
                    'id' => 'legacy-resource-cb-1',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'message' => [
                        'message_id' => 614,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $interaction->token,
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-notice-card:v1',
            'status' => 'sent',
        ]);
        $this->assertDatabaseMissing('telegram_deliveries', [
            'renderer_version' => 'telegram-docs-card:v1',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Command removed')
            && str_contains($request['text'], '/docs'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
            && str_contains((string) ($request->data()['text'] ?? ''), 'shortcut was removed'));
    }

    public function test_telegram_agents_card_sets_lane_default_agent_with_opaque_token(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 608]])]);

        $this->telegramSetting();
        $agent = User::factory()->agent()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Iris & Atlas',
            'status' => 'available',
        ]);
        $human = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $human->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $this->postTelegramCommand('/agents', updateId: 2025, messageId: 125);

        $agentButton = TelegramInteraction::where('interaction_type', 'agent')
            ->get()
            ->first(fn (TelegramInteraction $interaction) => ($interaction->payload['agent_id'] ?? null) === $agent->id);

        $this->assertNotNull($agentButton);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-agents-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) use ($agent, $agentButton) {
            if (! str_contains($request->url(), '/sendMessage') || ! str_contains($request['text'], 'Choose agent')) {
                return false;
            }

            $markup = json_decode((string) $request['reply_markup'], true);
            $encodedMarkup = json_encode($markup);

            return str_contains($request['text'], 'Iris')
                && str_contains($encodedMarkup, $agentButton->token)
                && ! str_contains($encodedMarkup, $agent->id)
                && ! str_contains($encodedMarkup, 'Talk to Iris');
        });

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2026,
                'callback_query' => [
                    'id' => 'agent-cb-1',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'message' => [
                        'message_id' => 608,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $agentButton->token,
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('telegram_conversations', [
            'chat_id' => '12345',
            'default_agent_id' => $agent->id,
        ]);

        $agentButton->refresh();
        $this->assertSame('selected', $agentButton->final_status);
        $this->assertSame($human->id, $agentButton->resolved_by_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
            && str_contains((string) ($request->data()['text'] ?? ''), 'Selected Iris'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Iris &amp; Atlas is now active in this lane'));
    }

    public function test_telegram_command_cards_fallback_to_plain_text_without_losing_buttons_on_parse_errors(): void
    {
        Queue::fake();
        Http::fake([
            'api.telegram.org/*/sendMessage' => Http::sequence()
                ->push([
                    'ok' => false,
                    'error_code' => 400,
                    'description' => "Bad Request: can't parse entities: malformed HTML",
                ], 400)
                ->push([
                    'ok' => true,
                    'result' => ['message_id' => 610],
                ]),
        ]);

        $this->telegramSetting();
        $agent = User::factory()->agent()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Iris',
            'status' => 'available',
        ]);

        $this->postTelegramCommand('/agents', updateId: 2028, messageId: 128);

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-agents-card:v1',
            'telegram_message_id' => '610',
            'parse_mode' => 'plain_text_fallback',
            'status' => 'sent',
        ]);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), '/sendMessage')
                && ($data['parse_mode'] ?? null) === 'HTML'
                && str_contains($request['text'], '<b>Choose agent</b>')
                && str_contains((string) $request['reply_markup'], 'inline_keyboard');
        });
        Http::assertSent(function ($request) use ($agent) {
            $data = $request->data();

            return str_contains($request->url(), '/sendMessage')
                && ! isset($data['parse_mode'])
                && str_contains($request['text'], 'Choose agent')
                && str_contains($request['text'], 'Iris')
                && ! str_contains($request['text'], '<b>')
                && str_contains((string) $request['reply_markup'], 'inline_keyboard')
                && ! str_contains((string) $request['reply_markup'], $agent->id);
        });
    }

    public function test_telegram_agents_card_handles_legacy_non_uuid_agent_ids(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 609]])]);

        $this->telegramSetting();
        User::factory()->agent()->create([
            'id' => 'a1',
            'workspace_id' => $this->workspace->id,
            'name' => 'Atlas',
            'status' => 'idle',
        ]);

        $this->postTelegramCommand('/agents', updateId: 2027, messageId: 127);

        $agentButton = TelegramInteraction::where('interaction_type', 'agent')
            ->get()
            ->first(fn (TelegramInteraction $interaction) => ($interaction->payload['agent_id'] ?? null) === 'a1');

        $this->assertNotNull($agentButton);
        $this->assertNull($agentButton->target_id);
        $this->assertDatabaseHas('telegram_update_receipts', [
            'update_id' => '2027',
            'status' => 'processed',
            'error_message' => null,
        ]);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-agents-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) use ($agentButton) {
            if (! str_contains($request->url(), '/sendMessage') || ! str_contains($request['text'], 'Atlas')) {
                return false;
            }

            $markup = json_decode((string) $request['reply_markup'], true);

            return str_contains(json_encode($markup), $agentButton->token)
                && ! str_contains(json_encode($markup), 'a1');
        });
    }

    public function test_telegram_command_center_shows_tasks_and_approvals(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 600]])]);

        $this->enableTelegramDirectResourceCommands();
        $setting = $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);

        Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Vendor renewal analysis',
            'description' => 'Analyze the vendor renewal.',
            'status' => Task::STATUS_ACTIVE,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
        ]);

        ApprovalRequest::factory()->pending()->create([
            'requester_id' => $agent->id,
            'title' => 'Send renewal email',
        ]);

        $this->postTelegramCommand('/tasks', updateId: 2001, messageId: 101);
        $this->postTelegramCommand('/approvals', updateId: 2002, messageId: 102);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Vendor renewal analysis'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-tasks-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Send renewal email'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-approvals-card:v1',
            'status' => 'sent',
        ]);

        $this->assertSame($setting->id, IntegrationSetting::where('integration_id', 'telegram')->first()->id);
    }

    public function test_telegram_files_and_docs_cards_use_stable_renderer_versions(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 603]])]);

        $this->enableTelegramDirectResourceCommands();
        $this->telegramSetting();
        $human = User::factory()->create(['name' => 'Rutger']);

        WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'telegram-plan.pdf',
            'description' => 'Telegram plan export.',
            'is_folder' => false,
            'storage_disk' => 'local',
            'storage_path' => 'workspaces/telegram-plan.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1200,
            'owner_id' => $human->id,
        ]);

        Document::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Telegram implementation notes',
            'content' => 'Renderer notes.',
            'content_format' => 'markdown',
            'author_id' => $human->id,
            'is_folder' => false,
        ]);

        $this->postTelegramCommand('/files', updateId: 2003, messageId: 103);
        $this->postTelegramCommand('/docs', updateId: 2004, messageId: 104);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Files')
            && str_contains($request['text'], 'telegram-plan.pdf'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-files-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Docs')
            && str_contains($request['text'], 'Telegram implementation notes'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-docs-card:v1',
            'status' => 'sent',
        ]);
    }

    public function test_telegram_operational_cards_trim_runtime_noise_for_chat_readability(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 612]])]);

        $this->enableTelegramDirectResourceCommands();
        $this->telegramSetting();
        $human = User::factory()->create(['name' => 'Rutger']);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Mini App Browser Automation Smoke 235325',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345',
        ]);

        Message::create([
            'id' => Str::uuid()->toString(),
            'content' => "👋 **Hello from the Telegram Mini App Smoke Test!**\n\n✅ **Everything is ready for *Mini App Browser Automation*.**",
            'channel_id' => $channel->id,
            'author_id' => $human->id,
            'timestamp' => now(),
            'source' => 'telegram',
        ]);

        WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => '2f0f3b24-fa37-4eb3-a61c-d12496ab9808.png',
            'description' => 'Telegram screenshot.',
            'is_folder' => false,
            'storage_disk' => 'local',
            'storage_path' => 'workspaces/2f0f3b24-fa37-4eb3-a61c-d12496ab9808.png',
            'mime_type' => 'image/png',
            'size' => 1200,
            'owner_id' => $human->id,
        ]);

        $this->postTelegramCommand('/activity', updateId: 2036, messageId: 136);
        $this->postTelegramCommand('/files', updateId: 2037, messageId: 137);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Activity')
            && str_contains($request['text'], 'Hello from the Telegram Mini App')
            && str_contains($request['text'], 'Rutger / Mini App')
            && ! str_contains($request['text'], '**')
            && ! str_contains($request['text'], '*Mini App Browser Automation*')
            && ! str_contains($request['text'], 'Automation Smoke 235325')
            && ! str_contains($request['text'], "\n\n✅"));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Files')
            && str_contains($request['text'], 'PNG file')
            && str_contains($request['text'], '2f0f3b24')
            && ! str_contains($request['text'], '2f0f3b24-fa37-4eb3-a61c-d12496ab9808.png'));
    }

    public function test_telegram_workload_dashboard_and_automation_controls(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 604]])]);

        $this->enableTelegramDirectResourceCommands();
        $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris', 'status' => 'working']);
        $human = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $human->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Blocked revenue analysis',
            'description' => 'Analyze the revenue bottleneck.',
            'status' => Task::STATUS_ACTIVE,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
        ]);

        $automation = Automation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Morning Digest',
            'trigger_type' => 'schedule',
            'execution_type' => 'prompt',
            'description' => 'Summarize workspace state.',
            'agent_id' => $agent->id,
            'prompt' => 'Summarize workspace state.',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'is_active' => true,
            'run_count' => 3,
            'consecutive_failures' => 2,
            'created_by_id' => $human->id,
            'keep_history' => true,
        ]);

        $this->postTelegramCommand('/workload', updateId: 2008, messageId: 108);
        $this->postTelegramCommand('/dashboard', updateId: 2009, messageId: 109);
        $this->postTelegramCommand('/automation status Morning', updateId: 2010, messageId: 110);
        $this->postTelegramCommand('/automation status '.Str::limit($automation->id, 8, ''), updateId: 2015, messageId: 115);
        $this->postTelegramCommand('/automation failures', updateId: 2011, messageId: 111);
        $this->postTelegramCommand('/automation run Morning', updateId: 2012, messageId: 112);
        $this->postTelegramCommand('/automation pause Morning', updateId: 2013, messageId: 113);
        $this->postTelegramCommand('/automation resume Morning', updateId: 2014, messageId: 114);

        Queue::assertPushed(RunAutomationJob::class);

        $automation->refresh();
        $this->assertTrue($automation->is_active);
        $this->assertSame(0, $automation->consecutive_failures);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Workload')
            && str_contains($request['text'], 'Iris'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-workload-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Automation')
            && str_contains($request['text'], 'Morning Digest')
            && str_contains($request['text'], 'Iris')
            && str_contains($request['text'], 'Summarize workspace state.')
            && str_contains($request['text'], "/w/{$this->workspace->slug}/automation/{$automation->id}/edit"));

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-automation-status-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Automation run queued: Morning Digest'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-automation-action-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Automation failures')
            && str_contains($request['text'], 'Morning Digest'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-automation-failures-card:v1',
            'status' => 'sent',
        ]);
    }

    public function test_telegram_task_card_actions_use_persisted_interaction_tokens(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 605]])]);

        $this->enableTelegramDirectResourceCommands();
        $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $human->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $task = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Investigate churn',
            'description' => 'Investigate churn.',
            'status' => Task::STATUS_ACTIVE,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
        ]);

        $this->postTelegramCommand('/task '.$task->id, updateId: 2015, messageId: 115);

        $pause = TelegramInteraction::where('interaction_type', 'task')
            ->where('target_id', $task->id)
            ->where('payload->action', 'pause')
            ->firstOrFail();

        Http::assertSent(function ($request) use ($pause) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $markup = json_decode((string) $request['reply_markup'], true);

            return str_contains($request['text'], 'Investigate churn')
                && str_contains(json_encode($markup), $pause->token)
                && ! str_contains(json_encode($markup), 'pause:');
        });

        $originalDelivery = TelegramDelivery::where('renderer_version', TelegramCardRenderer::TASK_CARD_VERSION)
            ->where('source_id', $task->id)
            ->firstOrFail();
        $this->assertSame(['pause', 'cancel', 'open'], $originalDelivery->request_payload['buttons']);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2016,
                'callback_query' => [
                    'id' => 'task-cb-1',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'message' => [
                        'message_id' => 605,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $pause->token,
                ],
            ])
            ->assertOk();

        $task->refresh();
        $pause->refresh();

        $this->assertSame(Task::STATUS_PAUSED, $task->status);
        $this->assertSame('pause', $pause->final_status);
        $this->assertSame($human->id, $pause->resolved_by_id);

        $updatedDelivery = TelegramDelivery::where('renderer_version', TelegramCardRenderer::TASK_CARD_UPDATED_VERSION)
            ->where('source_id', $task->id)
            ->firstOrFail();
        $resume = TelegramInteraction::where('interaction_type', 'task')
            ->where('target_id', $task->id)
            ->where('payload->action', 'resume')
            ->firstOrFail();

        $this->assertSame($originalDelivery->id, $updatedDelivery->previous_delivery_id);
        $this->assertSame('sent', $updatedDelivery->status);
        $this->assertSame('editMessageText', $updatedDelivery->request_payload['method']);
        $this->assertSame(['resume', 'cancel', 'open'], $updatedDelivery->request_payload['buttons']);

        Http::assertSent(function ($request) use ($resume) {
            if (! str_contains($request->url(), '/editMessageText')) {
                return false;
            }

            $markup = json_decode((string) $request['reply_markup'], true);

            return str_contains($request['text'], 'paused')
                && str_contains(json_encode($markup), $resume->token)
                && ! str_contains(json_encode($markup), 'pause:');
        });
    }

    public function test_telegram_task_card_includes_runtime_steps_blockers_and_web_link(): void
    {
        config(['app.url' => 'https://local-ngrok.example']);

        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 606]])]);

        $this->enableTelegramDirectResourceCommands();
        $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $human->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $task = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Investigate rich run card',
            'description' => 'Investigate rich run card.',
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_HIGH,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'context' => ['blocked_reason' => 'Waiting for production credentials'],
            'started_at' => now()->subMinutes(12),
        ]);
        TaskStep::create([
            'id' => Str::uuid()->toString(),
            'task_id' => $task->id,
            'description' => 'Read the incident brief',
            'status' => TaskStep::STATUS_COMPLETED,
            'step_type' => TaskStep::TYPE_ACTION,
            'completed_at' => now()->subMinutes(8),
        ]);
        TaskStep::create([
            'id' => Str::uuid()->toString(),
            'task_id' => $task->id,
            'description' => 'Check Telegram delivery telemetry',
            'status' => TaskStep::STATUS_IN_PROGRESS,
            'step_type' => TaskStep::TYPE_ACTION,
            'started_at' => now()->subMinutes(5),
        ]);

        $this->postTelegramCommand('/task '.Str::limit($task->id, 8, ''), updateId: 2031, messageId: 131);

        Http::assertSent(function ($request) use ($task) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $markup = json_decode((string) $request['reply_markup'], true);
            $markupJson = json_encode($markup, JSON_UNESCAPED_SLASHES);

            return str_contains($request['text'], 'Investigate rich run card')
                && ! str_contains($request['text'], 'Priority')
                && ! str_contains($request['text'], 'ID:')
                && str_contains($request['text'], 'Working for')
                && str_contains($request['text'], 'Progress:')
                && str_contains($request['text'], '1/2 steps')
                && str_contains($request['text'], 'Current step:')
                && str_contains($request['text'], 'Check Telegram delivery telemetry')
                && str_contains($request['text'], 'Needs attention:')
                && str_contains($request['text'], 'Waiting for production credentials')
                && str_contains((string) $markupJson, "https://local-ngrok.example/w/{$this->workspace->slug}/tasks/{$task->id}");
        });
    }

    public function test_telegram_task_lifecycle_updates_refresh_existing_run_card(): void
    {
        config(['app.url' => 'https://local-ngrok.example']);
        Queue::fake();
        Http::fake(['api.telegram.org/*/editMessageText' => Http::response(['ok' => true, 'result' => ['message_id' => 707]])]);

        $setting = $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        $task = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Refresh Telegram lifecycle card',
            'description' => 'Refresh Telegram lifecycle card.',
            'status' => Task::STATUS_COMPLETED,
            'priority' => Task::PRIORITY_HIGH,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'started_at' => now()->subMinutes(8),
            'completed_at' => now(),
            'context' => ['blocked_reason' => 'A stale blocked reason should not survive completion.'],
        ]);
        $previousDelivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'source_type' => Task::class,
            'source_id' => $task->id,
            'chat_id' => '12345',
            'telegram_message_id' => '707',
            'renderer_version' => TelegramCardRenderer::RUN_CARD_VERSION,
            'parse_mode' => 'HTML',
            'status' => 'sent',
            'request_payload' => ['method' => 'sendMessage'],
            'sent_at' => now()->subMinute(),
        ]);

        event(new TaskUpdated($task, 'completed'));

        $updatedDelivery = TelegramDelivery::where('renderer_version', TelegramCardRenderer::TASK_CARD_UPDATED_VERSION)
            ->where('source_id', $task->id)
            ->firstOrFail();

        $this->assertSame($previousDelivery->id, $updatedDelivery->previous_delivery_id);
        $this->assertSame('sent', $updatedDelivery->status);
        $this->assertSame(['open'], $updatedDelivery->request_payload['buttons']);
        $this->assertSame('completed', $updatedDelivery->request_payload['lifecycle_action']);

        Http::assertSent(function ($request) use ($task) {
            $markup = json_decode((string) $request['reply_markup'], true);
            $markupJson = json_encode($markup, JSON_UNESCAPED_SLASHES);

            return str_contains($request->url(), '/editMessageText')
                && (string) $request['chat_id'] === '12345'
                && (int) $request['message_id'] === 707
                && str_contains($request['text'], 'Refresh Telegram lifecycle card')
                && str_contains($request['text'], 'Iris finished')
                && ! str_contains($request['text'], 'Blocked:')
                && ! str_contains($request['text'], 'ID:')
                && ! str_contains((string) $markupJson, 'callback_data')
                && str_contains((string) $markupJson, "https://local-ngrok.example/w/{$this->workspace->slug}/tasks/{$task->id}");
        });
    }

    public function test_telegram_task_progress_updates_refresh_existing_run_card(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/editMessageText' => Http::response(['ok' => true, 'result' => ['message_id' => 708]])]);

        $setting = $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        $task = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Refresh Telegram progress card',
            'description' => 'Refresh Telegram progress card.',
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'started_at' => now()->subMinute(),
        ]);
        TaskStep::create([
            'id' => Str::uuid()->toString(),
            'task_id' => $task->id,
            'description' => 'Calling workspace tool',
            'status' => TaskStep::STATUS_IN_PROGRESS,
            'step_type' => TaskStep::TYPE_ACTION,
            'started_at' => now(),
        ]);
        TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'source_type' => Task::class,
            'source_id' => $task->id,
            'chat_id' => '12345',
            'telegram_message_id' => '708',
            'renderer_version' => TelegramCardRenderer::RUN_CARD_VERSION,
            'parse_mode' => 'HTML',
            'status' => 'sent',
            'request_payload' => ['method' => 'sendMessage'],
            'sent_at' => now()->subMinute(),
        ]);

        event(new TaskUpdated($task->fresh(['steps']) ?? $task, 'progress'));

        $updatedDelivery = TelegramDelivery::where('renderer_version', TelegramCardRenderer::TASK_CARD_UPDATED_VERSION)
            ->where('source_id', $task->id)
            ->firstOrFail();

        $this->assertSame('progress', $updatedDelivery->request_payload['lifecycle_action']);
        $this->assertSame(['pause', 'cancel', 'open'], $updatedDelivery->request_payload['buttons']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/editMessageText')
            && str_contains($request['text'], 'Refresh Telegram progress card')
            && str_contains($request['text'], 'Current')
            && str_contains($request['text'], 'Calling workspace tool'));

        $pauseToken = TelegramInteraction::where('interaction_type', 'task')
            ->where('target_id', $task->id)
            ->where('payload->action', 'pause')
            ->firstOrFail()
            ->token;
        $cancelToken = TelegramInteraction::where('interaction_type', 'task')
            ->where('target_id', $task->id)
            ->where('payload->action', 'cancel')
            ->firstOrFail()
            ->token;

        event(new TaskUpdated($task->fresh(['steps']) ?? $task, 'progress'));

        $this->assertSame(1, TelegramInteraction::where('interaction_type', 'task')->where('target_id', $task->id)->where('payload->action', 'pause')->count());
        $this->assertSame(1, TelegramInteraction::where('interaction_type', 'task')->where('target_id', $task->id)->where('payload->action', 'cancel')->count());
        $this->assertSame($pauseToken, TelegramInteraction::where('interaction_type', 'task')->where('target_id', $task->id)->where('payload->action', 'pause')->value('token'));
        $this->assertSame($cancelToken, TelegramInteraction::where('interaction_type', 'task')->where('target_id', $task->id)->where('payload->action', 'cancel')->value('token'));
    }

    public function test_telegram_digest_and_notify_commands_persist_subscriptions(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 601]])]);

        $this->enableTelegramDirectResourceCommands();
        $this->telegramSetting();

        $this->postTelegramCommand('/digest weekly', updateId: 2003, messageId: 103);
        $this->postTelegramCommand('/digest time 09:00', updateId: 2020, messageId: 120);
        $this->postTelegramCommand('/notify task_failed high silent', updateId: 2004, messageId: 104);
        $this->postTelegramCommand('/settings', updateId: 2028, messageId: 128);
        $this->postTelegramCommand('/help', updateId: 2029, messageId: 129);

        $this->assertDatabaseHas('telegram_subscriptions', [
            'event_type' => 'workspace_digest',
            'schedule' => 'weekly',
            'enabled' => true,
        ]);

        $this->assertDatabaseHas('telegram_subscriptions', [
            'event_type' => 'task_failed',
            'severity' => 'high',
            'enabled' => true,
        ]);

        $digest = TelegramSubscription::where('event_type', 'workspace_digest')->firstOrFail();
        $this->assertSame('09:00', $digest->filters['time'] ?? null);

        $notification = TelegramSubscription::where('event_type', 'task_failed')->firstOrFail();
        $this->assertSame('silent', $notification->filters['mode'] ?? null);

        $this->assertSame(2, TelegramSubscription::count());
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-digest-command-card:v1',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-notify-command-card:v1',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-notice-card:v1',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-help-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'OpenCompany Telegram')
            && str_contains($request['text'], 'Send a message to the active agent')
            && str_contains($request['text'], '/agents switch agent')
            && str_contains($request['text'], '/topic change lane behavior')
            && str_contains($request['text'], 'Summarize the files I sent')
            && ! str_contains($request['text'], '/status /dashboard /activity')
            && ! str_contains($request['text'], '/automation list, status, run, pause, resume, history, failures')
            && ! str_contains($request['text'], '/files')
            && ! str_contains($request['text'], '/docs'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Settings live in OpenCompany'));
    }

    public function test_telegram_notify_off_disables_subscription(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 604]])]);

        $this->enableTelegramDirectResourceCommands();
        $this->telegramSetting();

        $this->postTelegramCommand('/notify task_failed high off', updateId: 2005, messageId: 105);

        $subscription = TelegramSubscription::where('event_type', 'task_failed')->firstOrFail();
        $this->assertFalse($subscription->enabled);
        $this->assertSame('off', $subscription->filters['mode'] ?? null);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-notify-command-card:v1',
            'status' => 'sent',
        ]);
    }

    public function test_telegram_task_notifications_route_to_subscribed_lane(): void
    {
        Http::fake([
            'api.telegram.org/*/sendMessage' => Http::sequence()
                ->push([
                    'ok' => false,
                    'error_code' => 400,
                    'description' => "Bad Request: can't parse entities: malformed HTML",
                ], 400)
                ->push(['ok' => true, 'result' => ['message_id' => 650]]),
        ]);

        $setting = $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Product',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345:55',
        ]);
        $conversation = TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'chat_type' => 'supergroup',
            'topic_id' => '55',
            'channel_id' => $channel->id,
        ]);
        TelegramSubscription::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'scope_type' => 'telegram_conversation',
            'scope_id' => $conversation->id,
            'event_type' => 'task_failed',
            'severity' => 'high',
            'timezone' => 'UTC',
            'enabled' => true,
        ]);
        $task = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Renewal analysis',
            'description' => 'Analyze renewal risk.',
            'status' => Task::STATUS_FAILED,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'channel_id' => $channel->id,
            'completed_at' => now(),
            'result' => ['error' => 'Tool timed out while fetching CRM data.'],
        ]);

        event(new TaskUpdated($task, 'failed'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'source_type' => Task::class,
            'source_id' => $task->id,
            'chat_id' => '12345',
            'topic_id' => '55',
            'renderer_version' => 'telegram-task-notification:v1',
            'parse_mode' => 'plain_text_fallback',
            'status' => 'sent',
        ]);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && (string) $request['chat_id'] === '12345'
            && (int) $request['message_thread_id'] === 55
            && str_contains($request['text'], 'Task failed')
            && str_contains($request['text'], 'Renewal analysis')
            && ! str_contains($request['text'], 'Task ID:')
            && str_contains($request['text'], 'Tool timed out'));
    }

    public function test_telegram_task_notifications_respect_silent_and_deferred_modes(): void
    {
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 652]])]);

        $setting = $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        $conversation = TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'chat_type' => 'private',
        ]);

        foreach ([
            ['event_type' => 'task_failed', 'severity' => 'high', 'mode' => 'silent'],
            ['event_type' => 'task_completed', 'severity' => 'normal', 'mode' => 'digest'],
        ] as $subscription) {
            TelegramSubscription::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $this->workspace->id,
                'integration_setting_id' => $setting->id,
                'scope_type' => 'telegram_conversation',
                'scope_id' => $conversation->id,
                'event_type' => $subscription['event_type'],
                'severity' => $subscription['severity'],
                'filters' => ['mode' => $subscription['mode']],
                'timezone' => 'UTC',
                'enabled' => true,
            ]);
        }

        $failedTask = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Renewal analysis',
            'description' => 'Analyze renewal risk.',
            'status' => Task::STATUS_FAILED,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'completed_at' => now(),
        ]);
        $completedTask = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Daily report',
            'description' => 'Create the daily report.',
            'status' => Task::STATUS_COMPLETED,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'completed_at' => now(),
        ]);

        event(new TaskUpdated($failedTask, 'failed'));
        event(new TaskUpdated($completedTask, 'completed'));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && (bool) $request['disable_notification'] === true
            && str_contains($request['text'], 'Task failed')
            && ! str_contains($request['text'], 'Task ID:'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'source_type' => Task::class,
            'source_id' => $completedTask->id,
            'renderer_version' => 'telegram-task-notification:v1',
            'status' => 'deferred',
        ]);
    }

    public function test_telegram_automation_failure_notifications_route_with_context_and_digest_modes(): void
    {
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 653]])]);

        $setting = $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        $immediateConversation = TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'chat_type' => 'private',
        ]);
        $digestConversation = TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '67890',
            'chat_type' => 'private',
        ]);
        $automation = Automation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Morning Brief',
            'trigger_type' => 'schedule',
            'execution_type' => 'prompt',
            'agent_id' => $agent->id,
            'prompt' => 'Summarize the workspace.',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'is_active' => true,
            'run_count' => 4,
            'consecutive_failures' => 2,
            'last_result' => ['error' => 'Previous CRM outage'],
            'created_by_id' => $human->id,
            'keep_history' => true,
        ]);

        foreach ([
            [$immediateConversation, 'immediate'],
            [$digestConversation, 'digest'],
        ] as [$conversation, $mode]) {
            TelegramSubscription::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $this->workspace->id,
                'integration_setting_id' => $setting->id,
                'scope_type' => 'telegram_conversation',
                'scope_id' => $conversation->id,
                'event_type' => 'automation_failed',
                'severity' => 'high',
                'filters' => ['mode' => $mode],
                'timezone' => 'UTC',
                'enabled' => true,
            ]);
        }

        $task = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Scheduled: Morning Brief',
            'description' => 'Summarize the workspace.',
            'status' => Task::STATUS_FAILED,
            'source' => Task::SOURCE_AUTOMATION,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'completed_at' => now(),
            'context' => ['automation_id' => $automation->id],
            'result' => ['error' => 'CRM API timed out'],
        ]);

        event(new TaskUpdated($task, 'failed'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'source_type' => Automation::class,
            'source_id' => $automation->id,
            'chat_id' => '12345',
            'renderer_version' => 'telegram-automation-notification:v1',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('telegram_deliveries', [
            'source_type' => Automation::class,
            'source_id' => $automation->id,
            'chat_id' => '67890',
            'renderer_version' => 'telegram-automation-notification:v1',
            'status' => 'deferred',
        ]);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && (string) $request['chat_id'] === '12345'
            && str_contains($request['text'], 'Automation failed')
            && str_contains($request['text'], 'Morning Brief')
            && str_contains($request['text'], 'Consecutive failures: 2')
            && ! str_contains($request['text'], 'Task ID:')
            && str_contains($request['text'], 'CRM API timed out'));
    }

    public function test_telegram_approval_needed_notifications_route_from_subscriptions_with_secure_buttons(): void
    {
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 654]])]);

        $setting = $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Approvals',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345',
            'creator_id' => $human->id,
        ]);
        $immediateConversation = TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'chat_type' => 'private',
            'channel_id' => $channel->id,
        ]);
        $digestConversation = TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '67890',
            'chat_type' => 'private',
        ]);

        foreach ([
            [$immediateConversation, 'immediate'],
            [$digestConversation, 'digest'],
        ] as [$conversation, $mode]) {
            TelegramSubscription::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $this->workspace->id,
                'integration_setting_id' => $setting->id,
                'scope_type' => 'telegram_conversation',
                'scope_id' => $conversation->id,
                'event_type' => 'approval_needed',
                'severity' => 'high',
                'filters' => ['mode' => $mode],
                'timezone' => 'UTC',
                'enabled' => true,
            ]);
        }

        $approval = ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => 'action',
            'title' => 'Deploy production',
            'description' => 'Approve production deployment.',
            'requester_id' => $agent->id,
            'status' => 'pending',
            'channel_id' => $channel->id,
            'tool_execution_context' => [
                'tool_slug' => 'deploy_app',
                'parameters' => [
                    'environment' => 'production',
                    'api_token' => 'secret-token-value',
                ],
            ],
        ]);

        $this->assertDatabaseHas('telegram_deliveries', [
            'source_type' => ApprovalRequest::class,
            'source_id' => $approval->id,
            'chat_id' => '12345',
            'renderer_version' => 'telegram-approval-card:v1',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('telegram_deliveries', [
            'source_type' => ApprovalRequest::class,
            'source_id' => $approval->id,
            'chat_id' => '67890',
            'renderer_version' => 'telegram-approval-notification:v1',
            'status' => 'deferred',
        ]);
        $this->assertSame(3, TelegramInteraction::where('interaction_type', 'approval')->where('target_id', $approval->id)->count());

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($approval) {
            if (! str_contains($request->url(), '/sendMessage') || (string) $request['chat_id'] !== '12345') {
                return false;
            }

            $markup = json_decode((string) $request['reply_markup'], true);

            return str_contains($request['text'], 'Approval needed')
                && str_contains($request['text'], 'Deploy production')
                && str_contains($request['text'], 'deploy_app')
                && str_contains($request['text'], 'api_token=[redacted]')
                && ! str_contains($request['text'], 'secret-token-value')
                && str_contains(json_encode($markup), 'Approve')
                && str_contains(json_encode($markup), 'Reject')
                && ! str_contains(json_encode($markup), 'approve:'.$approval->id);
        });
    }

    public function test_telegram_digest_command_sends_due_digest_and_batches_deferred_notifications(): void
    {
        Carbon::setTestNow('2026-05-25 09:05:00 UTC');
        Http::fake([
            'api.telegram.org/*/sendMessage' => Http::sequence()
                ->push([
                    'ok' => false,
                    'error_code' => 400,
                    'description' => "Bad Request: can't parse entities: malformed HTML",
                ], 400)
                ->push(['ok' => true, 'result' => ['message_id' => 700]]),
            'api.telegram.org/*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $setting = $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $human->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Digest',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345',
            'creator_id' => $human->id,
        ]);
        $conversation = TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'chat_type' => 'private',
            'channel_id' => $channel->id,
        ]);
        TelegramSubscription::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'scope_type' => 'telegram_conversation',
            'scope_id' => $conversation->id,
            'event_type' => 'workspace_digest',
            'severity' => 'normal',
            'filters' => ['time' => '09:00'],
            'schedule' => 'daily',
            'timezone' => 'UTC',
            'enabled' => true,
        ]);

        Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Digest me',
            'author_id' => $human->id,
            'channel_id' => $channel->id,
            'timestamp' => now()->subHour(),
        ]);
        $completedTask = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Daily report',
            'description' => 'Create the daily report.',
            'status' => Task::STATUS_COMPLETED,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'channel_id' => $channel->id,
            'completed_at' => now()->subMinutes(20),
        ]);
        Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'CRM sync',
            'description' => 'Sync CRM accounts.',
            'status' => Task::STATUS_FAILED,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'channel_id' => $channel->id,
            'completed_at' => now()->subMinutes(15),
        ]);
        $document = Document::factory()->create([
            'workspace_id' => $this->workspace->id,
            'author_id' => $human->id,
            'title' => 'Launch Notes',
        ]);
        $document->forceFill(['updated_at' => now()->subMinutes(10)])->save();
        WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'forecast.csv',
            'is_folder' => false,
            'storage_disk' => 'local',
            'storage_path' => 'forecast.csv',
            'mime_type' => 'text/csv',
            'size' => 128,
            'owner_id' => $human->id,
        ]);
        CalendarEvent::create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Pipeline review',
            'start_at' => now()->addHours(2),
            'end_at' => now()->addHours(3),
            'all_day' => false,
            'created_by' => $human->id,
        ]);
        $approval = ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => 'action',
            'title' => 'Deploy staging',
            'description' => 'Approve staging deploy.',
            'requester_id' => $human->id,
            'status' => 'pending',
            'channel_id' => $channel->id,
        ]);
        Automation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Morning Brief',
            'trigger_type' => 'schedule',
            'execution_type' => 'prompt',
            'agent_id' => $agent->id,
            'prompt' => 'Summarize the workspace.',
            'cron_expression' => '0 10 * * *',
            'timezone' => 'UTC',
            'is_active' => true,
            'next_run_at' => now()->addHour(),
            'run_count' => 0,
            'consecutive_failures' => 0,
            'keep_history' => true,
            'created_by_id' => $human->id,
        ]);
        $failedAutomation = Automation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'CRM Sync',
            'trigger_type' => 'schedule',
            'execution_type' => 'prompt',
            'agent_id' => $agent->id,
            'prompt' => 'Sync CRM data.',
            'cron_expression' => '*/15 * * * *',
            'timezone' => 'UTC',
            'is_active' => true,
            'next_run_at' => now()->addMinutes(15),
            'run_count' => 4,
            'consecutive_failures' => 2,
            'last_result' => ['error' => 'CRM API timed out'],
            'keep_history' => true,
            'created_by_id' => $human->id,
        ]);
        $deferred = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'source_type' => Task::class,
            'source_id' => $completedTask->id,
            'chat_id' => '12345',
            'renderer_version' => 'telegram-task-notification:v1',
            'status' => 'deferred',
            'request_payload' => [
                'method' => 'deferNotification',
                'event_type' => 'task_completed',
                'severity' => 'normal',
                'mode' => 'digest',
                'task_id' => $completedTask->id,
            ],
        ]);
        $deferredAutomation = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'source_type' => Automation::class,
            'source_id' => $failedAutomation->id,
            'chat_id' => '12345',
            'renderer_version' => 'telegram-automation-notification:v1',
            'status' => 'deferred',
            'request_payload' => [
                'method' => 'deferNotification',
                'event_type' => 'automation_failed',
                'severity' => 'high',
                'mode' => 'digest',
                'automation_id' => $failedAutomation->id,
            ],
        ]);
        $deferredApproval = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'source_type' => ApprovalRequest::class,
            'source_id' => $approval->id,
            'chat_id' => '12345',
            'renderer_version' => 'telegram-approval-notification:v1',
            'status' => 'deferred',
            'request_payload' => [
                'method' => 'deferNotification',
                'event_type' => 'approval_needed',
                'severity' => 'high',
                'mode' => 'digest',
                'approval_id' => $approval->id,
            ],
        ]);

        $this->artisan('telegram:send-digests')->assertExitCode(0);
        $this->artisan('telegram:send-digests')->assertExitCode(0);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && (string) $request['chat_id'] === '12345'
            && str_contains($request['text'], 'Daily Digest - Test Workspace')
            && str_contains($request['text'], 'Messages: 1')
            && str_contains($request['text'], 'Tasks completed: 1')
            && str_contains($request['text'], 'Failed work: 1')
            && str_contains($request['text'], 'Docs changed: 1')
            && str_contains($request['text'], 'Files uploaded: 1')
            && str_contains($request['text'], 'Calendar next 24h: 1')
            && str_contains($request['text'], 'Pending approvals: 1')
            && str_contains($request['text'], 'Deferred alerts: 3')
            && str_contains($request['text'], 'Next automation: CRM Sync')
            && str_contains($request['text'], 'Completed: Daily report')
            && str_contains($request['text'], 'Automation failed: CRM Sync')
            && str_contains($request['text'], 'Approval needed: Deploy staging')
            && str_contains((string) $request['reply_markup'], 'Open dashboard')
            && str_contains((string) $request['reply_markup'], 'Snooze'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'source_type' => TelegramSubscription::class,
            'chat_id' => '12345',
            'renderer_version' => 'telegram-workspace-digest:v1',
            'status' => 'sent',
            'telegram_message_id' => '700',
            'parse_mode' => 'plain_text_fallback',
        ]);

        $deferred->refresh();
        $this->assertSame('batched', $deferred->status);
        $this->assertNotNull($deferred->response_payload['digest_delivery_id'] ?? null);
        $this->assertSame('batched', $deferredAutomation->fresh()->status);
        $this->assertSame('batched', $deferredApproval->fresh()->status);

        $snooze = TelegramInteraction::where('interaction_type', 'notification_snooze')->firstOrFail();
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2050,
                'callback_query' => [
                    'id' => 'digest-snooze-callback',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger'],
                    'message' => [
                        'message_id' => 700,
                        'date' => now()->timestamp,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $snooze->token,
                ],
            ])
            ->assertOk();

        $this->assertSame('snoozed', $snooze->fresh()->final_status);
        $laneSnooze = TelegramSubscription::where('event_type', 'all_notifications')->firstOrFail();
        $this->assertTrue($laneSnooze->enabled);
        $this->assertNotEmpty($laneSnooze->filters['snoozed_until'] ?? null);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
            && $request['callback_query_id'] === 'digest-snooze-callback'
            && isset($request['text'])
            && str_contains($request['text'], 'Snoozed until'));

        Carbon::setTestNow();
    }

    public function test_telegram_task_notifications_respect_lane_snooze(): void
    {
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 651]])]);

        $setting = $this->telegramSetting();
        $agent = User::factory()->agent()->create(['name' => 'Iris']);
        $human = User::factory()->create(['name' => 'Rutger']);
        $conversation = TelegramConversation::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => '12345',
            'chat_type' => 'private',
        ]);

        foreach ([
            ['event_type' => 'task_completed', 'severity' => 'normal', 'filters' => []],
            ['event_type' => 'all_notifications', 'severity' => 'normal', 'filters' => ['snoozed_until' => now()->addHour()->toIso8601String()]],
        ] as $subscription) {
            TelegramSubscription::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $this->workspace->id,
                'integration_setting_id' => $setting->id,
                'scope_type' => 'telegram_conversation',
                'scope_id' => $conversation->id,
                'event_type' => $subscription['event_type'],
                'severity' => $subscription['severity'],
                'filters' => $subscription['filters'],
                'timezone' => 'UTC',
                'enabled' => true,
            ]);
        }

        $task = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Daily report',
            'description' => 'Create the daily report.',
            'status' => Task::STATUS_COMPLETED,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'completed_at' => now(),
        ]);

        event(new TaskUpdated($task, 'completed'));

        $this->assertDatabaseMissing('telegram_deliveries', [
            'source_type' => Task::class,
            'source_id' => $task->id,
            'renderer_version' => 'telegram-task-notification:v1',
        ]);

        Http::assertNothingSent();
    }

    public function test_telegram_snooze_and_calendar_commands_persist_lane_state_and_show_events(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 603]])]);

        $this->enableTelegramDirectResourceCommands();
        $this->telegramSetting();
        $creator = User::factory()->create();

        CalendarEvent::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Customer launch review',
            'description' => 'Prepare launch notes.',
            'start_at' => now()->addHours(3),
            'end_at' => now()->addHours(4),
            'all_day' => false,
            'location' => 'Zoom',
            'created_by' => $creator->id,
        ]);

        $this->postTelegramCommand('/snooze 2h', updateId: 2021, messageId: 121);
        $this->postTelegramCommand('/calendar', updateId: 2022, messageId: 122);

        $snooze = TelegramSubscription::where('event_type', 'all_notifications')->firstOrFail();
        $this->assertTrue($snooze->enabled);
        $this->assertNotEmpty($snooze->filters['snoozed_until'] ?? null);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Telegram notifications snoozed until'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-snooze-command-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Upcoming calendar')
            && str_contains($request['text'], 'Customer launch review')
            && str_contains($request['text'], 'Zoom'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-calendar-card:v1',
            'status' => 'sent',
        ]);
    }

    public function test_telegram_calendar_add_uses_confirmation_token_before_creating_event(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 612]])]);

        $this->enableTelegramDirectResourceCommands();
        $this->telegramSetting();
        $human = User::factory()->create(['name' => 'Rutger']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'admin',
        ]);
        UserExternalIdentity::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $human->id,
            'provider' => 'telegram',
            'external_id' => '111',
            'display_name' => 'Rutger',
        ]);

        $this->postTelegramCommand('/calendar add 2026-05-25 14:00 Product review', updateId: 2036, messageId: 136);

        $create = TelegramInteraction::where('interaction_type', 'calendar_draft')
            ->where('payload->action', 'create')
            ->firstOrFail();

        $this->assertDatabaseMissing('calendar_events', [
            'title' => 'Product review',
        ]);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-calendar-draft-card:v1',
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) use ($create) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $markup = json_decode((string) $request['reply_markup'], true);

            return str_contains($request['text'], 'Calendar draft')
                && str_contains($request['text'], 'Product review')
                && str_contains(json_encode($markup), $create->token);
        });

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => 2037,
                'callback_query' => [
                    'id' => 'calendar-cb-1',
                    'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
                    'message' => [
                        'message_id' => 612,
                        'chat' => ['id' => 12345, 'type' => 'private'],
                    ],
                    'data' => $create->token,
                ],
            ])
            ->assertOk();

        $event = CalendarEvent::where('title', 'Product review')->firstOrFail();
        $this->assertSame($this->workspace->id, $event->workspace_id);
        $this->assertSame($human->id, $event->created_by);
        $this->assertSame('created', $create->fresh()->final_status);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
            && str_contains((string) ($request->data()['text'] ?? ''), 'Calendar event created'));
    }

    public function test_telegram_health_and_activity_commands_show_operator_state(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 602]])]);

        $this->enableTelegramDirectResourceCommands();
        $setting = $this->telegramSetting();
        TelegramIntegrationProfile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_setting_id' => $setting->id,
            'bot_username' => 'OpenCompanyBot',
            'webhook_url' => 'https://local-ngrok.example/api/webhooks/chat/telegram',
            'allowed_updates' => ['message', 'callback_query'],
            'command_sync_status' => 'synced',
            'health_status' => 'degraded',
            'capabilities' => [
                'diagnostics' => [[
                    'code' => 'missing_allowed_updates',
                    'severity' => 'warning',
                    'message' => 'Telegram webhook is missing allowed updates: message_reaction.',
                ]],
            ],
            'pending_update_count' => 0,
            'last_health_checked_at' => now(),
        ]);

        $human = User::factory()->create(['name' => 'Rutger']);
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Telegram: Product',
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => '12345',
        ]);

        Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Investigated account expansion.',
            'channel_id' => $channel->id,
            'author_id' => $human->id,
            'timestamp' => now(),
        ]);

        $this->postTelegramCommand('/health', updateId: 2005, messageId: 105);
        $this->postTelegramCommand('/activity', updateId: 2006, messageId: 106);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Telegram health')
            && str_contains($request['text'], '@OpenCompanyBot')
            && str_contains($request['text'], 'degraded')
            && str_contains($request['text'], 'missing_allowed_updates'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Activity')
            && str_contains($request['text'], 'Investigated account expansion'));

        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-health-card:v1',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('telegram_deliveries', [
            'renderer_version' => 'telegram-activity-card:v1',
            'status' => 'sent',
        ]);
    }

    public function test_slack_team_id_alone_cannot_bind_a_workspace(): void
    {
        IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'slack',
            'account_alias' => '',
            'config' => [
                'team_id' => 'T123',
                'signing_secret' => 'slack-secret',
                'api_key' => 'xoxb-token',
            ],
            'enabled' => true,
            'is_default' => true,
        ]);

        $this->postJson('/api/webhooks/chat/slack', ['team_id' => 'T123'])
            ->assertUnauthorized();
    }

    public function test_discord_application_id_alone_cannot_bind_a_workspace(): void
    {
        IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'discord',
            'account_alias' => '',
            'config' => [
                'application_id' => 'app-123',
                'public_key' => str_repeat('a', 64),
                'api_key' => 'bot-token',
            ],
            'enabled' => true,
            'is_default' => true,
        ]);

        $this->postJson('/api/webhooks/chat/discord', ['application_id' => 'app-123'])
            ->assertUnauthorized();
    }

    public function test_teams_recipient_id_alone_cannot_bind_a_workspace(): void
    {
        IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'teams',
            'account_alias' => '',
            'config' => [
                'app_id' => 'teams-app',
                'app_password' => 'teams-secret',
            ],
            'enabled' => true,
            'is_default' => true,
        ]);

        $this->postJson('/api/webhooks/chat/teams', ['recipient' => ['id' => 'teams-app']])
            ->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function telegramSetting(array $config = []): IntegrationSetting
    {
        return IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'telegram',
            'account_alias' => '',
            'config' => array_merge([
                'api_key' => 'telegram-token',
                'webhook_secret' => 'telegram-secret',
                'bot_user_id' => '999',
                'bot_username' => 'OpenCompanyBot',
            ], $config),
            'enabled' => true,
            'is_default' => true,
        ]);
    }

    private function enableTelegramDirectResourceCommands(): void
    {
        config(['telegram.direct_resource_commands_enabled' => true]);
    }

    private function postTelegramCommand(
        string $command,
        int $updateId,
        int $messageId,
        ?array $chat = null,
        ?array $from = null,
        ?int $threadId = null,
    ): void {
        $message = [
            'message_id' => $messageId,
            'date' => now()->timestamp,
            'chat' => $chat ?? ['id' => 12345, 'type' => 'private', 'first_name' => 'Rutger'],
            'from' => $from ?? ['id' => 111, 'is_bot' => false, 'first_name' => 'Rutger', 'username' => 'rutger'],
            'text' => $command,
            'entities' => [[
                'type' => 'bot_command',
                'offset' => 0,
                'length' => strlen(strtok($command, ' ')),
            ]],
        ];

        if ($threadId !== null) {
            $message['message_thread_id'] = $threadId;
        }

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', [
                'update_id' => $updateId,
                'message' => $message,
            ])
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function signedTelegramInitData(array $params, string $botToken = 'telegram-token'): string
    {
        $params['auth_date'] ??= (string) now()->timestamp;
        ksort($params);

        $dataCheckString = collect($params)
            ->map(fn ($value, $key) => $key.'='.$value)
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $params['hash'] = hash_hmac('sha256', $dataCheckString, $secretKey);

        return http_build_query($params);
    }
}
