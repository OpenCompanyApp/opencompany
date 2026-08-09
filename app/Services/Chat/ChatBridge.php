<?php

namespace App\Services\Chat;

use App\Events\MessageSent;
use App\Jobs\AgentRespondJob;
use App\Jobs\CompactConversationJob;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\ConversationSummary;
use App\Models\IntegrationSetting;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Services\ApprovalExecutionService;
use App\Services\WorkspaceStatusService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenCompany\Chatogrator\Messages\Message as ChatMessage;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Threads\Thread;

/**
 * Bridges inbound Chatogrator events into OpenCompany channels, users, and jobs.
 *
 * This service is the trust boundary for external chat messages. It maps vendor
 * thread/user IDs into workspace records, enforces per-integration allowlists,
 * and starts agent response jobs only after the inbound message is represented
 * as a normal OpenCompany message.
 */
class ChatBridge
{
    public function handleInbound(Thread $thread, ChatMessage $chatMessage, string $workspaceId): void
    {
        $adapter = $thread->adapter;
        $adapterName = $adapter->name();

        if ($this->isAppOwnedProvider($adapterName)) {
            Log::warning('Ignoring app-owned chat provider event in Chatogrator bridge', [
                'provider' => $adapterName,
                'workspace_id' => $workspaceId,
            ]);

            return;
        }

        // The allowlist check happens before creating channels/messages so an
        // unauthorized external user cannot populate workspace history.
        if (! $this->isUserAllowed($adapterName, $chatMessage->author->userId, $workspaceId)) {
            $thread->post(PostableMessage::text('You are not authorized to use this bot. Contact your administrator.'));

            return;
        }

        // Decode the provider thread into a stable external ID. The raw thread
        // ID stays in external_config because adapters may need the full value
        // for replies even when the channel identity is simpler.
        $decoded = $adapter->decodeThreadId($thread->id);
        $externalId = $this->resolveExternalId($adapterName, $decoded);

        // External channels are workspace-scoped. Provider/channel pairs must
        // never resolve globally because the same Telegram/Slack ID can be
        // configured in different workspaces.
        $channel = Channel::firstOrCreate(
            ['external_provider' => $adapterName, 'external_id' => $externalId, 'workspace_id' => $workspaceId],
            [
                'id' => Str::uuid()->toString(),
                'name' => $this->resolveChannelName($adapterName, $chatMessage->author),
                'type' => 'external',
                'workspace_id' => $workspaceId,
                'external_config' => [
                    'thread_id' => $thread->id,
                    'adapter' => $adapterName,
                ],
            ]
        );

        // Resolve system user
        $user = $this->resolveUser($adapterName, $chatMessage->author);

        // Ensure channel memberships
        ChannelMember::firstOrCreate(
            ['channel_id' => $channel->id, 'user_id' => $user->id],
            ['id' => Str::uuid()->toString(), 'joined_at' => now()]
        );

        $agent = $this->resolveAgent($workspaceId, $adapterName);
        if ($agent) {
            ChannelMember::firstOrCreate(
                ['channel_id' => $channel->id, 'user_id' => $agent->id],
                ['id' => Str::uuid()->toString(), 'joined_at' => now()]
            );
        }

        // Reply threading is best-effort. If the external platform references a
        // message we have not seen, keep the inbound message instead of failing
        // the bridge.
        $replyToId = null;
        $replyToMessageId = $chatMessage->metadata['replyToMessageId'] ?? null;
        if ($replyToMessageId) {
            $replyToId = Message::where('external_message_id', (string) $replyToMessageId)
                ->where('channel_id', $channel->id)
                ->value('id');
        }

        // Create internal message
        $internalMessage = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => $chatMessage->text,
            'channel_id' => $channel->id,
            'author_id' => $user->id,
            'reply_to_id' => $replyToId,
            'timestamp' => now(),
            'source' => $adapterName,
            'external_message_id' => $chatMessage->id ?: null,
        ]);

        broadcast(new MessageSent($internalMessage));
        $channel->update(['last_message_at' => now()]);

        // Subscribing makes subsequent non-mention messages eligible for the
        // same inbound path, subject to the adapter's own subscription rules.
        $thread->subscribe();

        // Agent responses run through the normal queue/task path so external
        // chat behaves like in-app chat for memory, approvals, and retries.
        if ($agent) {
            if (app(ChatProviderCapabilities::class)->supports($adapterName, 'typing')) {
                try {
                    $thread->startTyping();
                } catch (\Throwable) {
                    // Typing indicators are transient provider UX and must not
                    // block the durable OpenCompany message or queued task.
                }
            }

            $task = Task::createPending($internalMessage, $agent, $channel->id);
            AgentRespondJob::dispatch($internalMessage, $agent, $channel->id, $task->id);
        }
    }

    /**
     * Handle button/callback actions (e.g. approval buttons).
     */
    public function handleAction(object $event, string $workspaceId): void
    {
        $adapterName = isset($event->adapter) ? $event->adapter->name() : null;
        if ($adapterName && $this->isAppOwnedProvider($adapterName)) {
            Log::warning('Ignoring app-owned chat provider action in Chatogrator bridge', [
                'provider' => $adapterName,
                'workspace_id' => $workspaceId,
            ]);

            return;
        }

        $actionId = $event->actionId ?? '';
        $value = $event->value ?? '';

        // Approval actions: actionId="approve" or "reject", value=approvalId
        if (in_array($actionId, ['approve', 'reject'])) {
            $this->handleApprovalAction($event, $actionId, $value, $workspaceId);

            return;
        }
    }

    /**
     * Handle slash commands (/start, /status, /compact).
     */
    public function handleSlashCommand(object $event, string $workspaceId): void
    {
        $adapterName = isset($event->adapter) ? $event->adapter->name() : null;
        if ($adapterName && $this->isAppOwnedProvider($adapterName)) {
            Log::warning('Ignoring app-owned chat provider command in Chatogrator bridge', [
                'provider' => $adapterName,
                'workspace_id' => $workspaceId,
            ]);

            return;
        }

        $command = $event->command ?? '';

        match ($command) {
            '/start' => $this->handleStartCommand($event, $workspaceId),
            '/status' => $this->handleStatusCommand($event, $workspaceId),
            '/compact' => $this->handleCompactCommand($event, $workspaceId),
            default => null,
        };
    }

    // ── Command handlers ───────────────────────────────────────────

    private function handleStartCommand(object $event, string $workspaceId): void
    {
        $userId = $event->userId ?? 'unknown';
        $adapter = $event->adapter;
        $channelId = $event->channelId ?? '';

        $threadId = $adapter->encodeThreadId(['chatId' => $channelId]);

        $adapter->postMessage($threadId, PostableMessage::text(
            "Welcome to OpenCompany!\n\n"
            ."Your User ID is: {$userId}\n\n"
            .'Share this with your administrator to get access.'
        ));
    }

    private function handleStatusCommand(object $event, string $workspaceId): void
    {
        $adapter = $event->adapter;
        $channelId = $event->channelId ?? '';
        $threadId = $adapter->encodeThreadId(['chatId' => $channelId]);

        try {
            $status = app(WorkspaceStatusService::class)->gather($workspaceId);

            $lines = ["**Workspace Status**\n"];

            $lines[] = "Agents: {$status['agents_online']}/{$status['agents_total']} online";
            foreach ($status['agents'] as $a) {
                $icon = match ($a['status']) {
                    'working' => '🟢',
                    'idle' => '🟡',
                    default => '⚫',
                };
                $line = "   {$icon} {$a['name']} — {$a['status']}";
                if ($a['current_task']) {
                    $line .= ' · '.Str::limit($a['current_task'], 30);
                }
                $lines[] = $line;
            }

            $lines[] = "\n**Tasks**";
            $lines[] = "   Active: {$status['tasks_active']}";
            $lines[] = "   Completed today: {$status['tasks_today']}";
            $lines[] = "   Total completed: {$status['tasks_completed']}";

            $lines[] = "\n**Messages**";
            $lines[] = "   Today: {$status['messages_today']}";
            $lines[] = "   Total: {$status['messages_total']}";

            // Conversation context
            $adapterName = $adapter->name();
            $channel = Channel::where('external_provider', $adapterName)
                ->where('external_id', $channelId)
                ->first();

            if ($channel) {
                $summary = ConversationSummary::where('channel_id', $channel->id)
                    ->latest()
                    ->first();

                if ($summary) {
                    $lines[] = "\n**This conversation**";
                    $lines[] = "   Compactions: {$summary->compaction_count}";
                    $lines[] = '   Context tokens: ~'.number_format($summary->tokens_after);
                }
            }

            $lines[] = "\n".now()->utc()->format('Y-m-d H:i').' UTC';

            $adapter->postMessage($threadId, PostableMessage::markdown(implode("\n", $lines)));
        } catch (\Throwable $e) {
            Log::error('Chat /status failed', ['error' => $e->getMessage()]);
            $adapter->postMessage($threadId, PostableMessage::text(
                'Failed to get status: '.Str::limit($e->getMessage(), 100)
            ));
        }
    }

    private function handleCompactCommand(object $event, string $workspaceId): void
    {
        $adapter = $event->adapter;
        $channelId = $event->channelId ?? '';
        $adapterName = $adapter->name();
        $threadId = $adapter->encodeThreadId(['chatId' => $channelId]);

        $channel = Channel::where('external_provider', $adapterName)
            ->where('external_id', $channelId)
            ->first();

        if (! $channel) {
            $adapter->postMessage($threadId, PostableMessage::text('No conversation history in this channel yet.'));

            return;
        }

        // Resolve agent
        $setting = IntegrationSetting::where('workspace_id', $workspaceId)
            ->where('integration_id', $adapterName)
            ->first();

        $defaultAgentId = $setting?->getConfigValue('default_agent_id');
        $agent = $defaultAgentId ? User::find($defaultAgentId) : null;

        if (! $agent || $agent->type !== 'agent') {
            $agentIds = ChannelMember::where('channel_id', $channel->id)->pluck('user_id');
            $agent = User::where('type', 'agent')->whereIn('id', $agentIds)->first();
        }

        if (! $agent) {
            $adapter->postMessage($threadId, PostableMessage::text('No agent found in this channel.'));

            return;
        }

        $adapter->postMessage($threadId, PostableMessage::text('Compacting conversation memory...'));

        CompactConversationJob::dispatch($channel->id, $agent, $channelId);
    }

    // ── Approval handling ──────────────────────────────────────────

    private function handleApprovalAction(object $event, string $action, string $approvalId, string $workspaceId): void
    {
        $status = $action === 'approve' ? 'approved' : 'rejected';

        $approval = ApprovalRequest::query()
            ->whereHas('channel', fn ($query) => $query->where('workspace_id', $workspaceId))
            ->find($approvalId);
        if (! $approval || $approval->status !== 'pending') {
            return;
        }

        // Resolve the external approver into an OpenCompany user so the approval
        // audit trail points at a durable local identity.
        $user = $event->user ?? null;
        $userId = $user ? ($user->userId ?? null) : null;
        $adapterName = $event->adapter->name();

        $responder = $userId
            ? $this->resolveUser($adapterName, $user)
            : null;

        $approvalService = app(ApprovalExecutionService::class);
        if (! $approvalService->resolve($approval, $status, $responder)) {
            return;
        }

        // Update the message to show result (for Telegram, edit to remove buttons)
        if (isset($event->thread) && isset($event->payload['message']['message_id'])) {
            $messageId = (string) $event->payload['message']['message_id'];
            $statusEmoji = $status === 'approved' ? '✅' : '❌';
            $responderName = $user->fullName ?? $user->userName ?? 'User';

            try {
                $event->adapter->editMessage(
                    $event->thread->id,
                    $messageId,
                    PostableMessage::markdown(
                        "**{$statusEmoji} ".ucfirst($status)."**\n\n"
                        ."**{$approval->title}**\n"
                        ."By: {$responderName}"
                    )
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to edit approval message', ['error' => $e->getMessage()]);
            }
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function resolveExternalId(string $adapter, array $decoded): string
    {
        return match ($adapter) {
            'slack' => $decoded['channel'] ?? '',
            'discord' => $decoded['channelId'] ?? $decoded['channel'] ?? '',
            'teams' => $decoded['conversationId'] ?? '',
            'gchat' => $decoded['spaceName'] ?? '',
            'github' => ($decoded['owner'] ?? '').'/'.($decoded['repo'] ?? ''),
            'linear' => $decoded['issueId'] ?? '',
            default => $decoded['chatId'] ?? $decoded['channel'] ?? '',
        };
    }

    private function resolveChannelName(string $adapter, object $author): string
    {
        $name = $author->fullName ?: $author->userName ?: 'Unknown';
        $label = ucfirst($adapter);

        return "{$label}: {$name}";
    }

    private function resolveUser(string $provider, object $author): User
    {
        $externalId = $author->userId ?? '0';

        $linked = UserExternalIdentity::resolveUser($provider, (string) $externalId);
        if ($linked) {
            return $linked;
        }

        $name = $author->fullName ?: $author->userName ?: 'User';
        $label = ucfirst($provider);

        return User::firstOrCreate(
            ['email' => "{$provider}-{$externalId}@external.opencompany"],
            [
                'id' => Str::uuid()->toString(),
                'name' => "{$name} ({$label})",
                'type' => 'human',
                'presence' => 'online',
                'password' => bcrypt(Str::random(32)),
                'is_ephemeral' => true,
            ]
        );
    }

    private function resolveAgent(string $workspaceId, string $adapterName): ?User
    {
        $setting = IntegrationSetting::where('workspace_id', $workspaceId)
            ->where('integration_id', $adapterName)
            ->first();

        $defaultAgentId = $setting?->getConfigValue('default_agent_id');
        $agent = $defaultAgentId ? User::find($defaultAgentId) : null;

        if ($agent && $agent->type === 'agent') {
            return $agent;
        }

        // Fallback keeps simple setups usable, but it stays workspace-scoped so
        // an external integration never routes work to another tenant's agent.
        return User::where('type', 'agent')
            ->where('workspace_id', $workspaceId)
            ->first();
    }

    private function isUserAllowed(string $adapterName, string $userId, string $workspaceId): bool
    {
        $setting = IntegrationSetting::where('workspace_id', $workspaceId)
            ->where('integration_id', $adapterName)
            ->first();

        if (! $setting) {
            return true;
        }

        $allowed = $setting->getConfigValue('allowed_users', []);

        if (empty($allowed)) {
            return true;
        }

        // The allowlist stores provider-native user IDs as strings. Do not map
        // through local users here; unlinked users still need to be rejected
        // before local identity creation.
        return in_array($userId, $allowed);
    }

    private function isAppOwnedProvider(string $provider): bool
    {
        return $provider === 'telegram';
    }
}
